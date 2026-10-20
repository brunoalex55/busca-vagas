<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use App\Libraries\BuscadorDivulgaVagas;
use App\Libraries\CandidaturaDivulgaVagas;
use App\Libraries\GerenciadorCron;
use App\Libraries\LimiteExecucaoManual;
use App\Libraries\VerificadorLimiteEnvio;
use App\Models\ExecucaoDivulgaVagasModel;
use App\Models\PalavraChaveModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: PAINEL DO DIVULGA VAGAS (USUARIO)
 * --------------------------------------------------------------------------
 *
 * Telas e acoes do segundo provedor para o usuario comum:
 *
 *   - index(): tela de execucao manual (buscar e candidatar);
 *   - buscarManual(): dispara uma busca de vagas (POST);
 *   - candidatarManual(): dispara a candidatura das vagas pendentes (POST);
 *   - candidatarVaga(): candidata a UMA vaga especifica (POST);
 *   - historico(): as ultimas execucoes do usuario (GET, so leitura).
 *
 * REGRAS QUE O CONTROLLER SEMPRE CUMPRE:
 *
 *   - so o proprio usuario executa (a sessao define o dono);
 *   - toda escrita e POST;
 *   - a execucao manual NUNCA contorna os limites: os limites do
 *     usuario (execucoes manuais), o limite do bot e o limite de e-mail
 *     sao sempre conferidos;
 *   - toda execucao fica registrada em execucoes_divulgavagas.
 */
class PainelDivulgaVagas extends PainelBase
{
    /**
     * Prazo que o pedido web espera antes de devolver (em segundos).
     *
     * Passado o prazo, o comando continua rodando em segundo plano (o
     * resultado aparece no historico e nos selos das vagas).
     */
    private const PRAZO_DA_RESPOSTA = 3.0;

    /** @var VagaModel */
    private VagaModel $vagas;

    /** @var PalavraChaveModel */
    private PalavraChaveModel $palavras;

    /** @var ExecucaoDivulgaVagasModel */
    private ExecucaoDivulgaVagasModel $execucoes;

    /** @var LimiteExecucaoManual */
    private LimiteExecucaoManual $limites;

    /** @var GerenciadorCron */
    private GerenciadorCron $gerenciador;

    /**
     * Carrega os models depois da inicializacao base.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->vagas     = model(VagaModel::class);
        $this->palavras  = model(PalavraChaveModel::class);
        $this->execucoes = model(ExecucaoDivulgaVagasModel::class);
        $this->limites   = new LimiteExecucaoManual();
        $this->gerenciador = new GerenciadorCron();
    }

    /**
     * Tela de execucao manual do Divulga Vagas.
     */
    public function index(): string
    {
        return view('painel/divulgavagas/executar', $this->dadosPainel('Divulga Vagas', [
            'palavras'   => $this->palavrasAtivas(),
            'limiteBusca' => $this->limites->podeBuscarManualmente($this->usuarioId),
            'limiteCandidatura' => $this->limites->podeCandidatarManualmente($this->usuarioId),
            'limiteEmail' => (new VerificadorLimiteEnvio())->podeEnviarAgora($this->usuarioId),
            'situacoes'  => $this->vagas->contarPorSituacaoDivulgaVagas($this->usuarioId),
            'ultima'     => $this->execucoes->ultimaDoUsuario($this->usuarioId),
            'historico'  => $this->execucoes->listarDoUsuario($this->usuarioId, 5),
        ]));
    }

    /**
     * Historico das execucoes do usuario (so leitura).
     */
    public function historico(): string
    {
        return view('painel/divulgavagas/historico', $this->dadosPainel('Historico do Divulga Vagas', [
            'execucoes' => $this->execucoes->listarDoUsuario($this->usuarioId, 20),
        ]));
    }

