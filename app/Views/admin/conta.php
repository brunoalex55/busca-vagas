<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - MINHA CONTA
 * --------------------------------------------------------------------------
 *
 * Perfil (nome/e-mail) e troca de senha do administrador.
 * Nenhuma senha e repreenchida no formulario.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Minha conta';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Minha conta</h2>
        <p class="painel__descricao">Atualize seus dados de acesso e a senha do painel administrativo.</p>
    </div>
</section>

<section class="cartao" id="perfil">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Perfil</h3>
        <span class="selo selo--admin">Administrador</span>
    </div>
    <div class="cartao__corpo">
        <form class="formulario formulario--duas-colunas" method="post"
              action="<?php echo  site_url('admin/conta/perfil') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="nome">Nome completo</label>
                <input class="campo__entrada" type="text" id="nome" name="nome" maxlength="150" required
                       value="<?php echo  esc(old('nome', valor_ou($usuario, 'nome', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="email">E-mail de acesso</label>
                <input class="campo__entrada" type="email" id="email" name="email" maxlength="180" required
                       value="<?php echo  esc(old('email', valor_ou($usuario, 'email', ''))) ; ?>">
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('check') ; ?> Salvar perfil</button>
            </div>
        </form>

        <p class="texto-suave texto-pequeno">
            Conta criada em <?php echo  esc(formatar_data_hora(valor_ou($usuario, 'criado_em', null), false)) ; ?>.
        </p>
    </div>
</section>

<section class="cartao" id="seguranca">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Seguranca</h3>
    </div>
    <div class="cartao__corpo">
        <form class="formulario" method="post" action="<?php echo  site_url('admin/conta/senha') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="senha_atual">Senha atual</label>
                <div class="campo__com-acao">
                    <input class="campo__entrada" type="password" id="senha_atual" name="senha_atual"
                           autocomplete="current-password" required>
                    <button type="button" class="campo__ver-senha" data-alvo="senha_atual"
                            aria-label="Mostrar senha">Ver</button>
                </div>
            </div>

            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="nova_senha">Nova senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="nova_senha" name="nova_senha"
                               autocomplete="new-password" minlength="6" required>
                        <button type="button" class="campo__ver-senha" data-alvo="nova_senha"
                                aria-label="Mostrar senha">Ver</button>
                    </div>
                    <span class="campo__ajuda">Minimo de 6 caracteres.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha_confirmacao">Confirmar nova senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="senha_confirmacao" name="senha_confirmacao"
                               autocomplete="new-password" minlength="6" required>
                        <button type="button" class="campo__ver-senha" data-alvo="senha_confirmacao"
                                aria-label="Mostrar senha">Ver</button>
                    </div>
                </div>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('cadeado') ; ?> Alterar senha</button>
            </div>
        </form>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
