<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace Config;

use CodeIgniter\Config\BaseConfig;
use DateTimeInterface;

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO DE COOKIES
 * --------------------------------------------------------------------------
 *
 * No CodeIgniter 4 os atributos do cookie de sessao (Secure, HttpOnly e
 * SameSite) sao configurados AQUI, e nao em app/Config/Session.php.
 *
 * Regras deste projeto:
 *   - HttpOnly sempre ligado: o JavaScript da pagina nunca le o cookie.
 *   - Secure ligado automaticamente em producao (HTTPS obrigatorio).
 *   - SameSite "Lax" para compatibilidade com navegacao normal.
 */
class Cookie extends BaseConfig
{
    /**
     * Prefixo opcional no nome dos cookies.
     * Vazio: os nomes sao usados exatamente como definidos.
     */
    public string $prefix = '';

    /**
     * Validade padrao dos cookies comuns.
     * Zero significa cookie de sessao (some ao fechar o navegador).
     *
     * @var DateTimeInterface|int|string
     */
    public $expires = 0;

    /**
     * Caminho em que o cookie e valido.
     * "/" vale para todo o site.
     */
    public string $path = '/';

    /**
     * Dominio do cookie.
     * Vazio: vale para o dominio atual (sem compartilhar entre subdominios).
     */
    public string $domain = '';

    /**
     * Envia o cookie apenas por HTTPS.
     *
     * Em producao e obrigatoriamente true. Em desenvolvimento fica false
     * para permitir o acesso por http://localhost.
     * Pode ser sobrescrito no .env pela chave "cookie.secure".
     */
    public bool $secure = ENVIRONMENT === 'production';

    /**
     * Impede o acesso ao cookie via JavaScript (document.cookie).
     * Sempre ligado: nenhum script do projeto precisa ler a sessao.
     */
    public bool $httponly = true;

    /**
     * Politica SameSite do cookie.
     *
     * "Lax" envia o cookie em navegacao normal e em POST de mesmo site,
     * bloqueando o envio em requisicoes cruzadas suspeitas.
     *
     * @var ''|'Lax'|'None'|'Strict'
     */
    public string $samesite = 'Lax';

    /**
     * Quando true, o valor do cookie nao passa por rawurlencode().
     * Mantemos false, que e o comportamento seguro para a sessao.
     */
    public bool $raw = false;
}
