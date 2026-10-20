<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * FILTRO: ADMIN
 * --------------------------------------------------------------------------
 *
 * Exige que o usuario autenticado tenha perfil "admin".
 *
 * Comportamento:
 *   - Autenticado, mas sem perfil admin -> 302 para /painel
 *
 * Este filtro sempre roda DEPOIS do filtro de autenticacao, portanto
 * a sessao ja foi validada.
 */
class AdminFilter implements FilterInterface
{
    /**
     * Executado ANTES do controller.
     *
     * @param mixed $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Le o perfil gravado na sessao no momento do login.
        $perfil = session()->get('perfil');

        // Se nao for admin, devolve o usuario ao painel comum.
        if ($perfil !== 'admin') {
            // Codigo 302 explicito, independentemente do metodo HTTP.
            return redirect()->to(site_url('painel'), 302);
        }

        // Perfil admin confirmado.
        return null;
    }

    /**
     * Executado DEPOIS do controller.
     *
     * @param mixed $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
