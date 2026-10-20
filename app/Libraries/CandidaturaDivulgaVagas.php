<?php

namespace App\Libraries;

use App\Models\ConfiguracaoModel;
use App\Models\EmailEnviadoModel;
use App\Models\ExecucaoDivulgaVagasModel;
use App\Models\VagaModel;
use App\Libraries\ArmazenamentoCurriculo;
use App\Contracts\EnviadorEmailInterface;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: CANDIDATURA DO DIVULGA VAGAS (CRON 2)
 * --------------------------------------------------------------------------
 *
 * Candidata-se as vagas do Divulga Vagas pelo formulario do proprio
 * site e (quando a vaga tem e-mail de contacto valido) tambem envia o
 * e-mail de candidatura.
 *
 * REGRA DE OURO: este caminho NUNCA busca vagas. Ele apenas:
 *   1. confere os limites do bot (do usuario) e o intervalo;
 *   2. escolhe UMA vaga pendente (prioridade do projeto);
 *   3. chama o bot Python no MODO CANDIDATAR (anexa o curriculo em PDF,
 *      marca os termos e confirma o envio);
 *   4. se a vaga tem e-mail valido, envia o e-mail pelo MESMO mecanismo
 *      do SerpApi (EnviadorEmail, com a APP PASSWORD do usuario);
 *   5. repete ate a fila acabar (o loop esvazia a fila de uma vez, com
 *      uma pausa curta entre as candidaturas).
 *
 * REGRA DE CONTAGEM:
 *   - a candidatura pelo BOT nao consome o limite de e-mails (o limite de
 *     e-mails protege a conta Gmail);
 *   - o envio por E-MAIL consome o limite de e-mails;
 *   - o bot tem limites proprios (divulgavagas_max_candidaturas_bot_dia e
 *     o intervalo entre candidaturas).
 *
 * SITUACOES da candidatura (coluna candidatura_divulgavagas_status):
 *   - 'pendente'       -> o bot ainda nao tentou;
 *   - 'enviada'        -> o bot confirmou (sem e-mail de contacto);
 *   - 'enviada_email'  -> o bot confirmou E o e-mail foi enviado;
 *   - 'pendente_email' -> o bot confirmou, mas o e-mail ficou para o
 *                         proximo ciclo (limite, horario ou intervalo);
 *   - 'erro'           -> falhou tres vezes;
 *   - 'cloudflare'     -> bloqueado pelo Cloudflare (nao retenta).
 */
class CandidaturaDivulgaVagas
{
    /** @var ProvedorDivulgaVagas */
    private ProvedorDivulgaVagas $provedor;

    /** @var VagaModel */
    private VagaModel $vagas;

    /** @var LimiteExecucaoManual */
    private LimiteExecucaoManual $limites;

    /** @var VerificadorLimiteEnvio */
    private VerificadorLimiteEnvio $verificador;

    /** @var EnviadorEmail */
    private EnviadorEmail $enviador;

    /** @var ConfiguracaoModel */
    private ConfiguracaoModel $configuracoes;

    /** @var EmailEnviadoModel */
    private EmailEnviadoModel $emails;

    /** @var ArmazenamentoCurriculo */
    private ArmazenamentoCurriculo $curriculos;

    /** @var ExecucaoDivulgaVagasModel */
    private ExecucaoDivulgaVagasModel $execucoes;

