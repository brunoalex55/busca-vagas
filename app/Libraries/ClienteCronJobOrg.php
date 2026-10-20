<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * CLIENTE DA API REST DO CRON-JOB.ORG
 * --------------------------------------------------------------------------
 *
 * Envoltorio fino (wrapper) da API oficial do cron-job.org, usado apenas no
 * modo de agendamento `spark_manual` com servico externo.
 *
 * API base: https://api.cron-job.org
 * Autenticacao: header `Authorization: Bearer SUA_API_KEY`.
 *
 * Endpoints usados:
 *   PUT    /jobs                 -> criar job
 *   GET    /jobs                 -> listar jobs
 *   GET    /jobs/{jobId}         -> obter um job
 *   PATCH  /jobs/{jobId}         -> atualizar um job
 *   DELETE /jobs/{jobId}         -> excluir um job
 *   GET    /jobs/{jobId}/history -> historico de execucoes
 *
 * PEGADINHA IMPORTANTE: para CRIAR um job a API exige PUT (nao POST).
 * Os corpos de criacao e atualizacao devem vir embrulhados no campo "job":
 *
 *   { "job": { "url": "...", "enabled": true, ... } }
 *
 * SEGURANCA
 *   - A chave de API NUNCA e registrada em log.
 *   - O log guarda apenas o metodo, a URL e o codigo HTTP.
 *   - Nenhuma mensagem de erro devolvida inclui a chave.
 *
 * Requisicoes usam a biblioteca HTTP do proprio CodeIgniter (que e cURL por
 * baixo), sem qualquer SDK externo. Cada chamada tem tempo limite curto para
 * nao travar o painel.
 */
class ClienteCronJobOrg
{
    /**
     * URL base da API do cron-job.org.
     */
    private const URL_BASE = 'https://api.cron-job.org';

    /**
     * Tempo limite (em segundos) de cada requisicao.
     */
    private const TEMPO_LIMITE = 10;

    /**
     * Chave de API da conta (Bearer Token).
     */
    private string $apiKey;

    /**
     * URL base usada nas chamadas.
     */
    private string $urlBase;

    /**
     * Tempo limite de cada requisicao, em segundos.
     */
    private int $tempoLimite;

    /**
     * @param string $apiKey      Chave de API da conta do cron-job.org.
     * @param string $urlBase     URL base (trocavel em testes).
     * @param int    $tempoLimite Tempo limite por requisicao, em segundos.
     */
    public function __construct(string $apiKey, string $urlBase = self::URL_BASE, int $tempoLimite = self::TEMPO_LIMITE)
    {
        $this->apiKey     = trim($apiKey);
        $this->urlBase    = rtrim($urlBase, '/');
        $this->tempoLimite = $tempoLimite;
    }

    /**
     * Cria um job no cron-job.org (PUT /jobs).
     *
     * @param array<string, mixed> $dadosJob Definicao do job, SEM o campo
     *                                       "job" externo: a embrulhadora e
     *                                       adicionada aqui.
     *
     * @return array<string, mixed> Resultado padronizado.
     */
    public function criarJob(array $dadosJob): array
    {
        return $this->requisicao('PUT', '/jobs', ['job' => $dadosJob]);
    }

    /**
     * Lista todos os jobs da conta (GET /jobs).
     *
     * @return array<string, mixed> Resultado padronizado. Em caso de sucesso,
     *                              `dados.jobs` traz a lista devolvida.
     */
    public function listarJobs(): array
    {
        return $this->requisicao('GET', '/jobs');
    }

    /**
     * Obtem um job pelo id (GET /jobs/{jobId}).
     *
     * @return array<string, mixed> Resultado padronizado.
     */
    public function obterJob(int $jobId): array
    {
        return $this->requisicao('GET', '/jobs/' . $jobId);
    }

