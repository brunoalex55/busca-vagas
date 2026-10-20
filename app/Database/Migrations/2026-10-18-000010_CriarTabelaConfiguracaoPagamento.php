<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: TABELA `configuracao_pagamento`
 * --------------------------------------------------------------------------
 *
 * Dados de pagamento exibidos ao usuario pendente na tela de
 * "Aguardando aprovacao".
 *
 * A chave PIX e um texto simples, digitado pelo admin. O sistema nao
 * integra com nenhum gateway: o usuario paga e clica em
 * "Informar pagamento", e o admin libera manualmente.
 *
 * Assim como configuracao_site, esta tabela tem um unico registro.
 */
class CriarTabelaConfiguracaoPagamento extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Identificador do registro unico.
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            // Chave PIX em texto simples (CNPJ, e-mail, telefone, aleatoria).
            'chave_pix' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Valor mensal cobrado pela liberacao do acesso.
            'valor_mensal' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00',
                'null'       => false,
            ],

            // Instrucoes de pagamento exibidas ao usuario.
            'texto_instrucoes' => [
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
        $this->forge->createTable('configuracao_pagamento', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracao_pagamento', true);
    }
}
