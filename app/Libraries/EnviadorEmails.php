<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use App\Contracts\EnviadorEmailInterface;
use App\Models\ConfiguracaoModel;
use App\Models\EmailEnviadoModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;
use Config\Cron;
use DateTimeImmutable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: ENVIADOR DE E-MAILS (CRON 2)
 * --------------------------------------------------------------------------
 *
 * Orquestra o robo de candidaturas. E aqui que as regras do envio vivem:
 *
 *   1. QUEM envia: usuarios ativos que ligaram a chave `envio_automatico`.
 *   2. QUANDO envia: somente dentro da janela de dias/horarios do usuario.
 *   3. COM QUE RITMO: no maximo UM e-mail por usuario por execucao, sempre
 *      respeitando um intervalo de 3 a 7 minutos (sorteado e estavel)
 *      entre um envio e o proximo. Assim a conta Gmail fica protegida.
 *   4. QUANTOS: no maximo `cron.envioAutomaticoLimitePorExecucao` e-mails
 *      somando todos os usuarios, e nunca acima do limite diario de cada um.
 *   5. O QUE envia: a vaga prioritaria da fila que ainda tem e-mail e nao
 *      recebeu candidatura.
 *   6. COMO registra: cada tentativa vira uma linha em `emails_enviados`.
 *      Sucesso marca a vaga como candidatada; endereco invalido tambem sai
 *      da fila (nao adianta tentar de novo); falha de SMTP continua na fila
 *      para uma nova tentativa na proxima execucao.
 *
 * O envio real acontece SOMENTE via EnviadorEmailInterface. Trocar a forma
 * de envio nao muda esta classe.
 */
class EnviadorEmails
{
    /**
     * Motivo devolvido quando a vaga tem valor de "pular a fila".
     */
    private const STATUS_ENVIADO = EnviadorEmailInterface::STATUS_ENVIADO;

    /**
     * Enviador injetavel (testes podem passar um duble).
     */
    private EnviadorEmailInterface $enviador;

    /** Limites das execucoes automaticas. */
    private Cron $cron;

    /** Momento da execucao (injetavel para testes). */
    private DateTimeImmutable $agora;

    /** Calendario de envio. */
    private JanelaEnvio $janela;

    /** Regras de envio compartilhadas com os outros provedores. */
    private VerificadorLimiteEnvio $verificador;

    /** Models usados pela rotina. */
    private UsuarioModel $usuarios;
    private ConfiguracaoModel $configuracoes;
    private VagaModel $vagas;
    private EmailEnviadoModel $emails;

    /** Leitura do curriculo (checklist de prontidao). */
    private ArmazenamentoCurriculo $curriculos;

    /**
     * Todas as dependencias sao injetaveis para facilitar testes.
     */
    public function __construct(
        ?EnviadorEmailInterface $enviador = null,
        ?Cron $cron = null,
        ?DateTimeImmutable $agora = null
    ) {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($cron === null) {
            $cron = config(Cron::class);
        }

        // Sem momento informado: usa o agora.
        if ($agora === null) {
            $agora = new DateTimeImmutable('now');
        }

        // Sem enviador injetado: cria o padrao.
        if ($enviador === null) {
            $enviador = new EnviadorEmail();
        }

        $this->cron      = $cron;
        $this->agora     = $agora;
        $this->enviador  = $enviador;

        $this->janela        = new JanelaEnvio();
        $this->usuarios      = new UsuarioModel();
        $this->configuracoes = new ConfiguracaoModel();
        $this->vagas         = new VagaModel();
        $this->emails        = new EmailEnviadoModel();
        $this->curriculos    = new ArmazenamentoCurriculo();

        // Verificador das regras de envio (compartilhado com os outros
        // provedores: janela, intervalo e limite diario).
        $this->verificador = new VerificadorLimiteEnvio(
            $this->configuracoes,
            $this->emails,
            $this->janela
        );
    }