    public function __construct(
        ?ProvedorDivulgaVagas $provedor = null,
        ?VagaModel $vagas = null,
        ?LimiteExecucaoManual $limites = null,
        ?VerificadorLimiteEnvio $verificador = null,
        ?EnviadorEmail $enviador = null,
        ?ConfiguracaoModel $configuracoes = null,
        ?EmailEnviadoModel $emails = null,
        ?ArmazenamentoCurriculo $curriculos = null,
        ?ExecucaoDivulgaVagasModel $execucoes = null
    ) {
        if ($provedor === null) {
            $provedor = new ProvedorDivulgaVagas();
        }

        if ($vagas === null) {
            $vagas = model(VagaModel::class);
        }

        if ($limites === null) {
            $limites = new LimiteExecucaoManual();
        }

        if ($verificador === null) {
            $verificador = new VerificadorLimiteEnvio();
        }

        if ($enviador === null) {
            $enviador = new EnviadorEmail();
        }

        if ($configuracoes === null) {
            $configuracoes = model(ConfiguracaoModel::class);
        }

        if ($emails === null) {
            $emails = model(EmailEnviadoModel::class);
        }

        if ($curriculos === null) {
            $curriculos = new ArmazenamentoCurriculo();
        }

        if ($execucoes === null) {
            $execucoes = model(ExecucaoDivulgaVagasModel::class);
        }

        $this->provedor      = $provedor;
        $this->vagas         = $vagas;
        $this->limites       = $limites;
        $this->verificador   = $verificador;
        $this->enviador      = $enviador;
        $this->configuracoes = $configuracoes;
        $this->emails        = $emails;
        $this->curriculos    = $curriculos;
        $this->execucoes     = $execucoes;
    }

