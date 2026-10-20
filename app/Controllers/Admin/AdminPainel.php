<?php

namespace App\Controllers\Admin;

use App\Libraries\DistribuicaoConsultas;
use App\Models\ChaveSerpApiModel;
use App\Models\ConfiguracaoModel;
use App\Models\EmailEnviadoModel;
use App\Models\LogConsultaModel;
use App\Models\SolicitacaoLiberacaoModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - VISAO GERAL
 * --------------------------------------------------------------------------
 *
 * Dashboard administrativo com os numeros reais do sistema: usuarios,
 * chaves, vagas, candidaturas, consultas, solicitacoes e crons.
 *
 * Substitui a tela provisoria da Fase 1.
 */
class AdminPainel extends AdminBase
{
    /**
     * Dashboard principal do administrador.
     */
    public function index(): string
    {
        // Models usados no resumo.
        $usuarios      = model(UsuarioModel::class);
        $configuracoes = model(ConfiguracaoModel::class);
        $chaves        = model(ChaveSerpApiModel::class);
        $vagas         = model(VagaModel::class);
        $emails        = model(EmailEnviadoModel::class);
        $logs          = model(LogConsultaModel::class);
        $solicitacoes  = model(SolicitacaoLiberacaoModel::class);

        // Distribuicao de consultas calculada a partir das chaves.
        $distribuicao = (new DistribuicaoConsultas())->calcularTodas();

        return view('admin/visao_geral', $this->dadosAdmin('Visao geral', [
            'usuarios'        => $usuarios->estatisticas(),
            'usuariosRecentes' => $usuarios->listarRecentes(5),
            'crons'           => $usuarios->resumoCrons(),
            'comCredencial'   => $configuracoes->contarComAppPassword(),
            'chaves'          => $chaves->listarTodas(),
            'saldoChaves'     => $chaves->totalRestante(),
            'vagasTotal'      => $vagas->contarGlobal(),
            'vagasFila'       => $vagas->contarFilaGlobal(),
            'candidaturasFeitas' => $vagas->contarCandidaturasRealizadasGlobal(),
            'candidaturasFeitasLista' => $vagas->listarCandidaturasRealizadasGlobal(8),
            'envios'          => $emails->resumoGlobal(),
            'consultas'       => $logs->resumoGlobal(),
            'solicitacoes'    => $solicitacoes->contarPorStatus(),
            'distribuicao'    => $distribuicao,
        ]));
    }
}
