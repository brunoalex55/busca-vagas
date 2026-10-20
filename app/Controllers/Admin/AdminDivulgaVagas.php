<?php

namespace App\Controllers\Admin;

use App\Libraries\BuscadorDivulgaVagas;
use App\Libraries\CandidaturaDivulgaVagas;
use App\Libraries\GerenciadorCron;
use App\Libraries\LimiteExecucaoManual;
use App\Models\ConfiguracaoGlobalModel;
use App\Models\ExecucaoDivulgaVagasModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: PAINEL DO DIVULGA VAGAS (ADMINISTRADOR)
 * --------------------------------------------------------------------------
 *
 * Telas e acoes do segundo provedor para o administrador:
 *
 *   - index(): tela de execucao manual (buscar e candidatar);
 *   - historico(): as ultimas execucoes (GET, so leitura);
 *   - buscarManual(): dispara a busca de vagas (POST);
 *   - candidatarManual(): dispara a candidatura (POST);
 *   - salvarLimites(): grava os limites globais do dia (POST).
 *
 * O ADMINISTRADOR TEM DOIS ALVOS:
 *
 *   - "todos": a execucao vale para todos os usuarios ativos (cada um
 *     com as suas palavras-chave e os seus limites);
 *   - um usuario especifico: so a execucao dele e disparada.
 *
 * REGRAS QUE O CONTROLLER SEMPRE CUMPRE:
 *
 *   - toda escrita e POST;
 *   - a execucao manual NUNCA contorna os limites: os limites do
 *     administrador (execucoes manuais), o limite do bot de cada
 *     usuario e o limite de e-mail sao sempre conferidos;
 *   - toda execucao fica registrada em execucoes_divulgavagas.
 */
class AdminDivulgaVagas extends AdminBase
{
    /**
     * Prazo que o pedido web espera antes de devolver (em segundos).
     *
     * Passado o prazo, o comando continua rodando em segundo plano (e
     * o resultado aparece no historico).
     */
    private const PRAZO_DA_RESPOSTA = 3.0;

    /** @var VagaModel */
    private VagaModel $vagas;

    /** @var UsuarioModel */
    private UsuarioModel $usuarios;

    /** @var ExecucaoDivulgaVagasModel */
    private ExecucaoDivulgaVagasModel $execucoes;

