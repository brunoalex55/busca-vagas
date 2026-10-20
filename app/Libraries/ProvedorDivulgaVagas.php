<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use Config\DivulgaVagas as ConfigDivulgaVagas;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: PROVEDOR DIVULGA VAGAS
 * --------------------------------------------------------------------------
 *
 * Adaptador do segundo provedor de busca. Ele nao fala com o site
 * diretamente: chama o BOT PYTHON (automacoes-rpa/divulgavagas/bot.py), que
 * usa o Playwright connectedo no Chrome headless persistente.
 *
 * Dois modos, como na especificacao:
 *
 *   - buscar: abre a listagem, coleta os cards das vagas e devolve em
 *     JSON (titulo, empresa, localizacao, url, id e a descricao);
 *   - candidatar: abre o formulario de candidatura, anexa o curriculo em
 *     PDF, marca os termos e confirma o envio.
 *
 * O bot NUNCA fecha o Chrome: cada chamada abre UMA aba nova e fecha so
 * a aba. O servico do Chrome (chrome_service.py) e unico e persistente.
 *
 * A saida do bot e UM JSON no stdout. O stderr e log do bot e nunca
 * entra no log do sistema: nem o e-mail extraido nem o conteudo do
 * curriculo sao gravados aqui.
 */
class ProvedorDivulgaVagas
{
    /** @var ConfigDivulgaVagas */
    private ConfigDivulgaVagas $config;

    /** @var string */
    private string $pastaBot;

    /** @var string */
    private string $python;

    public function __construct(?ConfigDivulgaVagas $config = null, ?string $python = null)
    {
        if ($config === null) {
            $config = config(ConfigDivulgaVagas::class);
        }

        // A configuracao vem primeiro: as outras resolucoes a usam.
        $this->config = $config;

        $this->pastaBot = $this->resolverPastaBot();

        if ($python === null) {
            $python = $this->resolverPython();
        }

        $this->python = $python;
    }

    // =====================================================================
    // MODOS
    // =====================================================================

    /**
     * MODO BUSCAR: coleta as vagas de uma URL de listagem.
     *
     * @return array{
     *     sucesso: bool,
     *     dados: array<string, mixed>,
     *     erro: string|null,
     *     codigo: int
     * }
     */
    public function buscar(string $urlBusca, int $usuarioId, ?int $timeoutMs = null): array
    {
        // Sem URL: nao ha o que buscar.
        if (trim($urlBusca) === '') {
            return $this->falha('Informe a URL de busca.', 0);
        }

        // Tempo limite da chamada (o padrao vem da configuracao).
        $timeout = $this->config->timeoutBusca;

        if ($timeoutMs !== null) {
            $timeout = $timeoutMs;
        }

        $argumentos = [
            '--modo=buscar',
            '--usuario-id=' . $usuarioId,
            '--url-busca=' . $urlBusca,
            '--timeout=' . $timeout,
        ];

        return $this->executar($argumentos, $this->config->timeoutBusca);
    }

    /**
     * MODO CANDIDATAR: envia o curriculo no formulario do site.
     *
     * @return array{
     *     sucesso: bool,
     *     dados: array<string, mixed>,
     *     erro: string|null,
     *     codigo: int
     * }
     */
    public function candidatar(
        string $urlEnvio,
        string $caminhoCurriculo,
        int $usuarioId,
        ?int $timeoutMs = null
    ): array {
        // Sem URL: nao ha onde candidatar.
        if (trim($urlEnvio) === '') {
            return $this->falha('Informe a URL de envio.', 0);
        }

        // O curriculo precisa existir no disco.
        if (! is_file($caminhoCurriculo)) {
            return $this->falha('Curriculo nao encontrado para o envio.', 0);
        }

        // Tempo limite da chamada (o padrao vem da configuracao).
        $timeout = $this->config->timeoutCandidatura;

        if ($timeoutMs !== null) {
            $timeout = $timeoutMs;
        }

        $argumentos = [
            '--modo=candidatar',
            '--usuario-id=' . $usuarioId,
            '--url-envio=' . $urlEnvio,
            '--curriculo=' . $caminhoCurriculo,
            '--timeout=' . $timeout,
        ];

        return $this->executar($argumentos, $this->config->timeoutCandidatura);
    }

    /**
     * Monta a URL de listagem do site.
     *
     * O site aceita o termo, o estado (UF) e a cidade pela query string.
     */
    public function montarUrlBusca(string $termo, string $cidade, string $estado = ''): string
    {
        // O site usa o mesmo formato do formulario dele: o espaco vira "+"
        // (urlencode) e os acentos viram %XX.
        $partes = [
            'filt_buscar=' . urlencode($termo),
        ];

        if (trim($estado) !== '') {
            $partes[] = 'estado=' . urlencode($estado);
        }

        if (trim($cidade) !== '') {
            $partes[] = 'cidade=' . urlencode($cidade);
        }

        return rtrim($this->config->urlBase, '/')
            . $this->config->caminhoListagem
            . '?' . implode('&', $partes);
    }

