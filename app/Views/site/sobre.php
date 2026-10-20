<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: SOBRE
 * --------------------------------------------------------------------------
 *
 * Pagina institucional. O texto principal vem da configuracao_site
 * (campo texto_sobre), editavel pelo admin.
 *
 * Tambem abriga as ancoras legais usadas no footer:
 *   #termos, #privacidade, #lgpd
 */

$this->extend('layouts/site');

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Texto institucional configurado.
$textoSobre = trim(texto_ou($site, 'texto_sobre'));
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="pagina-topo">
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>
    <div class="container pagina-topo__interno">
        <nav class="migalhas" aria-label="Voce esta aqui">
            <a href="<?php echo  site_url('/') ; ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <span>Sobre</span>
        </nav>
        <h1 class="pagina-topo__titulo">
            Tecnologia a favor da <span class="texto-gradiente">sua carreira</span>
        </h1>
        <p class="pagina-topo__subtitulo">
            Conheca a historia, a missao e os principios do <?php echo  esc(texto_ou($site, 'nome_site', 'BuscaVagas PRO')) ; ?>.
        </p>
    </div>
</section>

<?php /* Texto institucional. */ ?>
<section class="secao">
    <div class="container container--estreito">
        <div class="prosa animar ao-rolar">
            <?php if ($textoSobre !== ''){ ?>
                <?php /* nl2br preserva as quebras de linha do texto do admin. */ ?>
                <p><?php echo  nl2br(esc($textoSobre)) ; ?></p>
            <?php } else { ?>
                <p>
                    O <?php echo  esc(texto_ou($site, 'nome_site', 'BuscaVagas PRO')) ; ?> nasceu para resolver um
                    problema simples e cansativo: procurar emprego todos os dias em varios
                    sites, preenchendo os mesmos formularios.
                </p>
                <p>
                    Automatizamos a busca no Google Jobs e o envio das candidaturas por
                    e-mail, sempre respeitando limites seguros para proteger a conta Gmail
                    de cada usuario.
                </p>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* Principios. */ ?>
<section class="secao secao--alternada">
    <div class="container">
        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Nossos principios</span>
            <h2 class="secao__titulo">O que guia o nosso trabalho</h2>
        </header>

        <div class="grade-cartoes">
            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('escudo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Privacidade em primeiro lugar</h3>
                <p class="cartao-recurso__texto">
                    Cada usuario usa a propria conta de e-mail. O APP PASSWORD e
                    criptografado e nunca e exibido, nem para o administrador.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('relogio') ; ?></span>
                <h3 class="cartao-recurso__titulo">Respeito ao seu tempo</h3>
                <p class="cartao-recurso__texto">
                    Automatizamos o que e repetitivo para que voce invista energia no que
                    importa: preparar-se para as entrevistas.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('alvo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Transparencia total</h3>
                <p class="cartao-recurso__texto">
                    Voce ve quantos e-mails foram enviados, para quais vagas e por qual
                    origem. Nada acontece escondido.
                </p>
            </article>
        </div>
    </div>
</section>

<?php /* Termos de uso. */ ?>
<section class="secao" id="termos">
    <div class="container container--estreito">
        <div class="prosa animar ao-rolar">
            <h2>Termos de uso</h2>
            <p>
                Ao utilizar o <?php echo  esc(texto_ou($site, 'nome_site', 'BuscaVagas PRO')) ; ?>, voce concorda
                em fornecer informacoes verdadeiras no cadastro, usar o sistema apenas para
                fins legitimos de busca de emprego e nao compartilhar sua conta com
                terceiros.
            </p>
            <p>
                O envio de candidaturas depende da configuracao da sua propria conta Gmail e
                do seu APP PASSWORD. O sistema nao garante entrevistas ou contratacao, pois
                essas decisoes cabem exclusivamente as empresas contratantes.
            </p>
            <p>
                O uso indevido, como envio de conteudo ofensivo ou tentativa de burlar os
                limites de envio, pode levar ao bloqueio da conta.
            </p>
        </div>
    </div>
</section>

<?php /* Politica de privacidade. */ ?>
<section class="secao secao--alternada" id="privacidade">
    <div class="container container--estreito">
        <div class="prosa animar ao-rolar">
            <h2>Politica de privacidade</h2>
            <p>
                Coletamos apenas os dados necessarios para operar o servico: nome, e-mail
                de acesso, palavras-chave, curriculo em PDF e configuracoes de envio.
            </p>
            <p>
                O APP PASSWORD do Gmail e armazenado com criptografia AES-256-CBC e usado
                exclusivamente no momento do envio da candidatura. Ele nunca aparece em
                telas, logs ou respostas de API.
            </p>
            <p>
                Nao vendemos nem compartilhamos seus dados com terceiros para fins de
                marketing. Os dados podem ser excluidos a seu pedido.
            </p>
        </div>
    </div>
</section>

<?php /* LGPD. */ ?>
<section class="secao" id="lgpd">
    <div class="container container--estreito">
        <div class="prosa animar ao-rolar">
            <h2>LGPD</h2>
            <p>
                Em conformidade com a Lei Geral de Protecao de Dados (Lei 13.709/2018),
                voce pode solicitar a confirmacao, o acesso, a correcao, a portabilidade
                ou a exclusao dos seus dados pessoais.
            </p>
            <p>
                Para exercer qualquer um desses direitos, entre em contato pelo e-mail
                <?php if (! empty($site['email_contato'])){ ?>
                    <a href="mailto:<?php echo  esc($site['email_contato']) ; ?>"><?php echo  esc($site['email_contato']) ; ?></a>.
                <?php } else { ?>
                    informado na pagina de contato.
                <?php } ?>
            </p>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="container cta-final__interno animar ao-rolar">
        <h2 class="cta-final__titulo">Vamos acelerar a sua busca?</h2>
        <p class="cta-final__subtitulo">
            Crie sua conta e deixe o sistema trabalhar por voce.
        </p>
        <a class="botao botao--claro botao--grande" href="<?php echo  site_url('registro') ; ?>">
            Comece gratis agora <?php echo  icone_svg('seta') ; ?>
        </a>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
