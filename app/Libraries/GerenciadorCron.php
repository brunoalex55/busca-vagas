<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use App\Models\ConfiguracaoCronModel;
use App\Models\CronJobUsuarioModel;
use App\Models\ExecucaoManualUsuarioModel;
use App\Models\UsuarioModel;
use Config\Cron as ConfigCron;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * GERENCIADOR DO AGENDAMENTO (CRON)
 * --------------------------------------------------------------------------
 *
 * Centraliza TUDO que envolve tarefas agendadas (Fase 7):
 *
 *   1. MODO DE AGENDAMENTO
 *      O admin escolhe entre `cron_linux`, `cron_windows` e `spark_manual`.
 *      O modo NAO altera o codigo dos Spark Commands: ele apenas decide
 *      quem os dispara.
 *
 *   2. EXECUCAO DIRETA
 *      executarComando() roda `php spark ...` em um processo filho, o que
 *      serve tanto para o disparo manual pelo painel quanto para os
 *      snipets exibidos ao admin.
 *
 *   3. INTEGRACAO COM O CRON-JOB.ORG
 *      Criar, atualizar, excluir e sincronizar os jobs de cada usuario.
 *      REGRA CRITICA: essa integracao SO existe no modo `spark_manual`.
 *
 *   4. LIMITE DE EXECUCOES MANUAIS DO USUARIO
 *      Quantas vezes por dia o usuario comum pode disparar manualmente
 *      (vagas + e-mails somados). O admin nao entra nesse limite.
 *
 * Os metodos de integracao devolvem sempre:
 *   ['sucesso' => bool, 'erro' => string|null, 'dados' => array]
 */
class GerenciadorCron
{
    /**
     * Modos de agendamento aceitos.
     */
    public const MODO_LINUX   = 'cron_linux';
    public const MODO_WINDOWS = 'cron_windows';
    public const MODO_MANUAL  = 'spark_manual';

    /**
     * Tipos de job suportados.
     */
    public const TIPO_VAGAS  = 'vagas';
    public const TIPO_EMAILS = 'emails';

    /**
     * Tipo do job que busca vagas no Divulga Vagas (segundo provedor).
     */
    public const TIPO_DIVULGA_BUSCAR = 'divulgavagas_buscar';

    /**
     * Tipo do job que candidata no Divulga Vagas (segundo provedor).
     */
    public const TIPO_DIVULGA_CANDIDATAR = 'divulgavagas_candidatar';

