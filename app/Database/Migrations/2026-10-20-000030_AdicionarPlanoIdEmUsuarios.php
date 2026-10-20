<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: PLANO DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Cada usuario passa a pertencer a um plano (a regra que o site publico
 * promete). A coluna `usuarios.plano_id` fica NULL enquanto o usuario
 * nao tem plano definido (o fallback e o plano basico).
 *
 * `plano_inicio_em` e `plano_fim_em` guardam o periodo do plano (o fim
 * pode ficar NULL = sem expiracao).
 *
 * A coluna `usuarios.limite_manual` (que ja existia) CONTINUA TENDO
 * PRIORIDADE sobre o plano: quando ela esta preenchida, o valor
 * sobrescreve o do plano.
 */
class AdicionarPlanoIdEmUsuarios extends Migration
{
    public function up(): void
    {
        // O plano do usuario.
        $this->forge->addColumn('usuarios', [
            'plano_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'plano_inicio_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'plano_fim_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // O indice do plano (as consultas por plano sao frequentes).
        $this->forge->addKey('plano_id');

        // A chave estrangeira (o plano pode sumir, o usuario nao).
        $this->db->query(
            'ALTER TABLE `usuarios`
             ADD CONSTRAINT `fk_usuarios_plano`
             FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`)
             ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        // Remove a chave estrangeira e as colunas.
        $this->db->query('ALTER TABLE `usuarios` DROP FOREIGN KEY `fk_usuarios_plano`');
        $this->db->query('ALTER TABLE `usuarios` DROP COLUMN `plano_id`');
        $this->db->query('ALTER TABLE `usuarios` DROP COLUMN `plano_inicio_em`');
        $this->db->query('ALTER TABLE `usuarios` DROP COLUMN `plano_fim_em`');
    }
}
