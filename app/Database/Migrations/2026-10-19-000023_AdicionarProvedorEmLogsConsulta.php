<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: PROVEDOR EM `logs_consulta`
 * --------------------------------------------------------------------------
 *
 * A tabela `logs_consulta` registra as consultas de vagas feitas pelo
 * sistema. Com o segundo provedor, o log passa a guardar de onde veio
 * a consulta:
 *   - 'serpapi'      -> consulta na API do SerpApi (Google Jobs);
 *   - 'divulgavagas' -> consulta no site do Divulga Vagas (feita pelo
 *                       bot, sem chave de API).
 *
 * Isso permite que o painel do administrador filtre a auditoria por
 * provedor, do mesmo jeito que ja filtra os envios de e-mail.
 *
 * As consultas antigas (todas do SerpApi) continuam com o valor padrao.
 */
class AdicionarProvedorEmLogsConsulta extends Migration
{
    public function up(): void
    {
        // Acrescenta a coluna do provedor.
        $this->forge->addColumn('logs_consulta', [
            'provedor' => [
                'type'       => 'ENUM',
                'constraint' => ['serpapi', 'divulgavagas'],
                'default'    => 'serpapi',
                'null'       => false,
            ],
        ]);

        // As consultas antigas sao do SerpApi.
        $this->db->query("UPDATE `logs_consulta` SET `provedor` = 'serpapi' WHERE `provedor` IS NULL");
    }

    public function down(): void
    {
        // Remove a coluna do provedor (SQL direto: o forge nao remove
        // colunas com enum e default).
        $this->db->query('ALTER TABLE `logs_consulta` DROP COLUMN `provedor`');
    }
}
