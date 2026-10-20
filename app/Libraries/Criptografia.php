<?php

namespace App\Libraries;

use RuntimeException;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: CRIPTOGRAFIA
 * --------------------------------------------------------------------------
 *
 * Responsavel por proteger o APP PASSWORD de cada usuario no banco.
 *
 * Algoritmo: AES-256-CBC (openssl_encrypt / openssl_decrypt).
 * Chave mestra: variavel CRIPTOGRAFIA_CHAVE do arquivo .env, em Base64
 * (precisa representar exatamente 32 bytes).
 *
 * Formato do valor salvo:
 *   base64( IV(16 bytes) . texto_cifrado )
 *
 * O IV e aleatorio a cada operacao, portanto o mesmo texto gera
 * resultados diferentes em cada chamada.
 *
 * IMPORTANTE: se a chave mestra for trocada, todos os APP PASSWORDS
 * ja gravados se tornam ilegiveis.
 */
class Criptografia
{
    /** Algoritmo de cifra simetrica utilizado. */
    private string $algoritmo = 'AES-256-CBC';

    /** Chave binaria de 32 bytes derivada do .env. */
    private string $chave;

    /**
     * Le e valida a chave mestra no momento da construcao.
     *
     * @throws RuntimeException Quando a chave nao existe ou e invalida.
     */
    public function __construct()
    {
        // Le a variavel do .env (string Base64).
        $chaveBase64 = (string) env('CRIPTOGRAFIA_CHAVE', '');

        // Decodifica o Base64 em modo estrito.
        $chave = base64_decode($chaveBase64, true);

        // AES-256 exige exatamente 32 bytes de chave.
        if ($chave === false || strlen($chave) !== 32) {
            throw new RuntimeException(
                'CRIPTOGRAFIA_CHAVE invalida ou ausente no .env. '
                . 'Gere uma nova com: php -r "echo base64_encode(random_bytes(32));"'
            );
        }

        // Guarda a chave binaria.
        $this->chave = $chave;
    }

    /**
     * Criptografa um texto (ex.: APP PASSWORD do Gmail).
     *
     * @throws RuntimeException Quando a cifragem falha.
     */
    public function criptografar(string $texto): string
    {
        // Descobre o tamanho de IV exigido pelo algoritmo (16 bytes).
        $tamanhoIv = openssl_cipher_iv_length($this->algoritmo);

        // Gera um IV aleatorio e criptograficamente seguro.
        $iv = random_bytes($tamanhoIv);

        // Cifra o texto com a chave mestra.
        $cifrado = openssl_encrypt(
            $texto,
            $this->algoritmo,
            $this->chave,
            OPENSSL_RAW_DATA,
            $iv
        );

        // Falha explicita se a extensao openssl nao conseguir cifrar.
        if ($cifrado === false) {
            throw new RuntimeException('Nao foi possivel criptografar o valor.');
        }

        // Junta IV + cifrado e codifica em Base64 para salvar no banco.
        return base64_encode($iv . $cifrado);
    }

    /**
     * Descriptografa um valor previamente cifrado.
     *
     * Retorna null quando o valor e invalido ou a chave nao confere,
     * sem lancar excecao (o chamador decide o que fazer).
     */
    public function descriptografar(string $texto): ?string
    {
        // Decodifica o Base64 salvo no banco.
        $bruto = base64_decode($texto, true);

        // Valor corrompido ou vazio.
        if ($bruto === false) {
            return null;
        }

        // Descobre o tamanho de IV exigido pelo algoritmo.
        $tamanhoIv = openssl_cipher_iv_length($this->algoritmo);

        // Precisa ter pelo menos o IV + 1 byte de conteudo.
        if (strlen($bruto) <= $tamanhoIv) {
            return null;
        }

        // Separa o IV do texto cifrado.
        $iv      = substr($bruto, 0, $tamanhoIv);
        $cifrado = substr($bruto, $tamanhoIv);

        // Decifra.
        $plano = openssl_decrypt(
            $cifrado,
            $this->algoritmo,
            $this->chave,
            OPENSSL_RAW_DATA,
            $iv
        );

        // Devolve null quando a decifragem falha.
        if ($plano === false) {
            return null;
        }

        return $plano;
    }
}
