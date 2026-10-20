<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: ORIGEM DO LIMITE DE ENVIOS
 * --------------------------------------------------------------------------
 *
 * O limite de envios por dia pode vir de tres lugares (a prioridade esta
 * na biblioteca ResolvedorPlano):
 *
 *   1. usuarios.limite_manual  (o admin sobrescreveu a mao) - VENCE;
 *   2. planos.limite_envios_dia (o plano do usuario);
 *   3. configuracoes.limite_envios_dia (o valor legado da conta).
 *
 * A coluna nova diz de onde veio o valor guardado na tabela
 * `configuracoes`, para o administrador saber se aquele numero e do
 * plano ou foi mexido a mao.
 */
class AdicionarOrigemDoLimiteEmConfiguracoes extends Migration
{
    public function up(): void
    {
        // De onde veio o limite de envios guardado na conta.
        $this->forge->addColumn('configuracoes', [
            'limite_envios_dia_origem' => [
                'type'       => 'ENUM',
                'constraint' => ['plano', 'manual'],
                'null'       => false,
                'default'    => 'plano',
            ],
        ]);
    }

    public function down(): void
    {
        // Remove a coluna da origem.
        $this->db->query('ALTER TABLE `configuracoes` DROP COLUMN `limite_envios_dia_origem`');
    }
}
