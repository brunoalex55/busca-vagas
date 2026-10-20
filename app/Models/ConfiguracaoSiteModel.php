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
 * MODEL: CONFIGURACAO DO SITE
 * --------------------------------------------------------------------------
 *
 * Acesso a tabela `configuracao_site`, que tem sempre um unico
 * registro (id = 1).
 *
 * O site publico le esses dados em todas as paginas: nome, slogan,
 * SEO, contatos, redes sociais e textos institucionais.
 */
class ConfiguracaoSiteModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'configuracao_site';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos editaveis pelo admin.
    protected $allowedFields = [
        'nome_site',
        'slogan',
        'descricao_site',
        'palavras_chave_seo',
        'email_contato',
        'telefone_contato',
        'whatsapp_contato',
        'endereco',
        'logo',
        'favicon',
        'imagem_og',
        'link_facebook',
        'link_instagram',
        'link_twitter',
        'link_linkedin',
        'link_youtube',
        'link_tiktok',
        'link_github',
        'link_site_pessoal',
        'texto_rodape',
        'texto_sobre',
        'google_analytics_id',
        'google_search_console_code',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Retorna a configuracao unica do site.
     *
     * Se ainda nao existir registro (banco recem-criado), devolve um
     * array de valores padrao para o layout nao quebrar.
     *
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        // Busca o primeiro registro da tabela.
        $registro = $this->orderBy('id', 'ASC')->first();

        // Fallback seguro quando o seeder ainda nao rodou.
        if ($registro === null) {
            return [
                'nome_site'          => 'BuscaVagas PRO',
                'slogan'             => 'Sua busca de emprego em piloto automatico',
                'descricao_site'     => 'Encontre vagas e candidate-se automaticamente.',
                'palavras_chave_seo' => 'busca de emprego, vagas, candidatura automatica',
                'email_contato'      => '',
                'telefone_contato'   => '',
                'whatsapp_contato'   => '',
                'endereco'           => '',
                'logo'               => null,
                'favicon'            => null,
                'imagem_og'          => null,
                'texto_rodape'       => '',
                'texto_sobre'        => '',
            ];
        }

        // Retorna o registro encontrado.
        return $registro;
    }

    /**
     * Cria ou atualiza o registro unico de configuracao do site.
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
