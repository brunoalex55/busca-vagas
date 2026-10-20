<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER BASE DO PAINEL ADMINISTRATIVO
 * --------------------------------------------------------------------------
 *
 * Reune o que todas as telas administrativas precisam:
 *   - identificacao do administrador logado;
 *   - dados comuns de SEO/titulo das views;
 *   - paginacao manual;
 *   - leitura segura de identificadores da rota.
 *
 * Herda de BaseController, que ja entrega a sessao, os helpers e o
 * tratamento padrao de erros de formulario (voltarComErros).
 */
abstract class AdminBase extends BaseController
{
    /** ID do administrador autenticado. */
    protected int $adminId = 0;

    /**
     * Inicializa o controller e guarda o ID do admin logado.
     */
    public function initController($request, $response, $logger)
    {
        // Executa a inicializacao padrao.
        parent::initController($request, $response, $logger);

        // O filtro "admin" garante que este valor existe e e um admin.
        $this->adminId = (int) $this->sessao->get('usuario_id');
    }

    /**
     * Monta os dados comuns de uma view do painel administrativo.
     *
     * @param array<string, mixed> $extras Dados especificos da tela.
     *
     * @return array<string, mixed>
     */
    protected function dadosAdmin(string $titulo, array $extras = []): array
    {
        // SEO proprio da area privada (noindex ja fica no layout).
        $seo = valor_ou($extras, 'seo', [
            'titulo'         => $titulo,
            'descricao'      => 'Area administrativa do sistema de candidaturas.',
            'palavras_chave' => '',
            'tipo'           => 'website',
        ]);

        // O SEO nao precisa ser duplicado nos extras.
        unset($extras['seo']);

        return $this->dadosComuns + $extras + [
            'seo'          => $seo,
            'tituloPagina' => $titulo,
            'nomeAdmin'    => (string) $this->sessao->get('nome'),
        ];
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
    protected function porPagina(int $padrao = 20): int
    {
        return max(1, min(100, $padrao));
    }

    /**
     * Converte o identificador recebido pela rota em inteiro positivo.
     *
     * @param int|string $valor Valor bruto da rota.
     *
     * @throws PageNotFoundException Quando o valor nao e um ID valido.
     */
    protected function idDaRota($valor): int
    {
        // O roteador usa (:num), mas reforcamos a checagem.
        $id = (int) $valor;

        if ($id <= 0) {
            throw PageNotFoundException::forPageNotFound('Registro nao encontrado.');
        }

        return $id;
    }

    /**
     * Monta a URL de retorno preservando os filtros de uma listagem.
     *
     * @param array<string, mixed> $filtros
     */
    protected function urlComFiltros(string $rota, array $filtros): string
    {
        // Remove valores vazios para a URL ficar limpa.
        $filtros = array_filter($filtros, static fn ($valor): bool => $valor !== '' && $valor !== null);

        $query = http_build_query($filtros);

        // Sem filtros: a URL fica sem interrogacao.
        if ($query === '') {
            return site_url($rota);
        }

        return site_url($rota) . '?' . $query;
    }
}
