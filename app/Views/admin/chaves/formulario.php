<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - FORMULARIO DE CHAVE
 * --------------------------------------------------------------------------
 *
 * Usada na criacao e na edicao ($modo).
 * O valor da chave nunca e repreenchido: na edicao mostra-se apenas o
 * trecho mascarado e um campo para digitar uma nova chave, se for o caso.
 */

$this->extend('layouts/admin');

// Modo de edicao.
$editando = $modo === 'editar';

// Titulo exibido no topo.
$tituloPagina = 'Nova chave';

if ($editando) {
    $tituloPagina = 'Editar chave';
}

// Acao do formulario.
$acao = site_url('admin/chaves');

if ($editando) {
    $acao = site_url('admin/chaves/' . (int) $chave['id'] . '/atualizar');
}

// Valores atuais.
$apelido   = old('apelido', valor_ou($chave, 'apelido', ''));
$quantidade = old('quantidade_consultas', valor_ou($chave, 'quantidade_consultas', 250));
$ordem     = old('ordem', valor_ou($chave, 'ordem', 1));
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao"><?php echo  esc($tituloPagina) ; ?></h2>
        <p class="painel__descricao">
            <?php echo escolher($editando, 'Atualize os dados da chave ou troque o valor da credencial.', 'A chave passa a valer imediatamente e entra na rotacao automatica.'); ?>
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/chaves') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Voltar
        </a>
    </div>
</section>

<section class="cartao">
    <div class="cartao__corpo">
        <form class="formulario" method="post" action="<?php echo  esc($acao) ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="apelido">Apelido</label>
                <input class="campo__entrada" type="text" id="apelido" name="apelido" maxlength="100" required
                       value="<?php echo  esc($apelido) ; ?>" placeholder="Ex.: Conta principal">
                <span class="campo__ajuda">Nome para voce identificar a conta no painel.</span>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="chave">Chave da SerpApi</label>

                <?php if ($editando && ! empty($chave['chave_mascarada'])){ ?>
                    <p class="texto-suave texto-pequeno">
                        Valor atual: <code class="codigo-mascarado"><?php echo  esc($chave['chave_mascarada']) ; ?></code>
                    </p>
                <?php } ?>

                <div class="campo__com-acao">
                    <input class="campo__entrada" type="password" id="chave" name="chave"
                           autocomplete="off" maxlength="255"
                           placeholder="<?php echo escolher($editando, 'Deixe em branco para manter a atual', 'Cole aqui a chave da API'); ?>"
                           <?php echo escolher($editando, '', 'required'); ?>>
                    <button type="button" class="campo__ver-senha" data-alvo="chave"
                            aria-label="Mostrar chave">Ver</button>
                </div>
                <span class="campo__ajuda">A chave e gravada no banco e nunca mais exibida por inteiro.</span>
            </div>

            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="quantidade_consultas">Consultas contratadas</label>
                    <input class="campo__entrada" type="number" id="quantidade_consultas" name="quantidade_consultas"
                           min="0" max="10000000" required value="<?php echo  esc($quantidade) ; ?>">
                    <span class="campo__ajuda">Total de consultas do plano desta conta.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="ordem">Ordem de uso</label>
                    <input class="campo__entrada" type="number" id="ordem" name="ordem"
                           min="1" max="9999" required value="<?php echo  esc($ordem) ; ?>">
                    <span class="campo__ajuda">A rotacao usa primeiro a chave ativa de menor ordem.</span>
                </div>
            </div>

            <div class="formulario__acoes">
                <a class="botao botao--fantasma" href="<?php echo  site_url('admin/chaves') ; ?>">Cancelar</a>
                <button type="submit" class="botao botao--primario">
                    <?php echo  icone_svg('check') ; ?> <?php echo escolher($editando, 'Salvar alteracoes', 'Cadastrar chave'); ?>
                </button>
            </div>
        </form>
    </div>
</section>

<?php if ($editando){ ?>

    <?php /* Acoes de ciclo de vida da chave. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Acoes da chave</h3>
            <span class="selo <?php echo  escolher((int) $chave['ativa'] === 1, 'selo--sucesso', 'selo--neutro') ; ?>">
                <?php echo  escolher((int) $chave['ativa'] === 1, 'Ativa', 'Inativa') ; ?>
            </span>
        </div>
        <div class="cartao__corpo">
            <p class="texto-suave texto-pequeno">
                Uso atual: <?php echo  (int) $chave['consultas_usadas'] ; ?> de <?php echo  (int) $chave['quantidade_consultas'] ; ?>
                consulta(s) &middot; mes de referencia <?php echo  esc(texto_ou($chave, 'mes_referencia', '-')) ; ?>.
            </p>

            <div class="linha-acoes">
                <form method="post" action="<?php echo  site_url('admin/chaves/' . (int) $chave['id'] . '/zerar') ; ?>"
                      data-confirmar="Zerar o contador de consultas desta chave?">
                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                        <?php echo  icone_svg('relogio') ; ?> Zerar contador
                    </button>
                </form>

                <form method="post" action="<?php echo  site_url('admin/chaves/' . (int) $chave['id'] . '/esgotar') ; ?>"
                      data-confirmar="Marcar como esgotada? Ela volta automaticamente na virada do mes.">
                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                        <?php echo  icone_svg('alvo') ; ?> Marcar esgotada
                    </button>
                </form>

                <form method="post" action="<?php echo  site_url('admin/chaves/' . (int) $chave['id'] . '/invalidar') ; ?>"
                      data-confirmar="Marcar como invalida? Ela so volta se voce reativar manualmente.">
                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                        <?php echo  icone_svg('info') ; ?> Marcar invalida
                    </button>
                </form>
            </div>

            <form method="post" action="<?php echo  site_url('admin/chaves/' . (int) $chave['id'] . '/excluir') ; ?>"
                  data-confirmar="Excluir a chave? O historico de consultas permanece.">
                <button type="submit" class="botao botao--fantasma botao--perigo">
                    <?php echo  icone_svg('lixeira') ; ?> Excluir chave
                </button>
            </form>
        </div>
    </section>

<?php } ?>

<?php echo  $this->endSection() ; ?>
