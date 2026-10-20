<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Base do verificador do checklist final (Etapa 33).
 *
 * Carrega o contexto do projeto (arquivos, banco) e oferece o registrador
 * de itens com evidencia.
 *
 * Uso: php tools/checklist/verificar.php
 */

// Raiz do projeto: tools/checklist -> tools -> raiz.
$raiz = dirname(__DIR__, 2);

// Pasta dos arquivos intermediarios (fora do versionamento).
$pastaTrabalho = $raiz . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'checklist';

if (! is_dir($pastaTrabalho)) {
    mkdir($pastaTrabalho, 0777, true);
}

// A lista de itens e recriada por cada parte da verificacao.
if (! isset($itens)) {
    $itens = [];
}

/**
 * Registra um item do checklist.
 *
 * @param string $secao    Secao do checklist.
 * @param string $texto    Texto do item (como no plano).
 * @param bool   $ok       Verdadeiro quando o item foi verificado no codigo.
 * @param string $evidencia Prova encontrada (curta, em PT-BR sem acentos).
 */
function item(string $secao, string $texto, bool $ok, string $evidencia = ''): void
{
    global $itens;

    $itens[] = [
        'secao'     => $secao,
        'texto'     => $texto,
        'ok'        => $ok,
        'evidencia' => $evidencia,
    ];
}

/**
 * Le o conteudo de um arquivo do projeto (string vazia se nao existir).
 */
function arq(string $relativo): string
{
    global $raiz;

    $caminho = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);

    if (! is_file($caminho)) {
        return '';
    }

    return (string) file_get_contents($caminho);
}

/**
 * O arquivo existe?
 */
function existe(string $relativo): bool
{
    global $raiz;

    $caminho = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);

    return is_file($caminho);
}

/**
 * O arquivo contem o trecho informado?
 */
function contem(string $relativo, string $trecho): bool
{
    $conteudo = arq($relativo);

    if ($conteudo === '') {
        return false;
    }

    return stripos($conteudo, $trecho) !== false;
}

/**
 * O arquivo casa com a expressao regular informada?
 */
function casa(string $relativo, string $padrao): bool
{
    $conteudo = arq($relativo);

    if ($conteudo === '') {
        return false;
    }

    return (bool) preg_match($padrao, $conteudo);
}

/**
 * Lista os arquivos de uma pasta do projeto (relativa), com subpastas.
 *
 * @return list<string>
 */
function listar(string $pastaRelativa, string $extensao = 'php'): array
{
    global $raiz;

    $pasta = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pastaRelativa);

    if (! is_dir($pasta)) {
        return [];
    }

    $achados = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $arquivo) {
        if (! $arquivo->isFile()) {
            continue;
        }

        if ($extensao !== '' && $arquivo->getExtension() !== $extensao) {
            continue;
        }

        $achados[] = $arquivo->getPathname();
    }

    sort($achados);

    return $achados;
}

/**
 * Conta as ocorrencias de um padrao em todos os arquivos de uma pasta.
 */
function contar(string $pastaRelativa, string $padrao, string $extensao = 'php'): int
{
    $total = 0;

    foreach (listar($pastaRelativa, $extensao) as $arquivo) {
        $conteudo = (string) file_get_contents($arquivo);
        $total += preg_match_all($padrao, $conteudo);
    }

    return (int) $total;
}

/**
 * Conexao com o banco do projeto (leitura).
 */
function banco(): mysqli
{
    global $raiz;

    $env = (string) file_get_contents($raiz . DIRECTORY_SEPARATOR . '.env');

    $valores = [];

    foreach (explode("\n", $env) as $linha) {
        $linha = trim($linha);

        if ($linha === '' || strpos($linha, '#') === 0) {
            continue;
        }

        $partes = explode('=', $linha, 2);

        if (count($partes) !== 2) {
            continue;
        }

        $valores[trim($partes[0])] = trim($partes[1]);
    }

    $conexao = new mysqli(
        $valores['database.default.hostname'] ?? '127.0.0.1',
        $valores['database.default.username'] ?? 'ci_employer',
        $valores['database.default.password'] ?? '',
        $valores['database.default.database'] ?? 'ci_employer',
        (int) ($valores['database.default.port'] ?? 3306)
    );

    if ($conexao->connect_errno !== 0) {
        throw new RuntimeException('Falha na conexao com o banco: ' . $conexao->connect_error);
    }

    return $conexao;
}

/**
 * Consulta ao banco: retorna as linhas como array associativo.
 *
 * @return list<array<string, mixed>>
 */
function consultar(string $sql): array
{
    $conexao = banco();
    $resultado = $conexao->query($sql);

    if ($resultado === false) {
        $conexao->close();

        return [];
    }

    $linhas = [];

    while ($linha = $resultado->fetch_assoc()) {
        $linhas[] = $linha;
    }

    $resultado->free();
    $conexao->close();

    return $linhas;
}

/**
 * Salva os itens coletados em JSON.
 */
function salvarItens(string $arquivo): void
{
    global $itens;

    file_put_contents(
        $arquivo,
        json_encode($itens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}
