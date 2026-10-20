<?php

namespace App\Controllers\Admin;

use App\Models\SolicitacaoLiberacaoModel;
use App\Models\UsuarioModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - SOLICITACOES DE LIBERACAO
 * --------------------------------------------------------------------------
 *
 * O usuario pendente avisa que pagou; o admin analisa e aprova (libera a
 * conta) ou rejeita (mantem pendente para o usuario corrigir/enviar de novo).
 */
class AdminSolicitacoes extends AdminBase
{
    /** Model das solicitacoes. */
    private SolicitacaoLiberacaoModel $solicitacoes;

    /** Model dos usuarios. */
    private UsuarioModel $usuarios;

    /**
     * Carrega os models.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->solicitacoes = model(SolicitacaoLiberacaoModel::class);
        $this->usuarios     = model(UsuarioModel::class);
    }

    /**
     * Lista as solicitacoes com abas por status e paginacao.
     */
    public function index(): string
    {
        // Filtros da consulta.
        $filtros = [
            'status' => (string) $this->request->getGet('status'),
            'busca'  => trim((string) $this->request->getGet('busca')),
        ];

        // Paginacao manual.
        $porPagina = $this->porPagina(20);
        $pagina    = $this->paginaAtual();
        $offset    = ($pagina - 1) * $porPagina;

        // Total e registros.
        $total        = $this->solicitacoes->contarComUsuario($filtros);
        $registros    = $this->solicitacoes->listarComUsuario($filtros, $porPagina, $offset);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        return view('admin/solicitacoes', $this->dadosAdmin('Solicitacoes', [
            'solicitacoes' => $registros,
            'contagens'    => $this->solicitacoes->contarPorStatus(),
            'filtros'      => $filtros,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'total'        => $total,
            'rotaBase'     => 'admin/solicitacoes',
        ]));
    }

    /**
     * Aprova a solicitacao e libera o acesso do usuario.
     *
     * @param int|string $id Identificador da solicitacao.
     */
    public function aprovar($id): RedirectResponse
    {
        // Solicitacao alvo.
        $solicitacao = $this->carregarSolicitacao($id);

        // Usuario dono da solicitacao.
        $usuario = $this->usuarios->find((int) $solicitacao['usuario_id']);

        if ($usuario === null) {
            return $this->voltar('Usuario da solicitacao nao existe mais.', true);
        }

        // Libera a conta e marca esta solicitacao (e as pendentes) como aprovada.
        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'ativo', 1);
        $this->solicitacoes->responderPendentesDoUsuario((int) $usuario['id'], 'aprovada');

        return $this->voltar('Solicitacao aprovada. Usuario liberado.');
    }

    /**
     * Rejeita a solicitacao (o usuario continua pendente).
     *
     * @param int|string $id Identificador da solicitacao.
     */
    public function rejeitar($id): RedirectResponse
    {
        $solicitacao = $this->carregarSolicitacao($id);

        // Marca apenas esta solicitacao como rejeitada.
        $this->solicitacoes->responder((int) $solicitacao['id'], 'rejeitada');

        return $this->voltar('Solicitacao rejeitada. O usuario pode enviar novamente.');
    }

    // =========================================================================
    // APOIO INTERNO
    // =========================================================================

    /**
     * Carrega uma solicitacao garantindo que ela existe.
     *
     * @param int|string $id Identificador da rota.
     *
     * @return array<string, mixed>
     */
    private function carregarSolicitacao($id): array
    {
        $solicitacao = $this->solicitacoes->buscar($this->idDaRota($id));

        if ($solicitacao === null) {
            throw PageNotFoundException::forPageNotFound('Solicitacao nao encontrada.');
        }

        return $solicitacao;
    }

    /**
     * Redireciona para a lista de solicitacoes com mensagem de flash.
     */
    private function voltar(string $mensagem, bool $erro = false): RedirectResponse
    {
        // Chave do flash conforme o tipo de mensagem.
        $chave = 'sucesso';

        if ($erro) {
            $chave = 'erro';
        }

        return redirect()
            ->to(site_url('admin/solicitacoes'))
            ->with($chave, $mensagem);
    }
}
