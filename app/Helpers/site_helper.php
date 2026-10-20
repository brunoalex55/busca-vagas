<?php

/**
 * --------------------------------------------------------------------------
 * HELPER: SITE
 * --------------------------------------------------------------------------
 *
 * Funcoes de apoio usadas pelas views do site publico e pelos
 * controllers. Mantem o codigo das views limpo.
 */

if (! function_exists('configuracao_site')) {
    /**
     * Retorna a configuracao do site (registro unico de configuracao_site).
     *
     * O resultado fica em cache estatico durante a requisicao, para
     * nao consultar o banco em cada view.
     *
     * @return array<string, mixed>
     */
    function configuracao_site(): array
    {
        // Cache estatico por requisicao.
        static $cache = null;

        // Se ja carregou nesta requisicao, devolve o cache.
        if ($cache !== null) {
            return $cache;
        }

        try {
            // Carrega o model e busca o registro unico.
            $cache = model(\App\Models\ConfiguracaoSiteModel::class)->obter();
        } catch (\Throwable $e) {
            // Em caso de banco indisponivel (ex.: durante a instalacao),
            // devolve valores padrao para o layout nao quebrar.
            $cache = [
                'nome_site'          => 'BuscaVagas PRO',
                'slogan'             => 'Sua busca de emprego em piloto automatico',
                'descricao_site'     => 'Encontre vagas e candidate-se automaticamente.',
                'palavras_chave_seo' => 'busca de emprego, vagas, candidatura automatica',
                'email_contato'      => '',
                'telefone_contato'   => '',
                'whatsapp_contato'   => '',
                'endereco'           => '',
                'logo'               => null,
                'favicon'            => null,
                'imagem_og'          => null,
                'texto_rodape'       => '',
                'texto_sobre'        => '',
            ];
        }

        return $cache;
    }
}

if (! function_exists('configuracao_pagamento')) {
    /**
     * Retorna a configuracao unica de pagamento (chave PIX etc).
     *
     * @return array<string, mixed>
     */
    function configuracao_pagamento(): array
    {
        // Cache estatico por requisicao.
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            $cache = model(\App\Models\ConfiguracaoPagamentoModel::class)->obter();
        } catch (\Throwable $e) {
            // Fallback seguro.
            $cache = [
                'chave_pix'        => '',
                'valor_mensal'     => '0.00',
                'texto_instrucoes' => 'Configuracao de pagamento ainda nao cadastrada.',
            ];
        }

        return $cache;
    }
}

if (! function_exists('resumir_texto')) {
    /**
     * Corta um texto no limite de caracteres, preservando palavras.
     * Util para meta description e trechos de descricao de vaga.
     */
    function resumir_texto(?string $texto, int $limite = 160, string $sufixo = '...'): string
    {
        // Normaliza espacos e remove tags HTML.
        $limpo = trim(preg_replace('/\s+/', ' ', strip_tags((string) $texto)));

        // Texto vazio.
        if ($limpo === '') {
            return '';
        }

        // Se couber no limite, devolve inteiro.
        if (mb_strlen($limpo) <= $limite) {
            return $limpo;
        }

        // Corta e procura o ultimo espaco para nao quebrar palavra.
        $corte = mb_substr($limpo, 0, $limite);
        $ultimoEspaco = mb_strrpos($corte, ' ');

        if ($ultimoEspaco !== false) {
            $corte = mb_substr($corte, 0, $ultimoEspaco);
        }

        return $corte . $sufixo;
    }
}

if (! function_exists('formatar_data_hora')) {
    /**
     * Formata uma data/hora do banco no padrao brasileiro.
     * Devolve um traco quando o valor e vazio ou invalido.
     */
    function formatar_data_hora(?string $valor, bool $comHora = true): string
    {
        // Valor vazio.
        if ($valor === null || trim($valor) === '') {
            return '—';
        }

        // Converte para timestamp.
        $timestamp = strtotime($valor);

        // Valor invalido.
        if ($timestamp === false) {
            return '—';
        }

        // Formato conforme a preferencia.
        $formato = 'd/m/Y';

        if ($comHora) {
            $formato = 'd/m/Y H:i';
        }

        return date($formato, $timestamp);
    }
}