    // =====================================================================
    // EXECUCAO DO BOT
    // =====================================================================

    /**
     * Executa o bot e devolve o resultado em JSON.
     *
     * @param list<string> $argumentos Argumentos do bot (ja sem o caminho).
     *
     * @return array{sucesso: bool, dados: array<string, mixed>, erro: string|null, codigo: int}
     */
    private function executar(array $argumentos, int $timeoutSegundos): array
    {
        // O bot precisa existir.
        $script = $this->pastaBot . DIRECTORY_SEPARATOR . 'bot.py';

        if (! is_file($script)) {
            return $this->falha('Bot nao encontrado em ' . $script . '.', 2);
        }

        // O Python precisa existir.
        if ($this->python === '') {
            return $this->falha('Python nao encontrado. Instale o Python 3 e informe o caminho em configuracao/divulgavagas.php.', 2);
        }

        // Linha de comando: cada parte escapada.
        $partes = [escapeshellarg($this->python), escapeshellarg($script)];

        foreach ($argumentos as $argumento) {
            $partes[] = escapeshellarg($argumento);
        }

        $linha = implode(' ', $partes);

        // Execucao com limite de tempo (o comando nao pode travar o CRON).
        $resultado = $this->rodarComTempo($linha, $timeoutSegundos);

        // Tempo estourado: o bot ainda esta rodando.
        if ($resultado['em_execucao']) {
            return [
                'sucesso' => false,
                'dados'   => [],
                'erro'    => 'O bot passou do tempo limite de ' . $timeoutSegundos . ' segundo(s).',
                'codigo'  => 2,
            ];
        }

        // Codigo 2 do bot: erro de infraestrutura.
        if ((int) $resultado['codigo'] === 2) {
            return [
                'sucesso' => false,
                'dados'   => [],
                'erro'    => $this->primeiraLinha($resultado['texto']),
                'codigo'  => 2,
            ];
        }

        // Le o JSON devolvido pelo bot.
        $dados = json_decode($resultado['texto'], true);

        if (! is_array($dados)) {
            return [
                'sucesso' => false,
                'dados'   => [],
                'erro'    => 'O bot nao devolveu um JSON valido.',
                'codigo'  => 1,
            ];
        }

        // O proprio bot informa se deu certo.
        $sucesso = (bool) valor_ou($dados, 'sucesso', false);

        // Com sucesso nao ha erro a relatar.
        $erro = null;

        if (! $sucesso) {
            $erro = (string) valor_ou($dados, 'erro', 'Erro desconhecido do bot.');
        }

        return [
            'sucesso' => $sucesso,
            'dados'   => $dados,
            'erro'    => $erro,
            'codigo'  => (int) $resultado['codigo'],
        ];
    }

    /**
     * Roda a linha de comando com limite de tempo.
     *
     * A saida vai para arquivos temporarios: assim o processo nao depende
     * da duracao da chamada e pode continuar rodando quando o limite de
     * tempo estourar.
     *
     * @return array{codigo: int|null, texto: string, em_execucao: bool}
     */
    private function rodarComTempo(string $linha, int $limiteSegundos): array
    {
        // Sem proc_open nao ha como capturar a saida.
        if (! function_exists('proc_open')) {
            return ['codigo' => -1, 'texto' => 'proc_open desabilitado neste servidor.', 'em_execucao' => false];
        }

        // Pasta de logs do bot (em writable/, fora do versionamento).
        $pastaLogs = $this->pastaTrabalho() . DIRECTORY_SEPARATOR . 'logs';

        if (! is_dir($pastaLogs)) {
            @mkdir($pastaLogs, 0777, true);
        }

        // Um arquivo por execucao (o nome tem data e hora).
        $arquivo = $pastaLogs . DIRECTORY_SEPARATOR
            . 'bot-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.log';

        // Descritores: entrada fechada, saida e erro no arquivo.
        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['file', $arquivo, 'w'],
            2 => ['file', $arquivo, 'a'],
        ];

        $tubos = [];

        try {
            $processo = proc_open($linha, $descritores, $tubos, ROOTPATH);
        } catch (Throwable $erro) {
            return ['codigo' => -1, 'texto' => 'Falha ao iniciar o bot: ' . $erro->getMessage(), 'em_execucao' => false];
        }

        if (! is_resource($processo)) {
            return ['codigo' => -1, 'texto' => 'Nao foi possivel iniciar o bot.', 'em_execucao' => false];
        }

        // O bot nao le do teclado.
        fclose($tubos[0]);

        $inicio = microtime(true);
        $codigo = null;
        $emExecucao = true;

        // Aguarda o processo, respeitando o limite de tempo.
        while (true) {
            $status = proc_get_status($processo);

            if (! $status['running']) {
                $codigo = (int) $status['exitcode'];
                $emExecucao = false;
                break;
            }

            if ((microtime(true) - $inicio) >= $limiteSegundos) {
                break;
            }

            usleep(100000);
        }

