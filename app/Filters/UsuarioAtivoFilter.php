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
        // O status vem do banco (e nao da sessao): quando o
        // administrador bloqueia a conta por falta de pagamento, o
        // bloqueio tem que valer na hora, mesmo com a sessao aberta.
        $usuarioId = (int) session()->get('usuario_id');
        $status = session()->get('status');

        if ($usuarioId > 0) {
            $conta = db_connect()->table('usuarios')
                ->select('status, ativo')
                ->where('id', $usuarioId)
                ->get()
                ->getRowArray();

            if ($conta !== null && $conta !== []) {
                $status = (string) $conta['status'];

                // A sessao passa a refletir a conta (o proximo pedido ja
                // usa o status novo).
                session()->set('status', $status);
            }
        }

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