    /**
     * Dispara uma busca de vagas agora (POST).
     *
     * A busca manual processa UMA combinacao (termo + cidade), escolhida
     * pelo usuario na tela.
     */
    public function buscarManual(): RedirectResponse|ResponseInterface
    {
        // Limite do usuario (execucoes manuais por dia).
        $permitido = $this->limites->podeBuscarManualmente($this->usuarioId);

        if (! $permitido['pode']) {
            return $this->voltarComMensagem(
                $this->limiteComoMensagem($permitido),
                false,
                $permitido
            );
        }

        // A combinacao escolhida pelo usuario.
        $palavraId = (int) $this->request->getPost('palavra_chave_id');
        $cidade = trim((string) $this->request->getPost('cidade'));

        $palavra = $this->palavraDoUsuario($palavraId);

        if ($palavra === null || $cidade === '') {
            return $this->voltarComMensagem(
                'Escolha a palavra-chave e a cidade da busca.',
                false
            );
        }

        // A cidade precisa estar na palavra-chave escolhida.
        if (! in_array($cidade, $this->cidadesDaPalavra($palavra), true)) {
            return $this->voltarComMensagem(
                'A cidade informada nao pertence a palavra-chave escolhida.',
                false
            );
        }

        // A execucao comeca (e fica registrada mesmo se der erro).
        $idExecucao = $this->execucoes->abrir('buscar', 'manual_usuario', $this->usuarioId, $this->usuarioId);

        // O orquestrador da busca (aqui, so uma combinacao).
        $buscador = new BuscadorDivulgaVagas();
        $resumo = $buscador->buscarCombinacao($this->usuarioId, $palavra, $cidade, 'manual_usuario');

        // Soma no contador do dia (a execucao manual do usuario).
        $this->limites->registrarBuscaManual($this->usuarioId, 'manual_usuario');

        $this->execucoes->finalizar($idExecucao, 'sucesso', $resumo);

        return $this->voltarComMensagem(
            $this->resumoDaBusca($resumo),
            true
        );
    }

    /**
     * Dispara a candidatura das vagas pendentes (POST).
     *
     * Sao ate 3 vagas por execucao, mas cada uma passa pelos limites
     * (do bot e de e-mail) individualmente.
     *
     * A execucao NAO roda dentro do pedido web (o bot leva dezenas de
     * segundos por vaga): o comando Spark e solto em segundo plano, como
     * no agendador. O resultado aparece no historico e nos selos.
     */
    public function candidatarManual(): RedirectResponse|ResponseInterface
    {
        // Limite do usuario (execucoes manuais por dia).
        $permitido = $this->limites->podeCandidatarManualmente($this->usuarioId);

        if (! $permitido['pode']) {
            return $this->voltarComMensagem(
                $this->limiteComoMensagem($permitido),
                false,
                $permitido
            );
        }

        // O comando roda em segundo plano (o pedido web nao espera).
        // Sem --limite: o bot candidata TODAS as vagas da fila, uma a uma.
        $resultado = $this->gerenciador->executarComando(
            GerenciadorCron::TIPO_DIVULGA_CANDIDATAR,
            $this->usuarioId,
            'manual_usuario',
            self::PRAZO_DA_RESPOSTA
        );

        if (! $resultado['sucesso']) {
            return $this->voltarComMensagem(
                'Nao foi possivel iniciar a candidatura: ' . (string) $resultado['erro'],
                false
            );
        }

        // Soma no contador do dia (uma execucao do usuario).
        $this->limites->registrarCandidaturaManual($this->usuarioId, 'manual_usuario');

        return $this->voltarComMensagem(
            'Candidatura iniciada em segundo plano. O resultado aparece no historico.',
            true
        );
    }

    /**
     * Candidata a UMA vaga especifica (POST).
     *
     * E o botao "Candidatar agora" da lista de vagas.
     */
    public function candidatarVaga($vagaId): RedirectResponse|ResponseInterface
    {
        $id = (int) $vagaId;

        // A vaga tem que ser do usuario e do provedor.
        // A vaga, mas somente se ela for do usuario da sessao (o helper
        // tambem registra a tentativa de acesso a vaga de outra pessoa).
        $vaga = obterVagaDoUsuarioOu404($id, $this->usuarioId);

        // A vaga precisa ser do segundo provedor.
        if (texto_ou($vaga, 'provedor_vaga') !== 'divulgavagas') {
            return $this->voltarComMensagem('Vaga nao encontrada.', false);
        }

        // Limite do usuario (execucoes manuais por dia).
        $permitido = $this->limites->podeCandidatarManualmente($this->usuarioId);

        if (! $permitido['pode']) {
            return $this->voltarComMensagem(
                $this->limiteComoMensagem($permitido),
                false,
                $permitido
            );
        }

        $idExecucao = $this->execucoes->abrir('candidatar', 'manual_usuario', $this->usuarioId, $this->usuarioId);

        $candidatura = new CandidaturaDivulgaVagas();
        $resumo = $candidatura->candidatarVaga($this->usuarioId, $id, 'manual_usuario');

        $this->limites->registrarCandidaturaManual($this->usuarioId, 'manual_usuario');

        // Status da execucao: erro quando houve alguma falha.
        $status = 'sucesso';

        if ((int) $resumo['erros'] > 0) {
            $status = 'erro';
        }

        $this->execucoes->finalizar($idExecucao, $status, $resumo);

        $mensagem = 'Candidaturas: ' . (int) $resumo['candidaturas_enviadas']
            . ' | e-mails: ' . (int) $resumo['emails_enviados']
            . ' | falhas: ' . (int) $resumo['erros'];

        if ($resumo['mensagem'] !== null) {
            $mensagem .= ' | ' . (string) $resumo['mensagem'];
        }

        return $this->voltarComMensagem($mensagem, (int) $resumo['erros'] === 0);
    }

