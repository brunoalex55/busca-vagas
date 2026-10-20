<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `execucoes_manuais_usuario`
 * --------------------------------------------------------------------------
 *
 * Contador diario das execucoes manuais que o USUARIO comum disparou pelo
 * proprio painel (busca de vagas e envio de e-mails).
 *
 * Regras:
 *   - o limite vale para o TOTAL do dia (vagas + e-mails), nao por comando;
 *   - o limite e definido pelo admin em
 *     configuracao_cron.limite_execucoes_manuais_usuario (padrao 3);
 *   - o contador reseta sozinho: cada linha representa UM dia, entao o
 *     "reset" e simplesmente passar a usar a linha da data de hoje;
 *   - as execucoes do admin NAO entram aqui;
 *   - as chamadas do endpoint externo (cron-job.org) NAO entram aqui,
 *     porque sao disparadas pelo sistema, nao pelo usuario.
 *
 * A restricao UNIQUE (usuario_id, data) garante uma unica linha por dia
 * por usuario, permitindo incrementar com seguranca.
 */
class CriarTabelaExecucoesManuaisUsuario extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Dono do contador.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Dia a que o contador se refere.
            'data' => [
                'type' => 'DATE',
                'null' => false,
            ],

            // Quantas execucoes manuais o usuario fez nesse dia.
            'total_execucoes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
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

        // Uma linha por usuario por dia.
        $this->forge->addUniqueKey(['usuario_id', 'data'], 'uniq_execucoes_manuais_dia');

        // Ao excluir o usuario, o contador dele saiu junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_execucoes_manuais_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('execucoes_manuais_usuario', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('execucoes_manuais_usuario', true);
    }
}
