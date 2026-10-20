<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * --------------------------------------------------------------------------
 * SEEDER: CONFIGURACAO INICIAL DO SITE
 * --------------------------------------------------------------------------
 *
 * Popula as tabelas de registro unico usadas pelo site publico:
 *
 *   - configuracao_site      -> nome, slogan, SEO, contatos, redes
 *   - configuracao_pagamento -> chave PIX e instrucoes de liberacao
 *
 * Todos esses valores sao editaveis pelo admin na Fase 5.
 */
class ConfiguracaoSiteSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------------
        // CONFIGURACAO DO SITE PUBLICO
        // -----------------------------------------------------------------

        // So insere se a tabela estiver vazia (registro unico).
        if ($this->db->table('configuracao_site')->countAllResults() === 0) {
            $this->db->table('configuracao_site')->insert([
                // Identidade da marca.
                'nome_site'            => 'BuscaVagas PRO',
                'slogan'               => 'Sua busca de emprego em piloto automatico',
                'descricao_site'       => 'Encontre vagas no Google Jobs e envie suas candidaturas automaticamente, com curriculo em PDF anexado. Economize horas por semana.',
                'palavras_chave_seo'   => 'busca de emprego automatica, candidatura automatica, encontrar emprego mais rapido, sistema de vagas, Google Jobs, vagas por e-mail, curriculo em PDF automatico',

                // Contatos publicos.
                'email_contato'        => 'contato@buscavagaspro.com.br',
                'telefone_contato'     => '(11) 99999-0000',
                'whatsapp_contato'     => '5511999990000',
                'endereco'             => 'Sao Paulo - SP',

                // Imagens (vazio = o layout usa o logo em SVG inline).
                'logo'                 => null,
                'favicon'              => null,
                'imagem_og'            => null,

                // Redes sociais.
                'link_facebook'        => null,
                'link_instagram'       => null,
                'link_twitter'         => null,
                'link_linkedin'        => null,
                'link_youtube'         => null,
                'link_tiktok'          => null,
                'link_github'          => null,
                'link_site_pessoal'    => null,

                // Textos institucionais.
                'texto_rodape'         => 'BuscaVagas PRO - Tecnologia a favor da sua carreira. Todos os direitos reservados.',
                'texto_sobre'          => 'O BuscaVagas PRO nasceu para resolver um problema simples e cansativo: procurar emprego todos os dias, em varios sites, preenchendo os mesmos formularios. Nos automatizamos a busca no Google Jobs e o envio das candidaturas por e-mail, respeitando limites seguros para proteger a sua conta Gmail. Voce define as palavras-chave e as cidades; o sistema encontra as vagas e envia o seu curriculo.',

                // Integracoes opcionais.
                'google_analytics_id'          => null,
                'google_search_console_code'   => null,

                // Datas de controle.
                'criado_em'     => date('Y-m-d H:i:s'),
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);
        }

        // -----------------------------------------------------------------
        // CONFIGURACAO DE PAGAMENTO (PIX)
        // -----------------------------------------------------------------

        // Tambem e um registro unico.
        if ($this->db->table('configuracao_pagamento')->countAllResults() === 0) {
            $this->db->table('configuracao_pagamento')->insert([
                // Chave PIX de exemplo. O admin deve trocar pela real.
                'chave_pix'        => 'pagamentos@buscavagaspro.com.br',

                // Valor mensal de exemplo.
                'valor_mensal'     => '49.90',

                // Instrucoes exibidas ao usuario pendente.
                'texto_instrucoes' => 'Faca o PIX para a chave acima no valor de R$ 49,90 e clique em "Informar pagamento". A liberacao e feita manualmente pelo nosso time em ate 24 horas uteis.',

                // Datas de controle.
                'criado_em'        => date('Y-m-d H:i:s'),
                'atualizado_em'    => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