    /**
     * Candidata as vagas do usuario, uma a uma, ate a fila acabar.
     *
     * O parametro $maximoVagas limita o loop (0 = todas as vagas da
     * fila). O limite diario do bot continua valendo: quando ele acaba,
     * o loop para e a execucao registra o motivo.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function candidatarDoUsuario(int $usuarioId, string $origem = 'cron', int $maximoVagas = 0): array
    {
        $resumo = $this->resumoVazio();

        // Registro de auditoria desta execucao.
        $idExecucao = $this->execucoes->abrir('candidatar', $origem, $usuarioId, $usuarioId);

        // Quantas vagas esta execucao pode candidatar (0 = todas da fila).
        $total = PHP_INT_MAX;

        if ($maximoVagas > 0) {
            $total = $maximoVagas;
        }

        $candidaturas = 0;
        $emails = 0;
        $erros = 0;
        $confirmacoes = 0;
        $avisos = [];

        // O registro de cada vaga (o painel mostra essa lista).
        $detalhes = [];

        // As vagas ja processadas nesta execucao (o loop nao repete).
        $processadas = [];

        for ($i = 0; $i < $total; $i++) {
            // 1) Escolhe UMA vaga pendente (prioridade do projeto).
            //
            // A escolha vem antes da checagem dos limites do bot: quando a
            // vaga esta com a situacao 'pendente_email' (o bot ja confirmou
            // em um ciclo anterior), este ciclo so tenta o e-mail e o bot
            // nem sera chamado. nesse caso os limites do bot nao podem
            // travar o reenvio do e-mail.
            $vaga = $this->vagas->proximaParaBot($usuarioId, 3, $processadas);

            // A fila acabou: para aqui.
            if ($vaga === null) {
                if ($i === 0) {
                    $resumo['motivo_parada'] = 'sem_fila';
                }

                break;
            }

            $vagaId = (int) $vaga['id'];
            $situacao = texto_ou($vaga, 'candidatura_divulgavagas_status', 'pendente');

            // Esta vaga nao volta para a fila nesta execucao.
            $processadas[] = $vagaId;

            $resumo['vaga_id'] = $vagaId;
            $resumo['vaga_titulo'] = texto_ou($vaga, 'titulo');

            // O nucleo da candidatura (usado tambem pela candidatura de UMA
            // vaga escolhida pelo usuario).
            $this->executarCandidatura($usuarioId, $vaga, $resumo);

            $candidaturas += (int) $resumo['candidaturas_enviadas'];
            $emails += (int) $resumo['emails_enviados'];
            $erros += (int) $resumo['erros'];
            $confirmacoes += (int) $resumo['confirmacoes'];

            if ($resumo['mensagem'] !== null) {
                $avisos[] = (string) $resumo['mensagem'];
            }

            // O registro da vaga (o painel mostra o que aconteceu).
            $detalhes[] = [
                'vaga_id' => $vagaId,
                'vaga_titulo' => texto_ou($vaga, 'titulo'),
                'vaga_empresa' => texto_ou($vaga, 'empresa'),
                'situacao' => $situacao,
                'bot_ok' => (bool) $resumo['bot_ok'],
                'candidaturas_enviadas' => (int) $resumo['candidaturas_enviadas'],
                'emails_enviados' => (int) $resumo['emails_enviados'],
                'confirmacao_url' => (string) $resumo['ultima_confirmacao_url'],
                'erro' => $resumo['mensagem'],
            ];

            // O limite do bot acabou: para a fila aqui (o proximo ciclo
            // continua de onde parou).
            $motivo = $resumo['motivo_parada'];

            if ($motivo === 'limite_diario_bot' || $motivo === 'aguardando_intervalo_bot') {
                $resumo['motivo_parada'] = $motivo;
                break;
            }

            // Pausa educada entre duas candidaturas (nao martela o site).
            $this->pausaEntreCandidaturas();
        }

        $resumo['candidaturas_enviadas'] = $candidaturas;
        $resumo['emails_enviados'] = $emails;
        $resumo['erros'] = $erros;
        $resumo['confirmacoes'] = $confirmacoes;
        $resumo['vagas_candidatas'] = count($detalhes);
        $resumo['detalhes'] = $detalhes;

        if ($avisos !== []) {
            $resumo['mensagem'] = implode(' ', $avisos);
        }

        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        // Status da execucao: erro quando houve alguma falha.
        $status = 'sucesso';

        if ($erros > 0) {
            $status = 'erro';
        }

        $this->execucoes->finalizar($idExecucao, $status, $resumo);

        return $resumo;
    }

    /**
     * Nucleo da candidatura de UMA vaga.
     *
     * Passos: limites do bot, o bot (quando ainda nao candidatada) e o
     * e-mail de contacto (a regra de duplo envio).
     *
     * @param array<string, mixed> $vaga
     * @param array<string, mixed> $resumo
     */
    private function executarCandidatura(int $usuarioId, array $vaga, array &$resumo): void
    {
        $situacao = texto_ou($vaga, 'candidatura_divulgavagas_status', 'pendente');

        $resumo['vaga_id'] = (int) $vaga['id'];
        $resumo['vaga_titulo'] = texto_ou($vaga, 'titulo');

        // 1) Limites do bot (dia e intervalo). So valem quando o bot
        //    realmente vai rodar (situacao 'pendente').
        if ($situacao === 'pendente') {
            // O loop ignora o intervalo entre as candidaturas (a pausa
            // entre elas e feita pelo proprio loop): o objetivo e
            // esvaziar a fila, nao deixar vaga para tras.
            $permitido = $this->limites->podeCandidatarPeloBot($usuarioId, true);

            if (! $permitido['pode']) {
                $resumo['motivo_parada'] = (string) $permitido['motivo'];
                $resumo['mensagem'] = $this->mensagemDoLimite($permitido);
                $resumo['terminado_em'] = date('Y-m-d H:i:s');

                return;
            }
        }

        // 2) O bot so roda quando a vaga ainda nao foi candidatada.
        //    A situacao 'pendente_email' significa que o bot JA confirmou
        //    e so o e-mail ficou para depois.
        if ($situacao === 'pendente') {
            $this->candidatarPeloBot($usuarioId, $vaga, $resumo);

            // Falha do bot: nao segue para o e-mail.
            if (! $resumo['bot_ok']) {
                $resumo['terminado_em'] = date('Y-m-d H:i:s');

                return;
            }
        } else {
            // O bot ja tinha confirmado antes.
            $resumo['bot_ok'] = true;
            $resumo['bot_pulado'] = true;
        }

        // 3) E-mail de contacto (a regra de duplo envio).
        $this->enviarEmailSeHouver($usuarioId, $vaga, $resumo);
    }

    /**
     * Pausa educada entre duas candidaturas do loop.
     *
     * O site nao deve receber candidaturas em cima uma da outra: a
     * pausa e configuravel (divulgavagas.pausaEntreCandidaturas).
     */
    private function pausaEntreCandidaturas(): void
    {
        $segundos = (int) config('DivulgaVagas')->pausaEntreCandidaturas;

        if ($segundos <= 0) {
            return;
        }

        usleep($segundos * 1000000);
    }

