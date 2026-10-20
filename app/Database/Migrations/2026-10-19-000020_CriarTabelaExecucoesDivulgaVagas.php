<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `execucoes_divulgavagas`
 * --------------------------------------------------------------------------
 *
 * Registro de auditoria das execucoes do segundo provedor (Divulga Vagas).
 * Cada linha diz QUEM mandou, O QUE foi feito e COMO terminou.
 *
 * Serve para tres coisas:
 *   - auditoria: quem executou, quando e o que aconteceu;
 *   - controle: o painel mostra o resultado da ultima execucao;
 *   - historico: a tela de historico lista as ultimas execucoes.
 *
 * Tipos e origens:
 *   - tipo   -> 'buscar' (CRON 1, so busca) ou 'candidatar' (CRON 2, so
 *               candidatura pelo site);
 *   - origem -> 'manual_usuario' (o usuario no painel dele),
 *               'manual_admin' (o administrador) ou 'cron' (agendador).
 *
 * O campo `usuario_id` guarda de quem foi a execucao: zero significa
 * execucao global do administrador (todos os usuarios, um por um).
 */
class CriarTabelaExecucoesDivulgaVagas extends Migration
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

            // De quem foi a execucao (zero = global do administrador).
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // O que a execucao fez.
            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['buscar', 'candidatar'],
                'null'       => false,
            ],

            // Quem disparou a execucao.
            'origem' => [
                'type'       => 'ENUM',
                'constraint' => ['manual_usuario', 'manual_admin', 'cron'],
                'null'       => false,
            ],

            // Usuario que apertou o botao (o autor da execucao).
            'executado_por_usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Como a execucao terminou.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['em_andamento', 'sucesso', 'erro', 'limite_atingido'],
                'default'    => 'em_andamento',
                'null'       => false,
            ],

            // Quantas vagas novas entraram na tabela de vagas.
            'vagas_inseridas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantas vagas existentes foram atualizadas.
            'vagas_atualizadas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantas candidaturas o bot confirmou no site.
            'candidaturas_enviadas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantos e-mails de contacto foram enviados.
            'emails_enviados' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantos erros aconteceram.
            'erros' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Detalhe do resultado (motivo da recusa, erro do bot...).
            'mensagem' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Quando a execucao comecou.
            'iniciado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Quando a execucao terminou.
            'finalizado_em' => [
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

        // Historico do usuario: as ultimas execucoes ficam no topo.
        // No CI4 o nome do indice e o quarto parametro de addKey().
        $this->forge->addKey(['usuario_id', 'iniciado_em'], false, false, 'idx_execucoes_divulgavagas_usuario');

        // Historico global: o administrador ve as execucoes de todos.
        $this->forge->addKey(['origem', 'iniciado_em'], false, false, 'idx_execucoes_divulgavagas_origem');

        // Cria a tabela.
        $this->forge->createTable('execucoes_divulgavagas', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('execucoes_divulgavagas', true);
    }
}
