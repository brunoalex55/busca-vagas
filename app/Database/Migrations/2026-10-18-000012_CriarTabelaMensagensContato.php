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
 * MIGRATION: TABELA `mensagens_contato`
 * --------------------------------------------------------------------------
 *
 * Guarda as mensagens enviadas pelo formulario publico de contato.
 *
 * Antes desta tabela a mensagem era apenas validada e descartada; agora
 * ela vira uma fila de atendimento no painel administrativo.
 *
 * Status:
 *   - nova       -> chegou e ninguem abriu;
 *   - lida       -> o admin leu, mas ainda nao respondeu;
 *   - respondida -> o admin enviou a resposta por e-mail;
 *   - arquivada  -> fora da fila principal (sem apagar o historico).
 *
 * A resposta e guardada na propria linha para que a central mostre o
 * que foi dito, mesmo que o envio do e-mail falhe depois.
 */
class CriarTabelaMensagensContato extends Migration
{
    /**
     * FASE PAUSADA: tabela mensagens_contato (fase de contato).
     *
     * A migration fica no lugar, mas nao cria nada enquanto esta flag
     * estiver desligada. Assim nao e preciso renomear o arquivo antes de
     * rodar "php spark migrate": quem rodar o comando por engano nao cria
     * a tabela sem querer.
     *
     * Para ativar a fase: ligar a flag e rodar as migrations de novo.
     */
    public const ATIVA = false;

    public function up(): void
    {
        // Fase pausada: nada e criado.
        if (! self::ATIVA) {
            return;
        }

        $this->forge->addField([
            // Identificador da mensagem.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Nome informado pelo visitante.
            'nome' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],

            // E-mail de resposta do visitante.
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => false,
            ],

            // Texto enviado pelo formulario.
            'mensagem' => [
                'type' => 'TEXT',
                'null' => false,
            ],

            // Situacao da mensagem na fila de atendimento.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['nova', 'lida', 'respondida', 'arquivada'],
                'default'    => 'nova',
                'null'       => false,
            ],

            // Texto da resposta enviada ao visitante.
            'resposta' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Momento da resposta.
            'respondido_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Administrador que respondeu (pode ser removido depois).
            'respondido_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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

        // Indice da fila: filtrar por situacao e ordenar por data.
        $this->forge->addKey(['status', 'criado_em'], false, false, 'idx_mensagens_status_data');

        // Se o administrador for removido, a mensagem continua existindo.
        $this->forge->addForeignKey(
            'respondido_por',
            'usuarios',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_mensagens_respondido_por'
        );

        // Cria a tabela.
        $this->forge->createTable('mensagens_contato', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        // Fase pausada: a tabela nunca foi criada.
        if (! self::ATIVA) {
            return;
        }

        $this->forge->dropTable('mensagens_contato', true);
    }
}