    /**
     * Envia candidaturas para TODOS os usuarios com envio automatico ligado.
     *
     * @param int|null $limite Teto de e-mails desta execucao.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function enviarGlobal(?int $limite = null): array
    {
        // Orcamento de e-mails desta execucao.
        $orcamento = $limite;

        // Sem limite informado: usa o teto configurado.
        if ($orcamento === null) {
            $orcamento = max(0, (int) $this->cron->envioAutomaticoLimitePorExecucao);
        }

        // Resumo geral devolvido ao chamador.
        $resumo = $this->resumoVazio();

        // Usuarios com envio automatico ligado, do mais atrasado ao mais recente.
        $usuarios = $this->usuarios->listarParaEnvio((int) $this->cron->limiteUsuariosPorExecucao);

        foreach ($usuarios as $usuario) {
            // Sem orcamento sobrando, encerra a execucao.
            if ($orcamento <= 0) {
                $resumo['motivo_parada'] = 'limite_global';
                break;
            }

            // Processa o usuario (a funcao pode reduzir o orcamento).
            $parcial = $this->executarUsuario($usuario, $orcamento);

            // Acumula os numeros.
            $resumo['usuarios']++;
            $resumo['enviados'] += $parcial['enviados'];
            $resumo['falhas']   += $parcial['falhas'];
            $resumo['detalhes'][] = $parcial;
        }

        $resumo['terminado_em'] = $this->agoraFormatado();

        // Quando a execucao processou um unico usuario, o motivo dele
        // tambem e o motivo da execucao (util para o agendador).
        if ($resumo['motivo_parada'] === null && count($resumo['detalhes']) === 1) {
            $resumo['motivo_parada'] = $resumo['detalhes'][0]['motivo_parada'];
        }

        return $resumo;
    }

    /**
     * Envia candidaturas para UM usuario especifico.
     *
     * @param int      $usuarioId Dono da conta Gmail.
     * @param int|null $limite    Teto de e-mails desta execucao.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function enviarDoUsuario(int $usuarioId, ?int $limite = null): array
    {
        // Orcamento: explicito > limite global.
        $orcamento = $limite;

        // Sem limite informado: usa o teto configurado.
        if ($orcamento === null) {
            $orcamento = max(0, (int) $this->cron->envioAutomaticoLimitePorExecucao);
        }

        // Usuario precisa existir.
        $usuario = $this->usuarios->find($usuarioId);

        if (! is_array($usuario)) {
            return $this->resumoDeParada('usuario_inexistente');
        }

        // Conta pendente ou bloqueada nao recebe envio.
        if (valor_ou($usuario, 'status', '') !== 'ativo' || inteiro_ou($usuario, 'ativo', 0) !== 1) {
            return $this->resumoDeParada('usuario_inativo');
        }

        // Executa apenas esse usuario.
        $parcial = $this->executarUsuario($usuario, $orcamento);

        return [
            'iniciado_em'   => $this->agoraFormatado(),
            'terminado_em'  => $this->agoraFormatado(),
            'usuarios'      => 1,
            'enviados'      => $parcial['enviados'],
            'falhas'        => $parcial['falhas'],
            'motivo_parada' => $parcial['motivo_parada'],
            'detalhes'      => [$parcial],
        ];
    }

    /**
     * Executa o envio de um unico usuario.
     *
     * O orcamento e recebido por referencia para que a execucao global
     * saiba quanto ainda resta depois deste usuario.
     *
     * @param array<string, mixed> $usuario
     * @param int                  $orcamento Teto de e-mails (por referencia).
     *
     * @return array<string, mixed>
     */
    private function executarUsuario(array $usuario, int &$orcamento): array
    {
        // Identificacao do usuario.
        $usuarioId = (int) $usuario['id'];

        // Resumo deste usuario.
        $resultado = [
            'usuario_id'    => $usuarioId,
            'nome'          => texto_ou($usuario, 'nome'),
            'enviados'      => 0,
            'falhas'        => 0,
            'motivo_parada' => null,
            'mensagens'     => [],
        ];

        // O usuario precisa ter ligado o envio automatico.
        if (inteiro_ou($usuario, 'envio_automatico', 0) !== 1) {
            $resultado['motivo_parada'] = 'envio_desligado';

            return $resultado;
        }

        // Configuracao do usuario (com os padroes quando ainda nao salvou).
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        // Checklist minimo: remetente, APP PASSWORD e curriculo no disco.
        if (! $this->prontoParaEnviar($configuracao)) {
            $resultado['motivo_parada'] = 'sem_configuracao';

            return $resultado;
        }

        // Regras de envio (janela, intervalo e limite diario).
        //
        // O verificador e COMPARTILHADO com o segundo provedor: e o mesmo
        // limite diario para os e-mails dos dois provedores.
        $verificacao = $this->verificador->podeEnviarAgora($usuarioId, $this->agora);

        if (! $verificacao['pode']) {
            $resultado['motivo_parada'] = (string) $verificacao['motivo'];

            // A janela fechada explica o motivo para o usuario.
            if ((string) $verificacao['motivo'] === 'janela_fechada') {
                $resultado['mensagens'][] = (string) $verificacao['mensagem'];
            }

            return $resultado;
        }

        // Vaga prioritaria da fila (com e-mail e nao candidatada).
        $vaga = $this->vagas->proximaParaEnvio($usuarioId);

        if ($vaga === null) {
            $resultado['motivo_parada'] = 'sem_fila';

            return $resultado;
        }

        // Orcamento da execucao.
        if ($orcamento <= 0) {
            $resultado['motivo_parada'] = 'limite_global';

            return $resultado;
        }

        // Momento da tentativa.
        $agora = $this->agoraFormatado();

        // Envio real (a credencial nunca sai da EnviadorEmail).
        $retorno = $this->enviador->enviar($configuracao, $vaga);

        $status   = texto_ou($retorno, 'status', EnviadorEmailInterface::STATUS_ERRO_SMTP);
        $mensagem = texto_ou($retorno, 'mensagem');
        $assunto  = texto_ou($retorno, 'assunto');

        // Uma tentativa consome o orcamento.
        $orcamento--;

        // Registra a tentativa no historico.
        // Mensagem de erro so faz sentido quando o envio nao deu certo.
        $mensagemErro = $mensagem;

        if ($status === self::STATUS_ENVIADO) {
            $mensagemErro = '';
        }

        $this->emails->registrar([
            'usuario_id'    => $usuarioId,
            'vaga_id'       => (int) $vaga['id'],
            'destinatario'  => mb_substr(trim(texto_ou($vaga, 'email_contato')), 0, 180),
            'assunto'       => mb_substr($assunto, 0, 255),
            'origem'        => 'automatico',
            'status'        => $status,
            'mensagem_erro' => $mensagemErro,
            'enviado_em'    => $agora,
        ]);

        if ($status === self::STATUS_ENVIADO) {
            // Sucesso: sai da fila e aumenta os contadores.
            $resultado['enviados'] = 1;

            $this->vagas->marcarCandidatado((int) $vaga['id'], $usuarioId);
            $this->verificador->registrarEnvio($usuarioId, $agora);
        } else {
            // Falha: registra e decide se a vaga continua ou sai da fila.
            $resultado['falhas']      = 1;
            $resultado['mensagens'][] = $mensagem;
            $resultado['motivo_parada'] = $status;

            // Endereco invalido e definitivo: nao vale tentar de novo.
            if ($status === EnviadorEmailInterface::STATUS_ERRO_DESTINATARIO) {
                $this->vagas->marcarCandidatado((int) $vaga['id'], $usuarioId);
            }

            // Registra o instante da tentativa para respeitar o intervalo.
            $this->configuracoes->marcarTentativa($usuarioId, $agora);
        }

        // Marca a execucao (tambem gira a rotacao de usuarios).
        $this->usuarios->registrarExecucaoEnvio($usuarioId, $resultado['enviados']);

        return $resultado;
    }

