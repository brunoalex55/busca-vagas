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
 * VIEW: LOGIN
 * --------------------------------------------------------------------------
 *
 * Formulario de login (POST). Sem token CSRF: a autenticacao usa
 * somente a sessao nativa do CodeIgniter.
 */

$this->extend('layouts/site');

$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="secao secao--autenticacao">
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>

    <div class="container autenticacao">

        <?php /* Coluna de marketing. */ ?>
        <aside class="autenticacao__lado animar ao-rolar">
            <a class="migalhas" href="<?php echo  site_url('/') ; ?>">
                &larr; Voltar para o site
            </a>
            <h1 class="autenticacao__titulo">
                Bem-vindo de volta
            </h1>
            <p class="autenticacao__texto">
                Entre para acompanhar suas vagas, os envios do dia e o seu historico
                de candidaturas.
            </p>

            <ul class="lista-checagem">
                <li><?php echo  icone_svg('check') ; ?> Painel com todas as vagas</li>
                <li><?php echo  icone_svg('check') ; ?> Controle de envios por dia</li>
                <li><?php echo  icone_svg('check') ; ?> Historico completo</li>
            </ul>
        </aside>

        <?php /* Formulario. */ ?>
        <div class="cartao-autenticacao animar ao-rolar">
            <h2 class="cartao-autenticacao__titulo">Entrar na sua conta</h2>
            <p class="cartao-autenticacao__subtitulo">
                Informe seu e-mail e sua senha.
            </p>

            <?php /* Formulario SEM csrf_field(). */ ?>
            <form class="formulario" method="post" action="<?php echo  site_url('login') ; ?>" novalidate>

                <div class="campo">
                    <label class="campo__rotulo" for="email">E-mail</label>
                    <input class="campo__entrada" type="email" id="email" name="email"
                           value="<?php echo  esc((string) old('email')) ; ?>"
                           placeholder="voce@exemplo.com"
                           required maxlength="180" autocomplete="email" autofocus>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha">Senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="senha" name="senha"
                               placeholder="Sua senha"
                               required maxlength="100" autocomplete="current-password">
                        <?php /* Botao mostrar/ocultar senha (JavaScript apenas visual). */ ?>
                        <button class="campo__ver-senha" type="button"
                                data-alvo="senha" aria-label="Mostrar senha">
                            Ver
                        </button>
                    </div>
                </div>

                <button class="botao botao--primario botao--bloco botao--grande" type="submit">
                    Entrar
                    <?php echo  icone_svg('seta') ; ?>
                </button>
            </form>

            <p class="cartao-autenticacao__rodape">
                Ainda nao tem conta?
                <a href="<?php echo  site_url('registro') ; ?>">Criar conta gratis</a>
            </p>
        </div>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
