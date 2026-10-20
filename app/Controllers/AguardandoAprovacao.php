<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


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
        // A conta do usuario logado (o status vem do banco: o bloqueio
        // por falta de pagamento tem que valer na hora).
        $usuarioId = (int) $this->sessao->get('usuario_id');
        $conta = db_connect()->table('usuarios')
            ->select('status, bloqueado_por_pagamento, motivo_bloqueio')
            ->where('id', $usuarioId)
            ->get()
            ->getRowArray();

        // Status atual do usuario logado.
        $status = 'pendente';

        if ($conta !== null && $conta !== []) {
            $status = (string) valor_ou($conta, 'status', 'pendente');
        }

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
        $temPendente = $solicitacoes->temPendente($usuarioId);

        // A conta foi bloqueada por falta de pagamento? (a renovacao)
        $bloqueadoPagamento = false;
        $motivoBloqueio = '';

        if ($status === 'bloqueado' && $conta !== null && $conta !== []) {
            $bloqueadoPagamento = (int) valor_ou($conta, 'bloqueado_por_pagamento', 0) === 1;
            $motivoBloqueio = texto_ou($conta, 'motivo_bloqueio', '');
        }

        return view('aprovacao/aguardando', $this->dadosComuns + [
            'seo' => [
                'titulo'         => 'Aguardando aprovacao | BuscaVagas PRO',
                'descricao'      => 'Confira as instrucoes para liberar o acesso a sua conta.',
                'palavras_chave' => 'aguardando aprovacao',
                'tipo'           => 'website',
            ],
            'pagamento'          => $pagamento,
            'temPendente'        => $temPendente,
            'statusConta'        => $status,
            'plano'              => (new \App\Libraries\ResolvedorPlano())->obterPlanoDoUsuario($usuarioId),
            'bloqueadoPagamento' => $bloqueadoPagamento,
            'motivoBloqueio'     => $motivoBloqueio,
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

        // Quem ja esta ativo nao usa este fluxo (o status vem do banco:
        // o bloqueio por falta de pagamento tem que valer na hora).
        $usuarioId = (int) $this->sessao->get('usuario_id');
        $conta = db_connect()->table('usuarios')->select('status')->where('id', $usuarioId)->get()->getRowArray();
        $status = 'pendente';

        if ($conta !== null && $conta !== []) {
            $status = (string) valor_ou($conta, 'status', 'pendente');
        }

        if ($status === 'ativo') {
            return redirect()->to(site_url('painel'));
        }

        // Observacao opcional digitada pelo usuario.
        $observacao = trim((string) $this->request->getPost('observacao'));

        /** @var SolicitacaoLiberacaoModel $solicitacoes */
        $solicitacoes = model(SolicitacaoLiberacaoModel::class);

        // Evita duplicar solicitacoes pendentes.
        if ($solicitacoes->temPendente($usuarioId)) {
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
