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

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * PROTECAO CSRF
     * --------------------------------------------------------------------------
     *
     * Este projeto NAO usa token CSRF. A decisao arquitetural e a protecao
     * pela sessao nativa do CodeIgniter 4:
     *   - cookie de sessao com HttpOnly, SameSite e Secure em producao;
     *   - regeneracao do id da sessao no login e na troca de senha;
     *   - toda acao de escrita passa por POST.
     *
     * O filtro 'csrf' foi removido de Config\Filters e nenhum formulario
     * usa csrf_field(). Por isso o valor abaixo e o padrao do framework,
     * que fica INERTE: sem o filtro registrado, o framework nunca gera,
     * envia nem valida token CSRF. O nome do token fica vazio para deixar
     * claro que nada procura o campo 'csrf_test_name' no pedido.
     *
     * @var string 'cookie' ou 'session' (inerte neste projeto)
     */
    public string $csrfProtection = 'cookie';

    /**
     * --------------------------------------------------------------------------
     * RANDOMIZACAO DO TOKEN CSRF
     * --------------------------------------------------------------------------
     *
     * Mantido desligado: nao existe token CSRF neste projeto.
     */
    public bool $tokenRandomize = false;

    /**
     * --------------------------------------------------------------------------
     * NOME DO TOKEN CSRF
     * --------------------------------------------------------------------------
     *
     * Vazio de proposito: nenhum formulario usa csrf_field() e nenhuma
     * rota valida token CSRF.
     */
    public string $tokenName = '';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     *
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     *
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     */
    public int $expires = 7200;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     *
     * Regenerate CSRF Token on every submission.
     */
    public bool $regenerate = true;

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     *
     * Redirect to previous page with error on failure.
     *
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     */
    public bool $redirect = (ENVIRONMENT === 'production');
}
