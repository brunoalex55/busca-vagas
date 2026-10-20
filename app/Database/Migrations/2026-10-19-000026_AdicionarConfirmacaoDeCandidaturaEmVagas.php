<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: CONFIRMACAO DA CANDIDATURA EM `vagas`
 * --------------------------------------------------------------------------
 *
 * O bot do Divulga Vagas so diz que a candidatura foi feita quando
 * chega na pagina de sucesso do site. Guardar essa confirmacao
 * permite responder a pergunta que o usuario sempre faz: "foi mesmo?".
 *
 * A tabela `vagas` ganha:
 *   - `candidatura_divulgavagas_confirmado_em` .... quando o site
 *     confirmou o envio do curriculo;
 *   - `candidatura_divulgavagas_confirmacao_url` . o endereco da pagina
 *     de sucesso (a prova, guardada para auditoria).
 *
 * As candidaturas feitas por e-mail (SerpApi) ja tem confirmacao
 * propria: a linha em `emails_enviados` com o status "enviado".
 */
class AdicionarConfirmacaoDeCandidaturaEmVagas extends Migration
{
    public function up(): void
    {
        // A confirmacao do site.
        $this->forge->addColumn('vagas', [
            'candidatura_divulgavagas_confirmado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'candidatura_divulgavagas_confirmacao_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove as colunas de confirmacao.
        $this->db->query('ALTER TABLE `vagas` DROP COLUMN `candidatura_divulgavagas_confirmado_em`');
        $this->db->query('ALTER TABLE `vagas` DROP COLUMN `candidatura_divulgavagas_confirmacao_url`');
    }
}
