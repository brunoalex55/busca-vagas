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
 * MIGRATION: LIMITES DO DIVULGA VAGAS EM `configuracoes`
 * --------------------------------------------------------------------------
 *
 * A tabela `configuracoes` guarda, uma linha por usuario, as preferencias
 * de envio. Com o segundo provedor, cada usuario passa a ter limites
 * proprios para o Divulga Vagas.
 *
 * Limites de execucao manual (disparadas pelo usuario no painel dele):
 *   - divulgavagas_max_buscas_manuais_dia: buscas por dia (padrao 3);
 *   - divulgavagas_max_candidaturas_manuais_dia: candidaturas por dia
 *     (padrao 5);
 *   - os dois contadores do dia e a data do ultimo reset.
 *
 * Limites do bot (candidaturas feitas no site do Divulga Vagas):
 *   - divulgavagas_max_candidaturas_bot_dia: candidaturas no site por dia
 *     (padrao 30);
 *   - divulgavagas_candidaturas_bot_hoje e a data do ultimo reset;
 *   - divulgavagas_intervalo_bot_min_segundos e
 *     divulgavagas_intervalo_bot_max_segundos: a faixa sorteada entre uma
 *     candidatura e outra (padrao 60 a 180 segundos).
 *
 * IMPORTANTE: o limite do bot e separado do limite de e-mail. O limite de
 * e-mail protege a conta Gmail do usuario; o limite do bot protege o site
 * do Divulga Vagas. Uma candidatura pelo bot NAO consome limite de e-mail.
 */
class AdicionarLimitesDivulgaVagasEmConfiguracoes extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('configuracoes', [
            // Quantas buscas manuais o usuario pode disparar por dia.
            'divulgavagas_max_buscas_manuais_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 3,
                'null'       => false,
            ],

            // Quantas candidaturas manuais o usuario pode disparar por dia.
            'divulgavagas_max_candidaturas_manuais_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 5,
                'null'       => false,
            ],

            // Quantas buscas manuais ja foram feitas hoje.
            'divulgavagas_buscas_manuais_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Quantas candidaturas manuais ja foram feitas hoje.
            'divulgavagas_candidaturas_manuais_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Dia do ultimo reset dos contadores manuais.
            'divulgavagas_data_reset_manual' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // Quantas candidaturas o bot pode fazer no site por dia.
            'divulgavagas_max_candidaturas_bot_dia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 30,
                'null'       => false,
            ],

            // Quantas candidaturas o bot ja fez hoje.
            'divulgavagas_candidaturas_bot_hoje' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
            ],

            // Dia do ultimo reset do contador do bot.
            'divulgavagas_data_reset_bot' => [
                'type' => 'DATE',
                'null' => true,
            ],

            // Intervalo minimo entre duas candidaturas do bot.
            'divulgavagas_intervalo_bot_min_segundos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 60,
                'null'       => false,
            ],

            // Intervalo maximo entre duas candidaturas do bot.
            'divulgavagas_intervalo_bot_max_segundos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 180,
                'null'       => false,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove as colunas de limite (SQL direto: o forge nao remove
        // colunas com default).
        foreach ([
            'divulgavagas_intervalo_bot_max_segundos',
            'divulgavagas_intervalo_bot_min_segundos',
            'divulgavagas_data_reset_bot',
            'divulgavagas_candidaturas_bot_hoje',
            'divulgavagas_max_candidaturas_bot_dia',
            'divulgavagas_data_reset_manual',
            'divulgavagas_candidaturas_manuais_hoje',
            'divulgavagas_buscas_manuais_hoje',
            'divulgavagas_max_candidaturas_manuais_dia',
            'divulgavagas_max_buscas_manuais_dia',
        ] as $coluna) {
            $this->db->query('ALTER TABLE `configuracoes` DROP COLUMN `' . $coluna . '`');
        }
    }
}
