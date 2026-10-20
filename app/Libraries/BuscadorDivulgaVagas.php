<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use App\Models\ExecucaoDivulgaVagasModel;
use App\Models\LogConsultaModel;
use App\Models\PalavraChaveModel;
use App\Models\VagaModel;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: BUSCADOR DO DIVULGA VAGAS (CRON 1)
 * --------------------------------------------------------------------------
 *
 * Busca vagas no site Divulga Vagas e salva na tabela `vagas`, com a
 * MESMA deduplicacao e a MESMA priorizacao do SerpApi.
 *
 * REGRA DE OURO: este caminho NUNCA candidata a nada. Ele apenas:
 *   1. monta a URL de listagem (termo + cidade + estado);
 *   2. chama o bot Python no MODO BUSCAR;
 *   3. extrai o e-mail de contato (Etapa 1 na descricao, Etapa 2 na
 *      pagina da vaga, quando a descricao nao tem e-mail);
 *   4. salva a vaga com provedor_vaga = 'divulgavagas' e a candidatura
 *      pendente (quem candidate e o CRON 2).
 *
 * O que este caminho NAO faz:
 *   - nao anexa curriculo, nao clica em "enviar", nao manda e-mail.
 */
class BuscadorDivulgaVagas
{
    /** @var ProvedorDivulgaVagas */
    private ProvedorDivulgaVagas $provedor;

    /** @var PalavraChaveModel */
    private PalavraChaveModel $palavras;

    /** @var DeduplicadoraVagas */
    private DeduplicadoraVagas $deduplicadora;

    /** @var ExtratorEmail */
    private ExtratorEmail $extrator;

    /** @var VagaModel */
    private VagaModel $vagas;

    /** @var LogConsultaModel */
    private LogConsultaModel $logs;

    /** @var ExecucaoDivulgaVagasModel */
    private ExecucaoDivulgaVagasModel $execucoes;

    public function __construct(
        ?ProvedorDivulgaVagas $provedor = null,
        ?PalavraChaveModel $palavras = null,
        ?DeduplicadoraVagas $deduplicadora = null,
        ?ExtratorEmail $extrator = null,
        ?VagaModel $vagas = null,
        ?LogConsultaModel $logs = null,
        ?ExecucaoDivulgaVagasModel $execucoes = null
    ) {
        if ($provedor === null) {
            $provedor = new ProvedorDivulgaVagas();
        }

        if ($palavras === null) {
            $palavras = model(PalavraChaveModel::class);
        }

        if ($deduplicadora === null) {
            $deduplicadora = new DeduplicadoraVagas();
        }

        if ($extrator === null) {
            $extrator = new ExtratorEmail();
        }

        if ($vagas === null) {
            $vagas = model(VagaModel::class);
        }

        if ($logs === null) {
            $logs = model(LogConsultaModel::class);
        }

        if ($execucoes === null) {
            $execucoes = model(ExecucaoDivulgaVagasModel::class);
        }

        $this->provedor     = $provedor;
        $this->palavras     = $palavras;
        $this->deduplicadora = $deduplicadora;
        $this->extrator     = $extrator;
        $this->vagas        = $vagas;
        $this->logs         = $logs;
        $this->execucoes    = $execucoes;
    }

