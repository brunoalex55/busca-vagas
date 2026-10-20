<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `emails_enviados`
 * --------------------------------------------------------------------------
 *
 * Auditoria de TODOS os e-mails de candidatura enviados.
 *
 * Alem de servir de historico, esta tabela e a garantia de que o
 * sistema nunca reenvia candidatura para a mesma vaga: antes de
 * enviar, o sistema verifica `ja_candidatado` na tabela de vagas e
 * registra aqui cada tentativa.
 */
class CriarTabelaEmailsEnviados extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do envio.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Usuario remetente (dono da conta Gmail usada).
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Vaga alvo da candidatura (pode ser removida depois).
            'vaga_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // E-mail que recebeu a candidatura.
            'destinatario' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],

            // Assunto realmente enviado (apos substituir as variaveis).
            'assunto' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Como o envio foi disparado.
            'origem' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'massa', 'automatico'],
                'default'    => 'manual',
                'null'       => false,
            ],

            // Resultado do envio (enviado, erro_smtp, etc.).
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'enviado',
                'null'       => false,
            ],

            // Mensagem de erro, quando houver.
            // NUNCA deve conter a APP PASSWORD.
            'mensagem_erro' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Momento do envio.
            'enviado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // Indice para o historico do usuario.
        $this->forge->addKey(['usuario_id', 'enviado_em'], false, false, 'idx_emails_usuario_data');

        // Indice para contabilizar envios por origem (manual/massa/auto).
        $this->forge->addKey(['usuario_id', 'origem'], false, false, 'idx_emails_origem');

        // Ao excluir o usuario, o historico dele sai junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_emails_usuario'
        );

        // Se a vaga for excluida, o historico permanece.
        $this->forge->addForeignKey(
            'vaga_id',
            'vagas',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_emails_vaga'
        );

        // Cria a tabela.
        $this->forge->createTable('emails_enviados', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('emails_enviados', true);
    }
}
