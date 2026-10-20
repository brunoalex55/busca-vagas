<?php

namespace App\Controllers\Admin;

use App\Libraries\GerenciadorCron;
use App\Libraries\TokenCron;
use App\Models\ExecucaoManualUsuarioModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cron;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - AGENDAMENTO (CRON)
 * --------------------------------------------------------------------------
 *
 * Painel central do agendamento (Fase 7). Concentra:
 *
 *   1. ESCOLHA DO MODO
 *      cron_linux, cron_windows ou spark_manual. O modo nao altera os
 *      Spark Commands: decide apenas quem os dispara.
 *
 *   2. CHAVE E INTEGRACAO COM O CRON-JOB.ORG
 *      Existe somente no modo `spark_manual`. Criar, atualizar, excluir,
 *      listar e sincronizar os jobs de cada usuario.
 *
 *   3. EXECUCAO MANUAL PELO ADMIN
 *      Roda os mesmos comandos que o agendador roda, sem limite diario.
 *
 *   4. LIMITE DE EXECUCOES MANUAIS DO USUARIO
 *      Configuracao e zeragem do contador diario do usuario comum.
 *
 *   5. ENDPOINT EXTERNO
 *      executarExterno() e PUBLICO (fora do grupo /admin) e protegido
 *      pelo token externo do usuario. E o unico caminho HTTP permitido
 *      para disparar tarefas de fora.
 *
 * REGRA: toda acao de escrita e POST. GET em rota de escrita responde 404.
 * Nunca existe token CSRF neste projeto: a protecao do endpoint externo
 * e o token do usuario, comparado com hash_equals().
 */
class AdminCron extends AdminBase
{
    /**
     * Tipos de job aceitos (mesmos do GerenciadorCron).
     *
     * @var list<string>
     */
    private const TIPOS = GerenciadorCron::TIPOS_DE_JOB;

    /**
     * Tamanho maximo da saida de um comando guardada para exibicao.
     */
    private const LIMITE_SAIDA = 4000;

    /**
     * Biblioteca que concentra a regra de agendamento.
     */
    private GerenciadorCron $gerenciador;

    /**
     * Model dos usuarios (lista de ativos).
     */
    private UsuarioModel $usuarios;

    /**
     * Model do contador de execucoes manuais.
     */
    private ExecucaoManualUsuarioModel $execucoes;

    /**
     * Carrega a biblioteca e os models.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->gerenciador = new GerenciadorCron();
        $this->usuarios    = model(UsuarioModel::class);
        $this->execucoes   = model(ExecucaoManualUsuarioModel::class);
    }

    // =========================================================================
    // TELA PRINCIPAL
    // =========================================================================

    /**
     * Tela de agendamento: modo, chave, limites, jobs e execucoes manuais.
     */
    public function index(): string
    {
        // Modo em uso.
        $modo = $this->gerenciador->obterModoAtual();

        // Chave da API (nunca vai completa para a view).
        $chave = $this->gerenciador->obterApiKeyCronJob();

        $apiKeyConfigurada = false;
        $apiKeyMascarada   = '';

        if ($chave !== null) {
            $apiKeyConfigurada = true;
            $apiKeyMascarada   = $this->mascarar($chave);
        }

        return view('admin/cron/index', $this->dadosAdmin('Agendamento', [
            'modoAtual'         => $modo,
            'modos'             => $this->gerenciador->modosDisponiveis(),
            'instrucoes'        => $this->gerenciador->obterInstrucoesDoModo($modo),
            'modoEhManual'      => $modo === GerenciadorCron::MODO_MANUAL,
            'apiKeyConfigurada' => $apiKeyConfigurada,
            'apiKeyMascarada'   => $apiKeyMascarada,
            'limites'           => $this->gerenciador->obterLimitesDoPlano(),
            'limiteManual'      => $this->gerenciador->obterLimiteExecucoesManuaisUsuario(),
            'usuariosDoPainel'  => $this->montarUsuariosDoPainel(),
            'execucoesRecentes' => $this->execucoes->listarRecentes(10),
            'tokenGlobal'       => (new TokenCron())->obter(),
        ]));
    }

    // =========================================================================
    // MODO DE AGENDAMENTO
    // =========================================================================

