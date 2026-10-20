<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: PEDIDO DE PLANO E BLOQUEIO POR PAGAMENTO
 * --------------------------------------------------------------------------
 *
 * Duas coisas que o painel do usuario e o do administrador precisam:
 *
 * 1. `solicitacoes_plano`: quando o usuario escolhe um plano no painel
 *    (upgrade), o pedido vira uma linha aqui. O administrador ve a
 *    fila de pedidos pendentes, aprova (o plano passa a valer) ou
 *    recusa (com a justificativa).
 *
 * 2. O bloqueio por falta de pagamento: quando o administrador bloqueia
 *    uma conta por falta de pagamento, a tela do usuario volta a mostrar
 *    os dados do PIX (a chave e o valor) para a renovacao. Por isso a
 *    coluna `bloqueado_por_pagamento` na tabela de usuarios.
 */
class CriarSolicitacoesDePlanoEBloqueioPorPagamento extends Migration
{
    public function up(): void
    {
        // 1) Os pedidos de troca de plano.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // O plano pedido (o plano atual fica no usuario).
            'plano_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // O plano que o usuario tinha quando pediu.
            'plano_anterior_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pendente', 'aprovada', 'recusada'],
                'null'       => false,
                'default'    => 'pendente',
            ],
            'mensagem' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'resposta' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'respondido_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'respondido_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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

        $this->forge->addKey('id', true);
        $this->forge->addKey('usuario_id');
        $this->forge->addKey('status');
        $this->forge->createTable('solicitacoes_plano', true, ['ENGINE' => 'InnoDB']);

        // 2) O bloqueio por falta de pagamento (na conta do usuario).
        $this->forge->addColumn('usuarios', [
            'bloqueado_por_pagamento' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'motivo_bloqueio' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove as colunas do bloqueio.
        $this->db->query('ALTER TABLE `usuarios` DROP COLUMN `bloqueado_por_pagamento`');
        $this->db->query('ALTER TABLE `usuarios` DROP COLUMN `motivo_bloqueio`');

        // Remove a tabela dos pedidos.
        $this->forge->dropTable('solicitacoes_plano', true);
    }
}
