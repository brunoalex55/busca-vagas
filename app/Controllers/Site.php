<?php

namespace App\Controllers;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: SITE PUBLICO
 * --------------------------------------------------------------------------
 *
 * Responde pelas paginas institucionais e de marketing:
 * inicio, recursos, como funciona, precos, contato e sobre.
 *
 * Nenhuma dessas rotas usa token CSRF. O formulario de contato e
 * enviado por POST e validado integralmente no servidor.
 */
class Site extends BaseController
{
    /**
     * Pagina inicial (landing page).
     */
    public function inicio(): string
    {
        // SEO especifico da home.
        $seo = [
            'titulo'         => 'Busca de emprego automatica | Candidate-se sem esforco',
            'descricao'      => 'O sistema busca vagas no Google Jobs e envia suas candidaturas por e-mail automaticamente, com curriculo em PDF. Comece gratis.',
            'palavras_chave' => 'busca de emprego automatica, candidatura automatica, encontrar emprego mais rapido, sistema de vagas, Google Jobs, vagas por e-mail',
            'tipo'           => 'website',
        ];

        // Estrutura Schema.org da organizacao e do site.
        $seo['schema'] = $this->schemaOrganizacao();

        return view('site/inicio', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Pagina detalhada de recursos.
     */
    public function recursos(): string
    {
        $seo = [
            'titulo'         => 'Recursos | Busca de vagas e candidatura automatica',
            'descricao'      => 'Veja todos os recursos: busca no Google Jobs, envio automatico de candidaturas, extracao de e-mails, curriculo em PDF e painel completo.',
            'palavras_chave' => 'recursos, busca de vagas, candidatura automatica, extracao de email, curriculo em PDF',
            'tipo'           => 'website',
        ];

        return view('site/recursos', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Pagina "Como funciona", com o passo a passo.
     */
    public function comoFunciona(): string
    {
        $seo = [
            'titulo'         => 'Como funciona | Busca de emprego em piloto automatico',
            'descricao'      => 'Em 3 passos: cadastre suas palavras-chave e cidades, configure seu curriculo e e-mail, e deixe o sistema enviar as candidaturas por voce.',
            'palavras_chave' => 'como funciona, busca de emprego automatica, candidatura automatica, passo a passo',
            'tipo'           => 'website',
        ];

        return view('site/como_funciona', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Pagina de planos e precos.
     */
    public function precos(): string
    {
        $seo = [
            'titulo'         => 'Precos | Planos sem fidelidade para buscar emprego',
            'descricao'      => 'Planos claros e sem fidelidade. Escolha o ideal para sua busca e cancele quando quiser. Comece gratis e faca upgrade quando precisar.',
            'palavras_chave' => 'precos, planos, assinatura, sem fidelidade, buscar emprego',
            'tipo'           => 'product',
        ];

        // Schema.org de produto com as ofertas.
        $seo['schema'] = $this->schemaProduto();

        return view('site/precos', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Pagina com o formulario de contato.
     */
    public function contato(): string
    {
        $seo = [
            'titulo'         => 'Contato | Fale com o time do BuscaVagas PRO',
            'descricao'      => 'Ficou com duvida sobre a busca automatica de vagas? Fale com nosso time pelo formulario ou pelo WhatsApp.',
            'palavras_chave' => 'contato, suporte, duvidas, busca de emprego',
            'tipo'           => 'website',
        ];

        return view('site/contato', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Processa o formulario de contato (SOMENTE POST).
     *
     * Nesta fase a mensagem e validada e confirmada ao visitante.
     * O envio por e-mail depende da conta pessoal de cada usuario e
     * nunca de uma conta global, portanto nao e disparado aqui.
     */
    public function enviarContato()
    {
        // Regras de validacao executadas no servidor.
        $regras = [
            'nome'     => 'required|min_length[3]|max_length[150]',
            'email'    => 'required|valid_email|max_length[180]',
            'mensagem' => 'required|min_length[10]|max_length[2000]',
        ];

        // Mensagens em portugues.
        $mensagens = [
            'nome' => [
                'required'   => 'Informe seu nome.',
                'min_length' => 'O nome deve ter pelo menos 3 caracteres.',
            ],
            'email' => [
                'required'    => 'Informe seu e-mail.',
                'valid_email' => 'Informe um e-mail valido.',
            ],
            'mensagem' => [
                'required'   => 'Escreva sua mensagem.',
                'min_length' => 'A mensagem deve ter pelo menos 10 caracteres.',
            ],
        ];

        // Se a validacao falhar, volta com os erros e os dados digitados.
        if (! $this->validate($regras, $mensagens)) {
            return redirect()->back()
                ->withInput()
                ->with('erros', $this->validator->getErrors());
        }

        // Confirma o recebimento ao visitante.
        return redirect()->to(site_url('contato'))
            ->with('sucesso', 'Mensagem recebida! Nosso time responde em ate 1 dia util.');
    }

    /**
     * Pagina institucional "Sobre".
     */
    public function sobre(): string
    {
        $seo = [
            'titulo'         => 'Sobre | BuscaVagas PRO',
            'descricao'      => 'Conheca a historia e a missao do BuscaVagas PRO: tecnologia para devolver o seu tempo e acelerar a busca por emprego.',
            'palavras_chave' => 'sobre, empresa, missao, busca de emprego',
            'tipo'           => 'website',
        ];

        return view('site/sobre', $this->dadosComuns + [
            'seo' => $seo,
        ]);
    }

    /**
     * Monta o JSON-LD de Organization + WebSite.
     *
     * @return array<string, mixed>
     */
    private function schemaOrganizacao(): array
    {
        // Dados do site configurados pelo admin.
        $site = configuracao_site();

        return [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'       => 'Organization',
                    '@id'         => site_url('/') . '#organizacao',
                    'name'        => texto_ou($site, 'nome_site'),
                    'url'         => site_url('/'),
                    'description' => texto_ou($site, 'descricao_site'),
                    'email'       => texto_ou($site, 'email_contato'),
                    'telephone'   => texto_ou($site, 'telefone_contato'),
                ],
                [
                    '@type'     => 'WebSite',
                    '@id'       => site_url('/') . '#site',
                    'url'       => site_url('/'),
                    'name'      => texto_ou($site, 'nome_site'),
                    'publisher' => ['@id' => site_url('/') . '#organizacao'],
                ],
            ],
        ];
    }

    /**
     * Monta o JSON-LD de produto com as ofertas dos planos.
     *
     * @return array<string, mixed>
     */
    private function schemaProduto(): array
    {
        $site = configuracao_site();

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => texto_ou($site, 'nome_site', 'BuscaVagas PRO'),
            'description' => texto_ou($site, 'descricao_site'),
            'brand'       => [
                '@type' => 'Brand',
                'name'  => texto_ou($site, 'nome_site', 'BuscaVagas PRO'),
            ],
            'offers' => [
                [
                    '@type'         => 'Offer',
                    'name'          => 'Basico',
                    'price'         => '29.90',
                    'priceCurrency' => 'BRL',
                    'availability'  => 'https://schema.org/InStock',
                ],
                [
                    '@type'         => 'Offer',
                    'name'          => 'Profissional',
                    'price'         => '49.90',
                    'priceCurrency' => 'BRL',
                    'availability'  => 'https://schema.org/InStock',
                ],
                [
                    '@type'         => 'Offer',
                    'name'          => 'Empresa',
                    'price'         => '99.90',
                    'priceCurrency' => 'BRL',
                    'availability'  => 'https://schema.org/InStock',
                ],
            ],
        ];
    }
}
