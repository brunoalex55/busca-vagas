<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO DE IMAGENS DO SITE
 * --------------------------------------------------------------------------
 *
 * Regras de armazenamento das imagens editaveis pelo administrador:
 * logo, favicon e imagem de compartilhamento (Open Graph).
 *
 * Diferente do curriculo, essas imagens SAO publicas: ficam em
 * public/uploads/site para serem servidas diretamente pelo servidor web.
 *
 * Mesmo assim, todo arquivo passa por validacao de tamanho, extensao,
 * MIME real (finfo) e conteudo (getimagesize) antes de ser gravado.
 */
class ImagemSite extends BaseConfig
{
    /**
     * Diretorio absoluto onde as imagens sao gravadas (dentro de public/).
     */
    public string $diretorio = FCPATH . 'uploads/site';

    /**
     * Caminho relativo usado no banco e nas views (base_url).
     */
    public string $diretorioRelativo = 'uploads/site';

    /**
     * Tamanho maximo aceito, em bytes (2 MB).
     */
    public int $tamanhoMaximo = 2 * 1024 * 1024;

    /**
     * Extensoes aceitas para logo e imagem de compartilhamento.
     *
     * @var list<string>
     */
    public array $extensoesPermitidas = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * Extensoes extras aceitas apenas para o favicon.
     *
     * @var list<string>
     */
    public array $extensoesFavicon = ['ico'];

    /**
     * Mapa tipo MIME -> extensao canonica usada no arquivo salvo.
     * A extensao enviada pelo navegador nunca e usada diretamente.
     *
     * @var array<string, string>
     */
    public array $mapaMime = [
        'image/png'                 => 'png',
        'image/jpeg'                => 'jpg',
        'image/webp'                => 'webp',
        'image/gif'                 => 'gif',
        'image/vnd.microsoft.icon'  => 'ico',
        'image/x-icon'              => 'ico',
    ];

    /**
     * Limite de largura/altura (px) para aceitar a imagem, evitando
     * arquivos absurdos que travariam a renderizacao.
     */
    public int $dimensaoMaxima = 4000;
}