    /**
     * Candidata a UMA vaga especifica do usuario.
     *
     * E o caminho do botao "Candidatar agora": a vaga ja foi escolhida
     * pelo usuario, mas TODOS os limites continuam valendo (os do bot e
     * o de e-mail).
     */
    public function candidatarVaga(int $usuarioId, int $vagaId, string $origem = 'manual_usuario'): array
    {
        $resumo = $this->resumoVazio();

        // Registro de auditoria desta execucao.
        $idExecucao = $this->execucoes->abrir('candidatar', $origem, $usuarioId, $usuarioId);

        // A vaga tem que ser do usuario e do provedor.
        $vaga = $this->vagas->buscarDoUsuario($vagaId, $usuarioId);

        if ($vaga === null || texto_ou($vaga, 'provedor_vaga') !== 'divulgavagas') {
            $resumo['motivo_parada'] = 'vaga_nao_encontrada';
            $resumo['terminado_em'] = date('Y-m-d H:i:s');
            $this->execucoes->finalizar($idExecucao, 'erro', $resumo);

            return $resumo;
        }

        $this->executarCandidatura($usuarioId, $vaga, $resumo);

        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        $status = 'sucesso';

        if ($resumo['erros'] > 0) {
            $status = 'erro';
        }

        $this->execucoes->finalizar($idExecucao, $status, $resumo);

        return $resumo;
    }

    /**
     * Candidata a UMA vaga de cada usuario ativo (um por vez).
     *
     * @return array<string, mixed>
     */
    public function candidatarGlobal(string $origem = 'cron', int $maximoVagas = 0): array
    {
        $resumo = $this->resumoVazio();

        $usuarios = model('UsuarioModel')->listarParaEnvio();

        foreach ($usuarios as $usuario) {
            $detalhe = $this->candidatarDoUsuario((int) $usuario['id'], $origem, $maximoVagas);

            $resumo['candidaturas_enviadas'] += (int) $detalhe['candidaturas_enviadas'];
            $resumo['emails_enviados'] += (int) $detalhe['emails_enviados'];
            $resumo['erros'] += (int) $detalhe['erros'];
        }

        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        return $resumo;
    }

    /**
     * Chama o bot para candidatar a vaga e grava o resultado.
     *
     * @param array<string, mixed> $vaga
     * @param array<string, mixed> $resumo
     */
    private function candidatarPeloBot(int $usuarioId, array $vaga, array &$resumo): void
    {
        $vagaId = (int) $vaga['id'];
        $urlEnvio = texto_ou($vaga, 'url_envio_divulgavagas');

        // Sem URL de envio: a vaga nao serve para candidatura.
        if ($urlEnvio === '') {
            $resumo['bot_ok'] = false;
            $resumo['erros']++;
            $resumo['mensagem'] = 'A vaga nao tem a URL de envio do site.';

            $this->vagas->atualizarCandidaturaDivulgaVagas($vagaId, $usuarioId, [
                'candidatura_divulgavagas_tentativas' => (int) texto_ou($vaga, 'candidatura_divulgavagas_tentativas', 0) + 1,
                'candidatura_divulgavagas_erro'        => 'A vaga nao tem a URL de envio do site.',
            ]);

            return;
        }

        // O curriculo em PDF do usuario.
        $curriculo = $this->caminhoDoCurriculo($usuarioId);

        if ($curriculo === '') {
            $resumo['bot_ok'] = false;
            $resumo['erros']++;
            $resumo['mensagem'] = 'O usuario nao tem curriculo em PDF cadastrado.';

            return;
        }

        // Chamada ao bot (MODO CANDIDATAR).
        $retorno = $this->provedor->candidatar($urlEnvio, $curriculo, $usuarioId);

        // Falha do bot: conta a tentativa e decide a situacao.
        if (! $retorno['sucesso']) {
            $this->registrarFalhaDoBot($usuarioId, $vaga, (string) $retorno['erro'], $resumo);

            return;
        }

        // Sucesso: o site confirmou o envio.
        //
        // A confirmacao (a pagina de sucesso do site) e guardada: e a
        // prova de que o curriculo foi mesmo postado.
        $confirmacao = trim((string) texto_ou($retorno['dados'], 'url_confirmacao', ''));

        // A URL da confirmacao (null quando o site nao a devolveu).
        $urlConfirmacao = null;

        if ($confirmacao !== '') {
            $urlConfirmacao = $confirmacao;
        }

        $this->vagas->atualizarCandidaturaDivulgaVagas($vagaId, $usuarioId, [
            'candidatura_divulgavagas_status'             => 'enviada',
            'candidatura_divulgavagas_erro'                => null,
            'candidatura_divulgavagas_em'                  => date('Y-m-d H:i:s'),
            'candidatura_divulgavagas_confirmado_em'      => date('Y-m-d H:i:s'),
            'candidatura_divulgavagas_confirmacao_url'    => $urlConfirmacao,
            'data_ultimo_envio_bot'                        => date('Y-m-d H:i:s'),
        ]);

        // Contador do bot (limite diario do bot).
        $this->limites->registrarCandidaturaBot($usuarioId);

        $resumo['bot_ok'] = true;
        $resumo['candidaturas_enviadas']++;
        $resumo['confirmacoes']++;

        // A prova, para o painel mostrar.
        $resumo['ultima_confirmacao_url'] = $confirmacao;
    }

