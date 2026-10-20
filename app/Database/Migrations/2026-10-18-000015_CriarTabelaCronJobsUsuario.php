<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `cron_jobs_usuario`
 * --------------------------------------------------------------------------
 *
 * Rastreia, por usuario, os dois jobs criados no cron-job.org quando o
 * modo de agendamento e `spark_manual`:
 *
 *   - tipo `vagas`  -> chama POST /cron/usuario/{id}/executar-externo?comando=vagas
 *   - tipo `emails` -> chama POST /cron/usuario/{id}/executar-externo?comando=emails
 *
 * Cada linha guarda:
 *   - `cronjob_org_job_id`: o id do job devolvido pela API do cron-job.org;
 *   - `token_externo`: o token que o job envia no header X-Cron-Token;
 *   - `ultima_execucao` e `ultimo_status`: resultado da ultima chamada.
 *
 * O token e unico POR USUARIO: as duas linhas do mesmo usuario compartilham
 * o mesmo valor. Regenerar o token atualiza as duas e invalida o antigo.
 *
 * O token fica gravado em claro (como as chaves SerpApi), mas nunca e
 * exibido por inteiro na tela nem escrito em log.
 *
 * A restricao UNIQUE (usuario_id, tipo) garante no maximo um job de cada
 * tipo por usuario: a sincronizacao pode, com seguranca, atualizar em vez
 * de duplicar.
 *
 * Fora do modo `spark_manual` esta tabela fica vazia: a integracao com o
 * cron-job.org so existe nesse modo.
 */
class CriarTabelaCronJobsUsuario extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Dono do job.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Identificador do job no cron-job.org.
            // Fica nulo enquanto o job nao foi criado no servico.
            'cronjob_org_job_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],

            // Qual comando o job executa.
            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['vagas', 'emails'],
                'null'       => false,
            ],

            // Token que o job envia no header X-Cron-Token.
            'token_externo' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],

            // Permite desligar o job sem excluir o registro.
            'ativo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],

            // Momento da ultima execucao registrada.
            'ultima_execucao' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Resultado da ultima execucao (sucesso, erro, ...).
            'ultimo_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],

            // Data de criacao do registro.
            'criado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Data da ultima alteracao.
            'atualizado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // No maximo um job de cada tipo por usuario.
        $this->forge->addUniqueKey(['usuario_id', 'tipo'], 'uniq_cron_jobs_usuario_tipo');

        // Ao excluir o usuario, os jobs dele saem junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_cron_jobs_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('cron_jobs_usuario', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('cron_jobs_usuario', true);
    }
}
