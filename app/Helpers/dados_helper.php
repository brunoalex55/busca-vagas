<?php

/**
 * --------------------------------------------------------------------------
 * HELPER: DADOS
 * --------------------------------------------------------------------------
 *
 * Funcoes de leitura segura de arrays, usadas no lugar do operador "??" (o
 * Bloco C do projeto proibe o operador em todo o codigo).
 *
 * Todas devolveem o valor padrao quando a chave nao existe no array - o
 * mesmo comportamento do "??" - mas com o if/else explicito e com o tipo
 * de retorno ja convertido, o que evita repetir o cast em cada uso.
 */

if (! function_exists('valor_ou')) {
    /**
     * Le um valor de um array, devolvendo o padrao quando a chave nao existe.
     *
     * @param array<string, mixed> $dados
     */
    function valor_ou(array $dados, string $chave, mixed $padrao = ''): mixed
    {
        // Chave presente: devolve o valor original.
        if (isset($dados[$chave])) {
            return $dados[$chave];
        }

        return $padrao;
    }
}

if (! function_exists('texto_ou')) {
    /**
     * Le um texto de um array, devolvendo o padrao quando a chave nao existe.
     *
     * @param array<string, mixed> $dados
     */
    function texto_ou(array $dados, string $chave, string $padrao = ''): string
    {
        // Chave presente: devolve o texto.
        if (isset($dados[$chave])) {
            return (string) $dados[$chave];
        }

        return $padrao;
    }
}

if (! function_exists('inteiro_ou')) {
    /**
     * Le um inteiro de um array, devolvendo o padrao quando a chave nao existe.
     *
     * @param array<string, mixed> $dados
     */
    function inteiro_ou(array $dados, string $chave, int $padrao = 0): int
    {
        // Chave presente: devolve o inteiro.
        if (isset($dados[$chave])) {
            return (int) $dados[$chave];
        }

        return $padrao;
    }
}

if (! function_exists('booleano_ou')) {
    /**
     * Le um booleano de um array, devolvendo o padrao quando a chave nao existe.
     *
     * @param array<string, mixed> $dados
     */
    function booleano_ou(array $dados, string $chave, bool $padrao = false): bool
    {
        // Chave presente: devolve o booleano.
        if (isset($dados[$chave])) {
            return (bool) $dados[$chave];
        }

        return $padrao;
    }
}

if (! function_exists('escolher')) {
    /**
     * Escolhe um valor conforme uma condicao (substitui o ternario).
     *
     * @param mixed $condicao        Condicao avaliada.
     * @param mixed $quandoVerdadeiro Valor devolvido quando a condicao e verdadeira.
     * @param mixed $quandoFalso     Valor devolvido quando a condicao e falsa.
     */
    function escolher(mixed $condicao, mixed $quandoVerdadeiro, mixed $quandoFalso): mixed
    {
        if ($condicao) {
            return $quandoVerdadeiro;
        }

        return $quandoFalso;
    }
}

if (! function_exists('primeiro_valor')) {
    /**
     * Devolve o primeiro valor quando ele tem conteudo; senao, o padrao.
     *
     * Equivale ao ternario curto (valor ?: padrao), mas com o if/else
     * explicito exigido pelo Bloco C.
     */
    function primeiro_valor(mixed $valor, mixed $padrao): mixed
    {
        if ($valor) {
            return $valor;
        }

        return $padrao;
    }
}