    /**
     * Registra a falha do bot e define a situacao da vaga.
     *
     * @param array<string, mixed> $vaga
     * @param array<string, mixed> $resumo
     */
    private function registrarFalhaDoBot(int $usuarioId, array $vaga, string $erro, array &$resumo): void
    {
        $vagaId = (int) $vaga['id'];
        $tentativas = (int) texto_ou($vaga, 'candidatura_divulgavagas_tentativas', 0) + 1;

        // Situacao da vaga depois da falha.
        $situacao = 'pendente';

        // Cloudflare: nao retenta automaticamente.
        if (stripos($erro, 'cloudflare') !== false) {
            $situacao = 'cloudflare';
        } elseif ($tentativas >= 3) {
            $situacao = 'erro';
        }

        $this->vagas->atualizarCandidaturaDivulgaVagas($vagaId, $usuarioId, [
            'candidatura_divulgavagas_status'     => $situacao,
            'candidatura_divulgavagas_tentativas' => $tentativas,
            'candidatura_divulgavagas_erro'        => mb_substr($erro, 0, 900),
        ]);

        $resumo['bot_ok'] = false;
        $resumo['erros']++;
        $resumo['mensagem'] = $erro;
    }

    /**
     * Envia o e-mail de contacto (a regra de duplo envio).
     *
     * @param array<string, mixed> $vaga
     * @param array<string, mixed> $resumo
     */
    private function enviarEmailSeHouver(int $usuarioId, array $vaga, array &$resumo): void
    {
        $vagaId = (int) $vaga['id'];
        $destinatario = trim(texto_ou($vaga, 'email_contato'));

        // Sem e-mail de contacto: a candidatura pelo bot ja bastou.
        if ($destinatario === '' || ! filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            $resumo['email_nao_aplicavel'] = true;

            return;
        }

        // O e-mail so sai dentro dos limites de e-mail do usuario.
        $verificacao = $this->verificador->podeEnviarAgora($usuarioId);

        if (! $verificacao['pode']) {
            // O bot ja confirmou: a vaga fica esperando o proximo ciclo
            // so pelo e-mail (o bot NAO e chamado de novo).
            $this->vagas->atualizarCandidaturaDivulgaVagas($vagaId, $usuarioId, [
                'candidatura_divulgavagas_status' => 'pendente_email',
            ]);

            $resumo['email_pendente'] = true;
            $resumo['mensagem'] = 'E-mail nao enviado agora: ' . (string) $verificacao['mensagem'];

            return;
        }

        // Configuracao do usuario (remetente, APP PASSWORD, corpo).
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);

        // Envio pelo MESMO mecanismo do SerpApi.
        $retorno = $this->enviador->enviar($configuracao, $vaga);

