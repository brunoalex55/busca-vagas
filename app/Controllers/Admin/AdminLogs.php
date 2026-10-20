<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers\Admin;

use App\Models\EmailEnviadoModel;
use App\Models\LogConsultaModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - LOGS
 * --------------------------------------------------------------------------
 *
 * Auditoria do sistema, em duas abas:
 *   - consultas ao provedor de vagas (logs_consulta);
 *   - candidaturas enviadas (emails_enviados).
 *
 * Ambas com filtros (busca, status, origem) e paginacao manual.
 */
class AdminLogs extends AdminBase
{
    /**
     * Painel de logs.
     */
    public function index(): string
    {
        // Aba atual.
        // Aba atual (apenas valores conhecidos).
        $aba = 'consultas';

        if ($this->request->getGet('aba') === 'envios') {
            $aba = 'envios';
        }

        // Filtros comuns as duas abas.
        $filtros = [
            'busca'    => trim((string) $this->request->getGet('busca')),
            'status'   => (string) $this->request->getGet('status'),
            'origem'   => (string) $this->request->getGet('origem'),
            'provedor' => (string) $this->request->getGet('provedor'),
        ];

        // Paginacao manual.
        $porPagina = $this->porPagina(25);
        $pagina    = $this->paginaAtual();
        $offset    = ($pagina - 1) * $porPagina;

        // Modelos de auditoria.
        $logs   = model(LogConsultaModel::class);
        $emails = model(EmailEnviadoModel::class);

        // Dados da aba ativa.
        if ($aba === 'envios') {
            $registros = $emails->listarGlobal($filtros, $porPagina, $offset);
            $total     = $emails->contarGlobal($filtros);
            $resumo    = $emails->resumoGlobal();
        } else {
            $registros = $logs->listarGlobal($filtros, $porPagina, $offset);
            $total     = $logs->contarGlobal($filtros);
            $resumo    = $logs->resumoGlobal();
        }

        return view('admin/logs', $this->dadosAdmin('Logs', [
            'aba'          => $aba,
            'registros'    => $registros,
            'filtros'      => $filtros,
            'resumo'       => $resumo,
            'totais'       => [
                'consultas' => $logs->resumoGlobal()['total'],
                'envios'    => $emails->resumoGlobal()['total'],
            ],
            'pagina'       => $pagina,
            'totalPaginas' => max(1, (int) ceil($total / $porPagina)),
            'total'        => $total,
            'rotaBase'     => 'admin/logs',
        ]));
    }
}