    /**
     * Busca vagas de UM usuario.
     *
     * @param int|null $maximoCombinacoes Teto de combinacoes (termo + cidade)
     *                                     desta execucao. Nulo = sem teto.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function buscarDoUsuario(
        int $usuarioId,
        string $origem = 'cron',
        ?int $maximoCombinacoes = null
    ): array {
        $resumo = $this->resumoVazio();

        // Registro de auditoria desta execucao.
        $idExecucao = $this->execucoes->abrir('buscar', $origem, $usuarioId, $usuarioId);

        // A lista de palavras-chave depende de como a execucao foi pedida:
        //
        // - execucao manual (usuario ou admin): TODAS as palavras ativas
        //   do usuario. Quem pediu, pediu na hora: o horario agendado
        //   nao pode impedir a busca;
        // - agendador (cron): so as palavras DEVIDAS no horario do
        //   segundo provedor (cada provedor tem o seu horario).
        $lista = $this->palavrasParaAExecucao($usuarioId, $origem);

        if ($lista === []) {
            $resumo['motivo_parada'] = 'sem_palavras';
            $resumo['terminado_em'] = date('Y-m-d H:i:s');
            $this->execucoes->finalizar($idExecucao, 'sucesso', $resumo);

            return $resumo;
        }

        $resumo['palavras_chave'] = count($lista);

        $processadas = 0;
        $erros = 0;

        foreach ($lista as $palavra) {
            // Quantas consultas esta palavra consumiu agora.
            $consultas = 0;

            // Cada combinacao e um par (termo + cidade).
            foreach ($this->cidadesDe($palavra) as $cidade) {
                if ($maximoCombinacoes !== null && $processadas >= $maximoCombinacoes) {
                    $resumo['motivo_parada'] = 'limite_combinacoes';
                    break 2;
                }

                $processadas++;
                $retorno = $this->executarCombinacao($usuarioId, $palavra, $cidade, $origem);

                $resumo['vagas_inseridas'] += $retorno['inseridas'];
                $resumo['vagas_atualizadas'] += $retorno['atualizadas'];
                $resumo['vagas_repetidas'] += $retorno['repetidas'];
                $resumo['com_email'] += $retorno['com_email'];
                $resumo['sem_email'] += $retorno['sem_email'];
                $resumo['consultas']++;
                $consultas++;
                $erros += $retorno['erros'];

                $resumo['detalhes'][] = $retorno;
            }

            // Agenda a proxima consulta desta palavra no Divulga Vagas
            // (o horario do SerpApi nao e tocado).
            $this->agendarProximaExecucao($palavra, $consultas);
        }

        $resumo['erros'] = $erros;
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
     * As palavras-chave que esta execucao pode consultar.
     *
     * @param int    $usuarioId Dono das palavras-chave.
     * @param string $origem     Origem da execucao (cron, manual_usuario,
     *                            manual_admin).
     *
     * @return list<array<string, mixed>>
     */
    private function palavrasParaAExecucao(int $usuarioId, string $origem): array
    {
        // Execucao manual: o usuario pediu agora, entao todas as
        // palavras ativas dele entram na fila (o horario agendado nao
        // pode impedir a busca).
        if ($origem === 'manual_usuario' || $origem === 'manual_admin') {
            return $this->palavras->listarAtivasDoUsuario($usuarioId);
        }

        // Agendador: so as palavras devidas no horario DESTE provedor.
        return $this->palavras->listarDevidasDoUsuarioDivulgaVagas($usuarioId);
    }

    /**
     * Agenda a proxima consulta da palavra no Divulga Vagas.
     *
     * A frequencia escolhida pelo usuario (em horas) e respeitada: a
     * palavra espera antes de ser consultada de novo. O horario do
     * SerpApi nao e tocado (cada provedor tem o seu).
     *
     * @param array<string, mixed> $palavra  Palavra-chave consultada.
     * @param int                   $consultas Consultas consumidas agora.
     */
    private function agendarProximaExecucao(array $palavra, int $consultas): void
    {
        $agora = date('Y-m-d H:i:s');

        // Frequencia minima escolhida pelo usuario (em horas).
        $frequencia = max(1, (int) texto_ou($palavra, 'frequencia_horas', 24));

        // Proxima consulta do Divulga Vagas para esta palavra.
        $proxima = date('Y-m-d H:i:s', strtotime('+' . $frequencia . ' hours', strtotime($agora)));

        $this->palavras->registrarExecucaoDivulgaVagas((int) $palavra['id'], $agora, $proxima, $consultas);
    }

