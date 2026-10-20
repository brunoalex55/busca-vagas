<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `logs_consulta`
 * --------------------------------------------------------------------------
 *
 * Auditoria de cada consulta feita ao provedor de vagas.
 * Registra qual chave SerpApi foi usada, o resultado e eventuais erros.
 *
 * Serve para o admin acompanhar consumo e diagnosticar problemas,
 * e para o usuario ver o historico de execucoes.
 */
class CriarTabelaLogsConsulta extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do log.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Usuario que originou a consulta.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Palavra-chave consultada (pode ser removida depois).
            'palavra_chave_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // Cidade usada nesta consulta especifica.
            'cidade_consultada' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            // Chave SerpApi utilizada na requisicao.
            'chave_serpapi_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // Termo pesquisado.
            'termo' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            // Origem da execucao: acao manual do usuario ou cron.
            'origem' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'cron'],
                'default'    => 'manual',
                'null'       => false,
            ],

            // Quantas vagas o provedor devolveu.
            'total_vagas_retornadas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Situacao da execucao (sucesso, erro_http, etc.).
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'sucesso',
                'null'       => false,
            ],

            // Mensagem de erro, quando houver.
            'mensagem_erro' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Momento em que a consulta foi executada.
            'consumido_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // Indice para o historico do usuario.
        $this->forge->addKey(['usuario_id', 'consumido_em'], false, false, 'idx_logs_usuario_data');

        // Ao excluir o usuario, os logs dele saem junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_logs_usuario'
        );

        // Se a palavra-chave for excluida, o log permanece para auditoria.
        $this->forge->addForeignKey(
            'palavra_chave_id',
            'palavras_chave',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_logs_palavra_chave'
        );

        // Se a chave SerpApi for excluida, o log permanece para auditoria.
        $this->forge->addForeignKey(
            'chave_serpapi_id',
            'chaves_serp_api',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_logs_chave'
        );

        // Cria a tabela.
        $this->forge->createTable('logs_consulta', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('logs_consulta', true);
    }
}
