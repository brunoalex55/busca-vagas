<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ROBOTS
 * --------------------------------------------------------------------------
 *
 * Gera o robots.txt dinamicamente.
 *
 * IMPORTANTE: o arquivo public/robots.txt foi removido para que esta
 * rota seja alcancada. Se o arquivo estatico existisse, o servidor web
 * o entregaria antes de chegar ao CodeIgniter.
 */
class Robots extends BaseController
{
    /**
     * Devolve o conteudo do robots.txt.
     */
    public function index(): ResponseInterface
    {
        // Bloqueia as areas privadas e aponta o sitemap.
        $conteudo = implode(PHP_EOL, [
            'User-agent: *',
            'Allow: /',
            '',
            '# Areas privadas',
            'Disallow: /painel',
            'Disallow: /admin',
            'Disallow: /aguardando',
            'Disallow: /login',
            'Disallow: /logout',
            'Disallow: /cron',
            '',
            '# Sitemap',
            'Sitemap: ' . site_url('sitemap.xml'),
            '',
        ]);

        // Devolve como texto puro.
        return $this->response
            ->setContentType('text/plain; charset=UTF-8')
            ->setBody($conteudo);
    }
}