    /**
     * Busca UMA combinacao (termo + cidade) escolhida pelo usuario.
     *
     * E o caminho da execucao manual: uma combinacao por vez, sempre
     * passando pelos mesmos limites do CRON 1.
     *
     * @param array<string, mixed> $palavra Palavra-chave do usuario.
     */
    public function buscarCombinacao(
        int $usuarioId,
        array $palavra,
        string $cidade,
        string $origem = 'manual_usuario'
    ): array {
        $resumo = $this->resumoVazio();

        $resumo['palavras_chave'] = 1;

        // A combinacao escolhida.
        $retorno = $this->executarCombinacao($usuarioId, $palavra, $cidade, $origem);

        $resumo['vagas_inseridas'] += $retorno['inseridas'];
        $resumo['vagas_atualizadas'] += $retorno['atualizadas'];
        $resumo['vagas_repetidas'] += $retorno['repetidas'];
        $resumo['com_email'] += $retorno['com_email'];
        $resumo['sem_email'] += $retorno['sem_email'];
        $resumo['consultas'] = 1;
        $resumo['erros'] = $retorno['erros'];
        $resumo['detalhes'][] = $retorno;
        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        return $resumo;
    }

    /**
     * Busca vagas de TODOS os usuarios ativos, um por um.
     *
     * @return array<string, mixed>
     */
    public function buscarGlobal(string $origem = 'cron', ?int $maximoCombinacoes = null): array
    {
        $resumo = $this->resumoVazio();

        // Usuarios ativos com envio automatico ligado ou nao: todos podem
        // ter palavras-chave cadastradas.
        $usuarios = model('UsuarioModel')->listarParaBusca();

        foreach ($usuarios as $usuario) {
            $detalhe = $this->buscarDoUsuario((int) $usuario['id'], $origem, $maximoCombinacoes);

            $resumo['vagas_inseridas'] += (int) $detalhe['vagas_inseridas'];
            $resumo['vagas_atualizadas'] += (int) $detalhe['vagas_atualizadas'];
            $resumo['vagas_repetidas'] += (int) $detalhe['vagas_repetidas'];
            $resumo['com_email'] += (int) $detalhe['com_email'];
            $resumo['sem_email'] += (int) $detalhe['sem_email'];
            $resumo['consultas'] += (int) $detalhe['consultas'];
            $resumo['erros'] += (int) $detalhe['erros'];
        }

        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        return $resumo;
    }

    /**
     * Executa UMA combinacao (termo + cidade): chama o bot e salva.
     *
     * @return array<string, mixed>
     */
    private function executarCombinacao(
        int $usuarioId,
        array $palavra,
        string $cidade,
        string $origem
    ): array {
        $resumo = [
            'palavra_chave_id' => (int) $palavra['id'],
            'termo'            => texto_ou($palavra, 'termo'),
            'cidade'           => $cidade,
            'inseridas'        => 0,
            'atualizadas'      => 0,
            'repetidas'        => 0,
            'com_email'        => 0,
            'sem_email'        => 0,
            'erros'            => 0,
            'mensagem'         => null,
        ];

        // Cidade e estado (o site aceita o UF separado).
        $partes = $this->cidadeEEstado($cidade);

        // URL de listagem do site.
        $url = $this->provedor->montarUrlBusca($resumo['termo'], $partes['cidade'], $partes['estado']);

        // Chamada ao bot (MODO BUSCAR).
        $retorno = $this->provedor->buscar($url, $usuarioId);

        // Falha de infraestrutura (Chrome fora, script ausente...).
        if (! $retorno['sucesso']) {
            $resumo['erros'] = 1;
            $resumo['mensagem'] = (string) $retorno['erro'];

            $this->registrarLog($usuarioId, $palavra, $cidade, $origem, 0, 'erro', $resumo['mensagem']);

            return $resumo;
        }

        // Vagas devolvidas pelo bot.
        $vagasDoBot = [];

        if (isset($retorno['dados']['vagas']) && is_array($retorno['dados']['vagas'])) {
            $vagasDoBot = $retorno['dados']['vagas'];
        }

        foreach ($vagasDoBot as $vagaDoBot) {
            if (! is_array($vagaDoBot)) {
                continue;
            }

            $salvo = $this->salvarVagaDoBot($usuarioId, $palavra, $vagaDoBot, $cidade);

            if ($salvo['situacao'] === 'nova') {
                $resumo['inseridas']++;
            } else {
                $resumo['repetidas']++;
                $resumo['atualizadas']++;
            }

            if ($salvo['com_email']) {
                $resumo['com_email']++;
            } else {
                $resumo['sem_email']++;
            }
        }

        // Log da consulta (a chave do SerpApi fica vazia: aqui nao ha
        // consulta a API, e scraping no site).
        $status = 'sucesso';

        if ($resumo['erros'] > 0) {
            $status = 'erro';
        }

        $this->registrarLog($usuarioId, $palavra, $cidade, $origem, count($vagasDoBot), $status, $resumo['mensagem']);

        return $resumo;
    }