    /** @var ConfiguracaoGlobalModel */
    private ConfiguracaoGlobalModel $globais;

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
        $this->usuarios  = model(UsuarioModel::class);
        $this->execucoes = model(ExecucaoDivulgaVagasModel::class);
        $this->globais   = model(ConfiguracaoGlobalModel::class);
        $this->limites   = new LimiteExecucaoManual();
        $this->gerenciador = new GerenciadorCron();
    }

    /**
     * Tela de execucao manual do Divulga Vagas (administrador).
     */
    public function index(): string
    {
        // Os limites globais do administrador (com o contador do dia).
        $limites = $this->limites->limitesAdmin();

        return view('admin/divulgavagas/executar', $this->dadosAdmin('Divulga Vagas', [
            'limites'    => $limites,
            'situacoes'  => $this->vagas->contarPorSituacaoDivulgaVagasGlobal(),
            'usuarios'   => $this->usuariosParaAlvo(),
            'execucoes'  => $this->execucoes->listarGlobais(10),
            'configuracao' => $this->globais->obter(),
        ]));
    }

    /**
     * Historico das execucoes do segundo provedor (so leitura).
     */
    public function historico(): string
    {
        return view('admin/divulgavagas/historico', $this->dadosAdmin('Historico do Divulga Vagas', [
            'execucoes' => $this->execucoes->listarGlobais(50),
        ]));
    }

    /**
     * Dispara a busca de vagas (POST).
     *
     * O alvo pode ser "todos" ou um usuario especifico.
     */
    public function buscarManual(): RedirectResponse|ResponseInterface
    {
        // Limite do administrador (execucoes manuais por dia).
        $permitido = $this->limites->limitesAdmin()['buscas'];

        if (! $permitido['pode']) {
            return $this->voltar(
                $this->limiteComoMensagem($permitido),
                false,
                $permitido
            );
        }

        // O alvo escolhido pelo administrador.
        $alvo = $this->alvoDoPost();

        // O usuario da execucao (null = todos os ativos).
        $usuarioAlvo = null;

        if (! $alvo['todos']) {
            $usuarioAlvo = $alvo['usuario_id'];
        }

        // A execucao NAO roda dentro do pedido web: o bot leva
        // minutos (uma aba do navegador por combinacao). O comando
        // Spark e solto em segundo plano, como no agendador, e o
        // resultado aparece no historico.
        $resultado = $this->gerenciador->executarComando(
            GerenciadorCron::TIPO_DIVULGA_BUSCAR,
            $usuarioAlvo,
            'manual_admin',
            self::PRAZO_DA_RESPOSTA
        );

        if (! $resultado['sucesso']) {
            return $this->voltar(
                'Nao foi possivel iniciar a busca: ' . (string) $resultado['erro'],
                false
            );
        }

        // Soma no contador do dia (uma execucao do administrador).
        $this->limites->registrarBuscaAdmin();

        return $this->voltar(
            'Busca iniciada em segundo plano. O resultado aparece no historico.',
            true,
            $this->limites->limitesAdmin()['buscas']
        );
    }

    /**
     * Dispara a candidatura (POST).
     *
     * O alvo pode ser "todos" ou um usuario especifico.
     *
     * Como a busca, a execucao roda em segundo plano.
     */
    public function candidatarManual(): RedirectResponse|ResponseInterface
    {
        // Limite do administrador (execucoes manuais por dia).
        $permitido = $this->limites->limitesAdmin()['candidaturas'];

        if (! $permitido['pode']) {
            return $this->voltar(
                $this->limiteComoMensagem($permitido),
                false,
                $permitido
            );
        }

        // O alvo escolhido pelo administrador.
        $alvo = $this->alvoDoPost();

        // O usuario da execucao (null = todos os ativos).
        $usuarioAlvo = null;

        if (! $alvo['todos']) {
            $usuarioAlvo = $alvo['usuario_id'];
        }

        // O comando roda em segundo plano (o pedido web nao espera).
        $resultado = $this->gerenciador->executarComando(
            GerenciadorCron::TIPO_DIVULGA_CANDIDATAR,
            $usuarioAlvo,
            'manual_admin',
            self::PRAZO_DA_RESPOSTA
        );

        if (! $resultado['sucesso']) {
            return $this->voltar(
                'Nao foi possivel iniciar a candidatura: ' . (string) $resultado['erro'],
                false
            );
        }

        // Soma no contador do dia (uma execucao do administrador).
        $this->limites->registrarCandidaturaAdmin();

        return $this->voltar(
            'Candidatura iniciada em segundo plano. O resultado aparece no historico.',
            true,
            $this->limites->limitesAdmin()['candidaturas']
        );
    }

    /**
     * Grava os limites globais de execucao manual (POST).
     */
    public function salvarLimites(): RedirectResponse|ResponseInterface
    {
        $buscas = (int) $this->request->getPost('admin_max_buscas_manuais_dia');
        $candidaturas = (int) $this->request->getPost('admin_max_candidaturas_manuais_dia');

        // Numeros negativos viram zero (que desliga a execucao manual).
        $this->globais->salvarLimites(max(0, $buscas), max(0, $candidaturas));

        return $this->voltar('Limites de execucao manual salvos.', true);
    }

    // =====================================================================
    // AJUDA
    // =====================================================================

    /**
     * O alvo escolhido no formulario: todos os usuarios ou um so.
     *
     * @return array{todos: bool, usuario_id: int}
     */
    private function alvoDoPost(): array
    {
        $alvo = trim((string) $this->request->getPost('alvo'));

        // "todos" e o padrao.
        if ($alvo === 'todos') {
            return ['todos' => true, 'usuario_id' => 0];
        }

        $usuarioId = (int) $this->request->getPost('usuario_id');

        // O usuario precisa existir e estar ativo.
        $usuario = $this->usuarioAtivo($usuarioId);

        if ($usuario === null) {
            return ['todos' => true, 'usuario_id' => 0];
        }

        return ['todos' => false, 'usuario_id' => $usuarioId];
    }

    /**
     * Um usuario ativo do sistema (pelo id).
     *
     * @return array<string, mixed>|null
     */
    private function usuarioAtivo(int $usuarioId): ?array
    {
        if ($usuarioId <= 0) {
            return null;
        }

        foreach ($this->usuarios->listarParaBusca(0) as $usuario) {
            if ((int) $usuario['id'] === $usuarioId) {
                return $usuario;
            }
        }

        return null;
    }

    /**
     * Os usuarios que podem ser alvo da execucao.
     *
     * @return list<array<string, mixed>>
     */
    private function usuariosParaAlvo(): array
    {
        $lista = [];

        foreach ($this->usuarios->listarParaBusca(0) as $usuario) {
            $lista[] = [
                'id'    => (int) $usuario['id'],
                'nome'  => (string) $usuario['nome'],
                'email' => (string) $usuario['email'],
            ];
        }

        return $lista;
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
        return 'Busca concluida: ' . (int) $resumo['vagas_inseridas'] . ' vaga(s) nova(s) | '
            . (int) $resumo['vagas_repetidas'] . ' repetida(s) | '
            . (int) $resumo['consultas'] . ' consulta(s) | '
            . (int) $resumo['erros'] . ' erro(s).';
    }

    /**
     * Resumo da candidatura, em texto.
     *
     * @param array<string, mixed> $resumo
     */
    private function resumoDaCandidatura(array $resumo): string
    {
        $mensagem = 'Candidaturas: ' . (int) $resumo['candidaturas_enviadas']
            . ' | e-mails: ' . (int) $resumo['emails_enviados']
            . ' | falhas: ' . (int) $resumo['erros'] . '.';

        $aviso = texto_ou($resumo, 'mensagem');

        if ($aviso !== '') {
            $mensagem .= ' ' . $aviso;
        }

        return $mensagem;
    }

    /**
     * Volta para a tela com uma mensagem.
     *
     * Quando o pedido veio do fetch (execucao sem recarregar a pagina), a
     * resposta e um JSON com o resumo. Sem fetch, e um redirect com
     * mensagem, como nas outras telas do admin.
     *
     * @param array<string, mixed> $limite Limite usado na resposta.
     */
    private function voltar(string $mensagem, bool $sucesso = true, array $limite = []): RedirectResponse|ResponseInterface
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
                ->to(site_url('admin/divulgavagas'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('admin/divulgavagas'))
            ->with('sucesso', $mensagem);
    }
}
