<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: AGENDAMENTO DO DIVULGA VAGAS EM `palavras_chave`
 * --------------------------------------------------------------------------
 *
 * Cada palavra-chave tem um horario de consulta (`proxima_execucao`),
 * que e o do SerpApi: quando a palavra roda, ela fica esperando a
 * frequencia escolhida pelo usuario (24 horas, por padrao).
 *
 * Com o segundo provedor isso virou um problema: os DOIS provedores
 * disputavam o mesmo horario. Quem rodasse primeiro empurrava a
 * palavra para o dia seguinte e o outro provedor nunca mais consultava
 * aquela palavra (a busca do Divulga Vagas parava com "sem_palavras").
 *
 * Aqui cada provedor passa a ter o seu proprio horario:
 *   - `proxima_execucao` .............. continua sendo o do SerpApi;
 *   - `proxima_execucao_divulgavagas` . o do Divulga Vagas.
 *
 * As palavras que nunca rodaram no Divulga Vagas comecam com o valor
 * nulo, ou seja, "devidas" (o primeiro CRON 1 do Divulga Vagas
 * consulta todas as palavras ativas do usuario).
 */
class AdicionarAgendamentoDivulgaVagasEmPalavrasChave extends Migration
{
    public function up(): void
    {
        // O horario do segundo provedor.
        $this->forge->addColumn('palavras_chave', [
            'proxima_execucao_divulgavagas' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ultima_execucao_divulgavagas' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        // Remove as colunas do segundo provedor.
        $this->db->query('ALTER TABLE `palavras_chave` DROP COLUMN `proxima_execucao_divulgavagas`');
        $this->db->query('ALTER TABLE `palavras_chave` DROP COLUMN `ultima_execucao_divulgavagas`');
    }
}
