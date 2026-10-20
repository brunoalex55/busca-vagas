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
 * VIEW: CONTATO
 * --------------------------------------------------------------------------
 *
 * Formulario de contato (POST) e canais diretos configurados pelo admin.
 *
 * NAO existe csrf_field(): o projeto nao usa token CSRF.
 */

$this->extend('layouts/site');

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}

// Link de WhatsApp, quando configurado.
$whatsapp = whatsapp_link(texto_ou($site, 'whatsapp_contato'), 'Ola! Tenho uma duvida sobre o BuscaVagas PRO.');

// Valores digitados antes (reaparecem depois de um erro de validacao).
$valorNome    = (string) old('nome');
$valorEmail   = (string) old('email');
$valorMensagem = (string) old('mensagem');
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="pagina-topo">
    <div class="hero__blob hero__blob--2" aria-hidden="true"></div>
    <div class="container pagina-topo__interno">
        <nav class="migalhas" aria-label="Voce esta aqui">
            <a href="<?php echo  site_url('/') ; ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <span>Contato</span>
        </nav>
        <h1 class="pagina-topo__titulo">
            Fale com o nosso <span class="texto-gradiente">time</span>
        </h1>
        <p class="pagina-topo__subtitulo">
            Duvidas sobre planos, pagamento ou funcionamento? Responda em ate 1 dia util.
        </p>
    </div>
</section>

<section class="secao">
    <div class="container contato__grade">

        <?php /* Coluna de canais. */ ?>
        <aside class="contato__canais animar ao-rolar">
            <h2 class="contato__titulo-secao">Canais diretos</h2>

            <?php if (! empty($site['email_contato'])){ ?>
                <a class="canal" href="mailto:<?php echo  esc($site['email_contato']) ; ?>">
                    <span class="canal__icone"><?php echo  icone_svg('email') ; ?></span>
                    <span class="canal__texto">
                        <strong>E-mail</strong>
                        <span><?php echo  esc($site['email_contato']) ; ?></span>
                    </span>
                </a>
            <?php } ?>

            <?php if ($whatsapp !== ''){ ?>
                <a class="canal" href="<?php echo  esc($whatsapp) ; ?>" target="_blank" rel="noopener noreferrer">
                    <span class="canal__icone"><?php echo  icone_svg('telefone') ; ?></span>
                    <span class="canal__texto">
                        <strong>WhatsApp</strong>
                        <span><?php echo  esc(texto_ou($site, 'telefone_contato', 'Falar agora')) ; ?></span>
                    </span>
                </a>
            <?php } ?>

            <?php if (! empty($site['telefone_contato']) && $whatsapp === ''){ ?>
                <div class="canal">
                    <span class="canal__icone"><?php echo  icone_svg('telefone') ; ?></span>
                    <span class="canal__texto">
                        <strong>Telefone</strong>
                        <span><?php echo  esc($site['telefone_contato']) ; ?></span>
                    </span>
                </div>
            <?php } ?>

            <?php if (! empty($site['endereco'])){ ?>
                <div class="canal">
                    <span class="canal__icone"><?php echo  icone_svg('site') ; ?></span>
                    <span class="canal__texto">
                        <strong>Endereco</strong>
                        <span><?php echo  esc($site['endereco']) ; ?></span>
                    </span>
                </div>
            <?php } ?>

            <div class="contato__aviso">
                <?php echo  icone_svg('relogio') ; ?>
                <p>Atendimento de segunda a sexta, das 9h as 18h (horario de Brasilia).</p>
            </div>
        </aside>

        <?php /* Coluna do formulario. */ ?>
        <div class="contato__formulario animar ao-rolar">
            <h2 class="contato__titulo-secao">Envie uma mensagem</h2>

            <?php /* Formulario SEM token CSRF: apenas POST + validacao PHP. */ ?>
            <form class="formulario" method="post" action="<?php echo  site_url('contato') ; ?>" novalidate>

                <div class="campo">
                    <label class="campo__rotulo" for="nome">Seu nome</label>
                    <input class="campo__entrada" type="text" id="nome" name="nome"
                           value="<?php echo  esc($valorNome) ; ?>"
                           placeholder="Como podemos te chamar?"
                           required maxlength="150" autocomplete="name">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="email">Seu e-mail</label>
                    <input class="campo__entrada" type="email" id="email" name="email"
                           value="<?php echo  esc($valorEmail) ; ?>"
                           placeholder="voce@exemplo.com"
                           required maxlength="180" autocomplete="email">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="mensagem">Sua mensagem</label>
                    <textarea class="campo__entrada campo__entrada--area" id="mensagem" name="mensagem"
                              rows="6" placeholder="Escreva sua duvida com detalhes..."
                              required maxlength="2000"><?php echo  esc($valorMensagem) ; ?></textarea>
                </div>

                <button class="botao botao--primario botao--bloco botao--grande" type="submit">
                    Enviar mensagem
                    <?php echo  icone_svg('seta') ; ?>
                </button>

                <p class="formulario__nota">
                    Ao enviar, voce concorda com a nossa politica de privacidade.
                </p>
            </form>
        </div>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
