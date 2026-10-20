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
 * VIEW: ADMIN - PEDIDOS DE PLANO
 * --------------------------------------------------------------------------
 *
 * A fila de pedidos que os usuarios enviaram do painel deles.
 *
 * O usuario escolhe o plano la dentro e o pedido aparece aqui: o
 * administrador aprova (o plano passa a valer na conta) ou recusa (com
 * a justificativa, que o usuario ve no painel).
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Pedidos de plano';

/**
 * Devolve a classe e o rotulo do selo conforme a situacao do pedido.
 */
$seloPedido = static function (string $status): array {
    return match ($status) {
        'aprovada' => ['selo--sucesso', 'Aprovado'],
        'recusada' => ['selo--erro', 'Recusado'],
        default    => ['selo--aviso', 'Pendente'],
    };
};
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Pedidos de plano</h2>
        <p class="painel__descricao">
            Os usuarios pedem a troca de plano pelo painel. Aprove para
            liberar o plano ou recuse com a justificativa.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('admin/planos') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Ver os planos
        </a>
    </div>
</section>

<?php /* As abas de filtro. */ ?>
<div class="linha-selos">
    <a class="selo selo--aviso" href="<?php echo  site_url('admin/planos/solicitacoes?status=pendente') ; ?>">
        Pendentes: <?php echo  (int) $contagens['pendente'] ; ?>
    </a>
    <a class="selo selo--sucesso" href="<?php echo  site_url('admin/planos/solicitacoes?status=aprovada') ; ?>">
        Aprovados: <?php echo  (int) $contagens['aprovada'] ; ?>
    </a>
    <a class="selo selo--erro" href="<?php echo  site_url('admin/planos/solicitacoes?status=recusada') ; ?>">
        Recusados: <?php echo  (int) $contagens['recusada'] ; ?>
    </a>
    <a class="selo selo--neutro" href="<?php echo  site_url('admin/planos/solicitacoes?status=todas') ; ?>">
        Todos: <?php echo  (int) $contagens['todas'] ; ?>
    </a>
</div>

<?php if ($pedidos === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('raio') ; ?>
                <h3>Nenhum pedido nesta situacao</h3>
                <p class="texto-suave">
                    Quando um usuario pedir outro plano pelo painel, o pedido
                    aparece aqui.
                </p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="grade-cartoes grade-cartoes--detalhada">
        <?php foreach ($pedidos as $pedido){ ?>
            <?php
            // O selo conforme a situacao.
            $selo = $seloPedido((string) valor_ou($pedido, 'status', 'pendente'));

            // O preco do plano pedido.
            $preco = valor_ou($pedido, 'plano_preco', null);
            $precoTexto = 'sob consulta';

            if ($preco !== null) {
                $precoTexto = 'R$ ' . number_format((float) $preco, 2, ',', '.');
            }

            // O plano que o usuario tinha antes do pedido.
            $planoAnterior = texto_ou($pedido, 'plano_anterior_nome', '');

            // A observacao do usuario.
            $mensagem = texto_ou($pedido, 'mensagem', '');

            // A justificativa do administrador (quando recusado).
            $resposta = texto_ou($pedido, 'resposta', '');
            ?>

            <article class="cartao">
                <div class="cartao__cabecalho">
                    <h3 class="cartao__titulo"><?php echo esc(texto_ou($pedido, 'usuario_nome', 'Usuario')) ; ?></h3>
                    <span class="selo <?php echo esc($selo[0]) ; ?>"><?php echo esc($selo[1]) ; ?></span>
                </div>

                <div class="cartao__corpo">
                    <p class="texto-suave texto-pequeno">
                        <?php echo esc(texto_ou($pedido, 'usuario_email', '')) ; ?>
                    </p>

                    <div class="bloco-resumo">
                        <span class="bloco-resumo__rotulo">Plano pedido</span>
                        <span class="bloco-resumo__valor">
                            <?php echo esc(texto_ou($pedido, 'plano_nome', 'Plano')) ; ?>
                        </span>
                    </div>

                    <div class="bloco-resumo">
                        <span class="bloco-resumo__rotulo">Valor</span>
                        <span class="bloco-resumo__valor"><?php echo esc($precoTexto) ; ?>/mes</span>
                    </div>

                    <?php if ($planoAnterior !== ''){ ?>
                        <div class="bloco-resumo">
                            <span class="bloco-resumo__rotulo">Plano atual</span>
                            <span class="bloco-resumo__valor"><?php echo esc($planoAnterior) ; ?></span>
                        </div>
                    <?php } ?>

                    <p class="texto-suave texto-pequeno">
                        Pedido em <?php echo esc(date('d/m/Y as H:i', strtotime((string) valor_ou($pedido, 'criado_em', 'now')))) ; ?>
                    </p>

                    <?php if ($mensagem !== ''){ ?>
                        <div class="alerta alerta--info">
                            <?php echo  icone_svg('info') ; ?>
                            <p><?php echo esc($mensagem) ; ?></p>
                        </div>
                    <?php } ?>

                    <?php if ($resposta !== ''){ ?>
                        <div class="alerta alerta--atencao">
                            <?php echo  icone_svg('info') ; ?>
                            <p><strong>Resposta:</strong> <?php echo esc($resposta) ; ?></p>
                        </div>
                    <?php } ?>

                    <?php if ((string) valor_ou($pedido, 'status', '') === 'pendente'){ ?>
                        <div class="linha-acoes">
                            <form method="post"
                                  action="<?php echo  site_url('admin/planos/solicitacoes/' . (int) $pedido['id'] . '/aprovar') ; ?>">
                                <button class="botao botao--primario botao--pequeno" type="submit">
                                    <?php echo  icone_svg('check') ; ?> Aprovar
                                </button>
                            </form>

                            <form class="formulario" method="post"
                                  action="<?php echo  site_url('admin/planos/solicitacoes/' . (int) $pedido['id'] . '/recusar') ; ?>">
                                <div class="campo">
                                    <label class="campo__rotulo" for="resposta_<?php echo esc((int) $pedido['id']) ; ?>">
                                        Justificativa da recusa (opcional)
                                    </label>
                                    <input class="campo__entrada"
                                           id="resposta_<?php echo esc((int) $pedido['id']) ; ?>"
                                           type="text" name="resposta" maxlength="1000"
                                           placeholder="Ex.: o pagamento nao foi identificado">
                                </div>

                                <div class="formulario__acoes">
                                    <button class="botao botao--secundario botao--pequeno" type="submit">
                                        <?php echo  icone_svg('info') ; ?> Recusar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>

<?php } ?>

<?php echo  $this->endSection() ; ?>
