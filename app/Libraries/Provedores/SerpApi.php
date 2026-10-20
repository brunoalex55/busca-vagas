<?php

namespace App\Libraries\Provedores;

use App\Contracts\JobsProviderInterface;
use App\Libraries\ExtratorEmail;
use Config\ProvedorVagas;
use DateTimeImmutable;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * PROVEDOR: SERPAPI (GOOGLE JOBS)
 * --------------------------------------------------------------------------
 *
 * Implementacao real do contrato JobsProviderInterface usando o motor
 * "google_jobs" do SerpApi.
 *
 * O QUE ESTA CLASSE FAZ
 *   1. Monta a requisicao HTTP com os parametros da consulta.
 *   2. Chama o servico externo com tempo limite e tentativas controladas.
 *   3. Traduz a resposta bruta para o formato normalizado do projeto.
 *   4. Classifica falhas de forma que o chamador saiba o que fazer
 *      (trocar de chave, tentar de novo, nao cobrar a cota...).
 *
 * O QUE ESTA CLASSE NAO FAZ
 *   - Nao conhece banco de dados, usuario ou cota.
 *   - Nao decide se a consulta pode ou nao acontecer.
 *   - Nao grava logs.
 *
 * https://serpapi.com/google-jobs-api
 */
class SerpApi implements JobsProviderInterface
{
    /**
     * Configuracao do provedor.
     */
    private ProvedorVagas $config;

    /**
     * Extrator usado para achar e-mails dentro das descricoes.
     */
    private ExtratorEmail $extrator;

    /**
     * Recebe a configuracao e o extrator (injetaveis para teste).
     */
    public function __construct(?ProvedorVagas $config = null, ?ExtratorEmail $extrator = null)
    {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(ProvedorVagas::class);
        }

        // Sem extrator injetado: cria o padrao.
        if ($extrator === null) {
            $extrator = new ExtratorEmail();
        }

