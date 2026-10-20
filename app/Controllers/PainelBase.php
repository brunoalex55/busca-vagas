<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use App\Libraries\ArmazenamentoCurriculo;
use App\Models\ConfiguracaoModel;
use App\Models\PalavraChaveModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER BASE DO PAINEL DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Reune o que todas as telas internas precisam:
 *   - identificacao do usuario logado;
 *   - configuracao individual (com APP PASSWORD removida das views);
 *   - dados comuns de SEO/titulo;
 *   - verificacoes de prontidao para envio;
 *   - paginacao manual e retorno seguro de erros.
 */
abstract class PainelBase extends BaseController
{
    /** ID do usuario autenticado. */
    protected int $usuarioId = 0;

    /** Model de configuracoes. */
    protected ConfiguracaoModel $configuracoes;

    /** Model de usuarios. */
    protected UsuarioModel $usuarios;

    /** Armazenamento seguro de curriculos. */
    protected ArmazenamentoCurriculo $curriculos;

    /**
     * Inicializa o controller e carrega os models do painel.
     */
    public function initController($request, $response, $logger)
    {
        // Executa a inicializacao padrao.
        parent::initController($request, $response, $logger);

        // O filtro de autenticacao garante que este valor existe.
        $this->usuarioId = (int) $this->sessao->get('usuario_id');

        // Models usados em quase todas as telas.
        $this->configuracoes = model(ConfiguracaoModel::class);
        $this->usuarios      = model(UsuarioModel::class);
        $this->curriculos    = new ArmazenamentoCurriculo();
    }

    /**
     * Monta os dados comuns de uma view do painel.
     *
     * @param array<string, mixed> $extras Dados especificos da tela.
     *
     * @return array<string, mixed>
     */
    protected function dadosPainel(string $titulo, array $extras = []): array
    {
        // SEO proprio da area privada (noindex ja fica no layout).
        $seo = valor_ou($extras, 'seo', [
            'titulo'         => $titulo,
            'descricao'      => 'Area do usuario do sistema de candidaturas.',
            'palavras_chave' => '',
            'tipo'           => 'website',
        ]);

        // O SEO nao precisa ser duplicado nos extras.
        unset($extras['seo']);

        return $this->dadosComuns + $extras + [
            'seo'          => $seo,
            'tituloPagina' => $titulo,
            'nomeUsuario'  => (string) $this->sessao->get('nome'),
        ];
    }

    /**
     * Configuracao do usuario (padroes quando ainda nao salvou nada).
     *
     * @return array<string, mixed>
     */
    protected function configuracaoDoUsuario(): array
    {
        return $this->configuracoes->obterOuPadroes($this->usuarioId);
    }

    /**
     * Remove qualquer dado sensivel antes de entregar a configuracao
     * para a view.
     *
     * @param array<string, mixed> $configuracao
     *
     * @return array<string, mixed>
     */
    protected function sanitizarConfiguracao(array $configuracao): array
    {
        // Calcula o indicador ANTES de remover o valor cifrado.
        $configuracao['tem_app_password'] = $this->configuracoes->temAppPassword($configuracao);

        // APP PASSWORD cifrada nunca vai para a view.
        unset($configuracao['app_password_criptografado']);

        // Confere se o PDF realmente existe no disco.
        $configuracao['tem_curriculo'] = $this->curriculos->existe(valor_ou($configuracao, 'curriculo_pdf', null));

        // Tamanho e nome do arquivo atual.
        $configuracao['curriculo_tamanho'] = $this->curriculos->tamanho(valor_ou($configuracao, 'curriculo_pdf', null));

        // Nome do curriculo (vazio quando nao ha PDF).
        $configuracao['curriculo_nome'] = '';

        if ($configuracao['tem_curriculo']) {
            $configuracao['curriculo_nome'] = basename((string) $configuracao['curriculo_pdf']);
        }

        return $configuracao;
    }

    /**
     * Dados do usuario logado.
     *
     * @return array<string, mixed>
     */
    protected function usuarioLogado(): array
    {
        // Usuario nao encontrado: devolve um array vazio.
        $usuario = $this->usuarios->find($this->usuarioId);

        if ($usuario === null) {
            return [];
        }

        return $usuario;
    }