    /**
     * Salva (ou atualiza) uma vaga devolvida pelo bot.
     *
     * @return array{situacao: string, com_email: bool}
     */
    private function salvarVagaDoBot(
        int $usuarioId,
        array $palavra,
        array $vagaDoBot,
        string $cidade
    ): array {
        $titulo = trim(texto_ou($vagaDoBot, 'titulo'));
        $empresa = trim(texto_ou($vagaDoBot, 'empresa'));
        $localizacao = trim(texto_ou($vagaDoBot, 'localizacao'));

        // Sem localizacao: usa a cidade consultada.
        if ($localizacao === '') {
            $localizacao = $cidade;
        }

        // Identificador da vaga no site (o mesmo papel do job_id).
        $vagaId = trim(texto_ou($vagaDoBot, 'vaga_id'));

        // URL do formulario de candidatura.
        $urlEnvio = trim(texto_ou($vagaDoBot, 'url_envio'));

        // Pagina da vaga (usada na etapa 2 da extracao).
        $urlVaga = trim(texto_ou($vagaDoBot, 'url_vaga'));

        // E-mail de contacto: etapa 1 (descricao) e, se faltar, etapa 2
        // (pagina da vaga).
        $contato = $this->extrairContato($vagaDoBot, $urlVaga);

        $dados = [
            'palavra_chave_id'                    => (int) $palavra['id'],
            'usuario_id'                          => $usuarioId,
            'job_id'                              => mb_substr('dv-' . $vagaId, 0, 255),
            'hash_unico'                          => VagaModel::gerarHashUnico($titulo, $empresa, $localizacao),
            'titulo'                              => mb_substr($titulo, 0, 255),
            'empresa'                             => mb_substr($empresa, 0, 255),
            'localizacao'                         => mb_substr($localizacao, 0, 255),
            'cidade_consultada'                   => mb_substr($cidade, 0, 150),
            'fonte'                               => 'Divulga Vagas',
            'url_original'                        => mb_substr($urlVaga, 0, 500),
            'url_candidatura'                     => mb_substr($urlEnvio, 0, 500),
            'descricao'                           => mb_substr(texto_ou($vagaDoBot, 'descricao'), 0, 5000),
            'email_contato'                       => $contato['email'],
            'emails_encontrados'                  => json_encode($contato['todos']),
            'origem_email'                        => $contato['origem'],
            'ja_candidatado'                      => 0,
            'e_prioritaria'                       => 0,
            // Bloco do segundo provedor.
            'provedor_vaga'                       => 'divulgavagas',
            'url_envio_divulgavagas'              => mb_substr($urlEnvio, 0, 500),
            'vaga_id_divulgavagas'                => mb_substr($vagaId, 0, 50),
            'candidatura_divulgavagas_status'     => 'pendente',
            'candidatura_divulgavagas_tentativas' => 0,
        ];

        $resultado = $this->deduplicadora->salvarOuAtualizarVaga($dados);

        return [
            'situacao'  => (string) $resultado['situacao'],
            'com_email' => $contato['email'] !== null,
        ];
    }