    /**
     * REGISTRO DOS TIPOS DE JOB (o que o cron-job.org agenda).
     *
     * Este e o unico lugar que precisa mudar quando entra um novo
     * provedor: cada tipo novo e uma entrada aqui, com o rotulo, o
     * comando Spark que roda e a frequencia (minutos do dia).
     *
     * O nome da chave e o "tipo" do job: ele vai na URL do endpoint
     * externo (?comando=<tipo>) e na coluna `tipo` da tabela
     * `cron_jobs_usuario`.
     *
     * @var array<string, array{rotulo: string, comando: string, minutos: list<int>, descricao: string}>
     */
    public const TIPOS_DE_JOB = [
        'vagas' => [
            'rotulo'    => 'vagas',
            'comando'   => 'vagas:consultar',
            'minutos'   => [0, 10, 20, 30, 40, 50],
            'descricao' => 'Busca as vagas no SerpApi (Google Jobs).',
            'aceita_origem' => true,
        ],
        'emails' => [
            'rotulo'    => 'e-mails',
            'comando'   => 'emails:enviar-automaticos',
            'minutos'   => [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
            'descricao' => 'Envia os e-mails de candidatura (SerpApi).',
            'aceita_origem' => false,
        ],
        'divulgavagas_buscar' => [
            'rotulo'    => 'Divulga Vagas (buscar)',
            'comando'   => 'divulgavagas:buscar-vagas',
            'minutos'   => [0, 30],
            'descricao' => 'Busca as vagas no site do Divulga Vagas (bot).',
            'aceita_origem' => true,
        ],
        'divulgavagas_candidatar' => [
            'rotulo'    => 'Divulga Vagas (candidatar)',
            'comando'   => 'divulgavagas:candidatar',
            'minutos'   => [15, 45],
            'descricao' => 'Candidata pelo site do Divulga Vagas (bot) e envia o e-mail de contacto.',
            'aceita_origem' => true,
        ],
    ];

    /**
     * Limites do plano gratuito do cron-job.org.
     */
    private const LIMITE_REQUISICOES_DIA = 100;
    private const LIMITE_JOBS_CONTA      = 50;

    /**
     * Mensagem padrao usada quando a integracao nao esta disponivel.
     */
    public const MSG_MODO_INTEGRACAO = "Integracao disponivel apenas no modo 'spark_manual'.";

    /**
     * Model da configuracao unica do cron.
     */
    private ConfiguracaoCronModel $configuracoes;

    /**
     * Model dos jobs por usuario.
     */
    private CronJobUsuarioModel $jobs;

    /**
     * Model do contador de execucoes manuais.
     */
    private ExecucaoManualUsuarioModel $execucoes;

    /**
     * Model dos usuarios (para listar os ativos).
     */
    private UsuarioModel $usuarios;

    /**
     * Configuracao dos limites de execucao.
     */
    private ConfigCron $config;

    public function __construct()
    {
        $this->configuracoes = new ConfiguracaoCronModel();
        $this->jobs          = new CronJobUsuarioModel();
        $this->execucoes     = new ExecucaoManualUsuarioModel();
        $this->usuarios      = new UsuarioModel();
        $this->config        = config(ConfigCron::class);
    }

    // =====================================================================
    // 1. MODO DE AGENDAMENTO
    // =====================================================================

    /**
     * Modo de agendamento em uso.
     */
    public function obterModoAtual(): string
    {
        $config = $this->configuracoes->obter();
        $modo   = (string) $config['modo'];

        // Valor inesperado no banco cai no padrao seguro.
        if (! in_array($modo, $this->modosValidos(), true)) {
            return self::MODO_MANUAL;
        }

        return $modo;
    }

    /**
     * Grava o modo escolhido pelo admin.
     */
    public function salvarModo(string $modo): bool
    {
        // So aceita um dos tres modos.
        if (! in_array($modo, $this->modosValidos(), true)) {
            return false;
        }

        $this->configuracoes->salvar(['modo' => $modo]);

        return true;
    }

    /**
     * Lista os modos disponiveis, no formato usado pelos cartoes do painel.
     *
     * @return list<array<string, string>>
     */
    public function modosDisponiveis(): array
    {
        return [
            [
                'valor'  => self::MODO_LINUX,
                'titulo' => 'Linux (crontab)',
                'resumo' => 'O proprio servidor Linux chama o comando a cada intervalo, usando o crontab.',
            ],
            [
                'valor'  => self::MODO_WINDOWS,
                'titulo' => 'Windows (Agendador de Tarefas)',
                'resumo' => 'O Agendador de Tarefas do Windows chama o comando a cada intervalo.',
            ],
            [
                'valor'  => self::MODO_MANUAL,
                'titulo' => 'Execucao Manual (Spark Commands)',
                'resumo' => 'Disparo pelo painel ou por um servico externo (cron-job.org) com token por usuario.',
            ],
        ];
    }

    /**
     * Instrucoes de instalacao do modo informado.
     *
     * @return array<string, mixed>
     */
    public function obterInstrucoesDoModo(string $modo): array
    {
        if ($modo === self::MODO_LINUX) {
            return $this->instrucoesLinux();
        }

        if ($modo === self::MODO_WINDOWS) {
            return $this->instrucoesWindows();
        }

        return $this->instrucoesManual();
    }

    // =====================================================================
    // 2. CHAVE DE API DO CRON-JOB.ORG
    // =====================================================================

    /**
     * Chave de API do cron-job.org, ou null quando nao configurada.
     */
    public function obterApiKeyCronJob(): ?string
    {
        $config = $this->configuracoes->obter();
        $chave  = trim((string) $config['api_key_cronjob']);

        if ($chave === '') {
            return null;
        }

        return $chave;
    }

    /**
     * Grava (ou limpa) a chave de API do cron-job.org.
     *
     * So faz sentido no modo `spark_manual`: a integracao nao existe fora
     * dele.
     */
    public function salvarApiKeyCronJob(string $apiKey): bool
    {
        if (! $this->modoEhManual()) {
            return false;
        }

        $chave = trim($apiKey);

        // Chave vazia limpa o campo.
        if ($chave === '') {
            $this->configuracoes->salvar(['api_key_cronjob' => null]);

            return true;
        }

        $this->configuracoes->salvar(['api_key_cronjob' => $chave]);

        return true;
    }

    // =====================================================================
    // 3. TOKEN EXTERNO POR USUARIO
    // =====================================================================

    /**
     * Gera um token aleatorio de 64 caracteres (32 bytes em hexadecimal).
     */
    public function gerarTokenExterno(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Valida o token informado contra o token do usuario indicado.
     *
     * A comparacao usa hash_equals() para nao vazar informacao pelo tempo
     * de resposta.
     */
    public function validarTokenExterno(string $token, int $usuarioId): bool
    {
        $token = trim($token);

        // Token vazio nunca e valido.
        if ($token === '') {
            return false;
        }

        // Compara com todas as linhas do usuario (as duas tem o mesmo valor).
        foreach ($this->jobs->doUsuario($usuarioId) as $linha) {
            // Job desligado localmente nao aceita chamada.
            if ((int) $linha['ativo'] !== 1) {
                continue;
            }

            if (hash_equals((string) $linha['token_externo'], $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Token atual do usuario, ou null quando ele ainda nao tem um.
     */
    public function obterTokenDoUsuario(int $usuarioId): ?string
    {
        $linhas = $this->jobs->doUsuario($usuarioId);

        if ($linhas === []) {
            return null;
        }

        return (string) $linhas[0]['token_externo'];
    }

    /**
     * Gera um token novo para o usuario e invalida o anterior.
     */
    public function regenerarTokenDoUsuario(int $usuarioId): string
    {
        $token = $this->gerarTokenExterno();

        $this->jobs->sincronizarLinhasDoUsuario($usuarioId, $token);

        return $token;
    }

    // =====================================================================
    // 4. EXECUCAO DIRETA DOS COMANDOS
    // =====================================================================

    /**
     * Executa um dos comandos agendados como processo filho.
     *
     * @param string $comando   'vagas' ou 'emails'.
     * @param int|null $usuarioId Quando informado, roda so para esse usuario.
     * @param string $origem    Rotulo gravado em logs_consulta (vagas).
     * @param float $limiteSegundos Tempo maximo de espera. Zero espera o
     *                              comando terminar; acima disso, o comando
     *                              continua em segundo plano quando o prazo
     *                              estoura.
     *
     * @return array<string, mixed> Resultado padronizado.
     */
    public function executarComando(
        string $comando,
        ?int $usuarioId = null,
        string $origem = 'cron',
        float $limiteSegundos = 0.0,
        array $argumentosExtra = []
    ): array {
        // Nome do comando Spark correspondente.
        $nomeSpark = $this->nomeSparkDoComando($comando);

        if ($nomeSpark === null) {
            return $this->falhaExecucao($comando, 'Comando invalido. Use um destes: ' . implode(', ', $this->tiposDeJob()) . '.');
        }

        // Binario do PHP CLI.
        $php = $this->caminhoDoPhp();

        if ($php === '') {
            return $this->falhaExecucao(
                $comando,
                'Nao foi possivel localizar o PHP de linha de comando. Defina cron.caminhoPhpCli no .env.'
            );
        }

        // Monta a linha de comando com cada parte escapada.
        $linha = $this->montarLinhaDeComando($php, $nomeSpark, $comando, $usuarioId, $origem, $argumentosExtra);

        // Executa e mede o tempo.
        $inicio  = microtime(true);
        $saida   = $this->rodarProcesso($linha, $limiteSegundos);
        $codigo  = $saida['codigo'];
        $duracao = round(microtime(true) - $inicio, 2);

        // O prazo estourou: o comando segue rodando em segundo plano.
        if ($saida['em_execucao']) {
            return [
                'sucesso'          => true,
                'erro'             => null,
                'comando'          => $comando,
                'saida'            => '',
                'codigo_saida'     => null,
                'duracao_segundos' => $duracao,
                'executado_em'     => date('Y-m-d H:i:s'),
                'em_execucao'      => true,
                'arquivo_saida'    => (string) $saida['arquivo'],
            ];
        }

        return [
            'sucesso'          => $codigo === 0,
            'erro'             => $this->mensagemDeSaida($codigo),
            'comando'          => $comando,
            'saida'            => $saida['texto'],
            'codigo_saida'     => $codigo,
            'duracao_segundos' => $duracao,
            'executado_em'     => date('Y-m-d H:i:s'),
            'em_execucao'      => false,
            'arquivo_saida'    => '',
        ];
    }

    // =====================================================================
    // 5. INTEGRACAO COM O CRON-JOB.ORG (somente modo spark_manual)
    // =====================================================================

    /**
     * Cria o job de um usuario no cron-job.org.
     *
     * @param string $token Token que o job enviara no header X-Cron-Token.
     *
     * @return array<string, mixed>
     */
    public function criarCronJobNoServico(int $usuarioId, string $tipo, string $token): array
    {
        // Integracao bloqueada fora do modo manual.
        $erroModo = $this->erroDoModo();

        if ($erroModo !== null) {
            return $erroModo;
        }

        if ($this->nomeSparkDoComando($tipo) === null) {
            return $this->falhaIntegracao('Tipo de job invalido. Use um destes: ' . implode(', ', $this->tiposDeJob()) . '.');
        }

        // Cliente exige chave configurada.
        $cliente = $this->cliente();

        if ($cliente === null) {
            return $this->falhaIntegracao('Configure a chave de API do cron-job.org antes de criar jobs.');
        }

        // Nao duplica job: se ja existe, manda atualizar.
        $linha = $this->jobs->porUsuarioETipo($usuarioId, $tipo);

        if ($linha !== null && $linha['cronjob_org_job_id'] !== null) {
            return $this->falhaIntegracao('Este usuario ja tem um job deste tipo. Use "Atualizar".');
        }

        // Token: usa o informado, senao o existente, senao gera um novo.
        $tokenFinal = trim($token);

        if ($tokenFinal === '') {
            $existente = $this->obterTokenDoUsuario($usuarioId);

            if ($existente === null) {
                $tokenFinal = $this->regenerarTokenDoUsuario($usuarioId);
            } else {
                $tokenFinal = $existente;
            }
        }

        // Garante as linhas locais antes de criar no servico.
        $this->jobs->sincronizarLinhasDoUsuario($usuarioId, $tokenFinal);

        // Cria no cron-job.org.
        $resultado = $cliente->criarJob($this->montarPayload($usuarioId, $tipo, $tokenFinal));

        // Uma requisicao de API consumida.
        $this->contabilizarRequisicao();

        if (! $resultado['sucesso']) {
            return $this->falhaIntegracao((string) $resultado['erro'], $resultado['dados']);
        }

        // Guarda o id devolvido pelo servico.
        $jobId = $this->jobIdDaResposta($resultado['dados']);

        if ($jobId > 0) {
            $this->jobs->definirJobId($usuarioId, $tipo, $jobId);
        }

        return [
            'sucesso' => true,
            'erro'    => null,
            'dados'   => ['jobId' => $jobId],
        ];
    }

    /**
     * Atualiza o job de um usuario no cron-job.org.
     *
     * @return array<string, mixed>
     */
    public function atualizarCronJobNoServico(int $usuarioId, string $tipo): array
    {
        $erroModo = $this->erroDoModo();

        if ($erroModo !== null) {
            return $erroModo;
        }

        if ($this->nomeSparkDoComando($tipo) === null) {
            return $this->falhaIntegracao('Tipo de job invalido. Use um destes: ' . implode(', ', $this->tiposDeJob()) . '.');
        }

        $cliente = $this->cliente();

        if ($cliente === null) {
            return $this->falhaIntegracao('Configure a chave de API do cron-job.org antes de atualizar jobs.');
        }

        // Precisa existir job criado para atualizar.
        $linha = $this->jobs->porUsuarioETipo($usuarioId, $tipo);

        if ($linha === null || $linha['cronjob_org_job_id'] === null) {
            return $this->falhaIntegracao('Nao existe job deste tipo para este usuario. Crie primeiro.');
        }

        $jobId = (int) $linha['cronjob_org_job_id'];
        $token = (string) $linha['token_externo'];

        $resultado = $cliente->atualizarJob($jobId, $this->montarPayload($usuarioId, $tipo, $token));

        $this->contabilizarRequisicao();

        if (! $resultado['sucesso']) {
            return $this->falhaIntegracao((string) $resultado['erro'], $resultado['dados']);
        }

        return [
            'sucesso' => true,
            'erro'    => null,
            'dados'   => ['jobId' => $jobId],
        ];
    }

    /**
     * Exclui o job de um usuario no cron-job.org.
     */
    public function excluirCronJobNoServico(int $usuarioId, string $tipo): bool
    {
        // Integracao bloqueada fora do modo manual.
        if (! $this->modoEhManual()) {
            return false;
        }

        if ($this->nomeSparkDoComando($tipo) === null) {
            return false;
        }

        $cliente = $this->cliente();

        if ($cliente === null) {
            return false;
        }

        $linha = $this->jobs->porUsuarioETipo($usuarioId, $tipo);

        if ($linha === null || $linha['cronjob_org_job_id'] === null) {
            return false;
        }

        $excluiu = $cliente->excluirJob((int) $linha['cronjob_org_job_id']);

        $this->contabilizarRequisicao();

        // So limpa o id local quando o servico confirmou.
        if ($excluiu) {
            $this->jobs->limparJobId($usuarioId, $tipo);
        }

        return $excluiu;
    }

    /**
     * Jobs locais de um usuario, com o token mascarado para exibicao.
     *
     * @return list<array<string, mixed>>
     */
    public function listarCronJobsDoUsuario(int $usuarioId): array
    {
        $linhas = $this->jobs->doUsuario($usuarioId);

        foreach ($linhas as $indice => $linha) {
            $linhas[$indice]['token_mascarado'] = $this->mascararToken((string) $linha['token_externo']);
        }

        return $linhas;
    }

    /**
     * Jobs existentes na conta do cron-job.org (chamada a API).
     *
     * @return array<string, mixed>
     */
    public function listarCronJobsDoServico(): array
    {
        $erroModo = $this->erroDoModo();

        if ($erroModo !== null) {
            return $erroModo;
        }

        $cliente = $this->cliente();

        if ($cliente === null) {
            return $this->falhaIntegracao('Configure a chave de API do cron-job.org antes de listar jobs.');
        }

        $resultado = $cliente->listarJobs();

        $this->contabilizarRequisicao();

        return $resultado;
    }

    /**
     * Limites do plano gratuito e uso atual.
     *
     * @return array<string, mixed>
     */
    public function obterLimitesDoPlano(): array
    {
        $usadas     = $this->requisicoesUsadasHoje();
        $jobsCriados = $this->jobs->contar();

        // Alerta quando o uso passa de 90% do limite.
        $limiteAlerta = (int) ceil(self::LIMITE_REQUISICOES_DIA * 0.9);
        $alertaRequisicoes = false;

        if ($usadas >= $limiteAlerta) {
            $alertaRequisicoes = true;
        }

        $limiteJobsAlerta = (int) ceil(self::LIMITE_JOBS_CONTA * 0.9);
        $alertaJobs = false;

        if ($jobsCriados >= $limiteJobsAlerta) {
            $alertaJobs = true;
        }

        // Restantes nunca ficam negativos.
        $requisicoesRestantes = self::LIMITE_REQUISICOES_DIA - $usadas;

        if ($requisicoesRestantes < 0) {
            $requisicoesRestantes = 0;
        }

        $jobsRestantes = self::LIMITE_JOBS_CONTA - $jobsCriados;

        if ($jobsRestantes < 0) {
            $jobsRestantes = 0;
        }

        return [
            'requisicoes_dia'       => self::LIMITE_REQUISICOES_DIA,
            'requisicoes_usadas'    => $usadas,
            'requisicoes_restantes' => $requisicoesRestantes,
            'alerta_requisicoes'    => $alertaRequisicoes,
            'jobs_maximos'          => self::LIMITE_JOBS_CONTA,
            'jobs_criados'          => $jobsCriados,
            'jobs_restantes'        => $jobsRestantes,
            'alerta_jobs'           => $alertaJobs,
        ];
    }

    /**
     * Cria (ou atualiza) os dois jobs de todos os usuarios ativos.
     *
     * @return array<string, mixed>
     */
    public function sincronizarJobs(): array
    {
        $erroModo = $this->erroDoModo();

        if ($erroModo !== null) {
            return $erroModo;
        }

        $cliente = $this->cliente();

        if ($cliente === null) {
            return $this->falhaIntegracao('Configure a chave de API do cron-job.org antes de sincronizar.');
        }

        // Usuarios ativos (mesma lista usada pela busca de vagas).
        $usuarios = $this->usuarios->listarParaBusca(0);

        $criados     = 0;
        $atualizados = 0;
        $erros       = 0;
        $mensagens   = [];
        $limiteAtingido = false;

        foreach ($usuarios as $usuario) {
            // Sem requisicoes sobrando, interrompe (limite do plano gratuito).
            if ($this->requisicoesUsadasHoje() >= self::LIMITE_REQUISICOES_DIA) {
                $limiteAtingido = true;
                break;
            }

            $usuarioId = (int) $usuario['id'];

            // Garante um token para o usuario.
            $token = $this->obterTokenDoUsuario($usuarioId);

            if ($token === null) {
                $token = $this->regenerarTokenDoUsuario($usuarioId);
            }

            // Cria ou atualiza cada um dos jobs do usuario (todos os
            // tipos do registro: SerpApi e Divulga Vagas).
            $tipos = array_keys(self::TIPOS_DE_JOB);

            foreach ($tipos as $tipo) {
                $linha  = $this->jobs->porUsuarioETipo($usuarioId, $tipo);
                $temJob = false;

                if ($linha !== null && $linha['cronjob_org_job_id'] !== null) {
                    $temJob = true;
                }

                if ($temJob) {
                    $resultado = $this->atualizarCronJobNoServico($usuarioId, $tipo);
                } else {
                    $resultado = $this->criarCronJobNoServico($usuarioId, $tipo, $token);
                }

                if ($resultado['sucesso']) {
                    if ($temJob) {
                        $atualizados++;
                    } else {
                        $criados++;
                    }

                    continue;
                }

                $erros++;
                $mensagens[] = 'Usuario #' . $usuarioId . ' (' . $tipo . '): ' . (string) $resultado['erro'];
            }
        }

        // Aviso quando o limite diario da API foi alcancado.
        if ($limiteAtingido) {
            $mensagens[] = 'Limite diario de requisicoes da API do cron-job.org atingido. Continue amanha ou use os modos cron_linux / cron_windows.';
        }

        $sucesso = false;

        if ($erros === 0 && $criados + $atualizados > 0) {
            $sucesso = true;
        }

        return [
            'sucesso'     => $sucesso,
            'erro'        => null,
            'dados'       => [
                'criados'     => $criados,
                'atualizados' => $atualizados,
                'erros'       => $erros,
                'mensagens'   => $mensagens,
            ],
        ];
    }

    /**
     * Registra a ultima execucao de um job.
     */
    public function registrarExecucaoJob(int $usuarioId, string $tipo, string $status): void
    {
        if ($this->nomeSparkDoComando($tipo) === null) {
            return;
        }

        $this->jobs->registrarExecucao($usuarioId, $tipo, $status);
    }

    // =====================================================================
    // 6. LIMITE DE EXECUCOES MANUAIS DO USUARIO
    // =====================================================================

    /**
     * Limite diario de execucoes manuais do usuario comum.
     */
    public function obterLimiteExecucoesManuaisUsuario(): int
    {
        $config = $this->configuracoes->obter();

        return (int) $config['limite_execucoes_manuais_usuario'];
    }

    /**
     * Salva o limite diario de execucoes manuais (0 desabilita).
     */
    public function salvarLimiteExecucoesManuaisUsuario(int $limite): bool
    {
        // Fora da faixa aceita.
        if ($limite < 0 || $limite > 100) {
            return false;
        }

        $this->configuracoes->salvar(['limite_execucoes_manuais_usuario' => $limite]);

        return true;
    }

    /**
     * Diz se o usuario pode disparar manualmente e quantas vezes ainda pode.
     *
     * @return array<string, mixed>
     */
    public function usuarioPodeExecutarManualmente(int $usuarioId): array
    {
        $limite = $this->obterLimiteExecucoesManuaisUsuario();
        $usadas = $this->execucoes->obterTotal($usuarioId, date('Y-m-d'));

        // Limite zero: admin desligou a execucao manual.
        if ($limite <= 0) {
            return [
                'pode'      => false,
                'motivo'    => 'desabilitado',
                'restantes' => 0,
                'usadas'    => $usadas,
                'limite'    => 0,
            ];
        }

        // Ainda pode executar.
        if ($usadas < $limite) {
            return [
                'pode'      => true,
                'motivo'    => 'disponivel',
                'restantes' => $limite - $usadas,
                'usadas'    => $usadas,
                'limite'    => $limite,
            ];
        }

        // Chegou ao limite do dia.
        return [
            'pode'      => false,
            'motivo'    => 'limite_atingido',
            'restantes' => 0,
            'usadas'    => $usadas,
            'limite'    => $limite,
        ];
    }

    /**
     * Soma uma execucao manual no contador do dia.
     */
    public function registrarExecucaoManualUsuario(int $usuarioId): bool
    {
        $this->execucoes->incrementar($usuarioId, date('Y-m-d'));

        return true;
    }

    /**
     * Zera o contador de execucoes manuais do usuario no dia.
     */
    public function zerarExecucoesManuaisUsuario(int $usuarioId): bool
    {
        return $this->execucoes->zerar($usuarioId, date('Y-m-d'));
    }

    /**
     * Execucoes manuais do usuario hoje (para exibir no painel).
     */
    public function obterExecucoesManuaisDeHoje(int $usuarioId): int
    {
        return $this->execucoes->obterTotal($usuarioId, date('Y-m-d'));
    }

    // =====================================================================
    // AUXILIARES
    // =====================================================================

    /**
     * Lista dos tres modos validos.
     *
     * @return list<string>
     */
    private function modosValidos(): array
    {
        return [self::MODO_LINUX, self::MODO_WINDOWS, self::MODO_MANUAL];
    }

    /**
     * Diz se o modo atual permite a integracao com o cron-job.org.
     */
    private function modoEhManual(): bool
    {
        if ($this->obterModoAtual() === self::MODO_MANUAL) {
            return true;
        }

        return false;
    }

    /**
     * Erro padronizado usado quando a integracao nao esta disponivel.
     *
     * @return array<string, mixed>|null
     */
    private function erroDoModo(): ?array
    {
        if ($this->modoEhManual()) {
            return null;
        }

        return $this->falhaIntegracao(self::MSG_MODO_INTEGRACAO);
    }

    /**
     * Cliente da API, criado sob demanda.
     */
    private function cliente(): ?ClienteCronJobOrg
    {
        $chave = $this->obterApiKeyCronJob();

        if ($chave === null) {
            return null;
        }

        // A URL base vem da configuracao: assim o teste pode apontar para
        // um servidor local sem alterar nenhuma linha de codigo.
        $urlBase = (string) $this->config->urlBaseCronJobOrg;

        return new ClienteCronJobOrg($chave, $urlBase);
    }

    /**
     * Nome do Spark Command correspondente ao tipo/comando.
     */
    private function nomeSparkDoComando(string $comando): ?string
    {
        // O registro dos tipos e a fonte da verdade.
        if (! isset(self::TIPOS_DE_JOB[$comando])) {
            return null;
        }

        return (string) self::TIPOS_DE_JOB[$comando]['comando'];
    }

    /**
     * Os tipos de job validos (usado nas mensagens de erro).
     */
    public function tiposDeJob(): array
    {
        return array_keys(self::TIPOS_DE_JOB);
    }

    /**
     * Monta o corpo JSON enviado ao cron-job.org.
     *
     * @return array<string, mixed>
     */
    private function montarPayload(int $usuarioId, string $tipo, string $token): array
    {
        // URL publica do endpoint externo do sistema.
        $url = site_url('cron/usuario/' . $usuarioId . '/executar-externo') . '?comando=' . $tipo;

        // Cada tipo tem seu titulo e sua frequencia (vem do registro).
        $definicao = [];

        if (isset(self::TIPOS_DE_JOB[$tipo])) {
            $definicao = self::TIPOS_DE_JOB[$tipo];
        }

        $titulo = 'CI4 - Usuario ' . $usuarioId . ' - ' . texto_ou($definicao, 'rotulo', $tipo);
        $minutos = (array) valor_ou($definicao, 'minutos', [0]);

        return [
            'url'            => $url,
            'enabled'        => true,
            'title'          => $titulo,
            'saveResponses'  => true,
            'schedule'       => [
                'timezone'  => 'America/Sao_Paulo',
                'expiresAt' => 0,
                'hours'     => [-1],
                'mdays'     => [-1],
                'minutes'   => $minutos,
                'months'    => [-1],
                'wdays'     => [-1],
            ],
            // 1 = POST (o endpoint externo so aceita POST).
            'requestMethod'  => 1,
            'extendedData'   => [
                'headers' => [
                    'X-Cron-Token' => $token,
                ],
            ],
        ];
    }

    /**
     * Extrai o id do job devolvido pelo servico.
     *
     * @param mixed $dados
     */
    private function jobIdDaResposta($dados): int
    {
        if (! is_array($dados)) {
            return 0;
        }

        if (! isset($dados['jobId'])) {
            return 0;
        }

        if (! is_numeric($dados['jobId'])) {
            return 0;
        }

        return (int) $dados['jobId'];
    }

    /**
     * Mascara o token, mostrando apenas o inicio e o fim.
     */
    private function mascararToken(string $token): string
    {
        $tamanho = strlen($token);

        if ($tamanho <= 8) {
            return str_repeat('*', 12) . substr($token, -2);
        }

        return substr($token, 0, 4) . str_repeat('*', 12) . substr($token, -4);
    }

    /**
     * Requisicoes de API do cron-job.org usadas hoje.
     */
    private function requisicoesUsadasHoje(): int
    {
        $config = $this->configuracoes->obter();

        // Contador de outro dia: ja conta como zerado.
        if ((string) $config['data_reset_requisicoes'] !== date('Y-m-d')) {
            return 0;
        }

        return (int) $config['requisicoes_api_hoje'];
    }

    /**
     * Soma uma requisicao de API, virando o contador no dia novo.
     */
    private function contabilizarRequisicao(): void
    {
        $hoje   = date('Y-m-d');
        $config = $this->configuracoes->obter();

        $usadas = (int) $config['requisicoes_api_hoje'];

        // Contador de outro dia comeca do zero.
        if ((string) $config['data_reset_requisicoes'] !== $hoje) {
            $usadas = 0;
        }

        $this->configuracoes->salvar([
            'requisicoes_api_hoje'   => $usadas + 1,
            'data_reset_requisicoes' => $hoje,
        ]);
    }

    /**
     * Caminho do interpretador PHP de linha de comando.
     */
    private function caminhoDoPhp(): string
    {
        // 1. Configuracao explicita (cron.caminhoPhpCli).
        $configurado = trim((string) $this->config->caminhoPhpCli);

        if ($configurado !== '' && is_file($configurado)) {
            return $configurado;
        }

        // 2. O proprio binario em uso, quando ja estamos em CLI.
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            $binario = (string) PHP_BINARY;

            if ($binario !== '' && is_file($binario)) {
                return $binario;
            }
        }

        // 3. Alternativa portavel a partir do diretorio de binarios.
        $sufixo = '';

        if (DIRECTORY_SEPARATOR === '\\') {
            $sufixo = '.exe';
        }

        $alternativo = rtrim((string) PHP_BINDIR, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'php' . $sufixo;

        if (is_file($alternativo)) {
            return $alternativo;
        }

        // 4. Deixa o shell resolver pelo PATH.
        return 'php';
    }

    /**
     * Monta a linha de comando do processo filho.
     *
     * Cada argumento passa por escapeshellarg(), e o nome interno do
     * comando passa tambem por escapeshellcmd().
     *
     * Observacao: escapeshellcmd() NAO e aplicado ao caminho do PHP de
     * proposito. Em Windows ele escaparia as barras invertidas e
     * quebraria o caminho; escapeshellarg() ja resolve caminhos com
     * espacos nos dois sistemas.
     */
    private function montarLinhaDeComando(string $php, string $nomeSpark, string $comando, ?int $usuarioId, string $origem, array $argumentosExtra = []): string
    {
        // Nome interno do comando, saneado.
        $nomeInterno = escapeshellcmd($nomeSpark);

        $partes   = [];
        $partes[] = escapeshellarg($php);
        $partes[] = escapeshellarg('spark');
        $partes[] = escapeshellarg($nomeInterno);

        // Executa apenas um usuario.
        if ($usuarioId !== null) {
            $partes[] = escapeshellarg('--usuario=' . $usuarioId);
        }

        // Origem gravada no log (so os comandos que aceitam esse rotulo).
        $definicao = [];

        if (isset(self::TIPOS_DE_JOB[$comando])) {
            $definicao = self::TIPOS_DE_JOB[$comando];
        }

        $aceitaOrigem = (bool) valor_ou($definicao, 'aceita_origem', false);

        if ($aceitaOrigem && $origem !== 'cron') {
            $partes[] = escapeshellarg('--origem=' . $origem);
        }

        // Argumentos extras do comando (ex.: --limite=3).
        foreach ($argumentosExtra as $nome => $valor) {
            $partes[] = escapeshellarg('--' . $nome . '=' . (string) $valor);
        }

        // Junta tudo e direciona a saida de erro para a saida padrao.
        return implode(' ', $partes) . ' 2>&1';
    }

    /**
     * Roda a linha de comando e devolve a saida e o codigo de retorno.
     *
     * A saida vai para um arquivo (e nao para pipes) de proposito: assim o
     * processo nao depende da duracao da chamada HTTP e pode continuar
     * rodando quando o limite de tempo estourar. Nesse caso o comando
     * segue em segundo plano e escreve no arquivo de saida.
     *
     * @param float $limiteSegundos 0 = espera o comando terminar.
     *
     * @return array<string, mixed>
     */
    private function rodarProcesso(string $linha, float $limiteSegundos = 0.0): array
    {
        // Sem recursos de proc_open, nao ha como capturar a saida.
        if (! function_exists('proc_open')) {
            return [
                'codigo'      => -1,
                'texto'       => 'A funcao proc_open esta desabilitada neste servidor.',
                'em_execucao' => false,
                'arquivo'     => '',
            ];
        }

        // Arquivo exclusivo desta execucao (a saida do comando).
        $arquivo = $this->arquivoDaExecucao();

        // Fecha as saidas de erro dentro da saida padrao (2>&1).
        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['file', $arquivo, 'w'],
            2 => ['file', $arquivo, 'a'],
        ];

        $tubos = [];

        try {
            // Executa sempre a partir da raiz do projeto.
            $processo = proc_open($linha, $descritores, $tubos, ROOTPATH);
        } catch (Throwable $erro) {
            return [
                'codigo'      => -1,
                'texto'       => 'Falha ao iniciar o processo: ' . $erro->getMessage(),
                'em_execucao' => false,
                'arquivo'     => '',
            ];
        }

        if (! is_resource($processo)) {
            return [
                'codigo'      => -1,
                'texto'       => 'Nao foi possivel iniciar o comando.',
                'em_execucao' => false,
                'arquivo'     => '',
            ];
        }

        // Fecha a entrada (os comandos nao leem do teclado).
        fclose($tubos[0]);

        $inicio     = microtime(true);
        $codigo     = null;
        $emExecucao = true;

        // Aguarda o processo, respeitando o limite de tempo.
        while (true) {
            $status = proc_get_status($processo);

            if (! $status['running']) {
                $codigo     = (int) $status['exitcode'];
                $emExecucao  = false;
                break;
            }

            if ($limiteSegundos > 0 && (microtime(true) - $inicio) >= $limiteSegundos) {
                break;
            }

            usleep(100000);
        }

        // Ainda rodando: NAO chama proc_close (ele esperaria o fim) e
        // deixa o processo seguir em segundo plano.
        if ($emExecucao) {
            return [
                'codigo'      => null,
                'texto'       => '',
                'em_execucao' => true,
                'arquivo'     => $arquivo,
            ];
        }

        // Liberacao dos recursos do processo.
        proc_close($processo);

        // Le a saida desta execucao.
        $texto = (string) file_get_contents($arquivo);

        // Junta a saida no arquivo do dia e descarta o arquivo exclusivo.
        $this->registrarSaidaDoDia($texto);
        @unlink($arquivo);

        return [
            'codigo'      => $codigo,
            'texto'       => $texto,
            'em_execucao' => false,
            'arquivo'     => '',
        ];
    }

    /**
     * Arquivo exclusivo de uma execucao (a saida do comando).
     */
    private function arquivoDaExecucao(): string
    {
        $diretorio = WRITEPATH . 'logs';
        $nome      = 'cron-saida-' . date('Y-m-d-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.log';

        return $diretorio . DIRECTORY_SEPARATOR . $nome;
    }

    /**
     * Arquivo do dia que junta a saida de todas as execucoes.
     */
    private function arquivoDeSaida(): string
    {
        return WRITEPATH . 'logs' . DIRECTORY_SEPARATOR . 'cron-externo-saida-' . date('Y-m-d') . '.log';
    }

    /**
     * Acrescenta a saida de uma execucao no arquivo do dia.
     */
    private function registrarSaidaDoDia(string $texto): void
    {
        // Nada a registrar.
        if (trim($texto) === '') {
            return;
        }

        $marcador = PHP_EOL . '[' . date('Y-m-d H:i:s') . '] execucao concluida' . PHP_EOL;

        file_put_contents(
            $this->arquivoDeSaida(),
            $marcador . $texto . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Mensagem de erro a partir do codigo de retorno do processo.
     */
    private function mensagemDeSaida(int $codigo): ?string
    {
        if ($codigo === 0) {
            return null;
        }

        return 'O comando terminou com codigo ' . $codigo . '. Veja a saida para mais detalhes.';
    }

    /**
     * Resposta padronizada de falha ao executar um comando.
     *
     * @return array<string, mixed>
     */
    private function falhaExecucao(string $comando, string $mensagem): array
    {
        return [
            'sucesso'          => false,
            'erro'             => $mensagem,
            'comando'          => $comando,
            'saida'            => '',
            'codigo_saida'     => -1,
            'duracao_segundos' => 0.0,
            'executado_em'     => date('Y-m-d H:i:s'),
            'em_execucao'      => false,
            'arquivo_saida'    => '',
        ];
    }

    /**
     * Resposta padronizada de falha na integracao.
     *
     * @param array<string, mixed> $dados
     *
     * @return array<string, mixed>
     */
    private function falhaIntegracao(string $mensagem, array $dados = []): array
    {
        return [
            'sucesso' => false,
            'erro'    => $mensagem,
            'dados'   => $dados,
        ];
    }

    /**
     * Instrucoes exibidas no modo Linux.
     *
     * @return array<string, mixed>
     */
    private function instrucoesLinux(): array
    {
        return [
            'titulo'    => 'Linux (crontab)',
            'resumo'    => 'O servidor Linux chama o script scripts/cron-linux.sh pelo crontab. O script, por sua vez, executa php spark.',
            'documento' => 'docs/agendamento-linux.md',
            'passos'    => [
                'Envie o projeto para o servidor e confirme que o PHP CLI esta instalado.',
                'O script descobre o PHP sozinho: use a variavel PHP_CLI, a chave cron.caminhoPhpCli no .env ou o php do PATH.',
                'De permissao de execucao: chmod +x scripts/cron-linux.sh.',
                'Abra o crontab com: crontab -e.',
                'Cole as duas linhas de agendamento mostradas abaixo.',
                'Teste antes rodando o script na mao.',
            ],
            'comandos'  => [
                '*/10 * * * * /caminho/do/projeto/scripts/cron-linux.sh vagas >> /dev/null 2>&1',
                '*/5 * * * * /caminho/do/projeto/scripts/cron-linux.sh emails >> /dev/null 2>&1',
            ],
            'avisos'    => [
                'Nunca use curl ou wget: o comando precisa rodar no contexto CLI.',
                'O "cd" para a raiz do projeto e obrigatorio e ja esta dentro do script.',
            ],
        ];
    }

    /**
     * Instrucoes exibidas no modo Windows.
     *
     * @return array<string, mixed>
     */
    private function instrucoesWindows(): array
    {
        return [
            'titulo'    => 'Windows (Agendador de Tarefas)',
            'resumo'    => 'O Agendador de Tarefas do Windows chama scripts/cron-windows.bat, que executa php spark.',
            'documento' => 'docs/agendamento-windows.md',
            'passos'    => [
                'Abra o Agendador de Tarefas do Windows.',
                'Crie a tarefa "CI4 - Buscar Vagas" apontando para scripts/cron-windows.bat com o argumento vagas.',
                'Crie a tarefa "CI4 - Enviar E-mails" apontando para o mesmo script com o argumento emails.',
                'No campo "Iniciar em", informe a raiz do projeto (obrigatorio).',
                'Marque "Executar estando o usuario conectado ou nao".',
                'Desmarque "Iniciar a tarefa somente se o computador estiver na energia eletrica".',
            ],
            'comandos'  => [
                'scripts\\cron-windows.bat vagas',
                'scripts\\cron-windows.bat emails',
            ],
            'avisos'    => [
                'O campo "Iniciar em" e obrigatorio no Windows.',
                'O script descobre o PHP sozinho: use a variavel PHP_CLI, a chave cron.caminhoPhpCli no .env, o php.cmd da raiz do projeto ou o php do PATH.',
            ],
        ];
    }

    /**
     * Instrucoes exibidas no modo manual.
     *
     * @return array<string, mixed>
     */
    private function instrucoesManual(): array
    {
        return [
            'titulo'    => 'Execucao Manual (Spark Commands)',
            'resumo'    => 'Os comandos sao disparados pelo painel (admin ou usuario) ou por um servico externo como o cron-job.org, que chama um endpoint protegido por token.',
            'documento' => 'docs/agendamento-manual.md',
            'passos'    => [
                'Opcao A: use os botoes "Executar Agora" deste painel.',
                'Opcao B: integre com o cron-job.org usando a chave de API e o botao "Sincronizar Jobs".',
                'Opcao C: o proprio usuario dispara pelo painel dele, respeitando o limite diario.',
            ],
            'comandos'  => [
                'php spark vagas:consultar',
                'php spark emails:enviar-automaticos',
            ],
            'avisos'    => [
                'O endpoint externo e o unico caminho HTTP permitido e exige o token do usuario.',
                'As chamadas do servico externo nao contam no limite diario do usuario.',
            ],
        ];
    }
}