    /**
     * Grava o modo de agendamento escolhido.
     */
    public function salvarModo(): RedirectResponse
    {
        $modo = trim((string) $this->request->getPost('modo'));

        // Modo inexistente e recusado pela biblioteca.
        if (! $this->gerenciador->salvarModo($modo)) {
            return $this->voltar('Modo de agendamento invalido.', false);
        }

        $mensagem = 'Modo de agendamento atualizado.';

        if ($modo === GerenciadorCron::MODO_MANUAL) {
            $mensagem .= ' A integracao com o cron-job.org esta disponivel neste modo.';
        } else {
            $mensagem .= ' A integracao com o cron-job.org fica indisponivel fora do modo spark_manual.';
        }

        return $this->voltar($mensagem);
    }

    // =========================================================================
    // CHAVE DE API E CONEXAO
    // =========================================================================

    /**
     * Grava a chave de API do cron-job.org (modo spark_manual).
     *
     * O valor nunca e devolvido para a tela nem gravado em log: a view
     * recebe apenas a versao mascarada.
     */
    public function salvarApiKeyCronJob(): RedirectResponse
    {
        // Chave vazia limpa o campo, entao este nao e um campo sensivel a esconder.
        $apiKey = trim((string) $this->request->getPost('api_key_cronjob'));

        if (! $this->gerenciador->salvarApiKeyCronJob($apiKey)) {
            return $this->voltar('A chave so pode ser salva no modo spark_manual.', false);
        }

        if ($apiKey === '') {
            return $this->voltar('Chave removida.');
        }

        return $this->voltar('Chave salva. Use "Testar conexao" para validar.');
    }

    /**
     * Testa a conexao com o cron-job.org listando os jobs da conta.
     */
    public function testarConexaoCronJob(): RedirectResponse
    {
        $resultado = $this->gerenciador->listarCronJobsDoServico();

        if (! $resultado['sucesso']) {
            return $this->voltar('Falha na conexao: ' . (string) $resultado['erro'], false);
        }

        // Quantidade de jobs devolvidos pelo servico.
        $quantidade = 0;

        if (isset($resultado['dados']['jobs']) && is_array($resultado['dados']['jobs'])) {
            $quantidade = count($resultado['dados']['jobs']);
        }

        return $this->voltar('Conexao OK. A conta do cron-job.org tem ' . $quantidade . ' job(s).');
    }

    // =========================================================================
    // EXECUCAO MANUAL PELO ADMIN
    // =========================================================================

    /**
     * Dispara o comando de vagas na hora (sem limite diario do usuario).
     */
    public function executarVagas(): RedirectResponse
    {
        return $this->voltarComResultado(
            $this->gerenciador->executarComando(GerenciadorCron::TIPO_VAGAS)
        );
    }

    /**
     * Dispara o comando de e-mails na hora (sem limite diario do usuario).
     */
    public function executarEmails(): RedirectResponse
    {
        return $this->voltarComResultado(
            $this->gerenciador->executarComando(GerenciadorCron::TIPO_EMAILS)
        );
    }

    /**
     * Gera um token global novo, invalidando o anterior.
     *
     * Usado apenas pelo caminho antigo (rotas /cron/...): o disparo por
     * usuario usa o token individual de cron_jobs_usuario.
     */
    public function gerarTokenGlobal(): RedirectResponse
    {
        (new TokenCron())->regenerar();

        return $this->voltar('Token global regenerado. Atualize o agendador que o utiliza.');
    }

    // =========================================================================
    // JOBS NO CRON-JOB.ORG
    // =========================================================================

