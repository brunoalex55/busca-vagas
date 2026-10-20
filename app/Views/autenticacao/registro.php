<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: REGISTRO
 * --------------------------------------------------------------------------
 *
 * Cadastro de novo usuario (POST). O usuario nasce com status
 * "pendente" e segue para a tela de aguardando aprovacao.
 *
 * Sem token CSRF: validacao integral no servidor.
 */

$this->extend('layouts/site');

$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="secao secao--autenticacao">
    <div class="hero__blob hero__blob--2" aria-hidden="true"></div>

    <div class="container autenticacao">

        <?php /* Coluna de marketing. */ ?>
        <aside class="autenticacao__lado animar ao-rolar">
            <a class="migalhas" href="<?php echo  site_url('/') ; ?>">
                &larr; Voltar para o site
            </a>
            <h1 class="autenticacao__titulo">
                Comece gratis hoje
            </h1>
            <p class="autenticacao__texto">
                Crie sua conta, escolha suas palavras-chave e deixe o sistema buscar
                vagas enquanto voce faz outras coisas.
            </p>

            <ul class="lista-checagem">
                <li><?php echo  icone_svg('check') ; ?> Cadastro em menos de 1 minuto</li>
                <li><?php echo  icone_svg('check') ; ?> Sem fidelidade</li>
                <li><?php echo  icone_svg('check') ; ?> Seus dados protegidos</li>
            </ul>
        </aside>

        <?php /* Formulario. */ ?>
        <div class="cartao-autenticacao animar ao-rolar">
            <h2 class="cartao-autenticacao__titulo">Criar conta</h2>
            <p class="cartao-autenticacao__subtitulo">
                Depois do cadastro, siga as instrucoes de pagamento para liberar o acesso.
            </p>

            <?php /* Formulario SEM csrf_field(). */ ?>
            <form class="formulario" method="post" action="<?php echo  site_url('registro') ; ?>" novalidate>

                <div class="campo">
                    <label class="campo__rotulo" for="nome">Nome completo</label>
                    <input class="campo__entrada" type="text" id="nome" name="nome"
                           value="<?php echo  esc((string) old('nome')) ; ?>"
                           placeholder="Seu nome"
                           required minlength="3" maxlength="150" autocomplete="name" autofocus>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="email">E-mail</label>
                    <input class="campo__entrada" type="email" id="email" name="email"
                           value="<?php echo  esc((string) old('email')) ; ?>"
                           placeholder="voce@exemplo.com"
                           required maxlength="180" autocomplete="email">
                    <small class="campo__ajuda">Este sera o seu login.</small>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha">Senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="senha" name="senha"
                               placeholder="Minimo de 6 caracteres"
                               required minlength="6" maxlength="100" autocomplete="new-password">
                        <button class="campo__ver-senha" type="button"
                                data-alvo="senha" aria-label="Mostrar senha">
                            Ver
                        </button>
                    </div>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha_confirmacao">Confirmar senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="senha_confirmacao"
                               name="senha_confirmacao" placeholder="Repita a senha"
                               required maxlength="100" autocomplete="new-password">
                        <button class="campo__ver-senha" type="button"
                                data-alvo="senha_confirmacao" aria-label="Mostrar senha">
                            Ver
                        </button>
                    </div>
                </div>

            <?php /* O plano escolhido (vem de /precos) e a escolha do plano. */ ?>
            <div class="cartao-autenticacao__plano">
                <h3 class="cartao__titulo">Escolha seu plano</h3>

                <?php if ($planoEscolhido !== null){ ?>
                    <p class="texto-suave texto-pequeno">
                        Plano escolhido: <strong><?php echo esc($planoEscolhido['nome']) ; ?></strong>.
                        O administrador confirma o pagamento e libera o acesso.
                    </p>
                <?php } else { ?>
                    <p class="texto-suave texto-pequeno">
                        Escolha o plano que voce quer. Voce pode mudar depois com o
                        administrador.
                    </p>
                <?php } ?>

                <?php foreach ($planos as $plano){ ?>
                    <?php
                    // O plano marcado no formulario.
                    $marcado = false;

                    if ($planoEscolhido !== null && (int) $plano['id'] === (int) $planoEscolhido['id']) {
                        $marcado = true;
                    }
                    ?>
                    <label class="cartao-plano">
                        <input type="radio" name="plano" value="<?php echo esc($plano['slug']) ; ?>"
                               <?php echo  escolher($marcado, 'checked', '') ; ?>>
                        <span class="cartao-plano__corpo">
                            <strong><?php echo esc($plano['nome']) ; ?></strong>
                            <span class="item-lista__meta">
                                <?php if (valor_ou($plano, 'max_palavras_chave', null) === null){ ?>
                                    Palavras-chave ilimitadas
                                <?php } else { ?>
                                    <?php echo esc((int) $plano['max_palavras_chave']) ; ?> palavra-chave(s)
                                <?php } ?>
                                | <?php echo esc((int) $plano['limite_envios_dia']) ; ?> envios/dia
                                | <?php echo esc((int) $plano['max_usuarios']) ; ?> usuario(s)
                            </span>
                        </span>
                    </label>
                <?php } ?>
            </div>

                <button class="botao botao--primario botao--bloco botao--grande" type="submit">
                    Criar minha conta
                    <?php echo  icone_svg('seta') ; ?>
                </button>

                <p class="formulario__nota">
                    Ao criar a conta, voce concorda com os
                    <a href="<?php echo  site_url('sobre') ; ?>#termos">termos de uso</a> e a
                    <a href="<?php echo  site_url('sobre') ; ?>#privacidade">politica de privacidade</a>.
                </p>
            </form>

            <p class="cartao-autenticacao__rodape">
                Ja tem conta?
                <a href="<?php echo  site_url('login') ; ?>">Entrar</a>
            </p>
        </div>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
