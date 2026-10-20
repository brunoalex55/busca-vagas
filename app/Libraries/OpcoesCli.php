<?php

namespace App\Libraries;

use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * TRAIT: LEITURA DE OPCOES DA LINHA DE COMANDO
 * --------------------------------------------------------------------------
 *
 * O CodeIgniter separa as opcoes por espaco:
 *
 *     php spark vagas:chave criar --chave MINHA_CHAVE
 *
 * Porem, quase todo mundo digita usando o sinal de igual:
 *
 *     php spark vagas:chave criar --chave=MINHA_CHAVE
 *
 * Nas duas formas os comandos deste projeto precisam funcionar. A segunda
 * nao e entendida nativamente: o CodeIgniter guarda a opcao inteira como
 * nome ("chave=MINHA_CHAVE"), sem valor. Este trait resolve as duas.
 */
trait OpcoesCli
{
    /**
     * Le o valor de uma opcao, aceitando `--nome valor` e `--nome=valor`.
     *
     * @param string $nome Nome da opcao, sem os hifens.
     *
     * @return string|null Valor informado ou null quando a opcao nao veio.
     */
    protected function opcao(string $nome): ?string
    {
        // Opcoes recebidas do console.
        $opcoes = CLI::getOptions();

        // Forma nativa do CodeIgniter: --nome valor.
        if (isset($opcoes[$nome]) && is_string($opcoes[$nome])) {
            return $opcoes[$nome];
        }

        // Forma --nome=valor: o CodeIgniter guarda tudo no nome da opcao.
        $prefixo = $nome . '=';

        foreach ($opcoes as $chave => $valor) {
            if (is_string($chave) && str_starts_with($chave, $prefixo)) {
                return substr($chave, strlen($prefixo));
            }
        }

        // Opcao ausente.
        return null;
    }
}
