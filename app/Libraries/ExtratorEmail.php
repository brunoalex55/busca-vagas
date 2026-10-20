<?php

namespace App\Libraries;

use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: EXTRATOR DE E-MAILS
 * --------------------------------------------------------------------------
 *
 * Encontra enderecos de e-mail dentro do texto de uma vaga e dentro dos
 * links de candidatura devolvidos pelo provedor.
 *
 * POR QUE ISSO IMPORTA
 * O envio automatico so funciona para vagas que tenham um e-mail real de
 * contato. O Google Jobs costuma trazer o e-mail dentro da descricao ou
 * escondido em links de candidatura, e nao em um campo proprio.
 *
 * ORDEM DE CONFIANCA (do mais para o menos confiavel):
 *   1. descricao      -> texto escrito pelo anunciante.
 *   2. apply_options  -> links oficiais de candidatura da vaga.
 *   3. pagina         -> captura posterior da pagina (nao usada aqui).
 *
 * O extrator e conservador: nao inventa e-mail e descarta enderecos
 * tecnicos (noreply, postmaster) e falsos positivos de imagens/arquivos.
 */
class ExtratorEmail
{
    /**
     * Partes locais que nunca servem para candidatura.
     *
     * @var list<string>
     */
    private const PARTES_LOCAIS_IGNORADAS = [
        'noreply',
        'no-reply',
        'naoresponda',
        'nao-responda',
        'nao_responda',
        'donotreply',
        'do-not-reply',
        'mailer-daemon',
        'mailerdaemon',
        'postmaster',
        'abuse',
        'webmaster',
    ];

    /**
     * Dominios que sao ruido (servicos, exemplos, rastreadores).
     *
     * @var list<string>
     */
    private const DOMINIOS_IGNORADOS = [
        'serpapi.com',
        'google.com',
        'google.com.br',
        'googlemail.com.br',
        'example.com',
        'example.com.br',
        'exemplo.com',
        'exemplo.com.br',
        'dominio.com',
        'dominio.com.br',
        'seudominio.com',
        'test.com',
        'teste.com',
        'localhost',
        'sentry.io',
        'sentry-next.wixpress.com',
        'wixpress.com',
        'schema.org',
        'w3.org',
        'gravatar.com',
        'cloudflare.com',
        'jquery.com',
        'bootstrapcdn.com',
        'fontawesome.com',
    ];

    /**
     * Extensoes de arquivo que o extrator pode confundir com dominio
     * (ex.: "logo@2x.png"). Nunca sao e-mails de verdade.
     *
     * @var list<string>
     */
    private const EXTENSOES_FALSAS = [
        'png',
        'jpg',
        'jpeg',
        'gif',
        'svg',
        'webp',
        'bmp',
        'ico',
        'css',
        'js',
        'json',
        'woff',
        'woff2',
        'ttf',
        'eot',
        'mp4',
        'webm',
        'pdf',
        'zip',
        'rar',
    ];

    /**
     * Limite da coluna email_contato na tabela de vagas.
     */
    private const TAMANHO_MAXIMO = 180;

    /**
     * Extrai e-mails do texto de uma descricao.
     *
     * @return list<string> Lista unica, em minusculas.
     */
    public function extrairDeTexto(string $texto): array
    {
        // Remove marcação HTML e entidades para nao quebrar enderecos.
        $texto = $this->limpar($texto);

        // Desfaz ofuscacoes comuns usadas para escapar de robos.
        $texto = $this->desofuscar($texto);

        return $this->encontrar($texto);
    }

    /**
     * Extrai e-mails de uma lista de links (apply_options e similares).
     *
     * Aceita tanto "mailto:" quanto enderecos embutidos na URL.
     *
     * @param list<string> $links
     *
     * @return list<string> Lista unica, em minusculas.
     */
    public function extrairDeLinks(array $links): array
    {
        // Junta todos os links em um unico texto para reaproveitar a busca.
        $texto = $this->desofuscar($this->limpar(implode(' ', $links)));

        return $this->encontrar($texto);
    }

