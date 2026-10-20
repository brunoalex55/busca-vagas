<?php

namespace App\Controllers\Admin;

use App\Libraries\DistribuicaoConsultas;
use App\Models\ChaveSerpApiModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - DISTRIBUICAO DE CONSULTAS
 * --------------------------------------------------------------------------
 *
 * Mostra como o saldo das chaves SerpApi e dividido entre os usuarios
 * ativos (regra de peso por combinacao, teto e piso) e permite alinhar
 * o contador das chaves com a virada do ciclo mensal.
 */
class AdminDistribuicao extends AdminBase
{
    /**
     * Painel da distribuicao atual.
     */
    public function index(): string
    {
        // Distribuicao calculada a partir das chaves.
        $distribuicao = (new DistribuicaoConsultas())->calcularTodas();

        // Dados dos usuarios ativos (nome e uso do mes).
        $usuarios = model(UsuarioModel::class)->listarParaDistribuicao();

        return view('admin/distribuicao', $this->dadosAdmin('Distribuicao', [
            'distribuicao' => $distribuicao,
            'usuarios'     => $usuarios,
        ]));
    }

    /**
     * Reinicia o ciclo mensal das chaves (zera o mes anterior e religa as
     * esgotadas). Util quando o ciclo do provedor nao bate com o mes civil.
     */
    public function recalcular(): RedirectResponse
    {
        // Reinicia o ciclo das chaves para o mes corrente.
        $religadas = model(ChaveSerpApiModel::class)->reiniciarMesSeNecessario(date('Y-m'));

        return redirect()
            ->to(site_url('admin/distribuicao'))
            ->with('sucesso', 'Ciclo das chaves reiniciado. Chaves religadas: ' . $religadas . '.');
    }
}
