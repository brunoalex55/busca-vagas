<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: DETALHE DAS EXECUCOES EM `execucoes_divulgavagas`
 * --------------------------------------------------------------------------
 *
 * Uma execucao pode candidatar varias vagas (o loop esvazia a fila).
 * A coluna `detalhes` guarda a lista, em JSON, do que aconteceu em cada
 * vaga: o titulo, a empresa, se o bot confirmou e a URL da confirmacao.
 *
 * E o que o historico mostra: "qual vaga foi, e o site confirmou?".
 */
class AdicionarDetalheEmExecucoesDivulgaVagas extends Migration
{
    public function up(): void
    {
        // O detalhe da execucao (JSON).
        $this->forge->addColumn('execucoes_divulgavagas', [
            'detalhes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove a coluna do detalhe.
        $this->db->query('ALTER TABLE `execucoes_divulgavagas` DROP COLUMN `detalhes`');
    }
}