    /**
     * ETAPA 2: extrai e-mails lendo a pagina da vaga.
     *
     * Usada quando a Etapa 1 (descricao e links) nao acha nenhum e-mail.
     * A pagina e lida com um tempo limite curto e, no maximo, tres
     * redirecionamentos.
     *
     * @param string $url Pagina da vaga.
     *
     * @return list<string> Lista unica, em minusculas (vazia em caso de erro).
     */
    public function extrairDaPaginaWeb(string $url): array
    {
        // Sem URL: nada a fazer.
        if (trim($url) === '') {
            return [];
        }

        // So enderecos http(s) sao lidos.
        if (preg_match('#^https?://#i', $url) !== 1) {
            return [];
        }

        $html = $this->lerPagina($url);

        // Pagina nao lida: devolve lista vazia.
        if ($html === '') {
            return [];
        }

        // Mesmo tratamento da descricao: limpa (com entidades e caracteres
        // de largura zero), desofusca e procura o endereco.
        $texto = $this->desofuscar($this->limpar($html));

        return $this->encontrar($texto);
    }

    /**
     * Le o HTML de uma pagina (com limite de tempo e de redirecionamento).
     */
    private function lerPagina(string $url): string
    {
        try {
            // Cliente HTTP do CodeIgniter (cURL, sem SDK externo).
            $cliente = service('curlrequest', [
                'timeout'         => 5,
                'connect_timeout' => 5,
                'http_errors'     => false,
                'allow_redirects' => true,
                'max_redirects'   => 3,
                'headers'         => [
                    'Accept'     => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 (compatible; CI_EMPLOYER/1.0; +https://localhost)',
                ],
            ], null, null, false);

            $resposta = $cliente->get($url);

            // Servidor respondeu com erro: nada a extrair.
            $codigo = $resposta->getStatusCode();

            if ($codigo < 200 || $codigo >= 300) {
                return '';
            }

            return (string) $resposta->getBody();
        } catch (Throwable $erro) {
            // Falha de rede: devolve lista vazia (a vaga segue sem e-mail).
            return '';
        }
    }

    /**
     * Decide qual e-mail deve ser gravado como contato principal da vaga.
     *
     * @param list<string> $daDescricao  E-mails achados na descricao.
     * @param list<string> $dosLinks     E-mails achados nos links.
     *
     * @return array{
     *     email: string|null,
     *     origem: string|null,
     *     todos: list<string>
     * }
     */
    public function escolher(array $daDescricao, array $dosLinks): array
    {
        // Junta tudo, sem repetir, preservando a ordem de confianca.
        $todos = $this->unir($daDescricao, $dosLinks);

        // A descricao tem prioridade absoluta.
        if ($daDescricao !== []) {
            return [
                'email'  => $daDescricao[0],
                'origem' => 'descricao',
                'todos'  => $todos,
            ];
        }

        // Depois vem o que apareceu nos links de candidatura.
        if ($dosLinks !== []) {
            return [
                'email'  => $dosLinks[0],
                'origem' => 'apply_options',
                'todos'  => $todos,
            ];
        }

        // Nenhum e-mail confiavel encontrado.
        return [
            'email'  => null,
            'origem' => null,
            'todos'  => [],
        ];
    }

    /**
     * Remove marcacao HTML, entidades e espacos repetidos.
     */
    public function limpar(string $texto): string
    {
        // Blocos de script/estilo: descarta o conteudo inteiro.
        $texto = (string) preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', ' ', $texto);

        // As demais tags viram ESPACO, e nao string vazia: sem isso,
        // "<p>a</p><p>b</p>" viraria "ab" e um e-mail colado ao texto
        // seguinte seria lido errado (ex.: "rh@empresa.com.brNao").
        $texto = (string) preg_replace('/<[^>]*>/', ' ', $texto);

        // Converte entidades conhecidas (&amp;, &#64;, &nbsp;...).
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove os caracteres de largura zero.
        //
        // Alguns sites (o Divulga Vagas, por exemplo) escondem o "@"
        // dentro de uma entidade &ZeroWidthSpace; (U+200B). Sem remover,
        // o e-mail vira "rh@empresa.com" com um caractere invisivel no
        // meio e a expressao regular nao acha o endereco.
        $texto = (string) preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $texto);

        // Troca espacos exoticos (nao quebraveis) por espaco comum.
        $texto = (string) preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $texto);

