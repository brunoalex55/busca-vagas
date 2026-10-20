<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers\Admin;

use App\Models\PlanoModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - FATURAMENTO
 * --------------------------------------------------------------------------
 *
 * A tela de relatorio mensal do administrador:
 *
 *   - quantos usuarios o sistema tinha em cada mes (a base que grows);
 *   - quantos entraram novos no mes;
 *   - a receita estimada (as contas ativas pelo preco do plano);
 *   - o bloqueio por falta de pagamento (a conta para e os dados do
 *     PIX voltam a aparecer para o usuario renovar).
 *
 * OBSERVACAO SOBRE A RECEITA:
 *
 * O sistema nao guarda o historico de mudanca de plano, entao a receita
 * de cada mes e uma ESTIMATIVA feita com os planos atuais das contas
 * que existiam no mes. O numero de usuarios e exato.
 *
 * REGRAS:
 *
 *   - toda escrita e POST (nada muda por GET);
 *   - o admin nao bloqueia a si mesmo;
 *   - o bloqueio por pagamento marca a conta (bloqueado_por_pagamento),
 *     para que a tela do usuario mostre de novo os dados do PIX.
 */
class AdminFaturamento extends AdminBase
{
    /** Quantos meses o relatorio mostra. */
    private const MESES = 12;

    /** Model dos usuarios. */
    private UsuarioModel $usuarios;

    /**
     * Carrega o model dos usuarios.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->usuarios = model(UsuarioModel::class);
    }

    /**
     * O relatorio mensal.
     */
    public function index(): string
    {
        return view('admin/faturamento', $this->dadosAdmin('Faturamento', [
            'meses' => $this->relatorioMensal(),
            'resumo' => $this->resumoGeral(),
            'bloqueados' => $this->contasBloqueadas(),
            'contasAtivas' => $this->contasAtivas(),
            'pagamento' => configuracao_pagamento(),
        ]));
    }

    /**
     * Bloqueia a conta do usuario por falta de pagamento.
     *
     * A conta para (os crons pulam ela) e a tela dela volta a mostrar
     * os dados do PIX para a renovacao.
     *
     * @param int|string|null $id Identificador do usuario (pela rota).
     */
    public function bloquearPagamento($id = null): RedirectResponse
    {
        // O id do usuario (pela rota ou pelo formulario).
        $usuarioId = 0;

        if ($id !== null && $id !== '') {
            $usuarioId = $this->idDaRota($id);
        }

        if ($usuarioId <= 0) {
            $usuarioId = (int) $this->request->getPost('usuario_id');
        }

        $usuario = $this->carregarUsuario($usuarioId);

        // O admin nao bloqueia a propria conta.
        if ((int) $usuario['id'] === (int) $this->adminId) {
            return $this->voltar('O administrador nao pode bloquear a propria conta.', true);
        }

        // O motivo do bloqueio (o admin escreve).
        $motivo = trim((string) $this->request->getPost('motivo'));

        if ($motivo === '') {
            $motivo = 'Falta de pagamento da mensalidade.';
        }

        if (mb_strlen($motivo) > 255) {
            $motivo = mb_substr($motivo, 0, 255);
        }

        // Bloqueia a conta e marca o motivo do bloqueio por pagamento.
        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'bloqueado', 0);

