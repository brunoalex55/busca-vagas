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
 * VIEW: ADMIN - FATURAMENTO
 * --------------------------------------------------------------------------
 *
 * O relatorio mensal: quantos usuarios o sistema tinha em cada mes,
 * quantos entraram novos e a receita estimada.
 *
 * Abaixo, as contas que estao bloqueadas por falta de pagamento (o
 * administrador ve os dados do PIX de novo e pode destravar a conta
 * quando o pagamento cair).
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Faturamento';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Faturamento</h2>
        <p class="painel__descricao">
            Como o sistema cresceu mes a mes e quanto cada mes rendeu.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('admin') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Visao geral
        </a>
    </div>
</section>

<?php /* Os numeros de hoje. */ ?>
<div class="grade-metricas">

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('usuario') ; ?></span>
        <span class="cartao-metrica__rotulo">Contas ativas</span>
        <strong class="cartao-metrica__valor"><?php echo  esc((int) $resumo['ativos']) ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  (int) $resumo['total'] ; ?> no total &middot;
            <?php echo  (int) $resumo['pendentes'] ; ?> pendente(s) &middot;
            <?php echo  (int) $resumo['bloqueados'] ; ?> bloqueada(s)
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--verde"><?php echo  icone_svg('alvo') ; ?></span>
        <span class="cartao-metrica__rotulo">Receita mensal estimada</span>
        <strong class="cartao-metrica__valor">
            R$ <?php echo  esc(number_format((float) $resumo['receita'], 2, ',', '.')) ; ?>
        </strong>
        <span class="cartao-metrica__detalhe">
            Somando os planos das contas ativas
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--vermelho"><?php echo  icone_svg('escudo') ; ?></span>
        <span class="cartao-metrica__rotulo">Bloqueadas por pagamento</span>
        <strong class="cartao-metrica__valor"><?php echo  esc(count($bloqueados)) ; ?></strong>
        <span class="cartao-metrica__detalhe">
            Esperando a renovacao do plano
        </span>
    </article>
</div>

