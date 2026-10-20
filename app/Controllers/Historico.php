<?php

namespace App\Controllers;

use App\Models\EmailEnviadoModel;
use App\Models\LogConsultaModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: HISTORICO DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Reune duas trilhas de auditoria em uma tela com abas:
 *   - consultas feitas ao provedor de vagas;
 *   - candidaturas enviadas por e-mail.
 */
class Historico extends PainelBase
{
    /** Itens por pagina do historico. */
    private const POR_PAGINA = 15;

    /** Model de consultas. */
    private LogConsultaModel $logs;

    /** Model de envios. */
    private EmailEnviadoModel $emails;

    /**
     * Carrega os models depois da inicializacao base.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->logs   = model(LogConsultaModel::class);
        $this->emails = model(EmailEnviadoModel::class);
    }

    /**
     * Exibe o historico conforme a aba escolhida.
     */
    public function index(): string
    {
        // Aba atual (apenas valores conhecidos).
        $aba = 'consultas';

        if ($this->request->getGet('aba') === 'envios') {
            $aba = 'envios';
        }

        // Paginacao manual.
        $porPagina = self::POR_PAGINA;
        $pagina    = $this->paginaAtual();

        // Total de registros da aba atual.
        $total = $this->logs->contarDoUsuario($this->usuarioId);

        if ($aba === 'envios') {
            $total = $this->emails->contarDoUsuario($this->usuarioId);
        }

        // Total de paginas.
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        // Nao permite pagina fora do intervalo.
        $pagina = min($pagina, $totalPaginas);

        // Deslocamento.
        $offset = ($pagina - 1) * $porPagina;

        // Registros da pagina.
        $registros = $this->logs->listarPaginado($this->usuarioId, $porPagina, $offset);

        if ($aba === 'envios') {
            $registros = $this->emails->listarPaginado($this->usuarioId, $porPagina, $offset);
        }

        // Contagens usadas nas abas.
        $totais = [
            'consultas' => $this->logs->contarDoUsuario($this->usuarioId),
            'envios'    => $this->emails->contarDoUsuario($this->usuarioId),
            'erros'     => $this->logs->contarComErro($this->usuarioId),
        ];

        return view('painel/historico', $this->dadosPainel('Historico', [
            'aba'          => $aba,
            'registros'    => $registros,
            'totais'       => $totais,
            'total'        => $total,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'rotaBase'     => 'painel/historico',
            'filtros'      => ['aba' => $aba],
        ]));
    }
}
