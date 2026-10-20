<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `solicitacoes_liberacao`
 * --------------------------------------------------------------------------
 *
 * Fluxo de liberacao de acesso:
 *
 *   1. O usuario se cadastra e fica com status "pendente".
 *   2. Ele ve a tela "Aguardando aprovacao" com a chave PIX.
 *   3. Ele clica em "Informar pagamento" e cria uma solicitacao.
 *   4. O admin analisa e aprova ou rejeita.
 *   5. Aprovada -> o usuario passa para status "ativo".
 */
class CriarTabelaSolicitacoesLiberacao extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador da solicitacao.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Usuario que solicitou a liberacao.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Observacao livre escrita pelo usuario (ex.: comprovante).
            'observacao' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Situacao da solicitacao.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pendente', 'aprovada', 'rejeitada'],
                'default'    => 'pendente',
                'null'       => false,
            ],

            // Momento em que o admin respondeu.
            'respondido_em' => [
                'type' => 'DATETIME',
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

        // Indice para listar solicitacoes pendentes rapidamente.
        $this->forge->addKey(['status', 'criado_em'], false, false, 'idx_solicitacoes_status');

        // Indice para o historico do usuario.
        $this->forge->addKey('usuario_id', false, false, 'idx_solicitacoes_usuario');

        // Ao excluir o usuario, as solicitacoes dele saem junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_solicitacoes_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('solicitacoes_liberacao', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('solicitacoes_liberacao', true);
    }
}