        // Ainda rodando: nao espera (proc_close esperaria o fim).
        if ($emExecucao) {
            return ['codigo' => null, 'texto' => '', 'em_execucao' => true];
        }

        proc_close($processo);

        // O bot escreve o JSON no stdout; os logs vao para stderr. Como os
        // dois foram para o mesmo arquivo, o JSON e a ultima linha.
        $conteudo = '';

        if (is_file($arquivo)) {
            $conteudo = (string) file_get_contents($arquivo);
        }

        return [
            'codigo'     => $codigo,
            'texto'      => $this->ultimaLinhaJson($conteudo),
            'em_execucao' => false,
        ];
    }

    /**
     * Pega a ultima linha do arquivo que parece um JSON.
     *
     * O bot escreve o JSON no stdout e os logs no stderr; como os dois
     * vao para o mesmo arquivo, o JSON e a ultima linha util.
     */
    private function ultimaLinhaJson(string $conteudo): string
    {
        $linhas = array_values(array_filter(array_map('trim', explode("\n", $conteudo))));

        // De tras para frente: a ultima linha com JSON.
        for ($i = count($linhas) - 1; $i >= 0; $i--) {
            $linha = $linhas[$i];

            if ($linha === '') {
                continue;
            }

            if (substr($linha, 0, 1) === '{') {
                return $linha;
            }
        }

        return '';
    }

    /**
     * Primeira linha do texto (usada como mensagem de erro).
     */
    private function primeiraLinha(string $texto): string
    {
        $linhas = array_values(array_filter(array_map('trim', explode("\n", $texto))));

        if ($linhas === []) {
            return 'Erro desconhecido do bot.';
        }

        return mb_substr($linhas[0], 0, 300);
    }

    /**
     * Resultado de falha padronizado.
     *
     * @return array{sucesso: bool, dados: array<string, mixed>, erro: string|null, codigo: int}
     */
    private function falha(string $erro, int $codigo): array
    {
        return [
            'sucesso' => false,
            'dados'   => [],
            'erro'    => $erro,
            'codigo'  => $codigo,
        ];
    }

    // =====================================================================
    // AMBIENTE
    // =====================================================================

    /**
     * Pasta do bot (onde ficam bot.py e o servico do Chrome).
     */
    public function pastaBot(): string
    {
        return $this->pastaBot;
    }

    /**
     * Caminho do Python em uso.
     */
    public function caminhoPython(): string
    {
        return $this->python;
    }

    /**
     * Descobre a pasta do bot.
     */
    private function resolverPastaBot(): string
    {
        $configurada = trim($this->config->pastaBot);

        if ($configurada !== '') {
            return rtrim($configurada, '/\\');
        }

        return rtrim(ROOTPATH, '/\\') . DIRECTORY_SEPARATOR . 'automacoes-rpa' . DIRECTORY_SEPARATOR . 'divulgavagas';
    }

    /**
     * Pasta de trabalho do bot (logs e listas).
     *
     * Fica em writable/, fora do versionamento.
     */
    public function pastaTrabalho(): string
    {
        $pasta = rtrim(WRITEPATH, '/\\')
            . DIRECTORY_SEPARATOR . 'automacoes-rpa'
            . DIRECTORY_SEPARATOR . 'divulgavagas';

        if (! is_dir($pasta)) {
            @mkdir($pasta, 0777, true);
        }

        return $pasta;
    }

    /**
     * Descobre o Python.
     *
     * A configuracao tem prioridade. Sem ela, o sistema procura o
     * interpretador no PATH: python3 no Linux, python no Windows.
     */
    private function resolverPython(): string
    {
        $configurado = trim($this->config->python);

        if ($configurado !== '') {
            return $configurado;
        }

        // Candidatos por sistema operacional.
        $candidatos = [];

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidatos[] = 'python';
            $candidatos[] = 'python3';
        } else {
            $candidatos[] = 'python3';
            $candidatos[] = 'python';
        }

        foreach ($candidatos as $candidato) {
            $caminho = $this->caminhoDoExecutavel($candidato);

            if ($caminho !== '') {
                return $caminho;
            }
        }

        return '';
    }

    /**
     * Procura um executavel no PATH.
     */
    private function caminhoDoExecutavel(string $nome): string
    {
        $caminho = (string) getenv('PATH');

        if ($caminho === '') {
            return '';
        }

        // Separador de PATH: ponto e virgula no Windows, dois pontos no Linux.
        $separador = ':';

        if (DIRECTORY_SEPARATOR === '\\') {
            $separador = ';';
        }

        foreach (explode($separador, $caminho) as $pasta) {
            if (trim($pasta) === '') {
                continue;
            }

            $possivel = rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . $nome;

            if (DIRECTORY_SEPARATOR === '\\' && ! str_ends_with(strtolower($possivel), '.exe')) {
                $possivel .= '.exe';
            }

            if (is_file($possivel)) {
                return $possivel;
            }
        }

        return '';
    }
}
