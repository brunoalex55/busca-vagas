<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: CONTROLE MENSAL DAS CHAVES SERPAPI
 * --------------------------------------------------------------------------
 *
 * As chaves do provedor tem uma cota mensal. Sem controle de mes, o saldo
 * de uma chave ficaria "gasto para sempre" e o sistema pararia de buscar
 * vagas depois do primeiro mes.
 *
 * Esta migration acrescenta a tabela `chaves_serp_api`:
 *
 *   mes_referencia     -> mes (YYYY-MM) a que a contagem se refere.
 *   motivo_inativacao  -> por que a chave esta fora de circulacao:
 *                         'esgotada' (volta sozinha no proximo mes),
 *                         'invalida' (credencial recusada) ou
 *                         'manual'   (desligada pelo administrador).
 *
 * Assim, na virada do mes, o sistema zera o contador e religa apenas as
 * chaves que estavam esgotadas - nunca as desligadas de proposito nem as
 * que tiveram a credencial recusada.
 */
class AdicionarControleMensalChavesSerpApi extends Migration
{
    public function up(): void
    {
        // Novas colunas de controle.
        $this->forge->addColumn('chaves_serp_api', [
            // Mes a que a contagem de consultas se refere.
            'mes_referencia' => [
                'type'       => 'CHAR',
                'constraint' => 7,
                'null'       => true,
            ],

            // Motivo pelo qual a chave foi retirada de circulacao.
            'motivo_inativacao' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
        ]);

        // Chaves ja existentes passam a apontar para o mes corrente.
        $this->db->table('chaves_serp_api')->update([
            'mes_referencia' => date('Y-m'),
        ]);
    }

    public function down(): void
    {
        // Remove as colunas criadas.
        $this->forge->dropColumn('chaves_serp_api', ['mes_referencia', 'motivo_inativacao']);
    }
}
