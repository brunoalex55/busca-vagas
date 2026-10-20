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
use CodeIgniter\Session\Handlers\BaseHandler;
use CodeIgniter\Session\Handlers\FileHandler;

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO DE SESSAO
 * --------------------------------------------------------------------------
 *
 * A autenticacao deste sistema usa EXCLUSIVAMENTE a sessao nativa do
 * CodeIgniter 4. Nao existe token CSRF em nenhum lugar do projeto.
 *
 * IMPORTANTE: No CodeIgniter 4 os atributos do cookie de sessao ficam em
 * app/Config/Cookie.php, e nao aqui. Este arquivo controla apenas o
 * armazenamento da sessao.
 *
 * Equivalencia das exigencias do projeto:
 *   - HttpOnly -> Config\Cookie::$httponly = true
 *   - Secure   -> Config\Cookie::$secure = true em producao
 *   - SameSite -> Config\Cookie::$samesite = 'Lax'
 */
class Session extends BaseConfig
{
    /**
     * Driver de sessao baseado em arquivo.
     * Os arquivos ficam em writable/session/.
     *
     * @var class-string<BaseHandler>
     */
    public string $driver = FileHandler::class;

    /**
     * Nome do cookie de sessao.
     * Aceita apenas caracteres [0-9a-z_-].
     */
    public string $cookieName = 'ci_session';

    /**
     * Tempo de expiracao da sessao, em segundos.
     * 7200 segundos = 2 horas.
     */
    public int $expiration = 7200;

    /**
     * Caminho absoluto onde os arquivos de sessao sao gravados.
     */
    public string $savePath = WRITEPATH . 'session';

    /**
     * Nao vincular a sessao ao IP do visitante.
     * Evita derrubar a sessao de usuarios em redes moveis, onde o IP
     * muda com frequencia (troca de torre, 4G/5G, Wi-Fi).
     */
    public bool $matchIP = false;

    /**
     * Intervalo (em segundos) para o CodeIgniter regenerar o ID da
     * sessao automaticamente. A regeneracao manual apos o login e
     * feita explicitamente com session()->regenerate().
     */
    public int $timeToUpdate = 300;

    /**
     * Destruir os dados da sessao antiga ao regenerar o ID.
     * Isso evita que um ID antigo continue valido (session fixation).
     *
     * ATENCAO: no CodeIgniter 4.7 o metodo regenerate() recebe o parametro
     * $destroy com valor padrao FALSE. Por isso o login chama
     * session()->regenerate(true) explicitamente.
     */
    public bool $regenerateDestroy = true;

    /**
     * Grupo de banco usado quando o driver for DatabaseHandler.
     * Nao utilizamos esse driver: fica nulo.
     */
    public ?string $DBGroup = null;

    /**
     * Tempo (microssegundos) de espera para adquirir o lock no Redis.
     * Nao utilizado com o driver de arquivo.
     */
    public int $lockRetryInterval = 100_000;

    /**
     * Numero maximo de tentativas de lock no Redis.
     * Nao utilizado com o driver de arquivo.
     */
    public int $lockMaxRetries = 300;
}