    /**
     * Informa se o usuario tem o minimo para enviar: remetente, APP
     * PASSWORD cadastrada e curriculo em PDF existente no disco.
     *
     * @param array<string, mixed> $configuracao
     */
    private function prontoParaEnviar(array $configuracao): bool
    {
        // Remetente preenchido.
        if (trim(texto_ou($configuracao, 'email_remetente')) === '') {
            return false;
        }

        // APP PASSWORD cadastrada (o valor cifrado presente basta).
        if (! $this->configuracoes->temAppPassword($configuracao)) {
            return false;
        }

        // Curriculo em PDF existente.
        return $this->curriculos->existe(valor_ou($configuracao, 'curriculo_pdf', null));
    }

    /**
     * Momento atual no formato do banco.
     */
    private function agoraFormatado(): string
    {
        return $this->agora->format('Y-m-d H:i:s');
    }

    /**
     * Resumo vazio usado por enviarGlobal().
     *
     * @return array<string, mixed>
     */
    private function resumoVazio(): array
    {
        return [
            'iniciado_em'   => $this->agoraFormatado(),
            'terminado_em'  => null,
            'usuarios'      => 0,
            'enviados'      => 0,
            'falhas'        => 0,
            'motivo_parada' => null,
            'detalhes'      => [],
        ];
    }

    /**
     * Resumo devolvido quando a execucao nem chegou a acontecer.
     *
     * @return array<string, mixed>
     */
    private function resumoDeParada(string $motivo): array
    {
        $agora = $this->agoraFormatado();

        return [
            'iniciado_em'   => $agora,
            'terminado_em'  => $agora,
            'usuarios'      => 0,
            'enviados'      => 0,
            'falhas'        => 0,
            'motivo_parada' => $motivo,
            'detalhes'      => [],
        ];
    }
}