if (! function_exists('formatar_tamanho')) {
    /**
     * Converte bytes em um texto legivel (KB, MB).
     */
    function formatar_tamanho(int $bytes): string
    {
        // Valores negativos nao fazem sentido.
        if ($bytes <= 0) {
            return '0 KB';
        }

        // Abaixo de 1 MB: mostra em KB.
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }

        // Acima de 1 MB: mostra em MB com uma casa decimal.
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
}

if (! function_exists('rotulo_dias_semana')) {
    /**
     * Transforma "1,2,3,4,5" em "Seg, Ter, Qua, Qui, Sex".
     *
     * @param string|array<int|string> $dias
     */
    function rotulo_dias_semana(string|array $dias): string
    {
        // Nomes curtos dos dias (1 = segunda ... 7 = domingo).
        $nomes = [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sab',
            7 => 'Dom',
        ];

        // Aceita string "1,2,3" ou array de numeros.
        $lista = explode(',', $dias);

        if (is_array($dias)) {
            $lista = $dias;
        }

        // Normaliza e remove valores invalidos.
        $rotulos = [];

        foreach ($lista as $dia) {
            $numero = (int) trim((string) $dia);

            if (isset($nomes[$numero])) {
                $rotulos[] = $nomes[$numero];
            }
        }

        // Todos os dias.
        if (count($rotulos) === 7) {
            return 'Todos os dias';
        }

        // Nenhum dia valido.
        if ($rotulos === []) {
            return 'Nenhum dia';
        }

        return implode(', ', $rotulos);
    }
}

