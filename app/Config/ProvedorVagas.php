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

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO: PROVEDOR DE VAGAS
 * --------------------------------------------------------------------------
 *
 * Define QUAL servico externo e usado para buscar vagas e COMO ele e
 * chamado. O restante da aplicacao so conhece a interface
 * App\Contracts\JobsProviderInterface, nunca este servico em concreto.
 *
 * Os valores podem ser sobrescritos pelo .env, por exemplo:
 *   provedorVagas.servico = serpapi
 *   provedorVagas.tempoLimite = 30
 */
class ProvedorVagas extends BaseConfig
{
    /**
     * Servico em uso.
     *
     *   serpapi -> provedor real (Google Jobs via SerpApi).
     *   falso   -> provedor de desenvolvimento: devolve vagas ficticias,
     *              sem acessar a internet. Serve para testar todo o fluxo
     *              (cota, rotacao de chave, deduplicacao, logs) sem chave
     *              real. NUNCA usar em producao.
     */
    public string $servico = 'serpapi';

    /**
     * Endereco do endpoint de busca do SerpApi.
     */
    public string $urlBase = 'https://serpapi.com/search.json';

    /**
     * Caminho (opcional) do bundle de certificados CA (arquivo .pem).
     *
     * Necessario apenas em maquinas onde o PHP nao encontra os certificados
     * raiz do sistema (erro 60 do cURL: "unable to get local issuer
     * certificate"). Nesse caso, aponte para o bundle:
     *
     *   provedorVagas.certificadoCa = C:/caminho/cacert.pem
     *
     * Vazio = usar a configuracao do proprio PHP (php.ini ou store do SO).
     */
    public string $certificadoCa = '';

    /**
     * Tempo maximo, em segundos, de cada requisicao.
     */
    public int $tempoLimite = 30;

    /**
     * Quantas vezes tentar de novo quando falhar por rede/timeout.
     * Nao vale para erros de credencial (nesses casos troca-se a chave).
     */
    public int $tentativas = 2;

    /**
     * Intervalo de espera, em segundos, antes de repetir uma tentativa.
     */
    public int $esperaEntreTentativas = 2;

    /**
     * Quantas paginas pedir por combinacao (termo + cidade).
     *
     * Cada pagina e UMA consulta cobrada pelo provedor, portanto
     * tambem consome uma unidade da cota do usuario.
     */
    public int $paginasPorConsulta = 1;

    /**
     * Quantidade de vagas pedida por pagina (quando o provedor aceita).
     */
    public int $resultadosPorPagina = 10;

    /**
     * Identificacao enviada ao provedor.
     */
    public string $userAgent = 'CI_EMPLOYER/1.0 (+https://localhost)';

    /**
     * Motor do SerpApi usado para vagas do Google.
     */
    public string $motor = 'google_jobs';

    /**
     * Dominio do Google usado quando a palavra-chave nao define um.
     */
    public string $dominioGooglePadrao = 'google.com.br';

    /**
     * Idioma padrao dos resultados quando a palavra-chave nao define.
     */
    public string $idiomaPadrao = 'pt';

    /**
     * Pais padrao dos resultados quando a palavra-chave nao define.
     */
    public string $paisPadrao = 'br';

    /**
     * Sufixo acrescentado ao termo quando a busca pede vagas remotas.
     * O Google Jobs nao tem um parametro proprio de "remoto", entao a
     * intencao e reforcada no proprio texto da busca.
     */
    public string $sufixoRemoto = 'remoto';
}
