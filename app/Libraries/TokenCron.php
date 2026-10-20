<?php

namespace App\Libraries;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: TOKEN GLOBAL DO CRON
 * --------------------------------------------------------------------------
 *
 * Le, cria e regenera o token que protege os endpoints de cron.
 *
 * O token fica em writable/cron_token.txt (fora da raiz web) e NUNCA e a
 * chave da SerpApi: e apenas o segredo que o agendador envia em ?token=.
 *
 * Esta biblioteca concentra a regra para que o controller `Cron` e o
 * painel administrativo usem exatamente a mesma fonte.
 */
class TokenCron
{
    /** Caminho absoluto do arquivo que guarda o token. */
    private string $arquivo;

    /**
     * Recebe um caminho alternativo (util em testes).
     */
    public function __construct(?string $arquivo = null)
    {
        // Sem caminho informado: usa o arquivo padrao do projeto.
        if ($arquivo === null) {
            $arquivo = WRITEPATH . 'cron_token.txt';
        }

        $this->arquivo = $arquivo;
    }

    /**
     * Devolve o caminho do arquivo do token.
     */
    public function caminho(): string
    {
        return $this->arquivo;
    }

    /**
     * Le o token do disco, criando um novo quando ainda nao existir.
     */
    public function obter(): string
    {
        // Arquivo ja existente e com conteudo util.
        if (is_file($this->arquivo)) {
            $conteudo = trim((string) file_get_contents($this->arquivo));

            if ($conteudo !== '') {
                return $conteudo;
            }
        }

        // Primeira execucao: cria um token novo.
        return $this->gravar($this->gerar());
    }

    /**
     * Gera um token novo, substituindo o anterior.
     *
     * Como o token antigo deixa de valer, um agendador mal configurado
     * passa a receber 403 ate ser atualizado com o novo valor.
     */
    public function regenerar(): string
    {
        return $this->gravar($this->gerar());
    }

    /**
     * Cria um token aleatorio de 64 caracteres hexadecimais.
     */
    private function gerar(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Grava o token no disco com a menor permissao possivel.
     */
    private function gravar(string $token): string
    {
        file_put_contents($this->arquivo, $token, LOCK_EX);

        // Restringe a permissao de leitura quando o sistema permitir.
        @chmod($this->arquivo, 0600);

        return $token;
    }
}
