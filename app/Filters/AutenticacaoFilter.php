<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * FILTRO: AUTENTICACAO
 * --------------------------------------------------------------------------
 *
 * Exige que exista uma sessao valida com `usuario_id`.
 *
 * Nao ha token CSRF em nenhum lugar: a unica fonte de verdade e a
 * sessao nativa do CodeIgniter 4.
 *
 * Comportamento:
 *   - Sem sessao valida -> 302 para /login
 */
class AutenticacaoFilter implements FilterInterface
{
    /**
     * Executado ANTES do controller.
     *
     * @param mixed $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Se nao existe usuario_id na sessao, o visitante nao esta
        // autenticado e vai para a tela de login.
        if (! session()->has('usuario_id')) {
            // Codigo 302 explicito, independentemente do metodo HTTP.
            return redirect()->to(site_url('login'), 302);
        }

        // Sessao valida: deixa a requisicao seguir.
        return null;
    }

    /**
     * Executado DEPOIS do controller.
     *
     * @param mixed $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nada a fazer depois da resposta.
        return null;
    }
}
