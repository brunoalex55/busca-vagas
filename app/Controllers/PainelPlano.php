<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use App\Models\PlanoModel;
use App\Models\SolicitacaoPlanoModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: PAINEL - PEDIDO DE PLANO
 * --------------------------------------------------------------------------
 *
 * O usuario escolhe o plano que quer dentro do painel (upgrade ou
 * downgrade). O pedido NAO muda a conta na hora: ele vira uma
 * solicitacao e aparece na fila do administrador, que aprova (o plano
 * passa a valer) ou recusa (com a justificativa).
 *
 * REGRAS:
 *
 *   - toda escrita e POST (nada muda por GET);
 *   - o usuario so pode ter UM pedido pendente por vez (um novo
 *     substitui o anterior);
 *   - o plano pedido precisa existir e estar ativo;
 *   - o plano basico nunca sai: se o usuario pedir o basico, o pedido
 *     e aceito normalmente (e o plano de fallback).
 */
class PainelPlano extends PainelBase
{
    /**
     * Model dos pedidos de plano.
     *
     * @var SolicitacaoPlanoModel
     */
    private SolicitacaoPlanoModel $solicitacoes;

    /**
     * Model dos planos.
     *
     * @var PlanoModel
     */
    private PlanoModel $planos;

    /**
     * Carrega o que o controller precisa.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->solicitacoes = model(SolicitacaoPlanoModel::class);
        $this->planos = model(PlanoModel::class);
    }

    /**
     * O usuario pede um plano. SOMENTE POST.
     *
     * O pedido vai para a fila do administrador (nao muda o plano da
     * conta agora: quem libera e o admin).
     */
    public function solicitar(): RedirectResponse
    {
        // O plano pedido.
        $planoId = (int) $this->request->getPost('plano_id');

        // A observacao do usuario (opcional).
        $mensagem = trim((string) $this->request->getPost('mensagem'));

        if (mb_strlen($mensagem) > 1000) {
            $mensagem = mb_substr($mensagem, 0, 1000);
        }

        // O plano precisa existir e estar ativo.
        $plano = $this->planos->find($planoId);

        if ($plano === null || (int) valor_ou($plano, 'ativo', 0) !== 1) {
            return redirect()
                ->to(site_url('painel'))
                ->with('erro', 'Escolha um plano valido para solicitar.');
        }

        // Ja existe um pedido igual pendente: avisa (nao duplica).
        $pendente = $this->solicitacaoPendente();

        if ($pendente !== null && (int) valor_ou($pendente, 'plano_id', 0) === $planoId) {
            return redirect()
                ->to(site_url('painel'))
                ->with('erro', 'Voce ja enviou um pedido deste plano. Aguarde a resposta do administrador.');
        }

        // Grava o pedido (o antigo pendente e substituido).
        $this->solicitacoes->criar($this->usuarioId, $planoId, $mensagem);

        return redirect()
            ->to(site_url('painel'))
            ->with('sucesso', 'Pedido enviado! O administrador vai analisar e liberar o plano ' . texto_ou($plano, 'nome') . '.');
    }

    /**
     * O usuario cancela o pedido pendente. SOMENTE POST.
     */
    public function cancelar(): RedirectResponse
    {
        $pendente = $this->solicitacaoPendente();

        if ($pendente === null) {
            return redirect()
                ->to(site_url('painel'))
                ->with('erro', 'Voce nao tem nenhum pedido pendente.');
        }

        // O pedido pendente vira "recusada" (o historico fica).
        $this->solicitacoes->recusar((int) valor_ou($pendente, 'id', 0), (int) $this->usuarioId, 'Cancelado pelo proprio usuario.');

        return redirect()
            ->to(site_url('painel'))
            ->with('sucesso', 'Pedido cancelado.');
    }

    // =====================================================================
    // AJUDA
    // =====================================================================

    /**
     * O pedido pendente do usuario (null quando nao ha).
     *
     * @return array<string, mixed>|null
     */
    private function solicitacaoPendente(): ?array
    {
        $pendentes = $this->solicitacoes->listarDoUsuario($this->usuarioId, 5);

        foreach ($pendentes as $pedido) {
            if ((string) valor_ou($pedido, 'status', '') === 'pendente') {
                return $pedido;
            }
        }

        return null;
    }
}
