<?php

namespace App\Libraries;

use App\Models\ConfiguracaoGlobalModel;
use App\Models\ConfiguracaoModel;
use App\Models\VagaModel;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: LIMITE DE EXECUCAO MANUAL
 * --------------------------------------------------------------------------
 *
 * A execucao manual (o usuario apertando o botao no painel, ou o
 * administrador) e um ATALHO para forcar a execucao sem esperar o
 * horario do agendador. Ela NUNCA substitui o CRON e NUNCA contorna
 * nenhum limite:
 *
 *   - os limites do CRON continuam valendo (janela, intervalo e limite
 *     diario de e-mail, limites do bot);
 *   - a execucao manual tem, alem disso, limites proprios, para nao
 *     sobrecarregar o servidor nem o site do Divulga Vagas.
 *
 * Limites proprios do usuario (tabela `configuracoes`):
 *   - divulgavagas_max_buscas_manuais_dia (padrao 3);
 *   - divulgavagas_max_candidaturas_manuais_dia (padrao 5).
 *
 * Limites proprios do administrador (tabela `configuracao_global`):
 *   - admin_max_buscas_manuais_dia (padrao 10);
 *   - admin_max_candidaturas_manuais_dia (padrao 20).
 *
 * Limites do bot (tabela `configuracoes`, por usuario):
 *   - divulgavagas_max_candidaturas_bot_dia (padrao 30);
 *   - divulgavagas_intervalo_bot_min_segundos e
 *     divulgavagas_intervalo_bot_max_segundos (padrao 60 a 180).
 *
 * Os contadores do dia se zeram sozinhos: cada gravacao confere a data do
 * ultimo reset e zera quando o dia virou.
 */
class LimiteExecucaoManual
{
    /** @var ConfiguracaoModel */
    private ConfiguracaoModel $configuracoes;

    /** @var ConfiguracaoGlobalModel */
    private ConfiguracaoGlobalModel $globais;

    /** @var VagaModel */
    private VagaModel $vagas;

    public function __construct(
        ?ConfiguracaoModel $configuracoes = null,
        ?ConfiguracaoGlobalModel $globais = null,
        ?VagaModel $vagas = null
    ) {
        if ($configuracoes === null) {
            $configuracoes = model(ConfiguracaoModel::class);
        }

        if ($globais === null) {
            $globais = model(ConfiguracaoGlobalModel::class);
        }

        if ($vagas === null) {
            $vagas = model(VagaModel::class);
        }

        $this->configuracoes = $configuracoes;
        $this->globais       = $globais;
        $this->vagas         = $vagas;
    }

    // =====================================================================
    // USUARIO COMUM
    // =====================================================================

