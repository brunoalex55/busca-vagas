<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Models;

use App\Libraries\GerenciadorCron;
use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: JOBS DO USUARIO NO CRON-JOB.ORG
 * --------------------------------------------------------------------------
 *
 * Cada usuario ativo tem um registro por tipo de job. Os tipos vem do
 * registro da biblioteca (GerenciadorCron::TIPOS_DE_JOB):
 *   - tipo `vagas` ................... busca de vagas (SerpApi);
 *   - tipo `emails` ................. envio de e-mails (SerpApi);
 *   - tipo `divulgavagas_buscar` .... busca no site do Divulga Vagas;
 *   - tipo `divulgavagas_candidatar` . candidatura no Divulga Vagas.
 *
 * O `token_externo` e unico por usuario: todas as linhas compartilham o
 * mesmo valor e a regeneracao invalida o token antigo de todos os jobs.
 */
class CronJobUsuarioModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'cron_jobs_usuario';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'cronjob_org_job_id',
        'tipo',
        'token_externo',
        'ativo',
        'ultima_execucao',
        'ultimo_status',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Lista os jobs locais de um usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function doUsuario(int $usuarioId): array
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->orderBy('tipo', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Busca a linha de um usuario para um tipo de job.
     *
     * @return array<string, mixed>|null
     */
    public function porUsuarioETipo(int $usuarioId, string $tipo): ?array
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo)
            ->get()
            ->getRowArray();
    }

    /**
     * Garante as duas linhas do usuario (vagas e emails) com o token dado.
     *
     * Serve tanto para o primeiro cadastro quanto para a regeneracao do
     * token: as duas linhas passam a ter sempre o mesmo valor.
     */
    public function sincronizarLinhasDoUsuario(int $usuarioId, string $token): void
    {
        // Tipos de job existentes (o registro da biblioteca).
        $tipos = array_keys(GerenciadorCron::TIPOS_DE_JOB);

        foreach ($tipos as $tipo) {
            $linha = $this->porUsuarioETipo($usuarioId, $tipo);

            // Primeira vez: cria a linha do tipo.
            if ($linha === null) {
                $this->insert([
                    'usuario_id'    => $usuarioId,
                    'tipo'          => $tipo,
                    'token_externo' => $token,
                    'ativo'         => 1,
                ]);

                continue;
            }

            // Ja existe: apenas alinha o token.
            $this->update((int) $linha['id'], ['token_externo' => $token]);
        }
    }

    /**
     * Grava o id do job devolvido pelo cron-job.org.
     */
    public function definirJobId(int $usuarioId, string $tipo, int $jobId): void
    {
        $linha = $this->porUsuarioETipo($usuarioId, $tipo);

        if ($linha === null) {
            return;
        }

        $this->update((int) $linha['id'], ['cronjob_org_job_id' => $jobId]);
    }

    /**
     * Limpa o id do job (quando ele e excluido no servico).
     */
    public function limparJobId(int $usuarioId, string $tipo): void
    {
        $linha = $this->porUsuarioETipo($usuarioId, $tipo);

        if ($linha === null) {
            return;
        }

        $this->update((int) $linha['id'], ['cronjob_org_job_id' => null]);
    }

    /**
     * Registra a ultima execucao e o status de um job.
     */
    public function registrarExecucao(int $usuarioId, string $tipo, string $status): void
    {
        $linha = $this->porUsuarioETipo($usuarioId, $tipo);

        if ($linha === null) {
            return;
        }

        $this->update((int) $linha['id'], [
            'ultima_execucao' => date('Y-m-d H:i:s'),
            'ultimo_status'   => $status,
        ]);
    }

    /**
     * Quantidade de jobs locais cadastrados (para os limites do plano).
     */
    public function contar(): int
    {
        return $this->db->table($this->table)->countAllResults();
    }
}
