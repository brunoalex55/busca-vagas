<?php

namespace App\Controllers;

use App\Libraries\BuscadorDivulgaVagas;
use App\Libraries\CandidaturaDivulgaVagas;
use App\Libraries\TokenCron;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: CRON DO DIVULGA VAGAS
 * --------------------------------------------------------------------------
 *
 * Endpoints do agendamento do segundo provedor (Divulga Vagas). Sao os
 * gatilhos que um agendador externo (crontab, Agendador de Tarefas ou um
 * servico como o cron-job.org) chama para rodar os comandos do projeto.
 *
 * Endpoints (todos POST):
 *   POST /cron/usuario/{id}/divulgavagas/buscar-vagas
 *   POST /cron/usuario/{id}/divulgavagas/candidatar
 *   POST /cron/global/divulgavagas/buscar-vagas
 *   POST /cron/global/divulgavagas/candidatar
 *
 * SEGURANCA: cada chamada traz o token global do sistema na query string
 * (?token=...). A comparacao usa hash_equals(), que nao devolve informacao
 * pelo tempo de resposta.
 *
 * SEPARACAO (a regra de ouro do segundo provedor):
 *   - o endpoint de BUSCA nunca candidata a nada;
 *   - o endpoint de CANDIDATURA nunca busca vagas.
 *
 * O agendamento deste provedor e INDEPENDENTE do SerpApi: o admin escolhe
 * os horarios separado, em /admin/cron.
 */
class CronDivulgaVagas extends BaseController
{
    /**
     * CRON 1 de UM usuario: busca vagas no Divulga Vagas.
     *
     * NUNCA candidata a nada.
     */
    public function buscarUsuario(int $usuarioId): ResponseInterface
    {
        // Token global obrigatorio.
        if (! $this->tokenValido()) {
            return $this->resposta(403, 'erro', 'Token invalido.');
        }

        $buscador = new BuscadorDivulgaVagas();
        $resumo = $buscador->buscarDoUsuario($usuarioId, 'cron');

        return $this->resposta(200, 'sucesso', 'Busca de vagas concluida.', $resumo);
    }

    /**
     * CRON 1 global: busca vagas no Divulga Vagas para todos os ativos.
     *
     * NUNCA candidata a nada.
     */
    public function buscarGlobal(): ResponseInterface
    {
        // Token global obrigatorio.
        if (! $this->tokenValido()) {
            return $this->resposta(403, 'erro', 'Token invalido.');
        }

        $buscador = new BuscadorDivulgaVagas();
        $resumo = $buscador->buscarGlobal('cron');

        return $this->resposta(200, 'sucesso', 'Busca de vagas concluida.', $resumo);
    }

    /**
     * CRON 2 de UM usuario: candidata a UMA vaga do Divulga Vagas.
     *
     * NUNCA busca vagas.
     */
    public function candidatarUsuario(int $usuarioId): ResponseInterface
    {
        // Token global obrigatorio.
        if (! $this->tokenValido()) {
            return $this->resposta(403, 'erro', 'Token invalido.');
        }

        $candidatura = new CandidaturaDivulgaVagas();
        $resumo = $candidatura->candidatarDoUsuario($usuarioId, 'cron', $this->maximoDeVagas());

        return $this->resposta(200, 'sucesso', 'Candidatura concluida.', $resumo);
    }

    /**
     * CRON 2 global: candidata a UMA vaga de cada usuario ativo.
     *
     * NUNCA busca vagas.
     */
    public function candidatarGlobal(): ResponseInterface
    {
        // Token global obrigatorio.
        if (! $this->tokenValido()) {
            return $this->resposta(403, 'erro', 'Token invalido.');
        }

        $candidatura = new CandidaturaDivulgaVagas();
        $resumo = $candidatura->candidatarGlobal('cron', $this->maximoDeVagas());

        return $this->resposta(200, 'sucesso', 'Candidatura concluida.', $resumo);
    }

    /**
     * Confere o token global informado na query string.
     */
    private function tokenValido(): bool
    {
        $informado = trim((string) $this->request->getGet('token'));

        if ($informado === '') {
            return false;
        }

        $esperado = (new TokenCron())->obter();

        // A comparacao e feita em tempo constante.
        return hash_equals($esperado, $informado);
    }

    /**
     * Quantas vagas esta chamada pode candidatar.
     *
     * O agendador externo (cron-job.org) abandona a requisicao depois
     * de 30 segundos: a chamada por HTTP fica dentro desse tempo. A
     * fila inteira e drenada pelas chamadas seguintes (e pelo comando
     * Spark, que roda sem esse limite).
     */
    private function maximoDeVagas(): int
    {
        return max(1, (int) config('DivulgaVagas')->maxVagasPorChamada);
    }

    /**
     * Monta a resposta JSON do endpoint.
     *
     * @param array<string, mixed> $dados
     */
    private function resposta(int $codigo, string $status, string $mensagem, array $dados = []): ResponseInterface
    {
        return $this->response
            ->setStatusCode($codigo)
            ->setJSON([
                'status'       => $status,
                'mensagem'     => $mensagem,
                'provedor'     => 'divulgavagas',
                'executado_em' => date('Y-m-d H:i:s'),
                'estatisticas' => $dados,
            ]);
    }
}
