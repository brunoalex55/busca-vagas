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
 * LAYOUT: PAINEL DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Estrutura base do painel do usuario: menu lateral, topo com dados
 * da conta e area de conteudo.
 *
 * As paginas internas NAO devem ser indexadas por buscadores.
 */

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Nome do usuario logado.
$nomeUsuario = session()->get('nome');

if ($nomeUsuario === null || $nomeUsuario === '') {
    $nomeUsuario = 'Usuario';
}

// Rota atual para marcar o item ativo do menu.
$rotaAtual = trim(uri_string(), '/');

// Itens do menu do painel.
$menuPainel = [
    ['rota' => 'painel',                'rotulo' => 'Visao geral',    'icone' => 'alvo'],
    ['rota' => 'painel/palavras-chave', 'rotulo' => 'Palavras-chave', 'icone' => 'lupa'],
    ['rota' => 'painel/vagas',          'rotulo' => 'Vagas',          'icone' => 'documento'],
    ['rota' => 'painel/divulgavagas',    'rotulo' => 'Divulga Vagas',  'icone' => 'raio'],
    ['rota' => 'painel/envio-massa',    'rotulo' => 'Envio em massa', 'icone' => 'email'],
    ['rota' => 'painel/historico',      'rotulo' => 'Historico',      'icone' => 'relogio'],
    ['rota' => 'painel/configuracoes',  'rotulo' => 'Configuracoes',  'icone' => 'escudo'],
];

// O envio em massa so aparece para quem tem o recurso no plano.
$usuarioSessao = (int) session()->get('usuario_id');

if ($usuarioSessao > 0) {
    $permiteMassa = (new \App\Libraries\ResolvedorPlano())->podeUsarEnvioEmMassa($usuarioSessao);

    if (! $permiteMassa) {
        $menuPainel = array_values(array_filter(
            $menuPainel,
            static fn (array $item): bool => $item['rota'] !== 'painel/envio-massa'
        ));
    }
}

// Titulo da pagina (vem da view filha, com fallback).
$tituloPaginaAtual = 'Painel';

if (isset($tituloPagina) && $tituloPagina !== '') {
    $tituloPaginaAtual = $tituloPagina;
}

$tituloPagina = $tituloPaginaAtual;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php /* Indica ao CSS que o JavaScript esta ativo (animacoes opcionais). */ ?>
    <script>document.documentElement.classList.add('js');</script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php /* Area privada: nunca indexar. */ ?>
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo  esc(valor_ou($seo, 'titulo', $tituloPagina) . ' | ' . $site['nome_site']) ; ?></title>

    <link rel="icon" href="<?php echo  esc(base_url('favicon.ico')) ; ?>" type="image/x-icon">

    <?php /* CSS puro, sem framework. */ ?>
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/reset.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/variaveis.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/base.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/componentes.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/painel.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/responsivo.css')) ; ?>">

    <meta name="theme-color" content="#4f46e5">
</head>
<body class="corpo-painel">

    <div class="painel" id="painel">

        <?php /* Menu lateral. */ ?>
        <aside class="painel__menu" id="painel-menu">

            <?php /* Marca. */ ?>
            <a class="marca marca--painel" href="<?php echo  site_url('painel') ; ?>">
                <span class="marca__simbolo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="7" width="18" height="13" rx="2.5"/>
                        <path d="M9 7V5.5A1.5 1.5 0 0110.5 4h3A1.5 1.5 0 0115 5.5V7"/>
                    </svg>
                </span>
                <span class="marca__texto">
                    <strong><?php echo  esc($site['nome_site']) ; ?></strong>
                    <small>Area do usuario</small>
                </span>
            </a>

            <?php /* Navegacao do painel. */ ?>
            <nav class="menu-lateral" aria-label="Menu do painel">
                <ul class="menu-lateral__lista">
                    <?php foreach ($menuPainel as $item){ ?>
                        <?php
                        // Item ativo quando a rota atual comeca pela rota do item.
                        $ativo = ($rotaAtual === $item['rota']) || ($item['rota'] !== 'painel' && str_starts_with($rotaAtual, $item['rota']));
                        ?>
                        <li>
                            <a class="menu-lateral__link<?php echo escolher($ativo, ' menu-lateral__link--ativo', ''); ?>"
                               href="<?php echo  site_url($item['rota']) ; ?>">
                                <?php echo  icone_svg($item['icone']) ; ?>
                                <span><?php echo  esc($item['rotulo']) ; ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </nav>

            <?php /* Rodape do menu: voltar ao site. */ ?>
            <div class="painel__menu-rodape">
                <a class="menu-lateral__link" href="<?php echo  site_url('/') ; ?>">
                    <?php echo  icone_svg('seta') ; ?>
                    <span>Ver site</span>
                </a>
            </div>
        </aside>

        <?php /* Area principal. */ ?>
        <div class="painel__conteudo">

            <?php /* Topo com usuario e logout. */ ?>
            <header class="painel__topo">
                <button class="menu-toggle menu-toggle--painel" id="menu-painel-toggle" type="button"
                        aria-expanded="false" aria-controls="painel-menu" aria-label="Abrir menu">
                    <span class="menu-toggle__barra"></span>
                    <span class="menu-toggle__barra"></span>
                    <span class="menu-toggle__barra"></span>
                </button>

                <h1 class="painel__titulo"><?php echo  esc($tituloPagina) ; ?></h1>

                <div class="painel__usuario">
                    <span class="avatar" aria-hidden="true"><?php echo  esc(iniciais_nome($nomeUsuario)) ; ?></span>
                    <span class="painel__usuario-nome"><?php echo  esc($nomeUsuario) ; ?></span>

                    <?php /* Logout SEMPRE por POST. */ ?>
                    <form method="post" action="<?php echo  site_url('logout') ; ?>" class="form-inline">
                        <button type="submit" class="botao botao--fantasma botao--pequeno">Sair</button>
                    </form>
                </div>
            </header>

            <?php /* Conteudo da pagina. */ ?>
            <main class="painel__principal">
                <?php echo  $this->renderSection('conteudo') ; ?>
            </main>
        </div>
    </div>

    <?php /* Mensagens flash. */ ?>
    <?php echo  $this->include('partials/flash') ; ?>

    <?php /* JavaScript vanilla do painel. */ ?>
    <script src="<?php echo  esc(asset_url('assets/js/utilidades.js')) ; ?>" defer></script>
    <script src="<?php echo  esc(asset_url('assets/js/painel.js')) ; ?>" defer></script>
</body>
</html>
