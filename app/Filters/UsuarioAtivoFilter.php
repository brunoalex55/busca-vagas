<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * FILTRO: USUARIO ATIVO
 * --------------------------------------------------------------------------
 *
 * Exige que o status do usuario autenticado seja "ativo".
 *
 * Comportamento:
 *   - Status diferente de ativo (pendente ou bloqueado) -> 302 para
 *     /aguardando
 *
 * A tela /aguardando NAO usa este filtro (apenas o de autenticacao),
 * para que o usuario pendente consiga ver as instrucoes de pagamento.
 */
class UsuarioAtivoFilter implements FilterInterface
{
    /**
     * Executado ANTES do controller.
     *
     * @param mixed $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Le o status gravado na sessao no momento do login.
        $status = session()->get('status');

        // Se nao estiver ativo, manda para a tela de espera.
        if ($status !== 'ativo') {
            // Codigo 302 explicito, independentemente do metodo HTTP.
            return redirect()->to(site_url('aguardando'), 302);
        }

        // Usuario ativo: libera o acesso.
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
