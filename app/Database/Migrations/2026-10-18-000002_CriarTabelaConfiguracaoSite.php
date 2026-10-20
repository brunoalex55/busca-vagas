<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `configuracao_site`
 * --------------------------------------------------------------------------
 *
 * Guarda os dados editaveis do site publico (nome, slogan, SEO,
 * contatos, redes sociais, textos institucionais e imagens).
 *
 * Esta tabela tem sempre UM unico registro (id = 1). O admin edita
 * esses campos pelo painel e o site publico os consome em todas as
 * paginas: layout, footer, Open Graph, JSON-LD, etc.
 */
class CriarTabelaConfiguracaoSite extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro unico de configuracao.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Nome comercial do site (ex.: "BuscaVagas PRO").
            'nome_site' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],

            // Frase de efeito exibida junto ao logo.
            'slogan' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
            ],

            // Meta description padrao do site.
            'descricao_site' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Meta keywords padrao (separadas por virgula).
            'palavras_chave_seo' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],

            // E-mail publico de contato exibido no site.
            'email_contato' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],

            // Telefone publico de contato.
            'telefone_contato' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],

            // Numero de WhatsApp (apenas digitos, para montar o link wa.me).
            'whatsapp_contato' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],

            // Endereco fisico (opcional).
            'endereco' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Caminho relativo do logo enviado pelo admin.
            'logo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Caminho relativo do favicon enviado pelo admin.
            'favicon' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Imagem usada em Open Graph e Twitter Card.
            'imagem_og' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Links das redes sociais. Vazio significa "nao exibir".
            'link_facebook' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_instagram' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_twitter' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_linkedin' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_youtube' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_tiktok' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_github' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'link_site_pessoal' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // Texto livre exibido no rodape.
            'texto_rodape' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Texto da pagina "Sobre".
            'texto_sobre' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // ID do Google Analytics (ex.: G-XXXXXXX), opcional.
            'google_analytics_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],

            // Codigo de verificacao do Google Search Console, opcional.
            'google_search_console_code' => [
                'type' => 'TEXT',
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

        // Cria a tabela.
        $this->forge->createTable('configuracao_site', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracao_site', true);
    }
}
