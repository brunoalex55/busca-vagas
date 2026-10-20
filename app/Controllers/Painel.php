<?php

namespace App\Controllers;

use App\Libraries\DistribuicaoConsultas;
use App\Libraries\GerenciadorCron;
use App\Models\EmailEnviadoModel;
use App\Models\LogConsultaModel;
use App\Models\PalavraChaveModel;
use App\Models\VagaModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: PAINEL DO USUARIO - VISAO GERAL
 * --------------------------------------------------------------------------
 *
 * Mostra o resumo real da conta:
 *   - vagas encontradas e candidaturas enviadas;
 *   - cota de consultas calculada automaticamente;
 *   - envios do dia e limite;
 *   - pendencias de configuracao;
 *   - ultimas vagas, envios e consultas.
 *
 * Tambem atende as duas execucoes manuais do usuario (Bloco E):
 *   - executarConsultaManual() -> POST /painel/executar-vagas
 *   - executarEnvioManual()    -> POST /painel/executar-emails
 *
 * O limite diario dessas execucoes e sempre conferido antes de rodar
 * qualquer comando. O valor do limite e definido pelo admin.
 */
class Painel extends PainelBase
{
    /**
     * Dashboard principal do usuario.
     */
    public function index(): string
    {
        // Models usados no resumo.
        $vagas    = model(VagaModel::class);
        $emails   = model(EmailEnviadoModel::class);
        $palavras = model(PalavraChaveModel::class);
        $logs     = model(LogConsultaModel::class);

        // Configuracao completa (com o valor cifrado, usado so no checklist).
        $configuracao = $this->configuracaoDoUsuario();

        // Dados da conta.
        $usuario = $this->usuarioLogado();

        // Cota de consultas do mes.
        $cota = (new DistribuicaoConsultas())->resumo($this->usuarioId);

        // Envios efetivamente concluidos hoje.
        $enviosHoje = $emails->contarHoje($this->usuarioId);

        // Limite diario configurado.
        $limiteDiario = max(1, inteiro_ou($configuracao, 'limite_envios_dia', 20));

        // Checklist de prontidao (inclui a fila de vagas).
        $verificacoes = $this->verificacoesEnvio($configuracao);

        // Quantidade de itens pendentes.
        $pendencias = count(array_filter(
            $verificacoes,
            static fn (array $item): bool => ! $item['ok']
        ));

        // Execucoes manuais do usuario (limite definido pelo admin).
        $gerenciador      = new GerenciadorCron();
        $ehAdmin          = $this->sessao->get('perfil') === 'admin';
        $execucoesManuais = $gerenciador->usuarioPodeExecutarManualmente($this->usuarioId);

        // Dados entregues a view.
        return view('painel/inicio', $this->dadosPainel('Visao geral', [
            'usuario'           => $usuario,
            'cota'              => $cota,
            'totalVagas'        => $vagas->contarDoUsuario($this->usuarioId),
            'totalCandidaturas' => $emails->contarEnviados($this->usuarioId),
            'totalCandidaturasFeitas' => $vagas->contarCandidaturasRealizadas($this->usuarioId),
            'candidaturasFeitas' => $vagas->listarCandidaturasRealizadas($this->usuarioId, 8),
            'totalPalavras'     => $palavras->totalAtivasDoUsuario($this->usuarioId),
            'enviosHoje'        => $enviosHoje,
            'limiteDiario'      => $limiteDiario,
            'envioAutomatico'   => inteiro_ou($usuario, 'envio_automatico', 0) === 1,
            'verificacoes'      => $verificacoes,
            'pendencias'        => $pendencias,
            'vagasRecentes'     => $vagas->listarRecentes($this->usuarioId, 5),
            'enviosRecentes'    => $emails->recentesDoUsuario($this->usuarioId, 5),
            'consultasRecentes' => $logs->recentesDoUsuario($this->usuarioId, 5),
            'configuracao'      => $this->sanitizarConfiguracao($configuracao),
            'execucoesManuais'  => $execucoesManuais,
            'ehAdmin'           => $ehAdmin,
        ]));
    }

