<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Contracts;

/**
 * --------------------------------------------------------------------------
 * CONTRATO: PROVEDOR DE VAGAS
 * --------------------------------------------------------------------------
 *
 * Esta interface e a UNICA porta de entrada para buscar vagas em servicos
 * externos. Nenhum outro ponto do sistema conhece SerpApi, Google Jobs ou
 * qualquer outro servico: tudo passa por aqui.
 *
 * Assim e possivel trocar o provedor (ex.: SerpApi -> outro) sem tocar em
 * controllers, models, views ou no agendador (cron).
 *
 * --------------------------------------------------------------------------
 * FORMATO DA CONSULTA (entrada)
 * --------------------------------------------------------------------------
 *   termo            string  Texto pesquisado (ex.: "Desenvolvedor PHP").
 *   cidade           string  Cidade da busca (ex.: "Sao Paulo"). Vazio = Brasil.
 *   remoto           bool    Quando true, a busca deve privilegiar vagas remotas.
 *   idioma           string  Idioma dos resultados (ex.: "pt").
 *   pais             string  Pais dos resultados (ex.: "br").
 *   dominio_google   string  Dominio usado na busca (ex.: "google.com.br").
 *   pagina           int     Pagina desejada, comecando em 1.
 *   quantidade       int     Quantidade maxima de vagas desejada (dica).
 *   token            string|null  Cursor da pagina anterior (quando houver).
 *
 * --------------------------------------------------------------------------
 * FORMATO DO RESULTADO (saida)
 * --------------------------------------------------------------------------
 *   status    string  Um dos valores de STATUS_* abaixo.
 *   mensagem  string  Descricao legivel (vazia quando sucesso).
 *   consumiu  bool    true quando a chamada DEVE contar na cota do usuario.
 *                     Vale true apenas para respostas em que o provedor
 *                     realmente executou a busca (sucesso ou sem resultados).
 *                     Erros de rede, credencial invalida e cota esgotada
 *                     NAO consomem a cota do usuario.
 *   vagas     list<array<string, mixed>>  Vagas normalizadas (ver VAGA_*).
 *   token_proxima_pagina  string|null  Cursor para a pagina seguinte.
 *
 * OBSERVACAO SOBRE "token": e um cursor opaco do provedor. Quem orquestra a
 * busca apenas devolve o valor recebido, sem interpretar o conteudo. Quando
 * o provedor nao pagina, basta devolver null.
 *
 * --------------------------------------------------------------------------
 * FORMATO DA VAGA NORMALIZADA (chaves de cada item de "vagas")
 * --------------------------------------------------------------------------
 *   job_id            string  Identificador na origem (obrigatorio).
 *   titulo            string
 *   empresa           string
 *   localizacao       string
 *   fonte             string  Nome do portal (Indeed, LinkedIn, Catho, Gupy...).
 *   url_original      string  Link publico do anuncio.
 *   url_candidatura   string  Link direto de candidatura (quando existir).
 *   descricao         string  Texto completo do anuncio.
 *   salario           string  Faixa salarial (quando informada).
 *   tipo_contrato     string  CLT, PJ, Tempo integral, etc.
 *   publicado_em      string|null  Data Y-m-d (quando o provedor informa).
 *   via               string  Portal intermediario ("via" do Google Jobs).
 */
interface JobsProviderInterface
{
    /** A busca foi concluida e devolveu uma lista (mesmo vazia). */
    public const STATUS_SUCESSO = 'sucesso';

    /** A busca foi concluida, mas nao havia nenhuma vaga. */
    public const STATUS_SEM_RESULTADOS = 'sem_resultados';

    /** Falha de rede, timeout ou resposta HTTP fora do esperado. */
    public const STATUS_ERRO_HTTP = 'erro_http';

    /** O servico respondeu, mas com erro de negocio (parametro invalido etc.). */
    public const STATUS_ERRO_API = 'erro_api';

    /** A credencial usada e invalida ou foi recusada. */
    public const STATUS_CHAVE_INVALIDA = 'chave_invalida';

    /** A credencial acabou (cota do provedor esgotada). */
    public const STATUS_LIMITE_ATINGIDO = 'limite_atingido';

    /**
     * Executa UMA consulta ao provedor.
     *
     * @param array{
     *     termo: string,
     *     cidade: string,
     *     remoto: bool,
     *     idioma: string,
     *     pais: string,
     *     dominio_google: string,
     *     pagina: int,
     *     quantidade: int,
     *     token: string|null
     * } $consulta Dados da busca a realizar.
     * @param string $credencial Chave da API a ser usada nesta chamada.
     *
     * @return array{
     *     status: string,
     *     mensagem: string,
     *     consumiu: bool,
     *     vagas: list<array<string, mixed>>,
     *     token_proxima_pagina: string|null
     * }
     */
    public function buscar(array $consulta, string $credencial): array;

    /**
     * Nome curto do provedor, usado apenas em logs e telas de diagnostico.
     */
    public function nome(): string;
}
