<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/

/**
 * --------------------------------------------------------------------------
 * LAYOUT: SITE PUBLICO
 * --------------------------------------------------------------------------
 *
 * Layout unico de todas as paginas publicas. Concentra:
 *   - SEO completo (title, description, keywords, canonical)
 *   - Open Graph e Twitter Card
 *   - Schema.org JSON-LD
 *   - CSS puro do projeto (sem framework)
 *   - Header fixo e footer com dados da configuracao_site
 *
 * O sistema e o site vivem no MESMO projeto (monolito), portanto este
 * layout tambem e usado como base visual do restante da aplicacao.
 */

// Configuracao do site (vem do BaseController, com fallback).
$configuracaoSite = $configuracaoSite;

if ($configuracaoSite === null) {
    $configuracaoSite = configuracao_site();
}

// Dados de SEO da pagina atual.
$seo = $seo;

if ($seo === null) {
    $seo = [];
}

// Titulo da pagina (maximo recomendado: 60 caracteres).
$titulo = texto_ou($seo, 'titulo', texto_ou($configuracaoSite, 'nome_site', 'BuscaVagas PRO'));

// Meta description (maximo recomendado: 155 caracteres).
$descricao = texto_ou($seo, 'descricao', texto_ou($configuracaoSite, 'descricao_site'));

// Meta keywords.
$palavrasChave = texto_ou($seo, 'palavras_chave', texto_ou($configuracaoSite, 'palavras_chave_seo'));

// URL canonica da pagina.
$canonical = texto_ou($seo, 'canonical', current_url());

// Tipo Open Graph (website, product, article...).
$tipoOg = texto_ou($seo, 'tipo', 'website');

// Imagem de compartilhamento (opcional).
$imagemOg = null;

if (! empty($configuracaoSite['imagem_og'])) {
    $imagemOg = base_url($configuracaoSite['imagem_og']);
}

// Favicon (usa o enviado pelo admin ou o padrao do projeto).
$favicon = base_url('favicon.ico');

if (! empty($configuracaoSite['favicon'])) {
    $favicon = base_url($configuracaoSite['favicon']);
}

// Nome do site para os metadados.
$nomeSite = texto_ou($configuracaoSite, 'nome_site', 'BuscaVagas PRO');

// Schema.org especifico da pagina (quando informado).
$schema = valor_ou($seo, 'schema', null);

// ID do Google Analytics (opcional, configurado pelo admin).
$gaId = texto_ou($configuracaoSite, 'google_analytics_id');

// Codigo de verificacao do Search Console (opcional).
$gscCode = texto_ou($configuracaoSite, 'google_search_console_code');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php /* Marca que o JavaScript esta disponivel. Sem isso, o conteudo
           aparece normalmente (as animacoes de scroll sao opcionais). */ ?>
    <script>document.documentElement.classList.add('js');</script>

    <?php /* Codificacao e viewport mobile-first. */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php /* Titulo unico da pagina. */ ?>
    <title><?php echo  esc($titulo) ; ?></title>

    <?php /* SEO basico. */ ?>
    <meta name="description" content="<?php echo  esc(resumir_texto($descricao, 155)) ; ?>">
    <?php if ($palavrasChave !== ''){ ?>
        <meta name="keywords" content="<?php echo  esc($palavrasChave) ; ?>">
    <?php } ?>
    <meta name="author" content="<?php echo  esc($nomeSite) ; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo  esc($canonical) ; ?>">

    <?php /* Open Graph (Facebook, LinkedIn, WhatsApp). */ ?>
    <meta property="og:type" content="<?php echo  esc($tipoOg) ; ?>">
    <meta property="og:site_name" content="<?php echo  esc($nomeSite) ; ?>">
    <meta property="og:title" content="<?php echo  esc($titulo) ; ?>">
    <meta property="og:description" content="<?php echo  esc(resumir_texto($descricao, 155)) ; ?>">
    <meta property="og:url" content="<?php echo  esc($canonical) ; ?>">
    <meta property="og:locale" content="pt_BR">
    <?php if ($imagemOg !== null){ ?>
        <meta property="og:image" content="<?php echo  esc($imagemOg) ; ?>">
    <?php } ?>

    <?php /* Twitter Card. */ ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo  esc($titulo) ; ?>">
    <meta name="twitter:description" content="<?php echo  esc(resumir_texto($descricao, 155)) ; ?>">
    <?php if ($imagemOg !== null){ ?>
        <meta name="twitter:image" content="<?php echo  esc($imagemOg) ; ?>">
    <?php } ?>

    <?php /* Favicon. */ ?>
    <link rel="icon" href="<?php echo  esc($favicon) ; ?>" type="image/x-icon">

    <?php /* Verificacao do Google Search Console (opcional). */ ?>
    <?php if ($gscCode !== ''){ ?>
        <meta name="google-site-verification" content="<?php echo  esc($gscCode) ; ?>">
    <?php } ?>

    <?php /* Schema.org JSON-LD da pagina. */ ?>
    <?php if (is_array($schema)){ ?>
        <script type="application/ld+json">
            <?php echo  json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ; ?>
        </script>
    <?php } ?>

    <?php /* CSS puro do projeto: sem Bootstrap, Tailwind ou qualquer framework. */ ?>
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/reset.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/variaveis.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/base.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/componentes.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/site.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/responsivo.css')) ; ?>">

    <?php /* Deixa claro para o navegador onde buscar pre-conexoes. */ ?>
    <meta name="theme-color" content="#4f46e5">

    <?php /* Google Analytics (opcional, sem bloquear a renderizacao). */ ?>
    <?php if ($gaId !== ''){ ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo  esc($gaId) ; ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', <?php echo  json_encode($gaId) ; ?>);
        </script>
    <?php } ?>
</head>
<body>
    <?php /* Link de acessibilidade para pular a navegacao. */ ?>
    <a class="pular-conteudo" href="#conteudo-principal">Pular para o conteudo</a>

    <?php /* Header fixo compartilhado por todas as paginas publicas. */ ?>
    <?php echo  $this->include('partials/header_site') ; ?>

    <?php /* Conteudo especifico de cada pagina. */ ?>
    <main id="conteudo-principal">
        <?php echo  $this->renderSection('conteudo') ; ?>
    </main>

    <?php /* Footer com dados da configuracao_site. */ ?>
    <?php echo  $this->include('partials/footer_site') ; ?>

    <?php /* Mensagens de sucesso/erro (toasts). */ ?>
    <?php echo  $this->include('partials/flash') ; ?>

    <?php /* JavaScript vanilla: apenas UX visual. */ ?>
    <script src="<?php echo  esc(asset_url('assets/js/utilidades.js')) ; ?>" defer></script>
    <script src="<?php echo  esc(asset_url('assets/js/site.js')) ; ?>" defer></script>
</body>
</html>
