<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * --------------------------------------------------------------------------
 * SEEDER: PLANOS PADRAO
 * --------------------------------------------------------------------------
 *
 * Cria os tres planos que o site publico promete:
 *
 *   - basico ....... 2 palavras-chave, 5 cidades por termo, 10 envios
 *                    por dia, envio automatico manual, sem envio em
 *                    massa, 1 usuario, suporte por e-mail;
 *   - profissional . palavras-chave e cidades ilimitadas, 20 envios por
 *                    dia, envio automatico ligado, envio em massa,
 *                    1 usuario, suporte por e-mail (o mais popular);
 *   - empresa ...... palavras-chave e cidades ilimitadas, 50 envios por
 *                    dia por usuario, envio automatico ligado, envio
 *                    em massa, ate 5 usuarios, suporte prioritario.
 *
 * O plano "basico" e o FALLBACK do sistema: nunca pode ser excluido
 * (so desativado) porque e o plano de quem ainda nao tem nenhum.
 */
class PlanoSeeder extends Seeder
{
    public function run(): void
    {
        // Os planos, na ordem em que aparecem no site.
        $planos = [
            [
                'slug'                 => 'basico',
                'nome'                 => 'Básico',
                'descricao'            => 'Para quem está começando a busca.',
                'preco_mensal'         => 29.90,
                'max_palavras_chave'   => 2,
                'max_cidades_por_termo' => 5,
                'limite_envios_dia'    => 10,
                'envio_automatico'     => 0,
                'envio_em_massa'       => 0,
                'max_usuarios'         => 1,
                'suporte'              => 'email',
                'destaque'             => 0,
                'ordem'                => 1,
                'ativo'                => 1,
            ],
            [
                'slug'                 => 'profissional',
                'nome'                 => 'Profissional',
                'descricao'            => 'Para quem quer resultado rápido.',
                'preco_mensal'         => 49.90,
                // NULL = ilimitado.
                'max_palavras_chave'   => null,
                'max_cidades_por_termo' => null,
                'limite_envios_dia'    => 20,
                'envio_automatico'     => 1,
                'envio_em_massa'       => 1,
                'max_usuarios'         => 1,
                'suporte'              => 'email',
                'destaque'             => 1,
                'ordem'                => 2,
                'ativo'                => 1,
            ],
            [
                'slug'                 => 'empresa',
                'nome'                 => 'Empresa',
                'descricao'            => 'Para equipes de recrutamento.',
                'preco_mensal'         => 99.90,
                // NULL = ilimitado.
                'max_palavras_chave'   => null,
                'max_cidades_por_termo' => null,
                'limite_envios_dia'    => 50,
                'envio_automatico'     => 1,
                'envio_em_massa'       => 1,
                'max_usuarios'         => 5,
                'suporte'              => 'prioritario',
                'destaque'             => 0,
                'ordem'                => 3,
                'ativo'                => 1,
            ],
        ];

        foreach ($planos as $plano) {
            // Verifica se o plano ja existe (o seeder pode rodar varias
            // vezes sem duplicar nada).
            $existente = $this->db->table('planos')
                ->where('slug', $plano['slug'])
                ->get()
                ->getRowArray();

            // Ja existe: atualiza os dados e nao duplica.
            if ($existente !== null && $existente !== []) {
                $this->db->table('planos')
                    ->where('slug', $plano['slug'])
                    ->update($plano);

                continue;
            }

            $this->db->table('planos')->insert($plano);
        }
    }
}
