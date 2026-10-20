<?php
/**
 * Detector preciso de ternarios e "??" (operador de coalescencia).
 *
 * Usa o tokenizador do PHP: nao confunde com strings, "?->" ou "::".
 * O "?" de tipo (ex.: ?string $x) e descartado pelo controle de profundidade.
 *
 * Uso: php tools/detector-c1.php [pasta]
 * Sem argumento, verifica a pasta app/ do projeto.
 */

$diretorio = isset($argv[1]) ? $argv[1] : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app';

if (is_file($diretorio)) {
    $arquivos = [new SplFileInfo($diretorio)];
} else {
    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($diretorio));
}

$totalTernario = 0;
$totalCoalesce = 0;
$totalArquivos = 0;
$totalAlternativo = 0;
$totalEcoCurto = 0;

/**
 * Devolve a linha de codigo (sem espaco a esquerda) de um arquivo.
 */
function linhaDoArquivo(string $caminho, int $numero): string
{
    static $cache = [];

    if (! isset($cache[$caminho])) {
        $cache[$caminho] = file($caminho) ?: [];
    }

    $linha = $cache[$caminho][$numero - 1] ?? '';

    return trim($linha);
}

foreach ($arquivos as $arquivo) {
    if (! $arquivo->isFile() || $arquivo->getExtension() !== 'php') {
        continue;
    }

    $totalArquivos++;
    $tokens = token_get_all((string) file_get_contents($arquivo->getPathname()));

    // Normaliza: [tipo, texto, linha].
    $lista = [];

    foreach ($tokens as $token) {
        if (is_array($token)) {
            $lista[] = [$token[0], $token[1], $token[2]];
            continue;
        }

        $lista[] = [null, $token, null];
    }

    $profundidade = 0;
    $ultimaLinha  = 0;

    foreach ($lista as $indice => $item) {
        [$tipo, $texto, $linha] = $item;

        if ($linha !== null) {
            $ultimaLinha = $linha;
        }

        // Contador de profundidade.
        if ($texto === '(' || $texto === '[' || $texto === '{') {
            $profundidade++;
            continue;
        }

        if ($texto === ')' || $texto === ']' || $texto === '}') {
            $profundidade--;
            continue;
        }

        // Sintaxe alternativa de blocos (Regra C2).
        if (in_array($tipo, [T_ENDIF, T_ENDFOREACH, T_ENDFOR, T_ENDWHILE, T_ENDSWITCH], true)) {
            echo "ALTERNATIVO {$arquivo->getPathname()}:{$ultimaLinha}  " . linhaDoArquivo($arquivo->getPathname(), $ultimaLinha) . PHP_EOL;
            $totalAlternativo++;
            continue;
        }

        // Tag de eco curto (Regra C3).
        if ($tipo === T_OPEN_TAG_WITH_ECHO) {
            echo "ECO_CURTO  {$arquivo->getPathname()}:{$ultimaLinha}  " . linhaDoArquivo($arquivo->getPathname(), $ultimaLinha) . PHP_EOL;
            $totalEcoCurto++;
            continue;
        }

        // Operador de coalescencia (?= e ??).
        if ($tipo === T_COALESCE || $tipo === T_COALESCE_EQUAL) {
            echo "COALESCE  {$arquivo->getPathname()}:{$ultimaLinha}  " . linhaDoArquivo($arquivo->getPathname(), $ultimaLinha) . PHP_EOL;
            $totalCoalesce++;
            continue;
        }

        // Somente o "?" simples interessa.
        if ($texto !== '?') {
            continue;
        }

        // Token anterior relevante (ignora espacos e comentarios).
        $anterior = null;

        for ($k = $indice - 1; $k >= 0; $k--) {
            [$tipoK, $textoK] = $lista[$k];

            if ($tipoK === T_WHITESPACE || $tipoK === T_COMMENT || $tipoK === T_DOC_COMMENT) {
                continue;
            }

            $anterior = $lista[$k];
            break;
        }

        // Tipo de retorno anulavel (ex.: "): ?array"): o "?" vem depois do ":".
        if ($anterior !== null && $anterior[1] === ':') {
            continue;
        }

        // Tipo anulavel de parametro ou propriedade (ex.: "?string $x").
        $proximo = null;
        $seguinte = null;

        for ($k = $indice + 1; $k < count($lista); $k++) {
            [$tipoK, $textoK] = $lista[$k];

            if ($tipoK === T_WHITESPACE || $tipoK === T_COMMENT || $tipoK === T_DOC_COMMENT) {
                continue;
            }

            $proximo = $lista[$k];
            break;
        }

        for ($k = $indice + 2; $k < count($lista); $k++) {
            [$tipoK, $textoK] = $lista[$k];

            if ($tipoK === T_WHITESPACE || $tipoK === T_COMMENT || $tipoK === T_DOC_COMMENT) {
                continue;
            }

            $seguinte = $lista[$k];
            break;
        }

        if (
            $proximo !== null
            && $proximo[0] === T_STRING
            && $seguinte !== null
            && ($seguinte[0] === T_VARIABLE || $seguinte[1] === '::')
        ) {
            continue;
        }

        // Procura um ":" na mesma profundidade (ternario).
        $achou     = false;
        $nivel     = $profundidade;
        $nivelBase = $profundidade;

        for ($j = $indice + 1; $j < count($lista); $j++) {
            [$tipoJ, $textoJ] = $lista[$j];

            if ($tipoJ === T_WHITESPACE || $tipoJ === T_COMMENT || $tipoJ === T_DOC_COMMENT) {
                continue;
            }

            if ($textoJ === '(' || $textoJ === '[' || $textoJ === '{') {
                $nivel++;
                continue;
            }

            if ($textoJ === ')' || $textoJ === ']' || $textoJ === '}') {
                $nivel--;

                // Saiu do bloco: nao e ternario (ex.: ?string $x).
                if ($nivel < $nivelBase) {
                    break;
                }

                continue;
            }

            if ($textoJ === ':' && $nivel === $nivelBase) {
                echo "TERNARIO  {$arquivo->getPathname()}:{$ultimaLinha}  " . linhaDoArquivo($arquivo->getPathname(), $ultimaLinha) . PHP_EOL;
                $totalTernario++;
                $achou = true;
                break;
            }

            if ($textoJ === ';' && $nivel === $nivelBase) {
                break;
            }
        }
    }
}

echo '---' . PHP_EOL;
echo "arquivos: {$totalArquivos} | ternarios: {$totalTernario} | ??: {$totalCoalesce}"
    . " | alternativa: {$totalAlternativo} | eco curto: {$totalEcoCurto}" . PHP_EOL;

exit(($totalTernario + $totalCoalesce + $totalAlternativo + $totalEcoCurto) > 0 ? 1 : 0);