        // Normaliza espacos em branco (inclusive quebras de linha).
        return (string) preg_replace('/\s+/u', ' ', $texto);
    }

    /**
     * Desfaz ofuscacoes tipicas: "(at)", "[at]", " arroba ", "(dot)" e
     * espacos soltos em volta do arroba.
     *
     * So substitui quando o padrao esta isolado, para nao corromper
     * palavras normais do texto.
     */
    public function desofuscar(string $texto): string
    {
        // Formas de arroba.
        $texto = (string) preg_replace(
            ['/\s*\(\s*at\s*\)\s*/i', '/\s*\[\s*at\s*\]\s*/i', '/\s+arroba\s+/i'],
            '@',
            $texto
        );

        // Formas de ponto.
        $texto = (string) preg_replace(
            ['/\s*\(\s*dot\s*\)\s*/i', '/\s*\[\s*dot\s*\]\s*/i', '/\s+\[ponto\]\s*/i'],
            '.',
            $texto
        );

        // Espaco em volta do arroba nunca faz parte do endereco
        // (comum em HTML: "rh&nbsp;@empresa.com.br").
        return (string) preg_replace('/\s*@\s*/', '@', $texto);
    }

    /**
     * Aplica a expressao regular e devolve apenas enderecos confiaveis.
     *
     * @return list<string>
     */
    private function encontrar(string $texto): array
    {
        // Expressao conservadora: exige parte local, dominio e TLD de letras.
        $padrao = '/(?<![\w.\-])'
            . '([a-z0-9][a-z0-9._%+\-]{0,63})'
            . '@'
            . '((?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,24})'
            . '(?![\w\-])/i';

        // Sem nenhum "@" no texto, nao ha o que procurar.
        if (! preg_match_all($padrao, $texto, $achados, PREG_SET_ORDER)) {
            return [];
        }

        $encontrados = [];

        foreach ($achados as $achado) {
            // Endereco completo, em minusculas e sem lixo nas pontas.
            $email = strtolower(trim($achado[0], " \t\n\r\0\x0B.,;:'\"()<>[]"));

            // Passa pelas regras de validade e ruido.
            if (! $this->ehUtilizavel($email, strtolower($achado[1]), strtolower($achado[2]))) {
                continue;
            }

            $encontrados[] = $email;
        }

        // Remove repetidos mantendo a ordem de aparicao.
        return array_values(array_unique($encontrados));
    }

    /**
     * Decide se um endereco encontrado serve como contato.
     */
    private function ehUtilizavel(string $email, string $parteLocal, string $dominio): bool
    {
        // Precisa caber na coluna do banco.
        if ($email === '' || strlen($email) > self::TAMANHO_MAXIMO) {
            return false;
        }

        // A parte local nao pode comecar ou terminar com ponto.
        if (str_starts_with($parteLocal, '.') || str_ends_with($parteLocal, '.')) {
            return false;
        }

        // Enderecos tecnicos (noreply, postmaster...) sao descartados.
        if (in_array($parteLocal, self::PARTES_LOCAIS_IGNORADAS, true)) {
            return false;
        }

        // TLD com cara de arquivo (ex.: "logo@2x.png") nao e e-mail.
        $tld = substr((string) strrchr($dominio, '.'), 1);

        if (in_array($tld, self::EXTENSOES_FALSAS, true)) {
            return false;
        }

        // Dominio precisa comecar com letra (evita "2x.png" e afins).
        if (! preg_match('/^[a-z]/', $dominio)) {
            return false;
        }

        // Dominios de servico/exemplo nunca servem como contato.
        if (in_array($dominio, self::DOMINIOS_IGNORADOS, true)) {
            return false;
        }

        // Subdominios de dominio ignorado tambem saem (ex.: x.sentry.io).
        foreach (self::DOMINIOS_IGNORADOS as $ignorado) {
            if (str_ends_with($dominio, '.' . $ignorado)) {
                return false;
            }
        }

        // Sobreviveu a todas as regras.
        return true;
    }

    /**
     * Junta duas listas sem repetir, preservando a primeira.
     *
     * @param list<string> $primeira
     * @param list<string> $segunda
     *
     * @return list<string>
     */
    private function unir(array $primeira, array $segunda): array
    {
        return array_values(array_unique(array_merge($primeira, $segunda)));
    }
}
