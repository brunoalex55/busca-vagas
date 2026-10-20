<?php

namespace App\Controllers;

use App\Models\SolicitacaoLiberacaoModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: AGUARDANDO APROVACAO
 * --------------------------------------------------------------------------
 *
 * Tela exibida ao usuario com status "pendente" ou "bloqueado".
 *
 * Mostra a chave PIX configurada pelo admin e permite que o usuario
 * avise que pagou (POST). O admin libera manualmente depois.
 */
class AguardandoAprovacao extends BaseController
{
    /**
     * Exibe a tela de espera.
     */
    public function index()
    {
        // Status atual do usuario logado.
        $status = $this->sessao->get('status');

        // Quem ja esta ativo nao precisa ver esta tela.
        if ($status === 'ativo') {
            if ($this->sessao->get('perfil') === 'admin') {
                return redirect()->to(site_url('admin'));
            }

            return redirect()->to(site_url('painel'));
        }

        // Dados de pagamento configurados pelo admin.
        $pagamento = configuracao_pagamento();

        /** @var SolicitacaoLiberacaoModel $solicitacoes */
        $solicitacoes = model(SolicitacaoLiberacaoModel::class);

        // Verifica se ja existe uma solicitacao pendente deste usuario.
        $temPendente = $solicitacoes->temPendente((int) $this->sessao->get('usuario_id'));

        return view('aprovacao/aguardando', $this->dadosComuns + [
            'seo' => [
                'titulo'         => 'Aguardando aprovacao | BuscaVagas PRO',
                'descricao'      => 'Confira as instrucoes para liberar o acesso a sua conta.',
                'palavras_chave' => 'aguardando aprovacao',
                'tipo'           => 'website',
            ],
            'pagamento'   => $pagamento,
            'temPendente' => $temPendente,
        ]);
    }

    /**
     * Registra o aviso de pagamento do usuario (SOMENTE POST).
     */
    public function informarPagamento()
    {
        // Usuario precisa estar logado (garantido pelo filtro, mas
        // conferimos novamente por seguranca).
        if (! $this->sessao->has('usuario_id')) {
            return redirect()->to(site_url('login'));
        }

        // Quem ja esta ativo nao usa este fluxo.
        if ($this->sessao->get('status') === 'ativo') {
            return redirect()->to(site_url('painel'));
        }

        // Observacao opcional digitada pelo usuario.
        $observacao = trim((string) $this->request->getPost('observacao'));

        /** @var SolicitacaoLiberacaoModel $solicitacoes */
        $solicitacoes = model(SolicitacaoLiberacaoModel::class);

        // Evita duplicar solicitacoes pendentes.
        if ($solicitacoes->temPendente((int) $this->sessao->get('usuario_id'))) {
            return redirect()->to(site_url('aguardando'))
                ->with('erro', 'Voce ja informou o pagamento. Aguarde a analise do nosso time.');
        }

        // Observacao (vazia quando o usuario nao escreveu nada).
        $textoObservacao = null;

        if ($observacao !== '') {
            $textoObservacao = $observacao;
        }

        // Grava a solicitacao.
        $solicitacoes->insert([
            'usuario_id' => (int) $this->sessao->get('usuario_id'),
            'observacao' => $textoObservacao,
            'status'     => 'pendente',
        ]);

        // Confirma ao usuario.
        return redirect()->to(site_url('aguardando'))
            ->with('sucesso', 'Pagamento informado! Vamos analisar e liberar seu acesso em ate 24 horas uteis.');
    }
}
