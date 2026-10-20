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
 * MIGRATION: TABELA `chaves_serp_api`
 * --------------------------------------------------------------------------
 *
 * Somente o administrador cadastra e gerencia estas chaves.
 * O usuario comum nunca ve nada relacionado a SerpApi.
 *
 * O sistema usa a chave ativa de menor `ordem` que ainda tenha
 * consultas restantes, fazendo rotacao automatica entre as contas.
 */
class CriarTabelaChavesSerpApi extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador interno da chave.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Nome amigavel para o admin identificar a conta
            // (ex.: "Conta 1", "Conta principal").
            'apelido' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],

            // Valor da chave da API. Nunca e exibida em tela por inteiro.
            'chave' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],

            // Quantidade total de consultas contratadas nessa chave.
            'quantidade_consultas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantidade ja consumida. Incrementada a cada requisicao.
            'consultas_usadas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Permite desligar temporariamente uma chave sem excluir.
            'ativa' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],

            // Prioridade de uso: menor numero e usada primeiro.
            'ordem' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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

        // Indice para localizar rapidamente a proxima chave disponivel.
        $this->forge->addKey(['ativa', 'ordem'], false, false, 'idx_chaves_ativa_ordem');

        // Cria a tabela.
        $this->forge->createTable('chaves_serp_api', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('chaves_serp_api', true);
    }
}
