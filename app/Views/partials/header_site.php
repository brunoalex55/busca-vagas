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
 * PARTIAL: HEADER DO SITE PUBLICO
 * --------------------------------------------------------------------------
 *
 * Header fixo (sticky) com logo, navegacao, botoes de acao e menu
 * hamburguer responsivo.
 *
 * O menu hamburguer e controlado por JavaScript vanilla puro
 * (assets/js/site.js), apenas para UX visual.
 */

// Rota atual, sem barras nas pontas (ex.: "precos"). O rotulo visivel e "Preços".
$rotaAtual = trim(uri_string(), '/');

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Usuario autenticado?
$logado = session()->has('usuario_id');

// Itens do menu principal.
$menu = [
    ['rota' => '',              'rotulo' => 'Inicio'],
    ['rota' => 'recursos',      'rotulo' => 'Recursos'],
    ['rota' => 'como-funciona', 'rotulo' => 'Como funciona'],
    ['rota' => 'precos',        'rotulo' => 'Preços'],
    ['rota' => 'contato',       'rotulo' => 'Contato'],
];
?>
<header class="cabecalho" id="cabecalho">
    <div class="container cabecalho__interno">

        <?php /* Marca: logo enviado pelo admin ou SVG inline. */ ?>
        <a class="marca" href="<?php echo  site_url('/') ; ?>" aria-label="<?php echo  esc(valor_ou($site, 'nome_site', 'Inicio')) ; ?>">
            <?php if (! empty($site['logo'])){ ?>
                <img class="marca__imagem" src="<?php echo  esc(base_url($site['logo'])) ; ?>" alt="<?php echo  esc(valor_ou($site, 'nome_site', '')) ; ?>" width="40" height="40">
            <?php } else { ?>
                <span class="marca__simbolo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="7" width="18" height="13" rx="2.5"/>
                        <path d="M9 7V5.5A1.5 1.5 0 0110.5 4h3A1.5 1.5 0 0115 5.5V7"/>
                        <path d="M13.2 10.2l-2.6 3.4h2.4l-.6 2.2 2.8-3.6h-2.5l.5-2z" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
            <?php } ?>
            <span class="marca__texto">
                <strong><?php echo  esc(valor_ou($site, 'nome_site', 'BuscaVagas PRO')) ; ?></strong>
                <small><?php echo  esc(valor_ou($site, 'slogan', '')) ; ?></small>
            </span>
        </a>

        <?php /* Navegacao principal. */ ?>
        <nav class="navegacao" id="navegacao" aria-label="Navegacao principal">
            <ul class="navegacao__lista">
                <?php foreach ($menu as $item){ ?>
                    <?php
                    // Marca o link da pagina atual.
                    $ativo = ($rotaAtual === $item['rota']);
                    ?>
                    <li>
                        <a class="navegacao__link<?php echo escolher($ativo, ' navegacao__link--ativo', ''); ?>"
                           href="<?php echo  site_url($item['rota']) ; ?>"
                           <?php echo escolher($ativo, 'aria-current="page"', ''); ?>>
                            <?php echo  esc($item['rotulo']) ; ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>

            <?php /* Botoes de acao. */ ?>
            <div class="navegacao__acoes">
                <?php if ($logado){ ?>
                    <?php
                    // Usuario ativo vai para o painel. Usuario pendente ou
                    // bloqueado vai para a tela de aguardando aprovacao.
                    if (session()->get('status') === 'ativo') {
                        $destino = 'painel';

                        if (session()->get('perfil') === 'admin') {
                            $destino = 'admin';
                        }

                        $rotulo  = 'Ir para o painel';
                    } else {
                        $destino = 'aguardando';
                        $rotulo  = 'Aguardando liberacao';
                    }
                    ?>
                    <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url($destino) ; ?>">
                        <?php echo  esc($rotulo) ; ?>
                    </a>
                <?php } else { ?>
                    <a class="botao botao--fantasma" href="<?php echo  site_url('login') ; ?>">Entrar</a>
                    <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('registro') ; ?>">
                        Comecar gratis
                    </a>
                <?php } ?>
            </div>
        </nav>

        <?php /* Botao do menu hamburguer (mobile). */ ?>
        <button class="menu-toggle" id="menu-toggle" type="button"
                aria-expanded="false" aria-controls="navegacao" aria-label="Abrir menu">
            <span class="menu-toggle__barra"></span>
            <span class="menu-toggle__barra"></span>
            <span class="menu-toggle__barra"></span>
        </button>
    </div>
</header>
