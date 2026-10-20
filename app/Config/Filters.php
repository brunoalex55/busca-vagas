<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace Config;

use App\Filters\AdminFilter;
use App\Filters\AutenticacaoFilter;
use App\Filters\UsuarioAtivoFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

/**
 * --------------------------------------------------------------------------
 * FILTROS DA APLICACAO
 * --------------------------------------------------------------------------
 *
 * REGRA DE SEGURANCA DESTE PROJETO:
 * O sistema NAO usa token CSRF. O filtro 'csrf' foi removido por
 * completo. A protecao de sessao e feita apenas pelos recursos
 * nativos do CodeIgniter 4 (session()).
 *
 * As acoes de escrita sao todas via POST, o que ja impede que um
 * link ou imagem dispare alteracoes de estado acidentalmente.
 */
class Filters extends BaseFilters
{
    /**
     * Apelidos (aliases) para as classes de filtro.
     * Permite usar nomes curtos em Routes.php.
     *
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        // Filtros de autenticacao e autorizacao do sistema.
        'autenticacao'  => AutenticacaoFilter::class, // Exige sessao valida.
        'admin'         => AdminFilter::class,        // Exige perfil admin.
        'usuarioativo'  => UsuarioAtivoFilter::class, // Exige status ativo.

        // Filtros nativos mantidos para funcionalidades basicas.
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
    ];

    /**
     * Filtros especiais obrigatorios do framework.
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Forca HTTPS quando configurado.
            'pagecache',  // Cache de pagina.
        ],
        'after' => [
            'pagecache',   // Cache de pagina.
            'performance', // Metricas de performance.
            'toolbar',     // Barra de debug (apenas em development).
        ],
    ];

    /**
     * Filtros globais aplicados a TODA requisicao.
     *
     * Nao existe 'csrf' aqui: a decisao arquitetural do projeto e
     * usar somente sessao nativa.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            // 'honeypot',
            // 'csrf',        // PROIBIDO neste projeto.
            // 'invalidchars',
        ],
        'after' => [
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * Filtros aplicados por metodo HTTP (GET, POST, ...).
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * Filtros aplicados por padrao de URI.
     * Os grupos de rota em Routes.php tambem aplicam os filtros,
     * o que deixa a intencao explicita em cada rota.
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
