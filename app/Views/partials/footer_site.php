<?php
/**
 * --------------------------------------------------------------------------
 * PARTIAL: FOOTER DO SITE PUBLICO
 * --------------------------------------------------------------------------
 *
 * Footer em quatro colunas, com dados vindos da configuracao_site:
 * logo, descricao, redes sociais, links uteis e textos legais.
 */

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Ano atual para o copyright.
$ano = date('Y');

// Redes sociais configuradas (apenas as que tem link).
$redes = [
    'facebook'  => valor_ou($site, 'link_facebook', ''),
    'instagram' => valor_ou($site, 'link_instagram', ''),
    'twitter'   => valor_ou($site, 'link_twitter', ''),
    'linkedin'  => valor_ou($site, 'link_linkedin', ''),
    'youtube'   => valor_ou($site, 'link_youtube', ''),
    'tiktok'    => valor_ou($site, 'link_tiktok', ''),
    'github'    => valor_ou($site, 'link_github', ''),
    'site'      => valor_ou($site, 'link_site_pessoal', ''),
];

// Remove as redes sem link configurado.
$redes = array_filter($redes);

// Link de WhatsApp (quando configurado).
$whatsapp = whatsapp_link(valor_ou($site, 'whatsapp_contato', ''));
?>
<footer class="rodape">
    <div class="container rodape__grade">

        <?php /* Coluna 1: marca e redes sociais. */ ?>
        <div class="rodape__coluna rodape__coluna--marca">
            <a class="marca marca--rodape" href="<?php echo  site_url('/') ; ?>">
                <?php if (! empty($site['logo'])){ ?>
                    <img class="marca__imagem" src="<?php echo  esc(base_url($site['logo'])) ; ?>" alt="<?php echo  esc(valor_ou($site, 'nome_site', '')) ; ?>" width="36" height="36">
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
                </span>
            </a>

            <p class="rodape__descricao"><?php echo  esc(resumir_texto(valor_ou($site, 'descricao_site', ''), 140)) ; ?></p>

            <?php /* Redes sociais com icones SVG inline. */ ?>
            <?php if (! empty($redes)){ ?>
                <ul class="redes-sociais" aria-label="Redes sociais">
                    <?php foreach ($redes as $rede => $link){ ?>
                        <li>
                            <a class="redes-sociais__link" href="<?php echo  esc($link) ; ?>"
                               target="_blank" rel="noopener noreferrer"
                               aria-label="<?php echo  esc(ucfirst($rede)) ; ?>">
                                <?php echo  icone_svg($rede) ; ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>

        <?php /* Coluna 2: produto. */ ?>
        <nav class="rodape__coluna" aria-label="Produto">
            <h2 class="rodape__titulo">Produto</h2>
            <ul class="rodape__lista">
                <li><a href="<?php echo  site_url('recursos') ; ?>">Recursos</a></li>
                <li><a href="<?php echo  site_url('como-funciona') ; ?>">Como funciona</a></li>
                <li><a href="<?php echo  site_url('precos') ; ?>">Precos</a></li>
                <li><a href="<?php echo  site_url('registro') ; ?>">Criar conta</a></li>
            </ul>
        </nav>

        <?php /* Coluna 3: empresa. */ ?>
        <nav class="rodape__coluna" aria-label="Empresa">
            <h2 class="rodape__titulo">Empresa</h2>
            <ul class="rodape__lista">
                <li><a href="<?php echo  site_url('sobre') ; ?>">Sobre</a></li>
                <li><a href="<?php echo  site_url('contato') ; ?>">Contato</a></li>
                <?php if ($whatsapp !== ''){ ?>
                    <li><a href="<?php echo  esc($whatsapp) ; ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
                <?php } ?>
            </ul>
        </nav>

        <?php /* Coluna 4: legal. */ ?>
        <nav class="rodape__coluna" aria-label="Legal">
            <h2 class="rodape__titulo">Legal</h2>
            <ul class="rodape__lista">
                <li><a href="<?php echo  site_url('sobre') ; ?>#termos">Termos de uso</a></li>
                <li><a href="<?php echo  site_url('sobre') ; ?>#privacidade">Politica de privacidade</a></li>
                <li><a href="<?php echo  site_url('sobre') ; ?>#lgpd">LGPD</a></li>
            </ul>
        </nav>
    </div>

    <?php /* Linha final: copyright e texto configuravel. */ ?>
    <div class="rodape__base">
        <div class="container rodape__base-interno">
            <p class="rodape__copyright">
                &copy; <?php echo  esc($ano) ; ?> <?php echo  esc(valor_ou($site, 'nome_site', 'BuscaVagas PRO')) ; ?>.
                Todos os direitos reservados.
            </p>
            <?php if (! empty($site['texto_rodape'])){ ?>
                <p class="rodape__texto"><?php echo  esc($site['texto_rodape']) ; ?></p>
            <?php } ?>
            <?php if (! empty($site['email_contato'])){ ?>
                <p class="rodape__texto">
                    <a href="mailto:<?php echo  esc($site['email_contato']) ; ?>"><?php echo  esc($site['email_contato']) ; ?></a>
                </p>
            <?php } ?>
        </div>
    </div>
</footer>