        $this->config   = $config;
        $this->extrator = $extrator;
    }

    /**
     * Nome exibido em logs e diagnosticos.
     */
    public function nome(): string
    {
        return 'serpapi';
    }

    /**
     * Executa uma busca de vagas no Google Jobs via SerpApi.
     *
     * @param array<string, mixed> $consulta
     *
     * @return array<string, mixed>
     */
    public function buscar(array $consulta, string $credencial): array
    {
        // Parametros enviados ao SerpApi.
        $parametros = $this->montarParametros($consulta, $credencial);

        // Executa a chamada com tentativas para falhas de rede.
        $resposta = $this->chamarComTentativas($parametros);

        // Falha de rede / servidor: nao cobra a cota do usuario.
        if ($resposta['erro'] !== null) {
            return $this->resultado(self::STATUS_ERRO_HTTP, $resposta['erro'], false);
        }

        // Corpo vazio ou JSON invalido: trata como erro do servico.
        if ($resposta['dados'] === null) {
            return $this->resultado(self::STATUS_ERRO_HTTP, 'Resposta invalida do provedor.', false);
        }

        // O SerpApi pode devolver erro de negocio com HTTP 200.
        $erroApi = $this->erroDaResposta($resposta['dados']);

        if ($erroApi !== null) {
            return $this->classificarErroApi($erroApi);
        }

        // Codigo HTTP fora do esperado (401, 403, 429...).
        if ($resposta['codigo'] < 200 || $resposta['codigo'] >= 300) {
            return $this->classificarErroHttp($resposta['codigo'], $resposta['dados']);
        }

        // Normaliza as vagas encontradas.
        $vagas = $this->normalizarVagas($resposta['dados'], $consulta);

        // Busca concluida, mas sem resultados.
        if ($vagas === []) {
            return $this->resultado(
                self::STATUS_SEM_RESULTADOS,
                'Nenhuma vaga encontrada para esta combinacao.',
                true
            );
        }

        // Sucesso: devolve as vagas e o cursor da proxima pagina.
        return $this->resultado(
            self::STATUS_SUCESSO,
            '',
            true,
            $vagas,
            $this->tokenDaResposta($resposta['dados'])
        );
    }

    /**
     * Monta a lista de parametros da requisicao.
     *
     * @param array<string, mixed> $consulta
     *
     * @return array<string, string>
     */
    private function montarParametros(array $consulta, string $credencial): array
    {
        // Termo base informado na busca.
        $termo = trim(texto_ou($consulta, 'termo'));

        // Vagas remotas: reforca a intencao no proprio texto, porque o
        // Google Jobs nao possui um filtro dedicado de trabalho remoto.
        if (! empty($consulta['remoto'])) {
            $sufixo = trim($this->config->sufixoRemoto);

            if ($sufixo !== '' && ! str_contains(mb_strtolower($termo), mb_strtolower($sufixo))) {
                $termo .= ' ' . $sufixo;
            }
        }

        // Parametros obrigatorios do motor de vagas.
        $parametros = [
            'engine'        => $this->config->motor,
            'q'             => $termo,
            'api_key'       => $credencial,
            'google_domain' => texto_ou($consulta, 'dominio_google', $this->config->dominioGooglePadrao),
            'hl'            => texto_ou($consulta, 'idioma', $this->config->idiomaPadrao),
            'gl'            => texto_ou($consulta, 'pais', $this->config->paisPadrao),
        ];

        // Cidade: quando vazia, busca no pais inteiro.
        $cidade = trim(texto_ou($consulta, 'cidade'));

        if ($cidade !== '') {
            $parametros['location'] = $cidade;
        }

        // Paginacao por cursor (a partir da segunda pagina).
        $token = texto_ou($consulta, 'token');

        if ($token !== '') {
            $parametros['next_page_token'] = $token;
        }

        return $parametros;
    }

    /**
     * Chama o SerpApi respeitando tempo limite e repeticoes.
     *
     * @param array<string, string> $parametros
     *
     * @return array{erro: string|null, codigo: int, dados: array<string, mixed>|null}
     */
    private function chamarComTentativas(array $parametros): array
    {
        // Quantidade minima de tentativas.
        $tentativas = max(1, $this->config->tentativas);

        // Ultimo erro visto, para devolver quando todas as tentativas falharem.
        $ultimoErro = null;

        for ($tentativa = 1; $tentativa <= $tentativas; $tentativa++) {
            try {
                // Cliente HTTP do proprio CodeIgniter (sem dependencia extra).
                // Assinatura do servico no CI 4.7:
                //   curlrequest($options, $response, $config, $getShared)
                // O ultimo argumento "false" cria uma instancia nova por
                // tentativa (evita reaproveitar cabecalhos e query string).
                $cliente = service('curlrequest', $this->opcoesDoCliente(), null, null, false);

                // Executa a requisicao GET.
                $resposta = $cliente->get($this->config->urlBase, ['query' => $parametros]);

                // Codigo HTTP e corpo bruto.
                $codigo = $resposta->getStatusCode();
                $corpo  = (string) $resposta->getBody();

                // Tenta interpretar o JSON.
                $dados = json_decode($corpo, true);

                // Corpo que nao e JSON valido tambem e erro de protocolo.
                if (! is_array($dados)) {
                    $dados = null;

                    // Erro de servidor merece nova tentativa.
                    if ($codigo >= 500) {
                        $ultimoErro = 'Resposta invalida do provedor (HTTP ' . $codigo . ').';
                        $this->aguardarAntesDeTentarNovamente($tentativa, $tentativas);

                        continue;
                    }
                }

                // Codigo 5xx (instabilidade) deve ser repetido.
                if ($codigo >= 500) {
                    $ultimoErro = $this->mensagemDoServidor($dados);

                    // Sem mensagem do servidor: monta uma com o codigo HTTP.
                    if ($ultimoErro === null) {
                        $ultimoErro = 'O provedor respondeu HTTP ' . $codigo . '.';
                    }

                    $this->aguardarAntesDeTentarNovamente($tentativa, $tentativas);

                    continue;
                }

                // Qualquer outra resposta e considerada definitiva.
                return [
                    'erro'   => null,
                    'codigo' => $codigo,
                    'dados'  => $dados,
                ];
            } catch (Throwable $e) {
                // Timeout, DNS, TLS, conexao recusada...
                $ultimoErro = $this->mensagemDaFalha($e);
                $this->aguardarAntesDeTentarNovamente($tentativa, $tentativas);
            }
        }

        // Todas as tentativas falharam.
        $erroFinal = $ultimoErro;

        // Sem erro registrado: mensagem generica.
        if ($erroFinal === null) {
            $erroFinal = 'Falha desconhecida ao consultar o provedor.';
        }

        return [
            'erro'   => $erroFinal,
            'codigo' => 0,
            'dados'  => null,
        ];
    }

    /**
     * Monta as opcoes do cliente HTTP.
     *
     * @return array<string, mixed>
     */
    private function opcoesDoCliente(): array
    {
        $opcoes = [
            'timeout'     => $this->config->tempoLimite,
            'http_errors' => false,
            'headers'     => [
                'Accept'     => 'application/json',
                'User-Agent' => $this->config->userAgent,
            ],
        ];

        // Bundle de CA quando o PHP nao encontra os certificados do sistema.
        $certificado = trim($this->config->certificadoCa);

        if ($certificado !== '' && is_file($certificado)) {
            $opcoes['verify'] = $certificado;
        }

        return $opcoes;
    }

    /**
     * Traduz a falha do cURL em uma mensagem que o admin consiga resolver.
     */
    private function mensagemDaFalha(Throwable $e): string
    {
        $mensagem = $e->getMessage();

        // Erro 60: o PHP nao achou os certificados raiz (bundle de CA).
        if (str_starts_with($mensagem, '60 :')) {
            return 'Certificados do sistema ausentes (erro 60 do cURL): '
                . 'configure o bundle de CA em "curl.cainfo" (php.ini) '
                . 'ou em "provedorVagas.certificadoCa".';
        }

        return 'Falha de comunicacao com o provedor: ' . $mensagem;
    }

    /**
     * Espera antes da proxima tentativa (nunca depois da ultima).
     */
    private function aguardarAntesDeTentarNovamente(int $tentativa, int $total): void
    {
        // Nao espera se nao houver proxima tentativa.
        if ($tentativa >= $total) {
            return;
        }

        // Espera configurada (pode ser zero).
        $espera = max(0, $this->config->esperaEntreTentativas);

        if ($espera > 0) {
            sleep($espera);
        }
    }

    /**
     * Extrai a mensagem de erro que o proprio SerpApi devolve.
     *
     * @param array<string, mixed>|null $dados
     */
    private function erroDaResposta(?array $dados): ?string
    {
        // Sem corpo nao ha mensagem.
        if ($dados === null) {
            return null;
        }

        // Campo "error" e o formato padrao do SerpApi.
        $erro = valor_ou($dados, 'error', null);

        if (is_string($erro) && trim($erro) !== '') {
            return trim($erro);
        }

        // Alguns motores usam search_metadata.status = "Error".
        $metadados = valor_ou($dados, 'search_metadata', []);
        $status    = valor_ou($metadados, 'status', null);

        if (is_string($status) && strtolower($status) === 'error') {
            return 'O provedor reportou erro na busca.';
        }

        return null;
    }

    /**
     * Mensagem de erro embutida em uma resposta 5xx.
     *
     * @param array<string, mixed>|null $dados
     */
    private function mensagemDoServidor(?array $dados): ?string
    {
        $erro = valor_ou($dados, 'error', null);

        if (is_string($erro) && trim($erro) !== '') {
            return trim($erro);
        }

        return null;
    }

    /**
     * Classifica um erro devolvido no corpo (HTTP 200 ou nao).
     *
     * @return array<string, mixed>
     */
    private function classificarErroApi(string $mensagem): array
    {
        // Texto normalizado para comparacao.
        $texto = mb_strtolower($mensagem);

        // Credencial recusada.
        if (
            str_contains($texto, 'invalid api key')
            || str_contains($texto, 'invalid api_key')
            || str_contains($texto, 'unauthorized')
            || str_contains($texto, 'api key is not valid')
        ) {
            return $this->resultado(self::STATUS_CHAVE_INVALIDA, $mensagem, false);
        }

        // Cota do provedor esgotada: a chave deve sair de circulacao.
        if (
            str_contains($texto, 'run out of searches')
            || str_contains($texto, 'searches left')
            || str_contains($texto, 'out of searches')
            || str_contains($texto, 'account has run out')
            || str_contains($texto, 'exceeded')
        ) {
            // O usuario NAO e cobrado: a culpa e da credencial esgotada.
            // Quem cuida do saldo da chave e o orquestrador (esgotar()).
            return $this->resultado(self::STATUS_LIMITE_ATINGIDO, $mensagem, false);
        }

        // Qualquer outro erro de negocio.
        return $this->resultado(self::STATUS_ERRO_API, $mensagem, false);
    }

    /**
     * Classifica um erro por codigo HTTP.
     *
     * @param array<string, mixed>|null $dados
     *
     * @return array<string, mixed>
     */
    private function classificarErroHttp(int $codigo, ?array $dados): array
    {
        // Mensagem do provedor, quando existir.
        $mensagem = $this->mensagemDoServidor($dados);

        // Sem mensagem do servidor: monta uma com o codigo HTTP.
        if ($mensagem === null) {
            $mensagem = 'O provedor respondeu HTTP ' . $codigo . '.';
        }

        // Se veio uma mensagem conhecida, ela decide a classificacao.
        if ($dados !== null && $this->erroDaResposta($dados) !== null) {
            return $this->classificarErroApi($mensagem);
        }

        // 401/403 sem mensagem: credencial invalida.
        if (in_array($codigo, [401, 403], true)) {
            return $this->resultado(self::STATUS_CHAVE_INVALIDA, $mensagem, false);
        }

        // 429 sem mensagem: excesso de requisicoes; nao cobra o usuario.
        if ($codigo === 429) {
            return $this->resultado(self::STATUS_ERRO_HTTP, 'Limite de requisicoes atingido. Tente novamente mais tarde.', false);
        }

        // 400/422: parametros invalidos.
        if (in_array($codigo, [400, 422], true)) {
            return $this->resultado(self::STATUS_ERRO_API, $mensagem, false);
        }

        // Qualquer outro codigo.
        return $this->resultado(self::STATUS_ERRO_HTTP, $mensagem, false);
    }

    /**
     * Traduz a resposta bruta para a lista normalizada de vagas.
     *
     * @param array<string, mixed> $dados
     * @param array<string, mixed> $consulta
     *
     * @return list<array<string, mixed>>
     */
    private function normalizarVagas(array $dados, array $consulta): array
    {
        // Lista devolvida pelo motor de vagas.
        $resultados = valor_ou($dados, 'jobs_results', []);

        if (! is_array($resultados) || $resultados === []) {
            return [];
        }

        // Cidade que originou a consulta (guardada junto da vaga).
        $cidade = trim(texto_ou($consulta, 'cidade'));

        $vagas = [];

        foreach ($resultados as $resultado) {
            // Ignora itens que nao sejam objetos.
            if (! is_array($resultado)) {
                continue;
            }

            // Titulo ausente invalida o anuncio.
            $titulo = trim(texto_ou($resultado, 'title'));

            if ($titulo === '') {
                continue;
            }

            // Demais campos basicos.
            $empresa     = trim(texto_ou($resultado, 'company_name'));
            $localizacao = trim(texto_ou($resultado, 'location'));

            // Sem localizacao no anuncio: usa a cidade consultada.
            if ($localizacao === '') {
                $localizacao = $cidade;
            }

            // Links de candidatura (apply_options) e link publico.
            $links         = $this->extrairLinks($resultado);
            $urlCandidatura = valor_ou($links, 0, '');
            $urlOriginal = trim(texto_ou($resultado, 'share_link'));

            // Sem link publico: usa o link de candidatura.
            if ($urlOriginal === '') {
                $urlOriginal = $urlCandidatura;
            }

            // Descricao completa.
            $descricao = texto_ou($resultado, 'description');

            // E-mails: primeiro na descricao, depois nos links.
            $emailsDescricao = $this->extrator->extrairDeTexto($descricao);
            $emailsLinks     = $this->extrator->extrairDeLinks(
                array_merge($links, $this->extrairLinksRelacionados($resultado))
            );
            $escolhido       = $this->extrator->escolher($emailsDescricao, $emailsLinks);

            // Extensoes detectadas pelo Google (salario, contrato, data).
            $extensoes = [];

            if (is_array(valor_ou($resultado, 'detected_extensions', null))) {
                $extensoes = $resultado['detected_extensions'];
            }

            $listaExtensoes = [];

            if (is_array(valor_ou($resultado, 'extensions', null))) {
                $listaExtensoes = array_values(array_filter($resultado['extensions'], 'is_string'));
            }

            // Identificador da vaga na origem. Sem ele, gera um estavel
            // a partir do conteudo (o banco exige job_id).
            $jobId = trim(texto_ou($resultado, 'job_id'));

            if ($jobId === '') {
                $jobId = 'hash-' . hash('sha256', mb_strtolower($titulo . '|' . $empresa . '|' . $localizacao));
            }

            $vagas[] = [
                'job_id'              => $jobId,
                'titulo'              => $titulo,
                'empresa'             => $empresa,
                'localizacao'         => $localizacao,
                'fonte'               => $this->identificarFonte($resultado, $links),
                'url_original'        => $urlOriginal,
                'url_candidatura'     => $urlCandidatura,
                'descricao'           => $descricao,
                'salario'             => $this->identificarSalario($extensoes, $listaExtensoes),
                'tipo_contrato'       => $this->identificarContrato($extensoes, $listaExtensoes),
                'publicado_em'        => $this->converterPublicacao($extensoes),
                'via'                 => trim(texto_ou($resultado, 'via')),
                'emails_encontrados'  => $escolhido['todos'],
                'email_contato'       => $escolhido['email'],
                'origem_email'        => $escolhido['origem'],
            ];
        }

        return $vagas;
    }

    /**
     * Lista os links de candidatura (campo apply_options).
     *
     * @param array<string, mixed> $resultado
     *
     * @return list<string>
     */
    private function extrairLinks(array $resultado): array
    {
        $opcoes = valor_ou($resultado, 'apply_options', []);

        if (! is_array($opcoes)) {
            return [];
        }

        $links = [];

        foreach ($opcoes as $opcao) {
            if (is_array($opcao) && ! empty($opcao['link']) && is_string($opcao['link'])) {
                $links[] = trim($opcao['link']);
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * Lista links relacionados (paginas do anunciante, por exemplo).
     *
     * @param array<string, mixed> $resultado
     *
     * @return list<string>
     */
    private function extrairLinksRelacionados(array $resultado): array
    {
        $relacionados = valor_ou($resultado, 'related_links', []);

        if (! is_array($relacionados)) {
            return [];
        }

        $links = [];

        foreach ($relacionados as $link) {
            if (is_array($link) && ! empty($link['link']) && is_string($link['link'])) {
                $links[] = trim($link['link']);
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * Descobre o portal de origem da vaga.
     *
     * O campo "via" vem no formato "via LinkedIn" e e o mais confiavel,
     * pois e o portal onde o Google encontrou o anuncio.
     *
     * @param array<string, mixed> $resultado
     * @param list<string>         $links
     */
    private function identificarFonte(array $resultado, array $links): string
    {
        // 1. Campo "via" do Google Jobs.
        $via = trim(texto_ou($resultado, 'via'));

        if ($via !== '') {
            $via = (string) preg_replace('/^via\s+/i', '', $via);

            if ($via !== '') {
                return mb_substr($via, 0, 60);
            }
        }

        // 2. Dominio do primeiro link de candidatura.
        if ($links !== []) {
            $host = (string) parse_url($links[0], PHP_URL_HOST);
            $host = (string) preg_replace('/^www\./i', '', $host);

            if ($host !== '') {
                return mb_substr($host, 0, 60);
            }
        }

        // 3. Sem informacao confiavel.
        return 'Outras';
    }

    /**
     * Descobre a faixa salarial informada.
     *
     * @param array<string, mixed> $extensoes
     * @param list<string>         $lista
     */
    private function identificarSalario(array $extensoes, array $lista): string
    {
        // Campo dedicado do Google.
        $salario = trim(texto_ou($extensoes, 'salary'));

        if ($salario !== '') {
            return mb_substr($salario, 0, 120);
        }

        // Alguns anuncios trazem o salario apenas na lista de extensoes.
        foreach ($lista as $item) {
            if (preg_match('/(R\$|US\$|\$|€|£)\s?[\d.,]+/u', $item)) {
                return mb_substr(trim($item), 0, 120);
            }
        }

        return '';
    }

    /**
     * Descobre o tipo de contrato (CLT, PJ, tempo integral...).
     *
     * @param array<string, mixed> $extensoes
     * @param list<string>         $lista
     */
    private function identificarContrato(array $extensoes, array $lista): string
    {
        // 1. Sigla brasileira explicita na lista de extensoes.
        foreach ($lista as $item) {
            if (preg_match('/\b(CLT|PJ|MEI|Estagio|Aprendiz|Intermitente|Temporario)\b/iu', $item, $achado)) {
                return mb_substr(trim($item), 0, 120);
            }
        }

        // 2. Tipo de jornada informado pelo Google.
        $jornada = trim(texto_ou($extensoes, 'schedule_type'));

        if ($jornada !== '') {
            return mb_substr($jornada, 0, 120);
        }

        // 3. Primeira extensao simples (ex.: "Tempo integral").
        foreach ($lista as $item) {
            $limpo = trim($item);

            if ($limpo !== '' && ! preg_match('/[\d]/', $limpo)) {
                return mb_substr($limpo, 0, 120);
            }
        }

        return '';
    }

    /**
     * Converte a data relativa do Google ("ha 2 dias") em uma data.
     *
     * O valor e necessariamente aproximado: o Google informa a idade do
     * anuncio, nao a data exata. Serve para ordenar e contextualizar.
     *
     * @param array<string, mixed> $extensoes
     */
    private function converterPublicacao(array $extensoes): ?string
    {
        // Texto informado pelo Google.
        $texto = trim(texto_ou($extensoes, 'posted_at'));

        if ($texto === '') {
            return null;
        }

        // Normaliza para facilitar a leitura ("há 2 dias" -> "ha 2 dias").
        $texto = mb_strtolower((string) preg_replace('/\s+/', ' ', $texto));

        // Padroes aceitos: "ha X (minuto|hora|dia|semana|mes|ano)".
        $padrao = '/(\d+)\s*(minuto|minutos|hora|horas|dia|dias|semana|semanas|mes|meses|ano|anos)/u';

        if (! preg_match($padrao, $texto, $achado)) {
            return null;
        }

        $quantidade = max(0, (int) $achado[1]);
        $unidade    = $achado[2];

        // Traduz a unidade para um intervalo de tempo.
        $emDias = match (true) {
            str_starts_with($unidade, 'minuto') => 0.0,
            str_starts_with($unidade, 'hora')   => $quantidade / 24,
            str_starts_with($unidade, 'dia')    => (float) $quantidade,
            str_starts_with($unidade, 'semana') => $quantidade * 7.0,
            str_starts_with($unidade, 'mes')    => $quantidade * 30.0,
            default                             => $quantidade * 365.0,
        };

        // Datas absurdamente antigas nao agregam informacao confiavel.
        if ($emDias > 365) {
            return null;
        }

        // Aproxima a data de publicacao.
        $agora = new DateTimeImmutable('now');

        return $agora->modify('-' . (int) round($emDias) . ' days')->format('Y-m-d');
    }

    /**
     * Le o cursor da proxima pagina, quando o provedor oferecer.
     *
     * @param array<string, mixed> $dados
     */
    private function tokenDaResposta(array $dados): ?string
    {
        // Campo padrao de paginacao do SerpApi.
        $token = valor_ou(valor_ou($dados, 'serpapi_pagination', []), 'next_page_token', null);

        // Formato alternativo: sem token, tenta o outro bloco de paginacao.
        if ($token === null) {
            $token = valor_ou(valor_ou($dados, 'pagination', []), 'next_page_token', null);
        }

        // Token valido: devolve; caso contrario, nulo.
        if (! is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }

    /**
     * Monta a estrutura de retorno padronizada.
     *
     * @param list<array<string, mixed>> $vagas
     *
     * @return array<string, mixed>
     */
    private function resultado(
        string $status,
        string $mensagem,
        bool $consumiu,
        array $vagas = [],
        ?string $token = null
    ): array {
        return [
            'status'               => $status,
            'mensagem'             => $mensagem,
            'consumiu'             => $consumiu,
            'vagas'                => $vagas,
            'token_proxima_pagina' => $token,
        ];
    }
}