    /**
     * O usuario pode disparar uma BUSCA manual agora?
     *
     * @return array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int}
     */
    public function podeBuscarManualmente(int $usuarioId, string $origem = 'manual_usuario'): array
    {
        // A execucao do admin usa os limites globais (conta uma vez).
        if ($origem === 'manual_admin') {
            return $this->podeExecutarDoAdmin('buscas');
        }

        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        $this->resetarContadoresSeNecessario($usuarioId, $origem);

        $limite = max(0, inteiro_ou($configuracao, 'divulgavagas_max_buscas_manuais_dia', 3));
        $usadas = max(0, inteiro_ou($configuracao, 'divulgavagas_buscas_manuais_hoje', 0));

        // Limite zero: execucao manual desligada.
        if ($limite === 0) {
            return [
                'pode'      => false,
                'motivo'    => 'desabilitado',
                'limite'    => 0,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Ja usou tudo hoje.
        if ($usadas >= $limite) {
            return [
                'pode'      => false,
                'motivo'    => 'limite_atingido',
                'limite'    => $limite,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Pode executar.
        return [
            'pode'      => true,
            'motivo'    => 'disponivel',
            'limite'    => $limite,
            'usadas'    => $usadas,
            'restantes' => $limite - $usadas,
        ];
    }

    /**
     * O usuario pode disparar uma CANDIDATURA manual agora?
     *
     * @return array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int}
     */
    public function podeCandidatarManualmente(int $usuarioId, string $origem = 'manual_usuario'): array
    {
        // A execucao do admin usa os limites globais (conta uma vez).
        if ($origem === 'manual_admin') {
            return $this->podeExecutarDoAdmin('candidaturas');
        }

        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        $this->resetarContadoresSeNecessario($usuarioId, $origem);

        $limite = max(0, inteiro_ou($configuracao, 'divulgavagas_max_candidaturas_manuais_dia', 5));
        $usadas = max(0, inteiro_ou($configuracao, 'divulgavagas_candidaturas_manuais_hoje', 0));

        // Limite zero: execucao manual desligada.
        if ($limite === 0) {
            return [
                'pode'      => false,
                'motivo'    => 'desabilitado',
                'limite'    => 0,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Ja usou tudo hoje.
        if ($usadas >= $limite) {
            return [
                'pode'      => false,
                'motivo'    => 'limite_atingido',
                'limite'    => $limite,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Pode executar.
        return [
            'pode'      => true,
            'motivo'    => 'disponivel',
            'limite'    => $limite,
            'usadas'    => $usadas,
            'restantes' => $limite - $usadas,
        ];
    }

    /**
     * Registra uma busca manual do usuario (ou do admin).
     */
    public function registrarBuscaManual(int $usuarioId, string $origem = 'manual_usuario'): void
    {
        // A execucao do admin conta no contador global.
        if ($origem === 'manual_admin') {
            $this->registrarBuscaAdmin();

            return;
        }

        $this->configuracoes->registrarBuscaManual($usuarioId, date('Y-m-d H:i:s'));
    }

    /**
     * Registra uma candidatura manual do usuario (ou do admin).
     */
    public function registrarCandidaturaManual(int $usuarioId, string $origem = 'manual_usuario'): void
    {
        // A execucao do admin conta no contador global.
        if ($origem === 'manual_admin') {
            $this->registrarCandidaturaAdmin();

            return;
        }

        $this->configuracoes->registrarCandidaturaManual($usuarioId, date('Y-m-d H:i:s'));
    }

    /**
     * Zera os contadores manuais do usuario quando o dia virou.
     */
    public function resetarContadoresSeNecessario(int $usuarioId, string $origem = 'manual_usuario'): void
    {
        // Os contadores do admin tem reset proprio.
        if ($origem === 'manual_admin') {
            $this->resetarContadoresAdminSeNecessario();

            return;
        }

        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);
        $hoje = date('Y-m-d');
        $ultimoReset = texto_ou($configuracao, 'divulgavagas_data_reset_manual', '');

        // Ja foi resetado hoje: nada a fazer.
        if ($ultimoReset === $hoje) {
            return;
        }

        $this->configuracoes->zerarContadoresManuais($usuarioId, date('Y-m-d H:i:s'));
    }

    // =====================================================================
    // LIMITES DO BOT
    // =====================================================================

    /**
     * O bot pode candidatar a uma vaga agora?
     *
     * Verifica duas coisas:
     *   1. o limite diario de candidaturas do bot;
     *   2. o intervalo minimo entre uma candidatura e outra.
     *
     * @return array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int, espera_segundos: int}
     */
    public function podeCandidatarPeloBot(int $usuarioId, bool $ignorarIntervalo = false): array
    {
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        // Reseta o contador do bot quando o dia virou.
        $this->resetarContadorDoBotSeNecessario($usuarioId, $configuracao);

        $limite = max(0, inteiro_ou($configuracao, 'divulgavagas_max_candidaturas_bot_dia', 30));
        $usadas = max(0, inteiro_ou($configuracao, 'divulgavagas_candidaturas_bot_hoje', 0));

        // Limite diario do bot.
        if ($usadas >= $limite) {
            return [
                'pode'             => false,
                'motivo'           => 'limite_diario_bot',
                'limite'           => $limite,
                'usadas'           => $usadas,
                'restantes'        => 0,
                'espera_segundos'  => 0,
            ];
        }

        // Intervalo entre duas candidaturas do bot.
        //
        // O loop de candidatura ignora este intervalo (a pausa entre as
        // candidatura e feita pelo proprio loop, para nao martelar o
        // site). O intervalo vale para o espaco ENTRE execucoes.
        $ultima = $this->vagas->ultimaCandidaturaDoBot($usuarioId);
        $espera = $this->segundosParaProximaCandidatura($configuracao, $ultima);

        if ($espera > 0 && ! $ignorarIntervalo) {
            return [
                'pode'            => false,
                'motivo'          => 'aguardando_intervalo_bot',
                'limite'          => $limite,
                'usadas'          => $usadas,
                'restantes'       => max(0, $limite - $usadas),
                'espera_segundos' => $espera,
            ];
        }

        // Pode candidatar.
        return [
            'pode'            => true,
            'motivo'          => 'disponivel',
            'limite'          => $limite,
            'usadas'          => $usadas,
            'restantes'       => max(0, $limite - $usadas),
            'espera_segundos' => 0,
        ];
    }

    /**
     * Registra uma candidatura feita pelo bot.
     */
    public function registrarCandidaturaBot(int $usuarioId): void
    {
        $this->configuracoes->registrarCandidaturaDoBot($usuarioId, date('Y-m-d H:i:s'));
    }

    /**
     * Quantos segundos faltam para a proxima candidatura do bot.
     *
     * O intervalo e derivado de forma estavel do instante da ultima
     * candidatura (mesma ideia do intervalo de e-mail), dentro da faixa
     * minima e maxima configurada.
     *
     * @param array<string, mixed> $configuracao
     */
    private function segundosParaProximaCandidatura(array $configuracao, ?string $ultima): int
    {
        // Sem candidatura anterior: pode agora.
        if ($ultima === null || trim($ultima) === '') {
            return 0;
        }

        $instante = strtotime($ultima);

        // Data invalida nao trava o bot.
        if ($instante === false) {
            return 0;
        }

        $minimo = max(1, inteiro_ou($configuracao, 'divulgavagas_intervalo_bot_min_segundos', 60));
        $maximo = max($minimo, inteiro_ou($configuracao, 'divulgavagas_intervalo_bot_max_segundos', 180));

        // Intervalo estavel dentro da faixa.
        $intervalo = $minimo + (int) (crc32($ultima) % ($maximo - $minimo + 1));

        $falta = ($instante + $intervalo) - time();

        if ($falta <= 0) {
            return 0;
        }

        return (int) $falta;
    }

    /**
     * Zera o contador diario do bot quando o dia virou.
     *
     * @param array<string, mixed> $configuracao
     */
    private function resetarContadorDoBotSeNecessario(int $usuarioId, array $configuracao): void
    {
        $hoje = date('Y-m-d');
        $ultimoReset = texto_ou($configuracao, 'divulgavagas_data_reset_bot', '');

        // Ja foi resetado hoje: nada a fazer.
        if ($ultimoReset === $hoje) {
            return;
        }

        $this->configuracoes->db->table($this->configuracoes->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'divulgavagas_candidaturas_bot_hoje' => 0,
                'divulgavagas_data_reset_bot'        => $hoje,
                'atualizado_em'                      => date('Y-m-d H:i:s'),
            ]);
    }

    // =====================================================================
    // ADMINISTRADOR
    // =====================================================================

    /**
     * Limites do administrador (para a tela do painel).
     *
     * @return array{buscas: array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int}, candidaturas: array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int}}
     */
    public function limitesAdmin(): array
    {
        $this->resetarContadoresAdminSeNecessario();

        return [
            'buscas'       => $this->podeExecutarDoAdmin('buscas'),
            'candidaturas' => $this->podeExecutarDoAdmin('candidaturas'),
        ];
    }

    /**
     * Registra uma busca manual disparada pelo administrador.
     */
    public function registrarBuscaAdmin(): void
    {
        $this->globais->registrarBusca();
    }

    /**
     * Registra uma candidatura manual disparada pelo administrador.
     */
    public function registrarCandidaturaAdmin(): void
    {
        $this->globais->registrarCandidatura();
    }

    /**
     * Zera os contadores do administrador quando o dia virou.
     */
    public function resetarContadoresAdminSeNecessario(): void
    {
        $this->globais->resetarContadoresSeNecessario();
    }

    /**
     * O administrador ainda pode disparar execucoes do tipo informado?
     *
     * @param string $tipo 'buscas' ou 'candidaturas'.
     *
     * @return array{pode: bool, motivo: string, limite: int, usadas: int, restantes: int}
     */
    private function podeExecutarDoAdmin(string $tipo): array
    {
        $configuracao = $this->globais->obter();

        if ($tipo === 'candidaturas') {
            $limite = max(0, inteiro_ou($configuracao, 'admin_max_candidaturas_manuais_dia', 20));
            $usadas = max(0, inteiro_ou($configuracao, 'admin_candidaturas_manuais_hoje', 0));
        } else {
            $limite = max(0, inteiro_ou($configuracao, 'admin_max_buscas_manuais_dia', 10));
            $usadas = max(0, inteiro_ou($configuracao, 'admin_buscas_manuais_hoje', 0));
        }

        // Limite zero: execucao manual desligada.
        if ($limite === 0) {
            return [
                'pode'      => false,
                'motivo'    => 'desabilitado',
                'limite'    => 0,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Ja usou tudo hoje.
        if ($usadas >= $limite) {
            return [
                'pode'      => false,
                'motivo'    => 'limite_atingido',
                'limite'    => $limite,
                'usadas'    => $usadas,
                'restantes' => 0,
            ];
        }

        // Pode executar.
        return [
            'pode'      => true,
            'motivo'    => 'disponivel',
            'limite'    => $limite,
            'usadas'    => $usadas,
            'restantes' => $limite - $usadas,
        ];
    }
}
