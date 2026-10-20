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
 * MIGRATION: TABELA `emails_sistema`
 * --------------------------------------------------------------------------
 *
 * Auditoria dos e-mails TRANSACIONAIS do sistema, ou seja, aqueles que
 * nao sao candidaturas:
 *
 *   - boas_vindas          -> conta criada;
 *   - pagamento_informado  -> confirmacao do aviso de pagamento;
 *   - alerta_pagamento     -> aviso interno para os administradores;
 *   - liberacao_aprovada   -> conta liberada pelo admin;
 *   - liberacao_rejeitada  -> solicitacao recusada;
 *   - conta_bloqueada      -> acesso suspenso;
 *   - resposta_contato     -> resposta a uma mensagem do site;
 *   - teste                -> envio de verificacao do remetente.
 *
 * A separacao em relacao a `emails_enviados` e proposital: aquela tabela
 * e a trava contra reenviar candidatura para a mesma vaga, com regras
 * proprias. Misturar os dois fluxos quebraria essa garantia.
 *
 * O campo `referencia` aponta, em texto, para a origem do envio
 * (ex.: `mensagem_contato:12`, `solicitacao:2`). Nenhuma credencial e
 * gravada aqui: `mensagem_erro` nunca contem a APP PASSWORD.
 */
class CriarTabelaEmailsSistema extends Migration
{
    /**
     * FASE PAUSADA: tabela emails_sistema (fase de e-mails de sistema).
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
            // Identificador do envio.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Tipo do e-mail transacional.
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => false,
            ],

            // Quem recebeu a mensagem.
            'destinatario' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => false,
            ],

            // Usuario relacionado ao envio, quando existir.
            // Mensagens de visitantes nao tem usuario.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // Origem do envio (ex.: mensagem_contato:12).
            'referencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],

            // Assunto realmente enviado.
            'assunto' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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

            // Momento da tentativa.
            'enviado_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Chave primaria.
        $this->forge->addKey('id', true);

        // Indice do historico por data.
        $this->forge->addKey(['enviado_em'], false, false, 'idx_emails_sistema_data');

        // Indice para filtrar por tipo.
        $this->forge->addKey(['tipo', 'status'], false, false, 'idx_emails_sistema_tipo');

        // Se o usuario for removido, o historico continua (auditoria).
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_emails_sistema_usuario'
        );

        // Cria a tabela.
        $this->forge->createTable('emails_sistema', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        // Fase pausada: a tabela nunca foi criada.
        if (! self::ATIVA) {
            return;
        }

        $this->forge->dropTable('emails_sistema', true);
    }
}
