<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: PROVEDOR EM `emails_enviados`
 * --------------------------------------------------------------------------
 *
 * A tabela `emails_enviados` registra as candidaturas enviadas por e-mail.
 * Ate aqui toda origem era do SerpApi (a busca por API e a candidatura por
 * e-mail). Com o segundo provedor, o mesmo registro passa a guardar de onde
 * veio o envio:
 *   - 'serpapi'      -> e-mail enviado para uma vaga achada no Google Jobs;
 *   - 'divulgavagas' -> e-mail enviado para uma vaga achada no Divulga
 *                       Vagas (a regra de duplo envio: o bot candidata no
 *                       site e o e-mail vai para o contacto encontrado).
 *
 * A coluna `origem` (manual, massa, automatico) continua dizendo COMO o
 * envio aconteceu. As duas colunas juntas descrevem a origem completa:
 * onde a vaga foi achada e como a candidatura saiu.
 */
class AdicionarProvedorEmEmailsEnviados extends Migration
{
    public function up(): void
    {
        // Acrescenta a coluna do provedor.
        $this->forge->addColumn('emails_enviados', [
            'provedor' => [
                'type'       => 'ENUM',
                'constraint' => ['serpapi', 'divulgavagas'],
                'default'    => 'serpapi',
                'null'       => false,
            ],
        ]);

        // Os envios antigos sao do SerpApi.
        $this->db->query("UPDATE `emails_enviados` SET `provedor` = 'serpapi' WHERE `provedor` IS NULL");
    }

    public function down(): void
    {
        // Remove a coluna do provedor (SQL direto: o forge nao remove
        // colunas com enum e default).
        $this->db->query('ALTER TABLE `emails_enviados` DROP COLUMN `provedor`');
    }
}
