<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: NOVOS TIPOS DE JOB EM `cron_jobs_usuario`
 * --------------------------------------------------------------------------
 *
 * A tabela `cron_jobs_usuario` guarda um job do cron-job.org por tipo
 * (por usuario). Com o segundo provedor, o painel passa a agendar
 * tambem o bot do Divulga Vagas:
 *
 *   - `divulgavagas_buscar` ....... job do CRON 1 do Divulga Vagas;
 *   - `divulgavagas_candidatar` ... job do CRON 2 do Divulga Vagas.
 *
 * O enum da coluna `tipo` ganha os dois valores. Os jobs antigos
 * continuam como estavam.
 *
 * O registro dos tipos vive em `GerenciadorCron::TIPOS_DE_JOB`: e ele
 * que decide o que o painel agenda e o que o endpoint externo executa.
 * Um proximo provedor entra la, sem mexer nesta tabela.
 */
class AdicionarNovosTiposDeJobEmCronJobsUsuario extends Migration
{
    public function up(): void
    {
        // O enum aceita os novos tipos de job.
        $this->db->query(
            "ALTER TABLE `cron_jobs_usuario`
             MODIFY COLUMN `tipo` ENUM('vagas','emails','divulgavagas_buscar','divulgavagas_candidatar')
             NOT NULL"
        );
    }

    public function down(): void
    {
        // Volta o enum para os dois tipos antigos (os jobs do Divulga
        // Vagas sao apagados antes, para nao sobrar valor invalido).
        $this->db->query("DELETE FROM `cron_jobs_usuario` WHERE `tipo` IN ('divulgavagas_buscar','divulgavagas_candidatar')");

        $this->db->query(
            "ALTER TABLE `cron_jobs_usuario`
             MODIFY COLUMN `tipo` ENUM('vagas','emails') NOT NULL"
        );
    }
}
