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
 * MIGRATION: TABELA `vagas`
 * --------------------------------------------------------------------------
 *
 * Cada vaga pertence a UM usuario. A mesma vaga (mesmo job_id) pode
 * existir para usuarios diferentes, cada um com o proprio controle de
 * candidatura.
 *
 * Deduplicacao obrigatoria em dois niveis, ambos por usuario:
 *   1. UNIQUE (job_id, usuario_id)     -> identifica a vaga na origem.
 *   2. UNIQUE (hash_unico, usuario_id) -> identifica a vaga por
 *      titulo + empresa + localizacao (evita duplicata entre fontes).
 */
class CriarTabelaVagas extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador da vaga.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Palavra-chave que originou a busca (opcional, mas ajuda
            // a rastrear de onde a vaga veio).
            'palavra_chave_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],

            // Dono da vaga. Obrigatorio.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],

            // Identificador unico devolvido pelo provedor de vagas.
            'job_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],

            // SHA-256 de titulo + empresa + localizacao (minusculo).
            'hash_unico' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => false,
            ],

            // Titulo da vaga.
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Nome da empresa contratante.
            'empresa' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Localizacao informada pelo anuncio.
            'localizacao' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            // Cidade que foi usada na consulta que trouxe esta vaga.
            'cidade_consultada' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],

            // Fonte original: Indeed, LinkedIn, Catho, Gupy, etc.
            'fonte' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],

            // Link publico da vaga.
            'url_original' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Link direto para candidatura, quando existir.
            'url_candidatura' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Descricao completa da vaga.
            'descricao' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],

            // Faixa salarial, quando informada.
            'salario' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],

            // Tipo de contrato (CLT, PJ, Tempo integral, etc.).
            'tipo_contrato' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],

            // Data de publicacao da vaga.
            'publicado_em' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // E-mail de contato encontrado (o de maior prioridade).
            'email_contato' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],

            // Todos os e-mails validos encontrados, em JSON.
            'emails_encontrados' => [
                'type' => 'JSON',
                'null' => true,
            ],

            // De onde o e-mail veio:
            //   descricao      -> extraido do texto da vaga (confiavel)
            //   apply_options  -> extraido dos links de candidatura
            //   pagina         -> scraping da pagina (nao confirmado)
            'origem_email' => [
                'type'       => 'ENUM',
                'constraint' => ['descricao', 'apply_options', 'pagina'],
                'null'       => true,
            ],

            // 1 quando o usuario ja enviou candidatura para esta vaga.
            // Impede reenvio.
            'ja_candidatado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],

            // Marcacao de vaga prioritaria.
            'e_prioritaria' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
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

        // Deduplicacao por job_id, sempre dentro do mesmo usuario.
        $this->forge->addKey(['usuario_id', 'job_id'], false, true, 'uq_vagas_usuario_job');

        // Deduplicacao por hash, sempre dentro do mesmo usuario.
        $this->forge->addKey(['usuario_id', 'hash_unico'], false, true, 'uq_vagas_usuario_hash');

        // Indice usado na listagem: vagas com e-mail e ainda nao candidatadas.
        $this->forge->addKey(
            ['usuario_id', 'ja_candidatado', 'email_contato'],
            false,
            false,
            'idx_vagas_prioridade'
        );

        // Indice para agrupar por fonte (abas do painel).
        $this->forge->addKey(['usuario_id', 'fonte'], false, false, 'idx_vagas_fonte');

        // Ao excluir o usuario, todas as vagas dele saem junto.
        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_vagas_usuario'
        );

        // Ao excluir a palavra-chave, a vaga continua existindo.
        $this->forge->addForeignKey(
            'palavra_chave_id',
            'palavras_chave',
            'id',
            'CASCADE',
            'SET NULL',
            'fk_vagas_palavra_chave'
        );

        // Cria a tabela.
        $this->forge->createTable('vagas', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('vagas', true);
    }
}