    /**
     * Lista os jobs da conta no servico, em JSON.
     *
     * GET de leitura, mas consome uma requisicao da API: por isso tambem
     * fica restrito ao modo spark_manual (responde 403 fora dele).
     */
    public function listarJobsCronJobOrg(): ResponseInterface
    {
        // Fora do modo manual a rota responde 403, com a mensagem padrao.
        if ($this->gerenciador->obterModoAtual() !== GerenciadorCron::MODO_MANUAL) {
            return $this->response->setStatusCode(403)->setJSON([
                'sucesso' => false,
                'erro'    => GerenciadorCron::MSG_MODO_INTEGRACAO,
                'dados'   => [],
            ]);
        }

        $resultado = $this->gerenciador->listarCronJobsDoServico();

        // Modo correto, mas a API recusou a chamada.
        if (! $resultado['sucesso']) {
            return $this->response->setStatusCode(502)->setJSON([
                'sucesso' => false,
                'erro'    => (string) $resultado['erro'],
                'dados'   => [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'sucesso' => true,
            'erro'    => null,
            'dados'   => $resultado['dados'],
            'limites' => $this->gerenciador->obterLimitesDoPlano(),
        ]);
    }

    /**
     * Cria o job de um usuario no cron-job.org.
     *
     * Recebe usuario_id e tipo pelo corpo do formulario.
     */
    public function criarJobParaUsuario(): RedirectResponse
    {
        $usuarioId = (int) $this->request->getPost('usuario_id');
        $tipo      = trim((string) $this->request->getPost('tipo'));

        if ($usuarioId <= 0) {
            return $this->voltar('Informe o usuario do job.', false);
        }

        if (! in_array($tipo, array_keys(self::TIPOS), true)) {
            return $this->voltar('Tipo de job invalido.', false);
        }

        // O token do usuario e criado/reaproveitado pela biblioteca.
        $resultado = $this->gerenciador->criarCronJobNoServico($usuarioId, $tipo, '');

        if (! $resultado['sucesso']) {
            return $this->voltar('Nao foi possivel criar o job: ' . (string) $resultado['erro'], false);
        }

        return $this->voltar('Job de ' . $tipo . ' criado para o usuario #' . $usuarioId . '.');
    }

    /**
     * Exclui o job de um usuario no cron-job.org.
     *
     * @param int|string $usuarioId Identificador do usuario.
     * @param string     $tipo      Tipo do job (vagas ou emails).
     */
    public function excluirJobDoUsuario($usuarioId, $tipo): RedirectResponse
    {
        $id = $this->idDaRota($usuarioId);

        if (! in_array($tipo, array_keys(self::TIPOS), true)) {
            return $this->voltar('Tipo de job invalido.', false);
        }

        if (! $this->gerenciador->excluirCronJobNoServico($id, $tipo)) {
            return $this->voltar('Nao foi possivel excluir o job de ' . $tipo . '.', false);
        }

        return $this->voltar('Job de ' . $tipo . ' excluido do usuario #' . $id . '.');
    }

    /**
     * Sincroniza os jobs de todos os usuarios ativos com o servico.
     */
    public function sincronizarJobs(): RedirectResponse
    {
        $resultado = $this->gerenciador->sincronizarJobs();

        // Fora do modo manual a biblioteca recusa a operacao.
        if (! isset($resultado['dados']['criados'])) {
            return $this->voltar('Nao foi possivel sincronizar: ' . (string) $resultado['erro'], false);
        }

        $dados = $resultado['dados'];

        $mensagem = 'Sincronizacao concluida: '
            . (int) $dados['criados'] . ' job(s) criado(s), '
            . (int) $dados['atualizados'] . ' atualizado(s), '
            . (int) $dados['erros'] . ' erro(s).';

        // Detalhes dos erros, limitados para nao estourar o aviso.
        if (isset($dados['mensagens']) && is_array($dados['mensagens']) && $dados['mensagens'] !== []) {
            $recortadas = array_slice($dados['mensagens'], 0, 5);
            $mensagem .= ' ' . implode(' ', $recortadas);
        }

        return $this->voltar($mensagem, (int) $dados['erros'] === 0);
    }

    /**
     * Gera um token externo novo para um usuario.
     *
     * O token anterior deixa de valer nos dois jobs do usuario.
     *
     * @param int|string $usuarioId Identificador do usuario.
     */
    public function regenerarTokenUsuario($usuarioId): RedirectResponse
    {
        $id = $this->idDaRota($usuarioId);

        $usuario = $this->usuarios->find($id);

        if ($usuario === null) {
            return $this->voltar('Usuario nao encontrado.', false);
        }

        $this->gerenciador->regenerarTokenDoUsuario($id);

        return $this->voltar(
            'Token do usuario #' . $id . ' regenerado. Atualize o job correspondente no servico.'
        );
    }

    // =========================================================================
    // LIMITE DE EXECUCOES MANUAIS DO USUARIO
    // =========================================================================

    /**
     * Grava o limite diario de execucoes manuais do usuario comum.
     */
    public function salvarLimiteExecucoesManuais(): RedirectResponse
    {
        $limite = (int) $this->request->getPost('limite_execucoes_manuais');

        if (! $this->gerenciador->salvarLimiteExecucoesManuaisUsuario($limite)) {
            return $this->voltar('Informe um limite entre 0 e 100 (0 desabilita).', false);
        }

        if ($limite === 0) {
            return $this->voltar('Execucoes manuais desabilitadas para os usuarios.');
        }

        return $this->voltar('Limite de execucoes manuais atualizado para ' . $limite . ' por dia.');
    }

    /**
     * Zera o contador de execucoes manuais de um usuario hoje.
     *
     * @param int|string $usuarioId Identificador do usuario.
     */
    public function zerarExecucoesManuaisUsuario($usuarioId): RedirectResponse
    {
        $id = $this->idDaRota($usuarioId);

        $usuario = $this->usuarios->find($id);

        if ($usuario === null) {
            return $this->voltar('Usuario nao encontrado.', false);
        }

        $this->gerenciador->zerarExecucoesManuaisUsuario($id);

        return $this->voltar('Contador de execucoes manuais do usuario #' . $id . ' zerado.');
    }

    // =========================================================================
    // ENDPOINT EXTERNO (PUBLICO, PROTEGIDO POR TOKEN)
    // =========================================================================

    /**
     * Executa um comando para um usuario, a pedido de um servico externo.
     *
     * O token vem do header X-Cron-Token (preferencial) ou da query
     * string ?token=. Nao ha sessao aqui: o token e a unica credencial,
     * validado com hash_equals() contra o token do usuario.
     *
     * O comando vem da query string ?comando=vagas ou ?comando=emails.
     *
     * Resposta (JSON):
     *   status            'sucesso' ou 'erro'
     *   comando           'vagas', 'emails' ou ''
     *   mensagem          texto do resultado
     *   estatisticas      detalhes da execucao
     *   duracao_segundos  tempo gasto
     *   executado_em      data e hora
     *
     * Se o comando passar do limite de tempo (25 segundos por padrao), a
     * resposta e 202 Accepted e o comando continua rodando em segundo
     * plano: o servico externo abandona a requisicao depois de 30
     * segundos, e o resultado fica no log do sistema.
     *
     * Este metodo NAO esta no grupo /admin: e a unica rota de escrita
     * publica do sistema.
     *
     * @param int|string $usuarioId Identificador do usuario da URL.
     */
    public function executarExterno($usuarioId): ResponseInterface
    {
        $id = (int) $usuarioId;

        // Token do header, com o da query string como alternativa.
        $token = trim($this->request->getHeaderLine('X-Cron-Token'));

        if ($token === '') {
            $token = trim((string) $this->request->getGet('token'));
        }

        // Token invalido vem antes de tudo, para nao revelar se o usuario existe.
        if (! $this->gerenciador->validarTokenExterno($token, $id)) {
            $this->registrarLogExterno($id, '', 401, 0.0, 'erro');

            return $this->respostaExterna(401, 'erro', '', 'Token invalido.', [], 0.0);
        }

        // Comando pedido pela URL.
        $comando = trim((string) $this->request->getGet('comando'));

        if (! in_array($comando, array_keys(self::TIPOS), true)) {
            $this->registrarLogExterno($id, $comando, 400, 0.0, 'erro');

            return $this->respostaExterna(
                400,
                'erro',
                $comando,
                'Informe o parametro comando com um destes valores: ' . implode(', ', array_keys(self::TIPOS)) . '.',
                [],
                0.0
            );
        }

        // Usuario precisa existir.
        $usuario = $this->usuarios->find($id);

        if ($usuario === null) {
            $this->registrarLogExterno($id, $comando, 404, 0.0, 'erro');

            return $this->respostaExterna(404, 'erro', $comando, 'Usuario nao encontrado.', [], 0.0);
        }

        // Executa com limite de tempo (o servico externo espera 30s).
        $resultado = $this->gerenciador->executarComando(
            $comando,
            $id,
            'cron',
            $this->limiteDeResposta()
        );

        // Prazo estourado: responde 202 e deixa o comando em segundo plano.
        if ($resultado['em_execucao']) {
            $this->registrarLogExterno($id, $comando, 202, (float) $resultado['duracao_segundos'], 'em_execucao');

            return $this->respostaExterna(
                202,
                'sucesso',
                $comando,
                'O comando foi iniciado e continua em execucao. O resultado fica no log do sistema.',
                [
                    'usuario_id'    => $id,
                    'em_execucao'   => true,
                    'arquivo_saida' => $resultado['arquivo_saida'],
                ],
                (float) $resultado['duracao_segundos']
            );
        }

        // Resultado final do comando.
        $sucesso = (bool) $resultado['sucesso'];
        $status  = 'erro';

        if ($sucesso) {
            $status = 'sucesso';
        }

        $mensagem = 'Comando executado com sucesso.';

        if (! $sucesso) {
            $mensagem = texto_ou($resultado, 'erro', 'O comando terminou com erro.');
        }

        // Registra a execucao no job local.
        $this->gerenciador->registrarExecucaoJob($id, $comando, $status);

        // Codigo HTTP da resposta.
        $codigoHttp = 500;

        if ($sucesso) {
            $codigoHttp = 200;
        }

        $this->registrarLogExterno($id, $comando, $codigoHttp, (float) $resultado['duracao_segundos'], $status);

        return $this->respostaExterna(
            $codigoHttp,
            $status,
            $comando,
            $mensagem,
            [
                'usuario_id'    => $id,
                'codigo_saida'  => $resultado['codigo_saida'],
                'saida'         => $this->resumirSaida((string) $resultado['saida']),
            ],
            (float) $resultado['duracao_segundos']
        );
    }

    /**
     * Resposta JSON padronizada do endpoint externo.
     *
     * @param array<string, mixed> $estatisticas
     */
    private function respostaExterna(
        int $codigoHttp,
        string $status,
        string $comando,
        string $mensagem,
        array $estatisticas,
        float $duracao
    ): ResponseInterface {
        return $this->response
            ->setStatusCode($codigoHttp)
            ->setJSON([
                'status'           => $status,
                'comando'          => $comando,
                'mensagem'         => $mensagem,
                'estatisticas'     => $estatisticas,
                'duracao_segundos' => $duracao,
                'executado_em'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Tempo maximo de espera do endpoint externo (segundos).
     */
    private function limiteDeResposta(): float
    {
        return (float) config(Cron::class)->limiteRespostaEndpointExterno;
    }

    // =========================================================================
    // APOIO INTERNO
    // =========================================================================

    /**
     * Monta a lista de usuarios ativos com os dados de job e de limite.
     *
     * @return list<array<string, mixed>>
     */
    private function montarUsuariosDoPainel(): array
    {
        $linhas = [];

        foreach ($this->usuarios->listarParaBusca(0) as $usuario) {
            $usuarioId = (int) $usuario['id'];

            // Jobs locais do usuario, com o token ja mascarado.
            $jobs = $this->gerenciador->listarCronJobsDoUsuario($usuarioId);

            $porTipo = [];

            foreach ($jobs as $job) {
                $porTipo[(string) $job['tipo']] = $job;
            }

            // Estado do limite manual do usuario.
            $estado = $this->gerenciador->usuarioPodeExecutarManualmente($usuarioId);

            $montado = [
                'id'                => $usuarioId,
                'nome'              => (string) $usuario['nome'],
                'email'             => (string) $usuario['email'],
                'tem_token'         => $jobs !== [],
                'execucoes_hoje'    => (int) $estado['usadas'],
                'restantes'         => (int) $estado['restantes'],
                'pode_executar'     => (bool) $estado['pode'],
                'motivo'            => (string) $estado['motivo'],
            ];

            // Um resumo para cada tipo de job do registro (assim um
            // provedor novo aparece na tabela sem mexer na view).
            foreach (array_keys(GerenciadorCron::TIPOS_DE_JOB) as $tipo) {
                $chave = 'job_' . $tipo;
                $montado[$chave] = $this->resumirJob($porTipo, $tipo);
            }

            $linhas[] = $montado;
        }

        return $linhas;
    }

    /**
     * Resume um job de um tipo, trazendo os dados usados na tabela.
     *
     * @param array<string, array<string, mixed>> $porTipo Jobs do usuario indexados pelo tipo.
     *
     * @return array<string, mixed>
     */
    private function resumirJob(array $porTipo, string $tipo): array
    {
        $vazio = [
            'existe'          => false,
            'job_id'          => null,
            'token_mascarado' => '',
            'ultimo_status'   => '',
            'ultima_execucao' => '',
        ];

        if (! isset($porTipo[$tipo])) {
            return $vazio;
        }

        $job = $porTipo[$tipo];

        $ultimoStatus = '';

        if ($job['ultimo_status'] !== null) {
            $ultimoStatus = (string) $job['ultimo_status'];
        }

        $ultimaExecucao = '';

        if ($job['ultima_execucao'] !== null) {
            $ultimaExecucao = (string) $job['ultima_execucao'];
        }

        $jobId = null;

        if ($job['cronjob_org_job_id'] !== null) {
            $jobId = (int) $job['cronjob_org_job_id'];
        }

        return [
            'existe'          => true,
            'job_id'          => $jobId,
            'token_mascarado' => (string) $job['token_mascarado'],
            'ultimo_status'   => $ultimoStatus,
            'ultima_execucao' => $ultimaExecucao,
        ];
    }

    /**
     * Redireciona para a tela de agendamento informando o resultado de um comando.
     *
     * @param array<string, mixed> $resultado Retorno de executarComando().
     */
    private function voltarComResultado(array $resultado): RedirectResponse
    {
        // Saida do comando, recortada, guardada para exibicao na tela.
        $saida = $this->resumirSaida((string) $resultado['saida']);

        if ($saida !== '') {
            $this->sessao->setFlashdata('saida_comando', $saida);
        }

        // Falha na execucao.
        if (! $resultado['sucesso']) {
            $mensagem = 'Falha no comando ' . (string) $resultado['comando'] . ': ' . (string) $resultado['erro'];

            return $this->voltar($mensagem, false);
        }

        $mensagem = 'Comando ' . (string) $resultado['comando'] . ' concluido em '
            . (string) $resultado['duracao_segundos'] . ' segundo(s).';

        return $this->voltar($mensagem);
    }

    /**
     * Recorta a saida de um comando para exibicao segura.
     */
    private function resumirSaida(string $saida): string
    {
        $saida = trim($saida);

        if (mb_strlen($saida) <= self::LIMITE_SAIDA) {
            return $saida;
        }

        return mb_substr($saida, 0, self::LIMITE_SAIDA) . "\n[saida truncada]";
    }

    /**
     * Grava o log das chamadas ao endpoint externo.
     *
     * Guarda apenas metodo, usuario, comando, status, resultado, duracao
     * e IP. O token NUNCA entra no log.
     */
    private function registrarLogExterno(
        int $usuarioId,
        string $comando,
        int $status,
        float $duracao,
        string $resultado = ''
    ): void {
        $diretorio = WRITEPATH . 'logs';

        if (! is_dir($diretorio)) {
            return;
        }

        $linha = date('Y-m-d H:i:s')
            . ' | ' . $this->request->getMethod()
            . ' | usuario=' . $usuarioId
            . ' | comando=' . $comando
            . ' | status=' . $status
            . ' | resultado=' . $resultado
            . ' | duracao=' . $duracao . 's'
            . ' | ip=' . $this->request->getIPAddress()
            . PHP_EOL;

        file_put_contents(
            $diretorio . DIRECTORY_SEPARATOR . 'cron-externo-' . date('Y-m-d') . '.log',
            $linha,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Mascara um segredo, exibindo apenas o inicio e o fim.
     */
    private function mascarar(string $valor): string
    {
        $tamanho = strlen($valor);

        // Valor curto: mostra apenas os ultimos caracteres.
        if ($tamanho <= 8) {
            return str_repeat('*', 12) . substr($valor, -2);
        }

        return substr($valor, 0, 4) . str_repeat('*', 12) . substr($valor, -4);
    }

    /**
     * Redireciona para a tela de agendamento com uma mensagem.
     */
    private function voltar(string $mensagem, bool $sucesso = true): RedirectResponse
    {
        // Mensagem de erro usa a chave "erro".
        if (! $sucesso) {
            return redirect()
                ->to(site_url('admin/cron'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('admin/cron'))
            ->with('sucesso', $mensagem);
    }
}
