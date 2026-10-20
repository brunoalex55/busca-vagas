<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: SITEMAP
 * --------------------------------------------------------------------------
 *
 * Gera o sitemap.xml dinamicamente a partir das rotas publicas.
 * A URL base vem da configuracao da aplicacao (app.baseURL).
 */
class Sitemap extends BaseController
{
    /**
     * Devolve o XML do sitemap.
     */
    public function index(): ResponseInterface
    {
        // Paginas publicas, com prioridade e frequencia de atualizacao.
        $paginas = [
            ['rota' => '/',              'prioridade' => '1.0', 'frequencia' => 'weekly'],
            ['rota' => 'recursos',       'prioridade' => '0.9', 'frequencia' => 'monthly'],
            ['rota' => 'como-funciona',  'prioridade' => '0.9', 'frequencia' => 'monthly'],
            ['rota' => 'precos',         'prioridade' => '0.9', 'frequencia' => 'monthly'],
            ['rota' => 'sobre',          'prioridade' => '0.7', 'frequencia' => 'yearly'],
            ['rota' => 'contato',        'prioridade' => '0.7', 'frequencia' => 'yearly'],
            ['rota' => 'registro',       'prioridade' => '0.8', 'frequencia' => 'monthly'],
            ['rota' => 'login',          'prioridade' => '0.6', 'frequencia' => 'yearly'],
        ];

        // Data da ultima atualizacao (hoje).
        $ultimaModificacao = date('Y-m-d');

        // Cabecalho XML.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;

        // Elemento raiz do sitemap.
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        // Percorre as paginas publicas.
        foreach ($paginas as $pagina) {
            // Remove a barra inicial para montar a URL com site_url().
            $rota = ltrim($pagina['rota'], '/');

            // URL absoluta da pagina.
            $url = site_url($rota);

            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $ultimaModificacao . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>' . $pagina['frequencia'] . '</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . $pagina['prioridade'] . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        // Fecha o elemento raiz.
        $xml .= '</urlset>' . PHP_EOL;

        // Devolve com o tipo de conteudo correto.
        return $this->response
            ->setContentType('application/xml; charset=UTF-8')
            ->setBody($xml);
    }
}
