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
 * MODEL: CONFIGURACAO DE PAGAMENTO
 * --------------------------------------------------------------------------
 *
 * Registro unico com a chave PIX, o valor mensal e as instrucoes
 * exibidas na tela de "Aguardando aprovacao".
 */
class ConfiguracaoPagamentoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'configuracao_pagamento';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'chave_pix',
        'valor_mensal',
        'texto_instrucoes',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Retorna a configuracao unica de pagamento.
     *
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        $registro = $this->orderBy('id', 'ASC')->first();

        // Fallback para quando o seeder ainda nao rodou.
        if ($registro === null) {
            return [
                'chave_pix'        => '',
                'valor_mensal'     => '0.00',
                'texto_instrucoes' => 'Configuracao de pagamento ainda nao cadastrada.',
            ];
        }

        return $registro;
    }

    /**
     * Cria ou atualiza o registro unico de configuracao de pagamento.
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
