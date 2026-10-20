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
 * MIGRATION: COLUNAS DO DIVULGA VAGAS EM `vagas`
 * --------------------------------------------------------------------------
 *
 * A tabela `vagas` ja existe e e compartilhada pelos provedores de busca.
 * O SerpApi preenche os campos de origem (job_id, hash_unico, url_original,
 * email_contato...). O Divulga Vagas usa a MESMA tabela e a MESMA
 * deduplicacao, entao precisa apenas de um bloco de colunas proprias.
 *
 * Colunas acrescentadas:
 *   - provedor_vaga: de onde veio a vaga ('serpapi' ou 'divulgavagas');
 *   - url_envio_divulgavagas: URL do formulario de candidatura do site;
 *   - vaga_id_divulgavagas: identificador numerico da vaga no site;
 *   - candidatura_divulgavagas_status: situation da candidatura no site;
 *   - candidatura_divulgavagas_tentativas: quantas vezes o bot tentou;
 *   - candidatura_divulgavagas_erro: mensagem da ultima falha;
 *   - candidatura_divulgavagas_em: quando o bot confirmou o envio;
 *   - data_ultimo_envio_bot: ultima candidatura feita pelo bot (intervalo);
 *   - email_enviado_divulgavagas_em: quando o e-mail de contacto foi enviado;
 *   - email_enviado_divulgavagas_erro: mensagem da falha do e-mail.
 *
 * O status da candidatura tem seis valores:
 *   - 'pendente'      -> o bot ainda nao tentou;
 *   - 'enviada'       -> o bot confirmou o envio (sem e-mail de contacto);
 *   - 'enviada_email' -> o bot confirmou E o e-mail foi enviado;
 *   - 'pendente_email'-> o bot confirmou, mas o e-mail ficou para o
 *                         proximo ciclo (limite diario, horario ou intervalo);
 *   - 'erro'          -> falhou tres vezes;
 *   - 'cloudflare'    -> o bot foi bloqueado pelo Cloudflare (nao retenta).
 */
class AdicionarColunasDivulgaVagasEmVagas extends Migration
{
    public function up(): void
    {
        // Acrescenta as colunas do Divulga Vagas na tabela de vagas.
        $this->forge->addColumn('vagas', [
            // De qual provedor veio a vaga.
            'provedor_vaga' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'serpapi',
                'null'       => false,
            ],

            // URL do formulario de candidatura no site do Divulga Vagas.
            'url_envio_divulgavagas' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],

            // Identificador da vaga no site (o mesmo papel do job_id).
            'vaga_id_divulgavagas' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],

            // Situacao da candidatura pelo site.
            'candidatura_divulgavagas_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pendente', 'enviada', 'enviada_email', 'pendente_email', 'erro', 'cloudflare'],
                'default'    => 'pendente',
                'null'       => false,
            ],

            // Quantas vezes o bot tentou esta vaga.
            'candidatura_divulgavagas_tentativas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'null'       => false,
            ],

            // Mensagem da ultima falha da candidatura.
            'candidatura_divulgavagas_erro' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Quando o bot confirmou o envio.
            'candidatura_divulgavagas_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Ultima candidatura feita pelo bot (controla o intervalo).
            'data_ultimo_envio_bot' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Quando o e-mail de contacto foi enviado.
            'email_enviado_divulgavagas_em' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // Mensagem da falha no envio do e-mail de contacto.
            'email_enviado_divulgavagas_erro' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        // Indice para o CRON 2 escolher as vagas pendentes do usuario.
        // O indice e criado com SQL direto: o addColumn() do forge nao
        // aceita addKey() na mesma migration.
        $this->db->query(
            'CREATE INDEX `idx_vagas_fila_divulgavagas` ON `vagas` '
            . '(`usuario_id`, `provedor_vaga`, `candidatura_divulgavagas_status`)'
        );
    }

    public function down(): void
    {
        // Remove o indice das filas do Divulga Vagas (se ainda existir).
        $existe = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.statistics "
            . "WHERE table_schema = DATABASE() AND table_name = 'vagas' "
            . "AND index_name = 'idx_vagas_fila_divulgavagas'"
        );

        if ($existe !== false && (int) $existe->getRow('total') > 0) {
            $this->db->query('ALTER TABLE `vagas` DROP INDEX `idx_vagas_fila_divulgavagas`');
        }

        // Remove as colunas do provedor (SQL direto: o forge nao remove
        // colunas com enum e default).
        foreach ([
            'email_enviado_divulgavagas_erro',
            'email_enviado_divulgavagas_em',
            'data_ultimo_envio_bot',
            'candidatura_divulgavagas_em',
            'candidatura_divulgavagas_erro',
            'candidatura_divulgavagas_tentativas',
            'candidatura_divulgavagas_status',
            'vaga_id_divulgavagas',
            'url_envio_divulgavagas',
            'provedor_vaga',
        ] as $coluna) {
            $this->db->query('ALTER TABLE `vagas` DROP COLUMN `' . $coluna . '`');
        }
    }
}
