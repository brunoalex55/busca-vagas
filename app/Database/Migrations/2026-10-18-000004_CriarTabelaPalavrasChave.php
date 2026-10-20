<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `palavras_chave`
 * --------------------------------------------------------------------------
 *
 * Cada palavra-chave pertence a um usuario e combina:
 *   - o termo buscado (ex.: "Desenvolvedor PHP")
 *   - uma ou mais cidades (uma por linha, salvas como JSON)
 *
 * O sistema gera uma consulta para cada combinacao (termo + cidade).
 * A frequencia de execucao e recalculada conforme o limite dinamico
 * do usuario e o total de combinacoes que ele possui.
 */
class CriarTabelaPalavrasChave extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador da palavra-chave.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Dono da palavra-chave.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Termo pesquisado no provedor de vagas.
            'termo' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],

            // Lista de cidades em JSON, pois o usuario pode informar
            // varias cidades para o mesmo termo.
            'cidades' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Indica se a busca deve filtrar apenas vagas remotas.
            'remoto' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],

            // Idioma da busca (ex.: "pt").
            'idioma' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'pt',
                'null'       => false,
            ],

            // Pais da busca (ex.: "br").
            'pais' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
                'default'    => 'br',
                'null'       => false,
            ],

            // Dominio do Google usado na busca.
            'dominio_google' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'default'    => 'google.com.br',
                'null'       => false,
            ],

            // Intervalo, em horas, entre as execucoes desta combinacao.
            'frequencia_horas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 24,
                'null'       => false,
            ],

            // Proxima execucao agendada.
            'proxima_execucao' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Ultima execucao realizada.
            'ultima_execucao' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Total de consultas ja realizadas para esta palavra-chave.
            'total_consultas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Permite pausar uma palavra-chave sem excluir.
            'ativa' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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

        // Indice para buscar as palavras-chave de um usuario.
        $this->forge->addKey('usuario_id', false, false, 'idx_palavras_usuario');

        // Indice para o cron localizar o que esta no horario de executar.
        $this->forge->addKey(['ativa', 'proxima_execucao'], false, false, 'idx_palavras_agenda');

        // Ao excluir o usuario, suas palavras-chave tambem sao removidas.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_palavras_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('palavras_chave', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        // Remove primeiro o vinculo com usuarios e depois a tabela.
        $this->forge->dropTable('palavras_chave', true);
    }
}
