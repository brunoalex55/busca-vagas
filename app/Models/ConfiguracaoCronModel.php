<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: CONFIGURACAO DO CRON
 * --------------------------------------------------------------------------
 *
 * Registro unico com o modo de agendamento, a chave da API do cron-job.org,
 * o contador diario de requisicoes dessa API e o limite de execucoes
 * manuais do usuario comum.
 *
 * Como configuracao_site e configuracao_pagamento, esta tabela tem um
 * unico registro: leitura por obter() e gravacao por salvar().
 */
class ConfiguracaoCronModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'configuracao_cron';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'modo',
        'api_key_cronjob',
        'requisicoes_api_hoje',
        'data_reset_requisicoes',
        'limite_execucoes_manuais_usuario',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Retorna a configuracao unica do cron.
     *
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        $registro = $this->orderBy('id', 'ASC')->first();

        // Fallback para quando a migration ainda nao rodou.
        if ($registro === null) {
            return [
                'id'                               => null,
                'modo'                             => 'spark_manual',
                'api_key_cronjob'                  => null,
                'requisicoes_api_hoje'             => 0,
                'data_reset_requisicoes'           => null,
                'limite_execucoes_manuais_usuario' => 3,
            ];
        }

        return $registro;
    }

    /**
     * Cria ou atualiza o registro unico de configuracao do cron.
     *
     * @param array<string, mixed> $dados Campos que serao gravados.
     */
    public function salvar(array $dados): void
    {
        // Registro atual (se existir).
        $registro = $this->db->table($this->table)->orderBy('id', 'ASC')->get()->getRowArray();

        // Primeiro salvamento: cria o registro unico.
        if ($registro === null) {
            $this->insert($dados);

            return;
        }

        // Atualiza apenas os campos enviados.
        $this->update((int) $registro['id'], $dados);
    }
}