    /**
     * Extrai o e-mail de contacto da vaga.
     *
     * Etapa 1: a descricao que o bot trouxe.
     * Etapa 2: a pagina da vaga (quando a descricao nao tem e-mail).
     *
     * @return array{email: string|null, origem: string|null, todos: list<string>}
     */
    private function extrairContato(array $vagaDoBot, string $urlVaga): array
    {
        // Etapa 1: descricao.
        $descricao = texto_ou($vagaDoBot, 'descricao');
        $daDescricao = $this->extrator->extrairDeTexto($descricao);

        // Etapa 2: pagina da vaga (so quando a descricao nao trouxe nada).
        $daPagina = [];

        if ($daDescricao === [] && $urlVaga !== '') {
            $daPagina = $this->extrator->extrairDaPaginaWeb($urlVaga);
        }

        $escolha = $this->extrator->escolher($daDescricao, $daPagina);

        // A origem e 'descricao' ou 'apply_options'; a etapa 2 e 'pagina'.
        $origem = $escolha['origem'];

        if ($daDescricao === [] && $daPagina !== []) {
            $origem = 'pagina';
        }

        return [
            'email'  => $escolha['email'],
            'origem' => $origem,
            'todos'  => $escolha['todos'],
        ];
    }

    /**
     * Lista as cidades da palavra-chave (uma consulta por cidade).
     *
     * @return list<string>
     */
    private function cidadesDe(array $palavra): array
    {
        $cidades = json_decode(texto_ou($palavra, 'cidades', '[]'), true);

        if (! is_array($cidades)) {
            return [''];
        }

        $limpas = [];

        foreach ($cidades as $cidade) {
            if (! is_string($cidade)) {
                continue;
            }

            $cidade = trim((string) preg_replace('/\s+/u', ' ', $cidade));

            if ($cidade === '' || in_array($cidade, $limpas, true)) {
                continue;
            }

            $limpas[] = $cidade;
        }

        // Sem cidade: busca no site inteiro.
        if ($limpas === []) {
            return [''];
        }

        return $limpas;
    }

    /**
     * Separa a cidade do estado (o site aceita o UF em outro parametro).
     *
     * "Ponta Grossa - PR" vira cidade "Ponta Grossa" e estado "PR".
     *
     * @return array{cidade: string, estado: string}
     */
    private function cidadeEEstado(string $cidade): array
    {
        $cidade = trim($cidade);

        // Sem sufixo de estado: usa a cidade inteira.
        if (preg_match('/^(.*)\s+-\s*([A-Za-z]{2})$/u', $cidade, $achado) !== 1) {
            return ['cidade' => $cidade, 'estado' => ''];
        }

        return [
            'cidade'  => trim($achado[1]),
            'estado'  => strtoupper(trim($achado[2])),
        ];
    }

    /**
     * Registra a consulta no log de consultas.
     */
    private function registrarLog(
        int $usuarioId,
        array $palavra,
        string $cidade,
        string $origem,
        int $total,
        string $status,
        ?string $mensagemErro
    ): void {
        // A origem aceita pelo enum da coluna.
        $origemLog = 'cron';

        if ($origem === 'manual_usuario' || $origem === 'manual_admin') {
            $origemLog = 'manual_usuario';
        }

        $this->logs->registrar([
            'usuario_id'             => $usuarioId,
            'palavra_chave_id'       => (int) $palavra['id'],
            'cidade_consultada'      => $cidade,
            'chave_serpapi_id'       => null,
            'termo'                  => texto_ou($palavra, 'termo'),
            'origem'                 => $origemLog,
            'provedor'               => 'divulgavagas',
            'total_vagas_retornadas' => $total,
            'status'                 => $status,
            'mensagem_erro'          => $mensagemErro,
        ]);
    }

    /**
     * Resumo vazio de uma execucao.
     *
     * @return array<string, mixed>
     */
    private function resumoVazio(): array
    {
        return [
            'iniciado_em'     => date('Y-m-d H:i:s'),
            'terminado_em'    => null,
            'vagas_inseridas'  => 0,
            'vagas_atualizadas' => 0,
            'vagas_repetidas' => 0,
            'com_email'       => 0,
            'sem_email'       => 0,
            'consultas'       => 0,
            'palavras_chave'  => 0,
            'erros'           => 0,
            'motivo_parada'   => null,
            'detalhes'        => [],
        ];
    }
}