        db_connect()->table('usuarios')->where('id', (int) $usuario['id'])->update([
            'bloqueado_por_pagamento' => 1,
            'motivo_bloqueio' => $motivo,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return $this->voltar('Conta bloqueada por falta de pagamento. O usuario ja ve os dados do PIX para renovar.');
    }

    /**
     * Destrava a conta (o pagamento foi confirmado).
     *
     * @param int|string $id Identificador do usuario.
     */
    public function cobrarPagamento($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($this->idDaRota($id));

        // Reativa a conta e limpa a marcacao do bloqueio por pagamento.
        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'ativo', 1);

        db_connect()->table('usuarios')->where('id', (int) $usuario['id'])->update([
            'bloqueado_por_pagamento' => 0,
            'motivo_bloqueio' => null,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return $this->voltar('Conta liberada. O pagamento foi confirmado.');
    }

    // =====================================================================
    // AJUDA
    // =====================================================================

    /**
     * O relatorio de cada mes (do mais antigo para o mais recente).
     *
     * @return list<array<string, mixed>>
     */
    private function relatorioMensal(): array
    {
        // Todos os usuarios com a data de criacao e o plano.
        $usuarios = db_connect()->table('usuarios')
            ->select('id, nome, email, status, ativo, plano_id, criado_em, bloqueado_por_pagamento')
            ->orderBy('criado_em', 'ASC')
            ->get()
            ->getResultArray();

        // Os precos dos planos (para a receita estimada).
        $precos = [];

        foreach (model(PlanoModel::class)->listarAtivos() as $plano) {
            $preco = valor_ou($plano, 'preco_mensal', null);

            if ($preco === null) {
                $preco = 0.0;
            }

            $precos[(int) $plano['id']] = (float) $preco;
        }

        // O mes mais antigo com usuarios (o relatorio comeca nele).
        $primeiroMes = date('Y-m');

        foreach ($usuarios as $usuario) {
            $criado = (string) valor_ou($usuario, 'criado_em', '');

            if ($criado !== '' && substr($criado, 0, 7) < $primeiroMes) {
                $primeiroMes = substr($criado, 0, 7);
            }
        }

        // Os meses do relatorio (do mais antigo para o mais novo).
        $meses = [];

        for ($i = self::MESES - 1; $i >= 0; $i--) {
            $meses[] = date('Y-m', strtotime('-' . $i . ' month'));
        }

        // Garante que o primeiro mes (o do primeiro usuario) entre na lista.
        if (! in_array($primeiroMes, $meses, true)) {
            array_unshift($meses, $primeiroMes);
        }

        $relatorio = [];

        foreach ($meses as $mes) {
            // O fim do mes (para as contas que ja existiam nele).
            $fimDoMes = date('Y-m-t 23:59:59', strtotime($mes . '-01'));

            $novos = 0;
            $ativos = 0;
            $bloqueados = 0;
            $receita = 0.0;

            foreach ($usuarios as $usuario) {
                $criado = (string) valor_ou($usuario, 'criado_em', '');

                // A conta ainda nao existia no mes.
                if ($criado === '' || substr($criado, 0, 7) > $mes) {
                    continue;
                }

                // Entrou neste mes.
                if (substr($criado, 0, 7) === $mes) {
                    $novos++;
                }

                // A conta estava bloqueada quando o mes fechou.
                $criadoAte = $criado;

                if ($criadoAte > $fimDoMes) {
                    $criadoAte = $fimDoMes;
                }

                $bloqueio = db_connect()->table('usuarios')
                    ->where('id', (int) $usuario['id'])
                    ->get()
                    ->getRowArray();

                $bloqueadoNoMes = (int) valor_ou($bloqueio, 'bloqueado_por_pagamento', 0) === 1;

                if ($bloqueadoNoMes) {
                    $bloqueados++;
                    continue;
                }

                // A conta ativa entra na receita estimada.
                $ativos++;

                $planoId = (int) valor_ou($usuario, 'plano_id', 0);

                if (isset($precos[$planoId])) {
                    $receita += $precos[$planoId];
                }
            }

            $relatorio[] = [
                'mes' => $mes,
                'rotulo' => $this->rotuloDoMes($mes),
                'novos' => $novos,
                'ativos' => $ativos,
                'bloqueados' => $bloqueados,
                'receita' => $receita,
            ];
        }

        return $relatorio;
    }

    /**
     * Os numeros do momento (as contas de hoje).
     *
     * @return array<string, mixed>
     */
    private function resumoGeral(): array
    {
        $total = (int) db_connect()->table('usuarios')->countAllResults();
        $ativos = (int) db_connect()->table('usuarios')->where('status', 'ativo')->where('ativo', 1)->countAllResults();
        $bloqueados = (int) db_connect()->table('usuarios')->where('status', 'bloqueado')->countAllResults();
        $pendentes = (int) db_connect()->table('usuarios')->where('status', 'pendente')->countAllResults();

        // A receita mensal estimada (as contas ativas pelo preco do plano).
        $receita = 0.0;

        foreach (db_connect()->table('usuarios')->where('status', 'ativo')->get()->getResultArray() as $usuario) {
            $plano = db_connect()->table('planos')
                ->where('id', (int) valor_ou($usuario, 'plano_id', 0))
                ->get()
                ->getRowArray();

            if ($plano !== null && $plano !== []) {
                $preco = valor_ou($plano, 'preco_mensal', null);

                if ($preco !== null) {
                    $receita += (float) $preco;
                }
            }
        }

        return [
            'total' => $total,
            'ativos' => $ativos,
            'bloqueados' => $bloqueados,
            'pendentes' => $pendentes,
            'receita' => $receita,
        ];
    }

    /**
     * As contas bloqueadas por falta de pagamento.
     *
     * @return list<array<string, mixed>>
     */
    private function contasBloqueadas(): array
    {
        return db_connect()->table('usuarios')
            ->select('id, nome, email, status, plano_id, motivo_bloqueio, bloqueado_por_pagamento, criado_em')
            ->where('bloqueado_por_pagamento', 1)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * O nome do mes ("out/2026").
     */
    private function rotuloDoMes(string $mes): string
    {
        $partes = explode('-', $mes);

        if (count($partes) !== 2) {
            return $mes;
        }

        $meses = [
            '01' => 'jan',
            '02' => 'fev',
            '03' => 'mar',
            '04' => 'abr',
            '05' => 'mai',
            '06' => 'jun',
            '07' => 'jul',
            '08' => 'ago',
            '09' => 'set',
            '10' => 'out',
            '11' => 'nov',
            '12' => 'dez',
        ];

        $nome = valor_ou($meses, $partes[0], $partes[0]);

        return $nome . '/' . $partes[1];
    }

    /**
     * As contas ativas (para o bloqueio por falta de pagamento).
     *
     * @return list<array<string, mixed>>
     */
    private function contasAtivas(): array
    {
        return db_connect()->table('usuarios')
            ->select('id, nome, email')
            ->where('status', 'ativo')
            ->orderBy('nome', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Carrega um usuario (ou devolve o erro).
     *
     * @param int $usuarioId O id do usuario.
     *
     * @return array<string, mixed>
     */
    private function carregarUsuario(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Usuario nao encontrado.');
        }

        $usuario = model(UsuarioModel::class)->find($usuarioId);

        if ($usuario === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Usuario nao encontrado.');
        }

        return $usuario;
    }

    /**
     * Volta para a tela de faturamento com uma mensagem.
     */
    private function voltar(string $mensagem, bool $erro = false): RedirectResponse
    {
        $chave = 'sucesso';

        if ($erro) {
            $chave = 'erro';
        }

        return redirect()
            ->to(site_url('admin/faturamento'))
            ->with($chave, $mensagem);
    }
}
