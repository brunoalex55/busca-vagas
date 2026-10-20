<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `configuracao_cron`
 * --------------------------------------------------------------------------
 *
 * Configuracao GLOBAL do agendamento de tarefas (Fase 7).
 *
 * A tabela tem um unico registro, como configuracao_site e
 * configuracao_pagamento. O admin altera tudo pelo painel /admin/cron.
 *
 * O campo `modo` decide COMO os comandos agendados sao disparados:
 *
 *   - cron_linux    -> o crontab do Linux chama `php spark ...`;
 *   - cron_windows  -> o Agendador de Tarefas do Windows chama `php spark ...`;
 *   - spark_manual  -> o admin dispara pelo painel, ou um servico externo
 *                      (cron-job.org) chama o endpoint protegido por token.
 *
 * O modo escolhido NAO altera o codigo dos Spark Commands: ele apenas
 * decide quem os chama.
 *
 * Sobre `api_key_cronjob`: e a chave unica da conta do cron-job.org.
 * Como as chaves SerpApi, ela fica gravada em claro no banco e NUNCA e
 * exibida por inteiro na tela, nem escrita em log.
 *
 * Sobre `requisicoes_api_hoje`: o plano gratuito do cron-job.org permite
 * 100 requisicoes de API por dia. O contador e zerado quando
 * `data_reset_requisicoes` e anterior ao dia atual.
 *
 * Sobre `limite_execucoes_manuais_usuario`: quantas vezes por dia o
 * USUARIO comum pode disparar manualmente a busca de vagas e o envio de
 * e-mails (somando os dois comandos). O admin nao entra nesse limite.
 */
class CriarTabelaConfiguracaoCron extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro unico.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Modo de agendamento em uso.
            'modo' => [
                'type'       => 'ENUM',
                'constraint' => ['cron_linux', 'cron_windows', 'spark_manual'],
                'default'    => 'spark_manual',
                'null'       => false,
            ],

            // Chave de API do cron-job.org (unica para a conta do SaaS).
            // Gravada em claro, mascarada na tela, nunca logada.
            'api_key_cronjob' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Requisicoes de API do cron-job.org feitas hoje.
            'requisicoes_api_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Data do ultimo reset do contador acima.
            'data_reset_requisicoes' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // Limite diario de execucoes manuais do usuario comum.
            'limite_execucoes_manuais_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 3,
                'null'       => false,
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

        // Cria a tabela.
        $this->forge->createTable('configuracao_cron', true, ['ENGINE' => 'InnoDB']);

        // Linha unica de configuracao, ja com os padroes do sistema.
        $agora = date('Y-m-d H:i:s');

        $this->db->table('configuracao_cron')->insert([
            'modo'                             => 'spark_manual',
            'api_key_cronjob'                  => null,
            'requisicoes_api_hoje'             => 0,
            'data_reset_requisicoes'           => null,
            'limite_execucoes_manuais_usuario' => 3,
            'criado_em'                        => $agora,
            'atualizado_em'                    => $agora,
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracao_cron', true);
    }
}
