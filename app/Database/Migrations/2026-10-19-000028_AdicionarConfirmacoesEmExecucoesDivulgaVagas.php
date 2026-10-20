<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: CONTADOR DE CONFIRMACOES EM `execucoes_divulgavagas`
 * --------------------------------------------------------------------------
 *
 * A coluna `confirmacoes` conta quantas candidaturas o site CONFIRMOU
 * (a pagina de sucesso foi alcancada). E o numero que responde "quantas
 * candidaturas foram realmente feitas?", e aparece no historico.
 */
class AdicionarConfirmacoesEmExecucoesDivulgaVagas extends Migration
{
    public function up(): void
    {
        // O contador de confirmacoes do site.
        $this->forge->addColumn('execucoes_divulgavagas', [
            'confirmacoes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove o contador de confirmacoes.
        $this->db->query('ALTER TABLE `execucoes_divulgavagas` DROP COLUMN `confirmacoes`');
    }
}
