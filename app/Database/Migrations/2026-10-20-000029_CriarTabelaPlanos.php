<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: CRIAR TABELA `planos`
 * --------------------------------------------------------------------------
 *
 * Os planos sao as regras que o site publico promete (Basico,
 * Profissional e Empresa) e que o administrador configura no painel
 * (/admin/planos).
 *
 * As colunas com valor NULL significam ILIMITADO (max_palavras_chave e
 * max_cidades_por_termo). Todo o codigo que as consome precisa tratar
 * o NULL explicitamente (nunca com ternario).
 *
 * A coluna `limite_envios_dia` nao aceita NULL: todo plano tem um
 * limite de envios por dia.
 */
class CriarTabelaPlanos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'descricao' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'preco_mensal' => [
                'type'       => 'DECIMAL',
                'constraint' => [10, 2],
                'null'       => true,
            ],
            // NULL = ilimitado.
            'max_palavras_chave' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            // NULL = ilimitado.
            'max_cidades_por_termo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'limite_envios_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 10,
            ],
            'envio_automatico' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'envio_em_massa' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'max_usuarios' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 1,
            ],
            'suporte' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
                'default'    => 'email',
            ],
            'destaque' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'ordem' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 0,
            ],
            'ativo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
            'criado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'atualizado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // O slug e unico (e o que identifica o plano nas rotas).
        $this->forge->addUniqueKey('slug');

        $this->forge->createTable('planos', true, ['ENGINE' => 'InnoDB']);

        // O indice dos planos ativos (SQL direto: o forge nao cria
        // indice simples junto com a chave unica).
        $this->db->query(
            'CREATE INDEX `idx_planos_ativo` ON `planos` (`ativo`)'
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('planos', true);
    }
}