<?php /* O relatorio por mes. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Historico por mes</h3>
        <span class="selo selo--neutro">Usuarios que o sistema tinha</span>
    </div>

    <div class="cartao__corpo">
        <?php if ($meses === []){ ?>
            <div class="estado-vazio">
                <?php echo  icone_svg('calendario') ; ?>
                <h3>Sem historico ainda</h3>
                <p class="texto-suave">O relatorio aparece quando houver usuarios cadastrados.</p>
            </div>
        <?php } else { ?>

            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Mes</th>
                            <th scope="col">Novos</th>
                            <th scope="col">Usuarios ativos</th>
                            <th scope="col">Bloqueados</th>
                            <th scope="col">Receita estimada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meses as $mes){ ?>
                            <tr>
                                <th scope="row">
                                    <span class="item-lista__titulo"><?php echo esc($mes['rotulo']) ; ?></span>
                                </th>

                                <td><?php echo esc((int) $mes['novos']) ; ?></td>

                                <td><?php echo esc((int) $mes['ativos']) ; ?></td>

                                <td>
                                    <?php if ((int) $mes['bloqueados'] > 0){ ?>
                                        <span class="selo selo--erro"><?php echo esc((int) $mes['bloqueados']) ; ?></span>
                                    <?php } else { ?>
                                        <span class="selo selo--neutro">0</span>
                                    <?php } ?>
                                </td>

                                <td>
                                    R$ <?php echo esc(number_format((float) $mes['receita'], 2, ',', '.')) ; ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="alerta alerta--info">
                <?php echo  icone_svg('info') ; ?>
                <p>
                    O numero de usuarios e exato. A receita e uma
                    <strong>estimativa</strong>: o sistema guarda o plano de
                    cada conta, mas nao o historico de troca de plano.
                </p>
            </div>

        <?php } ?>
    </div>
</section>

<?php /* As contas bloqueadas por falta de pagamento. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Contas bloqueadas por falta de pagamento</h3>
        <span class="selo selo--erro"><?php echo esc(count($bloqueados)) ; ?></span>
    </div>

    <div class="cartao__corpo">
        <?php /* Os dados do PIX (o admin ve de novo para cobrar). */ ?>
        <div class="pix">
            <h3 class="pix__titulo">Dados do pagamento</h3>

            <div class="pix__linha">
                <span class="pix__rotulo">Chave PIX</span>
                <div class="pix__valor-grupo">
                    <code class="pix__valor" id="pix-chave-faturamento">
                        <?php echo  esc(texto_ou($pagamento, 'chave_pix', 'Chave ainda nao configurada')) ; ?>
                    </code>
                    <?php if (texto_ou($pagamento, 'chave_pix', '') !== ''){ ?>
                        <button class="botao botao--fantasma botao--pequeno"
                                type="button"
                                data-copiar="pix-chave-faturamento"
                                data-copiado="Chave copiada!">
                            Copiar
                        </button>
                    <?php } ?>
                </div>
            </div>

            <div class="pix__linha">
                <span class="pix__rotulo">Valor mensal</span>
                <strong class="pix__valor-destaque">
                    R$ <?php echo  esc(number_format((float) valor_ou($pagamento, 'valor_mensal', 0), 2, ',', '.')) ; ?>
                </strong>
            </div>
        </div>

        <?php if ($bloqueados === []){ ?>

            <div class="estado-vazio">
                <?php echo  icone_svg('check') ; ?>
                <h3>Nenhuma conta bloqueada por pagamento</h3>
                <p class="texto-suave">
                    Quando voce bloquear uma conta por falta de pagamento,
                    ela aparece aqui com os dados para a renovacao.
                </p>
            </div>

        <?php } else { ?>

            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Motivo</th>
                            <th scope="col">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bloqueados as $conta){ ?>
                            <tr>
                                <th scope="row">
                                    <span class="item-lista__titulo">
                                        <?php echo esc(texto_ou($conta, 'nome', 'Usuario')) ; ?>
                                    </span>
                                    <span class="item-lista__meta">
                                        <?php echo esc(texto_ou($conta, 'email', '')) ; ?>
                                    </span>
                                </th>

                                <td>
                                    <?php echo esc(texto_ou($conta, 'motivo_bloqueio', 'Falta de pagamento da mensalidade.')) ; ?>
                                </td>

                                <td>
                                    <form method="post"
                                          action="<?php echo  site_url('admin/usuarios/' . (int) $conta['id'] . '/cobrar-pagamento') ; ?>">
                                        <button class="botao botao--secundario botao--pequeno" type="submit">
                                            <?php echo  icone_svg('check') ; ?> Libera acesso
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        <?php } ?>
    </div>
</section>

<?php /* O bloqueio por falta de pagamento (a partir de qualquer usuario). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Bloquear por falta de pagamento</h3>
    </div>

    <div class="cartao__corpo">
        <p class="texto-suave texto-pequeno">
            A conta para de funcionar (os crons pulam ela) e o usuario volta
            a ver os dados do PIX para renovar. Depois do pagamento, use
            <strong>Libera acesso</strong> acima ou a tela do usuario.
        </p>

        <form class="formulario" method="post"
              action="<?php echo  site_url('admin/usuarios/bloquear-pagamento') ; ?>">

            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="bloqueio_usuario">Conta do usuario</label>
                    <select class="campo__entrada" id="bloqueio_usuario" name="usuario_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($contasAtivas as $contaAtiva){ ?>
                            <option value="<?php echo esc((int) $contaAtiva['id']) ; ?>">
                                <?php echo esc(texto_ou($contaAtiva, 'nome', 'Usuario')) ; ?> - <?php echo esc(texto_ou($contaAtiva, 'email', '')) ; ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="campo__ajuda">A conta para de funcionar ate a renovacao.</p>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="bloqueio_motivo">Motivo (opcional)</label>
                    <input class="campo__entrada" id="bloqueio_motivo" type="text"
                           name="motivo" maxlength="255"
                           placeholder="Ex.: mensalidade de outubro nao paga">
                </div>
            </div>

            <div class="formulario__acoes">
                <button class="botao botao--primario" type="submit">
                    <?php echo  icone_svg('escudo') ; ?> Bloquear conta
                </button>
            </div>
        </form>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