    /**
     * Atualiza um job (PATCH /jobs/{jobId}).
     *
     * @param array<string, mixed> $dados Definicao do job, SEM o campo "job"
     *                                    externo: a embrulhadora e adicionada.
     *
     * @return array<string, mixed> Resultado padronizado.
     */
    public function atualizarJob(int $jobId, array $dados): array
    {
        return $this->requisicao('PATCH', '/jobs/' . $jobId, ['job' => $dados]);
    }

    /**
     * Exclui um job (DELETE /jobs/{jobId}).
     *
     * @return bool true quando o servico confirmou a exclusao.
     */
    public function excluirJob(int $jobId): bool
    {
        // Reaproveita a rotina unica de requisicao.
        $resultado = $this->requisicao('DELETE', '/jobs/' . $jobId);

        return $resultado['sucesso'];
    }

    /**
     * Obtem o historico de execucoes de um job (GET /jobs/{jobId}/history).
     *
     * @return array<string, mixed> Resultado padronizado.
     */
    public function obterHistoricoJob(int $jobId): array
    {
        return $this->requisicao('GET', '/jobs/' . $jobId . '/history');
    }

    /**
     * Executa uma requisicao e devolve um resultado sempre no mesmo formato:
     *
     *   [
     *     'sucesso' => bool,
     *     'dados'   => array<string, mixed>,
     *     'erro'    => string|null,
     *     'codigo'  => int,   // codigo HTTP (0 quando nem chegou a responder)
     *   ]
     *
     * @param array<string, mixed>|null $corpo Corpo JSON da requisicao.
     *
     * @return array<string, mixed>
     */
    private function requisicao(string $metodo, string $caminho, ?array $corpo = null): array
    {
        // Sem chave configurada nao ha como autenticar.
        if ($this->apiKey === '') {
            return $this->falha('A chave de API do cron-job.org nao foi configurada.', 0);
        }

        // URL completa da chamada.
        $url = $this->urlBase . $caminho;

        try {
            // Cliente HTTP do CodeIgniter (cURL, sem SDK externo).
            //
            // A assinatura do servico nesta versao do framework e
            // curlrequest(options, response, config, getShared). Passamos
            // getShared = false para garantir que as opcoes desta chamada
            // (headers com a chave e tempo limite) valham sempre.
            $cliente = service('curlrequest', [
                'timeout'         => $this->tempoLimite,
                'connect_timeout' => $this->tempoLimite,
                'http_errors'     => false,
                'headers'         => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'CI_EMPLOYER',
                ],
            ], null, null, false);

            // Opcoes da chamada: corpo JSON quando houver.
            $opcoes = [];

            if ($corpo !== null) {
                $opcoes['json'] = $corpo;
            }

            // Executa conforme o metodo HTTP.
            $resposta = $this->executar($cliente, $metodo, $url, $opcoes);

            // Codigo HTTP e corpo bruto.
            $codigo = $resposta->getStatusCode();
            $bruto  = (string) $resposta->getBody();

            // Auditoria: metodo, URL e status. Nunca a chave.
            $this->registrar($metodo, $url, $codigo);

            // Corpo pode vir vazio (ex.: DELETE). Vira array vazio.
            $dados = json_decode($bruto, true);

            if (! is_array($dados)) {
                $dados = [];
            }

            // Qualquer codigo 2xx e sucesso.
            if ($codigo >= 200 && $codigo < 300) {
                return [
                    'sucesso' => true,
                    'dados'   => $dados,
                    'erro'    => null,
                    'codigo'  => $codigo,
                ];
            }

            // Erro previsto pela API.
            return $this->falha($this->mensagemDoCodigo($codigo, $dados), $codigo);
        } catch (Throwable $erro) {
            // Timeout, DNS, TLS, conexao recusada...
            $this->registrar($metodo, $url, 0);

            return $this->falha('Falha de comunicacao com o cron-job.org: ' . $erro->getMessage(), 0);
        }
    }

    /**
     * Despacha a chamada conforme o metodo HTTP.
     *
     * @param array<string, mixed> $opcoes
     */
    private function executar(CURLRequest $cliente, string $metodo, string $url, array $opcoes)
    {
        if ($metodo === 'PUT') {
            return $cliente->put($url, $opcoes);
        }

        if ($metodo === 'PATCH') {
            return $cliente->patch($url, $opcoes);
        }

        if ($metodo === 'DELETE') {
            return $cliente->delete($url, $opcoes);
        }

        if ($metodo === 'POST') {
            return $cliente->post($url, $opcoes);
        }

        // Padrao: GET.
        return $cliente->get($url, $opcoes);
    }

    /**
     * Traduz o codigo HTTP em uma mensagem clara para o operador.
     *
     * @param array<string, mixed> $dados Corpo ja decodificado.
     */
    private function mensagemDoCodigo(int $codigo, array $dados): string
    {
        // Chave invalida ou expirada.
        if ($codigo === 401) {
            return 'Chave de API do cron-job.org invalida ou expirada.';
        }

        // Sem permissao (plano/conta).
        if ($codigo === 403) {
            return 'A conta do cron-job.org nao tem permissao para esta operacao.';
        }

        // Job ou recurso inexistente.
        if ($codigo === 404) {
            return 'Job nao encontrado no cron-job.org.';
        }

        // Limite de requisicoes da API.
        if ($codigo === 429) {
            return 'Limite de requisicoes da API do cron-job.org atingido. Tente novamente mais tarde.';
        }

        // Indisponibilidade do servico.
        if ($codigo >= 500) {
            return 'O cron-job.org esta indisponivel no momento (HTTP ' . $codigo . ').';
        }

        // Tenta aproveitar a mensagem que o proprio servico devolveu.
        $mensagem = $this->mensagemDoServico($dados);

        if ($mensagem !== null) {
            return $mensagem;
        }

        return 'O cron-job.org respondeu HTTP ' . $codigo . '.';
    }

    /**
     * Extrai a mensagem de erro que o cron-job.org costuma devolver
     * nos campos "error" ou "message".
     *
     * @param array<string, mixed> $dados
     */
    private function mensagemDoServico(array $dados): ?string
    {
        if (isset($dados['error']) && is_string($dados['error']) && $dados['error'] !== '') {
            return $dados['error'];
        }

        if (isset($dados['message']) && is_string($dados['message']) && $dados['message'] !== '') {
            return $dados['message'];
        }

        return null;
    }

    /**
     * Monta a resposta padronizada de falha.
     *
     * @return array<string, mixed>
     */
    private function falha(string $mensagem, int $codigo): array
    {
        return [
            'sucesso' => false,
            'dados'   => [],
            'erro'    => $mensagem,
            'codigo'  => $codigo,
        ];
    }

    /**
     * Registra uma linha de auditoria com metodo, URL e status.
     *
     * A chave de API NUNCA entra aqui: ela vai apenas no header da requisicao.
     * O arquivo fica em writable/logs/cron-job-org-YYYY-MM-DD.log.
     */
    private function registrar(string $metodo, string $url, int $codigo): void
    {
        // Diretorio de logs do projeto (fora da raiz web).
        $diretorio = WRITEPATH . 'logs';

        // Sem diretorio nao ha onde gravar.
        if (! is_dir($diretorio)) {
            return;
        }

        // Uma linha por requisicao.
        $linha = date('Y-m-d H:i:s') . ' | ' . $metodo . ' | ' . $url . ' | HTTP ' . $codigo . PHP_EOL;

        // Nome do arquivo por dia.
        $arquivo = $diretorio . DIRECTORY_SEPARATOR . 'cron-job-org-' . date('Y-m-d') . '.log';

        try {
            file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
        } catch (Throwable $erro) {
            // Falha de log nunca pode derrubar a operacao principal.
        }
    }
}