        // O retorno traz status, mensagem e assunto (igual ao SerpApi).
        $statusEnvio = texto_ou($retorno, 'status', EnviadorEmailInterface::STATUS_ERRO_SMTP);
        $mensagemEnvio = texto_ou($retorno, 'mensagem');
        $assunto = texto_ou($retorno, 'assunto');
        $sucesso = $statusEnvio === EnviadorEmailInterface::STATUS_ENVIADO;

        $situacao = 'enviada_email';
        $emailEnviadoEm = null;
        $erroEmail = null;

        if (! $sucesso) {
            $situacao = 'pendente_email';
            $erroEmail = mb_substr($mensagemEnvio, 0, 900);
            $resumo['erros']++;
            $resumo['mensagem'] = $erroEmail;
        } else {
            // Consome o limite diario de e-mails.
            $this->verificador->registrarEnvio($usuarioId, date('Y-m-d H:i:s'));

            $emailEnviadoEm = date('Y-m-d H:i:s');
            $resumo['emails_enviados']++;
        }

        // Grava a situacao da candidatura.
        $this->vagas->atualizarCandidaturaDivulgaVagas($vagaId, $usuarioId, [
            'candidatura_divulgavagas_status' => $situacao,
            'email_enviado_divulgavagas_em'   => $emailEnviadoEm,
            'email_enviado_divulgavagas_erro' => $erroEmail,
        ]);

        // Registro no historico de envios (com o provedor do novo caminho).
        $statusRegistro = 'erro';
        $mensagemRegistro = mb_substr($mensagemEnvio, 0, 900);

        if ($sucesso) {
            $statusRegistro = 'enviado';
            $mensagemRegistro = null;
        }

        $this->emails->registrar([
            'usuario_id'   => $usuarioId,
            'vaga_id'      => $vagaId,
            'destinatario' => $destinatario,
            'assunto'      => mb_substr($assunto, 0, 255),
            'origem'       => 'automatico',
            'provedor'     => 'divulgavagas',
            'status'       => $statusRegistro,
            'mensagem_erro' => $mensagemRegistro,
            'enviado_em'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Caminho do curriculo em PDF do usuario (no disco).
     */
    private function caminhoDoCurriculo(int $usuarioId): string
    {
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);
        $caminho = valor_ou($configuracao, 'curriculo_pdf', null);

        if ($caminho === null || $caminho === '') {
            return '';
        }

        // O caminho guardado e relativo a pasta de uploads.
        $completo = $this->curriculos->caminhoAbsoluto((string) $caminho);

        if ($completo === '' || ! is_file($completo)) {
            return '';
        }

        return $completo;
    }

    /**
     * Mensagem do limite que barrou a execucao.
     *
     * @param array<string, mixed> $permitido
     */
    private function mensagemDoLimite(array $permitido): string
    {
        $motivo = (string) $permitido['motivo'];

        if ($motivo === 'limite_diario_bot') {
            return 'Limite diario de candidaturas do bot atingido ('
                . (int) $permitido['usadas'] . ' de ' . (int) $permitido['limite'] . ').';
        }

        if ($motivo === 'aguardando_intervalo_bot') {
            return 'Aguardando o intervalo do bot (' . (int) $permitido['espera_segundos'] . ' segundo(s)).';
        }

        return 'O bot nao pode candidatar agora.';
    }

    /**
     * Resumo vazio de uma execucao.
     *
     * @return array<string, mixed>
     */
    private function resumoVazio(): array
    {
        return [
            'iniciado_em'           => date('Y-m-d H:i:s'),
            'terminado_em'          => null,
            'vaga_id'               => 0,
            'vaga_titulo'           => '',
            'bot_ok'                => false,
            'bot_pulado'            => false,
            'candidaturas_enviadas' => 0,
            'confirmacoes'          => 0,
            'ultima_confirmacao_url' => '',
            'emails_enviados'       => 0,
            'email_pendente'        => false,
            'email_nao_aplicavel'   => false,
            'erros'                 => 0,
            'motivo_parada'         => null,
            'mensagem'              => null,
        ];
    }
}
