<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `usuarios`
 * --------------------------------------------------------------------------
 *
 * Guarda todas as contas do sistema: administradores e usuarios comuns.
 *
 * Perfis:
 *   - admin   -> acesso total ao painel administrativo
 *   - usuario -> acesso restrito ao proprio painel
 *
 * Status:
 *   - pendente  -> cadastrou-se e aguarda aprovacao do pagamento
 *   - ativo     -> pode usar o sistema
 *   - bloqueado -> acesso suspenso pelo administrador
 *
 * Nao existe tabela de planos: o controle de uso e feito pelo
 * limite dinamico calculado em tempo de execucao.
 */
class CriarTabelaUsuarios extends Migration
{
    public function up(): void
    {
        // Define todos os campos da tabela.
        $this->forge->addField([
            // Identificador unico da conta.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Nome completo exibido no sistema.
            'nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],

            // E-mail de login. Deve ser unico em todo o sistema.
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => false,
            ],

            // Hash da senha gerado por password_hash(PASSWORD_BCRYPT).
            // O texto puro NUNCA e armazenado.
            'senha' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],

            // Perfil de acesso: administrador ou usuario comum.
            'perfil' => [
                'type'       => 'ENUM',
                'constraint' => ['admin', 'usuario'],
                'default'    => 'usuario',
                'null'       => false,
            ],

            // Situacao da conta no fluxo de aprovacao.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pendente', 'ativo', 'bloqueado'],
                'default'    => 'pendente',
                'null'       => false,
            ],

            // Quantidade de consultas feitas no mes corrente.
            // Zerado automaticamente na virada do mes.
            'consultas_usadas_mes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Mes de referencia do contador acima, no formato "AAAA-MM".
            'mes_referencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 7,
                'null'       => true,
            ],

            // Limite manual de consultas. Quando NULL, o sistema usa o
            // calculo dinamico e automatico descrito nas regras.
            'limite_manual' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // Chave (switch) que liga/desliga o envio automatico.
            'envio_automatico' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],

            // Total historico de e-mails automaticos enviados.
            'emails_automaticos_enviados' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Data/hora da ultima execucao do CRON 2 para este usuario.
            'ultima_execucao_envio_automatico' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Data/hora da ultima execucao do CRON 1 para este usuario.
            'ultima_execucao_busca_vagas' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Campo de desativacao logica da conta.
            'ativo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],

            // Data de criacao do registro.
            'criado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Data da ultima alteracao do registro.
            'atualizado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // E-mail unico: impede duas contas com o mesmo login.
        $this->forge->addKey('email', false, true, 'uq_usuarios_email');

        // Indice para filtrar rapidamente por status e perfil.
        $this->forge->addKey(['status', 'perfil'], false, false, 'idx_usuarios_status_perfil');

        // Cria a tabela com suporte a utf8mb4 (acentos e emojis).
        $this->forge->createTable('usuarios', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        // Remove a tabela ao desfazer a migration.
        $this->forge->dropTable('usuarios', true);
    }
}
