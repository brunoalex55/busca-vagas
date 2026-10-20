<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use App\Libraries\JanelaEnvio;
use App\Libraries\ResolvedorPlano;
use App\Models\EmailEnviadoModel;
use App\Models\VagaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ENVIO EM MASSA
 * --------------------------------------------------------------------------
 *
 * Central de envio de candidaturas.
 *
 * O disparo real (SMTP um a um, com pausas) e executado pelo robo de
 * envio. Esta tela:
 *   - mostra o estado do envio automatico;
 *   - confere a janela de horario e o limite diario;
 *   - lista a fila priorizada de vagas com e-mail;
 *   - liga/desliga o envio automatico (POST).
 */
class EnvioMassa extends PainelBase
{
    /**
     * Painel da central de envio.
     */
    public function index(): string
    {
        // O plano do usuario tem envio em massa? (sem ele, a tela nao
        // existe para este usuario).
        $permitido = (new ResolvedorPlano())->podeUsarEnvioEmMassa($this->usuarioId);

        if ($permitido === false) {
            throw PageNotFoundException::forPageNotFound(
                'O envio em massa esta disponivel nos planos Profissional e Empresa.'
            );
        }

        // Configuracao completa (o checklist precisa do valor cifrado).
        $configuracao = $this->configuracaoDoUsuario();

        // Dados da conta.
        $usuario = $this->usuarioLogado();

        // Models de apoio.
        $emails = model(EmailEnviadoModel::class);
        $vagas  = model(VagaModel::class);

        // Envios concluidos hoje.
        $enviosHoje = $emails->contarHoje($this->usuarioId);

        // Limite diario configurado.
        $limiteDiario = max(1, inteiro_ou($configuracao, 'limite_envios_dia', 20));

        // Avaliacao da janela de horario/dias.
        $janela = (new JanelaEnvio())->avaliar($configuracao);

        // Checklist de prontidao.
        $verificacoes = $this->verificacoesEnvio($configuracao);

        return view('painel/envio_massa', $this->dadosPainel('Envio em massa', [
            'envioAutomatico' => inteiro_ou($usuario, 'envio_automatico', 0) === 1,
            'enviosHoje'      => $enviosHoje,
            'limiteDiario'    => $limiteDiario,
            'restantesHoje'   => max(0, $limiteDiario - $enviosHoje),
            'janela'          => $janela,
            'fila'            => $vagas->listarFilaEnvio($this->usuarioId, 10),
            'totalFila'       => $vagas->contarComEmailNaoCandidatadas($this->usuarioId),
            'verificacoes'    => $verificacoes,
            'configuracao'    => $this->sanitizarConfiguracao($configuracao),
        ]));
    }

    /**
     * Liga ou desliga o envio automatico.
     */
    public function alternarAutomatico(): RedirectResponse
    {
        // Estado atual.
        $usuario = $this->usuarioLogado();
        $ligado  = inteiro_ou($usuario, 'envio_automatico', 0) === 1;

        // Desligar sempre e permitido.
        if ($ligado) {
            $this->usuarios->skipValidation(true)->update($this->usuarioId, [
                'envio_automatico' => 0,
            ]);

            return redirect()
                ->back()
                ->with('sucesso', 'Envio automatico desligado.');
        }

        // Para ligar, todos os requisitos precisam estar prontos.
        $pendencias = $this->pendenciasParaAtivarEnvio($this->configuracaoDoUsuario());

        if ($pendencias !== []) {
            return redirect()
                ->back()
                ->with('erro', 'Antes de ativar, complete: ' . implode(', ', $pendencias) . '.');
        }

        // Ativa o envio automatico.
        $this->usuarios->skipValidation(true)->update($this->usuarioId, [
            'envio_automatico' => 1,
        ]);

        return redirect()
            ->back()
            ->with('sucesso', 'Envio automatico ligado. O robo enviara uma candidatura por vez.');
    }
}