    /**
     * Checklist de prontidao para o envio automatico.
     *
     * O ultimo item (fila) e informativo: nao impede ativar o envio.
     *
     * @param array<string, mixed> $configuracao
     *
     * @return list<array{chave: string, rotulo: string, detalhe: string, ok: bool}>
     */
    protected function verificacoesEnvio(array $configuracao): array
    {
        // Models necessarios.
        $palavras = model(PalavraChaveModel::class);
        $vagas    = model(VagaModel::class);

        // Tem APP PASSWORD? (a configuracao sanitizada perde o valor cifrado)
        $temAppPassword = trim(texto_ou($configuracao, 'app_password_criptografado')) !== '';

        // Tem curriculo e o arquivo existe?
        $temCurriculo = trim(texto_ou($configuracao, 'curriculo_pdf')) !== ''
            && $this->curriculos->existe(valor_ou($configuracao, 'curriculo_pdf', null));

        // Quantidade de palavras-chave ativas.
        $totalPalavras = $palavras->totalAtivasDoUsuario($this->usuarioId);

        // Vagas prontas para envio.
        $fila = $vagas->contarComEmailNaoCandidatadas($this->usuarioId);

        return [
            [
                'chave'   => 'email',
                'rotulo'  => 'E-mail remetente',
                'detalhe' => 'Informe o Gmail que sera usado nas candidaturas.',
                'ok'      => trim(texto_ou($configuracao, 'email_remetente')) !== '',
            ],
            [
                'chave'   => 'app_password',
                'rotulo'  => 'APP PASSWORD do Gmail',
                'detalhe' => 'Cadastre a senha de aplicativo (nunca a senha normal).',
                'ok'      => $temAppPassword,
            ],
            [
                'chave'   => 'curriculo',
                'rotulo'  => 'Curriculo em PDF',
                'detalhe' => 'Anexe um PDF de ate 5 MB.',
                'ok'      => $temCurriculo,
            ],
            [
                'chave'   => 'palavras_chave',
                'rotulo'  => 'Palavra-chave ativa',
                'detalhe' => 'O sistema precisa saber o que buscar.',
                'ok'      => $totalPalavras > 0,
            ],
            [
                'chave'   => 'fila',
                'rotulo'  => 'Vaga com e-mail na fila',
                'detalhe' => $this->detalheDaFila($fila),
                'ok'      => $fila > 0,
            ],
        ];
    }

    /**
     * Detalhe da fila de envio, conforme a quantidade de vagas.
     */
    private function detalheDaFila(int $fila): string
    {
        // Com vagas na fila: mostra quantas.
        if ($fila > 0) {
            return $fila . ' vaga(s) pronta(s) para candidatura.';
        }

        return 'As vagas aparecem depois que a busca for executada.';
    }

    /**
     * Retorna apenas os requisitos que impedem ativar o envio automatico.
     *
     * @param array<string, mixed> $configuracao
     *
     * @return list<array{chave: string, rotulo: string, detalhe: string, ok: bool}>
     */
    protected function requisitosParaAtivarEnvio(array $configuracao): array
    {
        // A fila de vagas e apenas informativa.
        return array_values(array_filter(
            $this->verificacoesEnvio($configuracao),
            static fn (array $item): bool => $item['chave'] !== 'fila'
        ));
    }

    /**
     * Lista os rotulos dos requisitos ainda pendentes.
     *
     * @param array<string, mixed> $configuracao
     *
     * @return list<string>
     */
    protected function pendenciasParaAtivarEnvio(array $configuracao): array
    {
        // Coleta apenas o que esta faltando.
        $pendencias = [];

        foreach ($this->requisitosParaAtivarEnvio($configuracao) as $item) {
            if (! $item['ok']) {
                $pendencias[] = $item['rotulo'];
            }
        }

        return $pendencias;
    }

    /**
     * Numero da pagina atual (nunca menor que 1).
     */
    protected function paginaAtual(): int
    {
        // Pagina pedida na URL (1 quando nao houver).
        $pagina = $this->request->getGet('pagina');

        if ($pagina === null || $pagina === '') {
            return 1;
        }

        return max(1, (int) $pagina);
    }

    /**
     * Quantidade de itens por pagina.
     */
    protected function porPagina(int $padrao = 12): int
    {
        return max(1, min(50, $padrao));
    }
}
