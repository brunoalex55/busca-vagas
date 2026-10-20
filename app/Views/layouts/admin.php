<?php
/**
 * --------------------------------------------------------------------------
 * LAYOUT: PAINEL ADMINISTRATIVO
 * --------------------------------------------------------------------------
 *
 * Estrutura base do painel do administrador. Sempre protegido pelos
 * filtros "autenticacao", "usuarioativo" e "admin".
 *
 * O menu lateral cobre as secoes administrativas implementadas na Fase 5.
 */

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Nome do administrador logado.
$nomeAdmin = session()->get('nome');

if ($nomeAdmin === null || $nomeAdmin === '') {
    $nomeAdmin = 'Administrador';
}

// Rota atual.
$rotaAtual = trim(uri_string(), '/');

// Itens do menu administrativo.
$menuAdmin = [
    ['rota' => 'admin',                 'rotulo' => 'Visao geral',    'icone' => 'alvo'],
    ['rota' => 'admin/usuarios',        'rotulo' => 'Usuarios',       'icone' => 'escudo'],
    ['rota' => 'admin/chaves',          'rotulo' => 'Chaves',         'icone' => 'raio'],
    ['rota' => 'admin/distribuicao',    'rotulo' => 'Distribuicao',   'icone' => 'relogio'],
    ['rota' => 'admin/cron',            'rotulo' => 'Agendamento',    'icone' => 'raio'],
    ['rota' => 'admin/divulgavagas',    'rotulo' => 'Divulga Vagas',  'icone' => 'site'],
    ['rota' => 'admin/solicitacoes',    'rotulo' => 'Solicitacoes',   'icone' => 'documento'],
    ['rota' => 'admin/logs',            'rotulo' => 'Logs',           'icone' => 'lupa'],
    ['rota' => 'admin/configuracao-site','rotulo' => 'Configuracao do site', 'icone' => 'site'],
    ['rota' => 'admin/conta',           'rotulo' => 'Minha conta',    'icone' => 'usuario'],
];

// Titulo da pagina (padrao quando a view nao envia o titulo).
$tituloPaginaAtual = 'Administracao';

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

    <?php /* Area administrativa: nunca indexar. */ ?>
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo  esc(valor_ou($seo, 'titulo', $tituloPagina) . ' | ' . $site['nome_site']) ; ?></title>

    <link rel="icon" href="<?php echo  esc(base_url('favicon.ico')) ; ?>" type="image/x-icon">

    <?php /* CSS puro, sem framework. */ ?>
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/reset.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/variaveis.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/base.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/componentes.css')) ; ?>">
    <?php /* O admin reaproveita o esqueleto de painel e apenas sobrescreve cores. */ ?>
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/painel.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/admin.css')) ; ?>">
    <link rel="stylesheet" href="<?php echo  esc(asset_url('assets/css/responsivo.css')) ; ?>">

    <meta name="theme-color" content="#0f172a">
</head>
<body class="corpo-admin">

    <div class="painel painel--admin" id="painel">

        <?php /* Menu lateral administrativo. */ ?>
        <aside class="painel__menu painel__menu--admin" id="painel-menu">

            <a class="marca marca--painel" href="<?php echo  site_url('admin') ; ?>">
                <span class="marca__simbolo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3z"/>
                    </svg>
                </span>
                <span class="marca__texto">
                    <strong><?php echo  esc($site['nome_site']) ; ?></strong>
                    <small>Administracao</small>
                </span>
            </a>

            <nav class="menu-lateral" aria-label="Menu administrativo">
                <ul class="menu-lateral__lista">
                    <?php foreach ($menuAdmin as $item){ ?>
                        <?php
                        // Item ativo quando a rota atual comeca pela rota do item.
                        $ativo = ($rotaAtual === $item['rota']) || ($item['rota'] !== 'admin' && str_starts_with($rotaAtual, $item['rota']));
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

            <div class="painel__menu-rodape">
                <a class="menu-lateral__link" href="<?php echo  site_url('/') ; ?>">
                    <?php echo  icone_svg('seta') ; ?>
                    <span>Ver site</span>
                </a>
            </div>
        </aside>

        <?php /* Area principal. */ ?>
        <div class="painel__conteudo">

            <header class="painel__topo">
                <button class="menu-toggle menu-toggle--painel" id="menu-painel-toggle" type="button"
                        aria-expanded="false" aria-controls="painel-menu" aria-label="Abrir menu">
                    <span class="menu-toggle__barra"></span>
                    <span class="menu-toggle__barra"></span>
                    <span class="menu-toggle__barra"></span>
                </button>

                <h1 class="painel__titulo"><?php echo  esc($tituloPagina) ; ?></h1>

                <div class="painel__usuario">
                    <span class="selo selo--admin">Admin</span>
                    <span class="avatar" aria-hidden="true"><?php echo  esc(iniciais_nome($nomeAdmin)) ; ?></span>
                    <span class="painel__usuario-nome"><?php echo  esc($nomeAdmin) ; ?></span>

                    <?php /* Logout SEMPRE por POST. */ ?>
                    <form method="post" action="<?php echo  site_url('logout') ; ?>" class="form-inline">
                        <button type="submit" class="botao botao--fantasma botao--pequeno">Sair</button>
                    </form>
                </div>
            </header>

            <main class="painel__principal">
                <?php echo  $this->renderSection('conteudo') ; ?>
            </main>
        </div>
    </div>

    <?php echo  $this->include('partials/flash') ; ?>

    <script src="<?php echo  esc(asset_url('assets/js/utilidades.js')) ; ?>" defer></script>
    <script src="<?php echo  esc(asset_url('assets/js/painel.js')) ; ?>" defer></script>
</body>
</html>