    // =========================================================================
    // EXECUCOES MANUAIS DO USUARIO (BLOCO E)
    // =========================================================================

    /**
     * Dispara a busca de vagas manualmente, a pedido do usuario.
     */
    public function executarConsultaManual(): RedirectResponse
    {
        return $this->executarComandoManual(
            GerenciadorCron::TIPO_VAGAS,
            'Busca de vagas'
        );
    }

    /**
     * Dispara o envio de e-mails manualmente, a pedido do usuario.
     */
    public function executarEnvioManual(): RedirectResponse
    {
        return $this->executarComandoManual(
            GerenciadorCron::TIPO_EMAILS,
            'Envio de e-mails'
        );
    }

    /**
     * Fluxo comum das duas execucoes manuais do usuario.
     *
     * Ordem definida no Bloco E:
     *   1. confere se o usuario ainda pode executar hoje;
     *   2. executa o Spark Command somente para este usuario;
     *   3. soma a execucao no contador do dia;
     *   4. volta para o painel com a mensagem do resultado.
     *
     * O admin nao tem esse limite: ele dispara tudo pelo painel
     * administrativo, sem passar pelo contador.
     */
    private function executarComandoManual(string $comando, string $rotulo): RedirectResponse
    {
        // Biblioteca central do agendamento.
        $gerenciador = new GerenciadorCron();

        // O admin nao consome o limite do usuario comum.
        $ehAdmin = $this->sessao->get('perfil') === 'admin';

        if (! $ehAdmin) {
            $permissao = $gerenciador->usuarioPodeExecutarManualmente($this->usuarioId);

            // Limite atingido ou execucao manual desligada pelo admin.
            if (! $permissao['pode']) {
                return $this->voltarComMensagemDaExecucao(
                    $this->mensagemDoBloqueio($permissao),
                    false
                );
            }
        }

        // Origem propria das execucoes manuais do usuario (logs_consulta).
        $resultado = $gerenciador->executarComando($comando, $this->usuarioId, 'manual_usuario');

        // A tentativa conta no limite do dia mesmo quando o comando falha:
        // sem isso, um erro repetido liberaria execucoes infinitas.
        if (! $ehAdmin) {
            $gerenciador->registrarExecucaoManualUsuario($this->usuarioId);
        }

        // Comando terminou com erro.
        if (! $resultado['sucesso']) {
            return $this->voltarComMensagemDaExecucao(
                $rotulo . ' falhou: ' . (string) $resultado['erro'],
                false
            );
        }

        return $this->voltarComMensagemDaExecucao(
            $rotulo . ' concluido em ' . (string) $resultado['duracao_segundos'] . ' segundo(s).',
            true
        );
    }

    /**
     * Explica por que a execucao manual foi recusada.
     *
     * @param array<string, mixed> $permissao Retorno de usuarioPodeExecutarManualmente().
     */
    private function mensagemDoBloqueio(array $permissao): string
    {
        // Execucao manual desligada pelo admin (limite zero).
        if ($permissao['motivo'] === 'desabilitado') {
            return 'As execucoes manuais estao desabilitadas pelo administrador. '
                . 'O sistema continua rodando pelo agendamento automatico.';
        }

        // Limite diario esgotado.
        return 'Limite diario de execucoes manuais atingido ('
            . (int) $permissao['usadas'] . ' de ' . (int) $permissao['limite']
            . '). Tente novamente amanha ou use o agendamento automatico.';
    }

    /**
     * Volta para o painel mostrando o resultado da execucao manual.
     */
    private function voltarComMensagemDaExecucao(string $mensagem, bool $sucesso): RedirectResponse
    {
        // Erro usa a chave "erro", exibida pelo partial de mensagens.
        if (! $sucesso) {
            return redirect()
                ->to(site_url('painel'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('painel'))
            ->with('sucesso', $mensagem);
    }
}