    // =====================================================================
    // AJUDA
    // =====================================================================

    /**
     * Palavras-chave ativas do usuario (para o seletor da tela).
     *
     * @return list<array<string, mixed>>
     */
    private function palavrasAtivas(): array
    {
        $lista = $this->palavras->listarAtivasDoUsuario($this->usuarioId);

        $preparadas = [];

        foreach ($lista as $palavra) {
            $cidades = $this->cidadesDaPalavra($palavra);

            $preparadas[] = [
                'id'      => (int) $palavra['id'],
                'termo'   => texto_ou($palavra, 'termo'),
                'cidades' => $cidades,
            ];
        }

        return $preparadas;
    }

    /**
     * Cidades de uma palavra-chave.
     *
     * @param array<string, mixed> $palavra
     *
     * @return list<string>
     */
    private function cidadesDaPalavra(array $palavra): array
    {
        $cidades = json_decode(texto_ou($palavra, 'cidades', '[]'), true);

        if (! is_array($cidades)) {
            return [];
        }

        $limpas = [];

        foreach ($cidades as $cidade) {
            if (! is_string($cidade)) {
                continue;
            }

            $cidade = trim((string) preg_replace('/\s+/u', ' ', $cidade));

            if ($cidade !== '' && ! in_array($cidade, $limpas, true)) {
                $limpas[] = $cidade;
            }
        }

        return $limpas;
    }

    /**
     * Palavra-chave do usuario (pelo id).
     *
     * @return array<string, mixed>|null
     */
    private function palavraDoUsuario(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $linha = $this->palavras->db->table($this->palavras->table)
            ->where('id', $id)
            ->where('usuario_id', $this->usuarioId)
            ->get()
            ->getRowArray();

        if ($linha === null || $linha === []) {
            return null;
        }

        return $linha;
    }

    /**
     * Mensagem de um limite que barrou a execucao.
     *
     * @param array<string, mixed> $permitido
     */
    private function limiteComoMensagem(array $permitido): string
    {
        $motivo = texto_ou($permitido, 'motivo');

        if ($motivo === 'desabilitado') {
            return 'As execucoes manuais do Divulga Vagas estao desabilitadas pelo administrador.';
        }

        if ($motivo === 'limite_atingido') {
            return 'Limite diario de execucoes manuais atingido ('
                . (int) $permitido['usadas'] . ' de ' . (int) $permitido['limite'] . ').';
        }

        return 'Nao e possivel executar agora.';
    }

    /**
     * Resumo da busca, em texto.
     *
     * @param array<string, mixed> $resumo
     */
    private function resumoDaBusca(array $resumo): string
    {
        return 'Vagas novas: ' . (int) $resumo['vagas_inseridas']
            . ' | repetidas: ' . (int) $resumo['vagas_repetidas']
            . ' | com e-mail: ' . (int) $resumo['com_email'];
    }

    /**
     * Volta para o painel com uma mensagem.
     *
     * Quando o pedido veio do fetch (execucao sem recarregar a pagina), a
     * resposta e um JSON com o resumo. Sem fetch, e um redirect com
     * mensagem, como nas outras telas.
     *
     * @param array<string, mixed> $limite Limite usado na resposta.
     */
    private function voltarComMensagem(string $mensagem, bool $sucesso, array $limite = []): RedirectResponse|ResponseInterface
    {
        // Pedido do fetch: responde JSON.
        if ($this->request->getHeaderLine('X-Requested-With') === 'fetch') {
            // Codigo: 200 no sucesso, 400 na recusa.
            $codigo = 400;

            if ($sucesso) {
                $codigo = 200;
            }

            return $this->response
                ->setStatusCode($codigo)
                ->setJSON([
                    'sucesso'   => $sucesso,
                    'mensagem'  => $mensagem,
                    'limite'    => (int) valor_ou($limite, 'limite', 0),
                    'usadas'    => (int) valor_ou($limite, 'usadas', 0),
                    'restantes' => (int) valor_ou($limite, 'restantes', 0),
                ]);
        }

        if (! $sucesso) {
            return redirect()
                ->to(site_url('painel/divulgavagas'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('painel/divulgavagas'))
            ->with('sucesso', $mensagem);
    }
}
