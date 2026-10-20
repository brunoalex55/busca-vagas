<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `configuracao_global`
 * --------------------------------------------------------------------------
 *
 * Configuracao interna do sistema, separada da configuracao publica do
 * site (que esta em `configuracao_site`). Aqui ficam os limites das
 * execucoes manuais disparadas pelo ADMINISTRADOR:
 *
 *   - admin_max_buscas_manuais_dia: buscas globais por dia (padrao 10);
 *   - admin_max_candidaturas_manuais_dia: candidaturas globais por dia
 *     (padrao 20);
 *   - os dois contadores do dia e a data do ultimo reset.
 *
 * A tabela tem uma unica linha (id 1). Os limites do usuario ficam em
 * `configuracoes`, uma linha por usuario.
 *
 * O administrador nao fura nenhum limite: ele acelera o processo, mas a
 * candidatura por usuario continua respeitando o limite de e-mail e os
 * limites do bot daquele usuario.
 */
class CriarTabelaConfiguracaoGlobal extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro (sempre 1).
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Quantas buscas manuais o admin pode disparar por dia.
            'admin_max_buscas_manuais_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 10,
                'null'       => false,
            ],

            // Quantas candidaturas manuais o admin pode disparar por dia.
            'admin_max_candidaturas_manuais_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 20,
                'null'       => false,
            ],

            // Quantas buscas manuais do admin ja foram feitas hoje.
            'admin_buscas_manuais_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantas candidaturas manuais do admin ja foram feitas hoje.
            'admin_candidaturas_manuais_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Dia do ultimo reset dos contadores do admin.
            'admin_data_reset_manual' => [
                'type' => 'DATE',
                'null' => true,
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
        $this->forge->createTable('configuracao_global', true, ['ENGINE' => 'InnoDB']);

        // Cria a linha unica com os valores padrao.
        $this->db->query(
            'INSERT INTO `configuracao_global` ('
            . '`id`, `admin_max_buscas_manuais_dia`, `admin_max_candidaturas_manuais_dia`, '
            . '`admin_buscas_manuais_hoje`, `admin_candidaturas_manuais_hoje`, '
            . '`admin_data_reset_manual`, `criado_em`, `atualizado_em`'
            . ') VALUES (1, 10, 20, 0, 0, NULL, NOW(), NOW())'
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracao_global', true);
    }
}
