<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `configuracoes`
 * --------------------------------------------------------------------------
 *
 * Configuracoes INDIVIDUAIS de envio de cada usuario.
 *
 * IMPORTANTE: nao existe APP PASSWORD global. Cada usuario cadastra o
 * proprio Gmail e o proprio APP PASSWORD. O valor e armazenado
 * criptografado (AES-256-CBC) na coluna app_password_criptografado e
 * so e descriptografado no momento do envio.
 *
 * A tabela tambem guarda os limites de envio que protegem a conta
 * Gmail do usuario contra bloqueio por spam.
 */
class CriarTabelaConfiguracoes extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador da configuracao.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Dono da configuracao. Um registro por usuario.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // E-mail Gmail do usuario (remetente das candidaturas).
            'email_remetente' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],

            // APP PASSWORD criptografado. NUNCA e exibido em tela,
            // nem gravado em log, nem devolvido em JSON.
            'app_password_criptografado' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Nome que aparece como remetente.
            'nome_remetente' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            // Telefone divulgado no corpo do e-mail.
            'telefone' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],

            // Assunto padrao, com variaveis como {titulo_vaga}.
            'assunto_padrao' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Corpo padrao, com variaveis como {empresa} e {localizacao}.
            'corpo_padrao' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Assinatura anexada ao final do e-mail.
            'assinatura' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Caminho do curriculo em PDF enviado pelo usuario.
            'curriculo_pdf' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Fuso horario usado para calcular horarios de envio.
            'fuso_horario' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'default'    => 'America/Sao_Paulo',
                'null'       => false,
            ],

            // ------------------------------------------------------------------
            // LIMITES E HORARIOS DE ENVIO
            // ------------------------------------------------------------------

            // Quantidade maxima de e-mails por dia.
            'limite_envios_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 20,
                'null'       => false,
            ],

            // Intervalo minimo entre um envio e o proximo (segundos).
            'intervalo_minimo_segundos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 180,
                'null'       => false,
            ],

            // Intervalo maximo entre um envio e o proximo (segundos).
            // O sistema sorteia um valor entre o minimo e o maximo.
            'intervalo_maximo_segundos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 420,
                'null'       => false,
            ],

            // Quantos e-mails ja foram enviados hoje.
            'envios_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Data/hora do ultimo envio (usada para calcular o intervalo).
            'data_ultimo_envio' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Data do ultimo reset do contador diario.
            'data_reset_contador' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // Horario a partir do qual o sistema pode enviar.
            'horario_inicio_envio' => [
                'type'    => 'TIME',
                'default' => '08:00:00',
                'null'    => false,
            ],

            // Horario ate o qual o sistema pode enviar.
            'horario_fim_envio' => [
                'type'    => 'TIME',
                'default' => '20:00:00',
                'null'    => false,
            ],

            // Dias da semana permitidos, no formato "1,2,3,4,5"
            // (1 = segunda, 7 = domingo).
            'dias_semana_permitidos' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => '1,2,3,4,5',
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

        // Garante um unico registro de configuracao por usuario.
        $this->forge->addKey('usuario_id', false, true, 'uq_configuracoes_usuario');

        // Ao excluir o usuario, a configuracao dele tambem e removida.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_configuracoes_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('configuracoes', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracoes', true);
    }
}