if (! function_exists('url_segura')) {
    /**
     * Devolve a URL apenas quando ela usa http ou https.
     *
     * Evita que um link vindo do provedor de vagas use esquemas
     * perigosos (ex.: javascript:).
     */
    function url_segura(?string $url): ?string
    {
        // Valor vazio.
        if ($url === null || trim($url) === '') {
            return null;
        }

        // Precisa ser uma URL valida.
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        // Somente http e https.
        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($esquema, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}

if (! function_exists('whatsapp_link')) {
    /**
     * Monta um link wa.me a partir de um telefone com mascara.
     * Retorna string vazia quando nao ha numero configurado.
     */
    function whatsapp_link(?string $numero, string $mensagem = ''): string
    {
        // Remove tudo que nao for digito.
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        // Sem numero valido, nao gera link.
        if ($digitos === '') {
            return '';
        }

        // Monta a URL basica.
        $url = 'https://wa.me/' . $digitos;

        // Acrescenta a mensagem pre-preenchida, se houver.
        if ($mensagem !== '') {
            $url .= '?text=' . rawurlencode($mensagem);
        }

        return $url;
    }
}

if (! function_exists('iniciais_nome')) {
    /**
     * Extrai as iniciais de um nome (ex.: "Ana Souza" -> "AS").
     * Usado nos avatares feitos em CSS.
     */
    function iniciais_nome(string $nome): string
    {
        // Separa as palavras do nome.
        $partes = preg_split('/\s+/', trim($nome));

        // Sem palavras, devolve um marcador.
        if (empty($partes)) {
            return '?';
        }

        // Pega a primeira letra da primeira palavra.
        $iniciais = mb_substr($partes[0], 0, 1);

        // Se houver mais de uma palavra, pega a inicial da ultima.
        if (count($partes) > 1) {
            $iniciais .= mb_substr($partes[count($partes) - 1], 0, 1);
        }

        return mb_strtoupper($iniciais);
    }
}

if (! function_exists('url_atual')) {
    /**
     * Retorna a URL absoluta da requisicao atual.
     * Usada em canonical e Open Graph.
     */
    function url_atual(): string
    {
        return current_url();
    }
}

if (! function_exists('asset_url')) {
    /**
     * Monta a URL de um asset em public/ acrescentando um parametro
     * de versao baseado na data de modificacao do arquivo.
     *
     * Isso evita que o navegador use CSS/JS antigos do cache depois
     * de uma alteracao.
     */
    function asset_url(string $caminho): string
    {
        // Remove barras iniciais para montar o caminho relativo.
        $caminho = ltrim($caminho, '/');

        // URL publica do arquivo.
        $url = base_url($caminho);

        // Caminho fisico do arquivo dentro de public/.
        $arquivo = FCPATH . $caminho;

        // Se o arquivo existe, acrescenta a versao.
        if (is_file($arquivo)) {
            $url .= '?v=' . filemtime($arquivo);
        }

        return $url;
    }
}

if (! function_exists('icone_svg')) {
    /**
     * Devolve o SVG inline de um icone do projeto.
     *
     * Todos os icones sao SVG puro embutido: nao usamos fontes de icone
     * externas nem bibliotecas. Os tracos herdam a cor do texto
     * (currentColor).
     */
    function icone_svg(string $nome, string $classe = 'icone'): string
    {
        // Mapa de caminhos SVG por nome.
        $icones = [
            'facebook'  => '<path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
            'twitter'   => '<path d="M4 4l7.2 9.3L4.4 20h2.3l5.5-5.6L16.8 20H20l-7.5-9.7L19.4 4h-2.3l-5 5.1L8.1 4H4z"/>',
            'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 014 0v4"/>',
            'youtube'   => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3V9z" fill="currentColor" stroke="none"/>',
            'tiktok'    => '<path d="M15 4v9.5a3.5 3.5 0 11-3-3.46"/><path d="M15 4c.3 2.2 1.8 3.7 4 4"/>',
            'github'    => '<path d="M9 19c-4 1.2-4-2-6-2m12 4v-3.5c0-1 .1-1.4-.5-2 2.2-.2 4.5-1.1 4.5-5a4 4 0 00-1.1-2.8c.1-.4.5-1.4-.1-2.8 0 0-.9-.3-3 1.1a10 10 0 00-5.6 0C6.9 4.4 6 4.7 6 4.7c-.6 1.4-.2 2.4-.1 2.8A4 4 0 004.8 10c0 3.9 2.3 4.8 4.5 5-.4.4-.5.9-.5 1.5V22"/>',
            'site'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.7 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>',
            'email'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            'telefone'  => '<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.3-1.2a2 2 0 012.1-.5c.9.3 1.8.6 2.8.7a2 2 0 011.7 2z"/>',
            'seta'      => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'check'     => '<path d="M20 6L9 17l-5-5"/>',
            'raio'      => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
            'lupa'      => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
            'email2'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            'escudo'    => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
            'relogio'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'documento' => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/><path d="M14 3v5h5"/>',
            'alvo'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
            'usuario'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
            'cadeado'   => '<rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 018 0v3"/>',
            'upload'    => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>',
            'lixeira'   => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13a2 2 0 002 2h6a2 2 0 002-2l1-13"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"/>',
            'lapis'     => '<path d="M4 20h4L19 9a2.1 2.1 0 00-3-3L5 17l-1 4z"/><path d="M14 6l4 4"/>',
            'play'      => '<path d="M7 5l12 7-12 7V5z"/>',
            'pausa'     => '<path d="M9 5v14M15 5v14"/>',
            'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
            'local'     => '<path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'calendario' => '<rect x="4" y="5" width="16" height="16" rx="2.5"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        ];

        // Devolve string vazia para nomes desconhecidos.
        $caminho = valor_ou($icones, $nome, '');

        // Monta o elemento SVG com tamanho controlado por CSS.
        return '<svg class="' . esc($classe) . '" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $caminho
            . '</svg>';
    }
}
