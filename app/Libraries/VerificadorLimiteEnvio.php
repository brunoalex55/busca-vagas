<?php

namespace App\Libraries;

use App\Models\ConfiguracaoModel;
use App\Models\EmailEnviadoModel;
use DateTimeImmutable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: VERIFICADOR DE LIMITE DE ENVIO
 * --------------------------------------------------------------------------
 *
 * Regras que decidem se um e-mail de candidatura pode ser enviado AGORA.
 *
 * Estas regras sao COMPARTILHADAS por todos os provedores: o SerpApi e o
 * Divulga Vagas usam o MESMO verificador. Por isso o usuario tem UM unico
 * limite diario de e-mails (a soma dos dois provedores), e nao um limite
 * por provedor.
 *
 * As regras verificadas sao:
 *   1. a janela de dias e horarios permitidos (biblioteca JanelaEnvio);
 *   2. o intervalo minimo entre dois envios do mesmo usuario;
 *   3. o limite diario de envios.
 *
 * A ordem de verificacao e fixa e produz os mesmos motivos de parada do
 * CRON 2 do SerpApi: 'janela_fechada', 'aguardando_intervalo' e
 * 'limite_diario'. Assim o comportamento do sistema nao muda.
 *
 * Os limites do BOT (candidaturas feitas no site do Divulga Vagas) NAO
 * ficam aqui: eles sao separados e ficam em LimiteExecucaoManual.
 */
class VerificadorLimiteEnvio
{
    /** @var ConfiguracaoModel */
    private ConfiguracaoModel $configuracoes;

    /** @var EmailEnviadoModel */
    private EmailEnviadoModel $emails;

    /** @var JanelaEnvio */
    private JanelaEnvio $janela;

    public function __construct(
        ?ConfiguracaoModel $configuracoes = null,
        ?EmailEnviadoModel $emails = null,
        ?JanelaEnvio $janela = null
    ) {
        if ($configuracoes === null) {
            $configuracoes = model(ConfiguracaoModel::class);
        }

        if ($emails === null) {
            $emails = model(EmailEnviadoModel::class);
        }

        if ($janela === null) {
            $janela = new JanelaEnvio();
        }

        $this->configuracoes = $configuracoes;
        $this->emails       = $emails;
        $this->janela       = $janela;
    }

    /**
     * Avalia se o usuario pode enviar e-mail de candidatura agora.
     *
     * @return array{
     *     pode: bool,
     *     motivo: string|null,
     *     mensagem: string,
     *     limite: int,
     *     envios_hoje: int,
     *     configuracao: array<string, mixed>
     * }
     */
    public function podeEnviarAgora(int $usuarioId, ?DateTimeImmutable $agora = null): array
    {
        // Configuracao do usuario (com os padroes quando ainda nao salvou).
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        // Janela de dias e horarios.
        $janela = $this->janela->avaliar($configuracao, $agora);

        if (! $janela['aberta']) {
            return [
                'pode'         => false,
                'motivo'       => 'janela_fechada',
                'mensagem'     => (string) $janela['motivo'],
                'limite'       => 0,
                'envios_hoje'  => 0,
                'configuracao' => $configuracao,
            ];
        }

        // Intervalo minimo entre dois envios deste usuario.
        if (! $this->intervaloVencido($configuracao, $usuarioId)) {
            return [
                'pode'         => false,
                'motivo'       => 'aguardando_intervalo',
                'mensagem'     => 'Aguardando o intervalo minimo entre dois envios.',
                'limite'       => 0,
                'envios_hoje'  => 0,
                'configuracao' => $configuracao,
            ];
        }

        // Limite diario de envios (somando todos os provedores).
        $limiteDiario = max(1, inteiro_ou($configuracao, 'limite_envios_dia', 20));
        $enviosHoje = $this->emails->contarHoje($usuarioId);

        if ($enviosHoje >= $limiteDiario) {
            return [
                'pode'         => false,
                'motivo'       => 'limite_diario',
                'mensagem'     => 'Limite diario de envios atingido (' . $enviosHoje . ' de ' . $limiteDiario . ').',
                'limite'       => $limiteDiario,
                'envios_hoje'  => $enviosHoje,
                'configuracao' => $configuracao,
            ];
        }

        // Pode enviar.
        return [
            'pode'         => true,
            'motivo'       => null,
            'mensagem'     => 'Dentro do limite de envios.',
            'limite'       => $limiteDiario,
            'envios_hoje'  => $enviosHoje,
            'configuracao' => $configuracao,
        ];
    }

    /**
     * Registra UM envio de e-mail: contador do dia, data do ultimo envio e
     * data de reset.
     *
     * O mesmo registro vale para os dois provedores: e o que garante que o
     * limite diario seja UNICO para o usuario.
     */
    public function registrarEnvio(int $usuarioId, string $agora): void
    {
        $this->configuracoes->registrarEnvio($usuarioId, $agora);
    }

    /**
     * Informa se ja passou o intervalo exigido desde a ultima tentativa.
     *
     * O intervalo e sorteado entre o minimo e o maximo configurados pelo
     * usuario. Para nao depender de sorteio guardado, ele e derivado de
     * forma estavel do instante da ultima tentativa: assim duas execucoes
     * seguidas calculam exatamente o mesmo intervalo.
     *
     * @param array<string, mixed> $configuracao
     */
    private function intervaloVencido(array $configuracao, int $usuarioId): bool
    {
        // Ultima tentativa registrada (vazio na primeira vez).
        $ultimo = trim(texto_ou($configuracao, 'data_ultimo_envio'));

        if ($ultimo === '') {
            return true;
        }

        $instante = strtotime($ultimo);

        // Data invalida nao pode travar o envio.
        if ($instante === false) {
            return true;
        }

        // Faixa configurada pelo usuario.
        $minimo = max(1, inteiro_ou($configuracao, 'intervalo_minimo_segundos', 180));
        $maximo = max($minimo, inteiro_ou($configuracao, 'intervalo_maximo_segundos', 420));

        // Intervalo estavel dentro da faixa.
        $intervalo = $minimo + (int) (crc32($usuarioId . '|' . $ultimo) % ($maximo - $minimo + 1));

        return time() >= $instante + $intervalo;
    }
}
