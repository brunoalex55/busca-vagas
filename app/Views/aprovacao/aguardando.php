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
 * VIEW: AGUARDANDO APROVACAO
 * --------------------------------------------------------------------------
 *
 * Tela do usuario com status "pendente" ou "bloqueado".
 *
 * Mostra:
 *   - situacao atual da conta;
 *   - chave PIX e valor configurados pelo admin;
 *   - instrucoes de pagamento;
 *   - botao "Informar pagamento" (POST);
 *   - botao de sair (POST).
 */

$this->extend('layouts/site');

// Dados recebidos do controller.
$pagamento = $pagamento;

if ($pagamento === null) {
    $pagamento = configuracao_pagamento();
}

$temPendente = false;

if ($temPendente !== null) {
    $temPendente = (bool) $temPendente;
}

// Situacao atual (vem do controller, que le o status do banco).
$status = 'pendente';

if (isset($statusConta) && $statusConta !== '') {
    $status = (string) $statusConta;
}

// A conta foi bloqueada por falta de pagamento? (a renovacao).
$renovacaoPendente = false;

if (isset($bloqueadoPagamento)) {
    $renovacaoPendente = (bool) $bloqueadoPagamento;
}

// O motivo do bloqueio por pagamento.
$motivoRenovacao = '';

if (isset($motivoBloqueio)) {
    $motivoRenovacao = (string) $motivoBloqueio;
}

// Nome do usuario.
$nome = session()->get('nome');

if ($nome === null || $nome === '') {
    $nome = 'Usuario';
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="secao secao--aprovacao">
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>

    <div class="container container--estreito">

        <div class="cartao-aprovacao animar ao-rolar">

            <?php /* Cabecalho conforme o status. */ ?>
            <header class="cartao-aprovacao__cabecalho">
                <span class="cartao-aprovacao__icone">
                    <?php /* Icone conforme a situacao da conta. */ ?>
                <?php
                $iconeSituacao = 'relogio';

                if ($status === 'bloqueado') {
                    $iconeSituacao = 'escudo';
                }
                ?>
                <?php echo  icone_svg($iconeSituacao) ; ?>
                </span>

                <?php if ($status === 'bloqueado' && $renovacaoPendente){ ?>
                    <h1 class="cartao-aprovacao__titulo">Renove seu plano</h1>
                    <p class="cartao-aprovacao__texto">
                        Ola, <?php echo  esc($nome) ; ?>. O acesso a sua conta foi
                        suspenso por falta de pagamento.
                    </p>

                    <?php if ($motivoRenovacao !== ''){ ?>
                        <p class="cartao-aprovacao__texto">
                            Motivo: <strong><?php echo esc($motivoRenovacao) ; ?></strong>.
                        </p>
                    <?php } ?>

                    <p class="cartao-aprovacao__texto">
                        Faca o pagamento abaixo e avise o administrador. Assim que
                        o pagamento for confirmado, a sua conta volta a funcionar.
                    </p>
                <?php } elseif ($status === 'bloqueado'){ ?>
                    <h1 class="cartao-aprovacao__titulo">Sua conta esta bloqueada</h1>
                    <p class="cartao-aprovacao__texto">
                        Ola, <?php echo  esc($nome) ; ?>. O acesso a sua conta foi suspenso.
                        Entre em contato com o suporte para mais informacoes.
                    </p>
                <?php } else { ?>
                    <h1 class="cartao-aprovacao__titulo">Aguardando aprovacao</h1>
                    <p class="cartao-aprovacao__texto">
                        Ola, <?php echo  esc($nome) ; ?>. Sua conta foi criada com sucesso!
                        Para liberar o acesso, faca o pagamento via PIX e informe abaixo.
                    </p>

                    <?php /* O plano que o usuario escolheu no cadastro. */ ?>
                    <?php
                    // O plano escolhido (vazio quando nao tem).
                    $planoEscolhido = '';

                    if (isset($plano) && $plano !== null) {
                        $planoEscolhido = texto_ou($plano, 'nome', '');
                    }
                    ?>
                    <?php if ($planoEscolhido !== ''){ ?>
                        <p class="cartao-aprovacao__texto">
                            Plano escolhido: <strong><?php echo esc($planoEscolhido) ; ?></strong>.
                            O administrador confirma o pagamento e libera o acesso.
                        </p>
                    <?php } ?>
                <?php } ?>
            </header>

            <?php if ($status !== 'bloqueado' || $renovacaoPendente){ ?>

                <?php /* Dados do PIX. */ ?>
                <div class="pix">

                    <h2 class="pix__titulo">Pagamento via PIX</h2>

                    <div class="pix__linha">
                        <span class="pix__rotulo">Chave PIX</span>
                        <div class="pix__valor-grupo">
                            <code class="pix__valor" id="pix-chave"><?php echo  esc((string) texto_ou($pagamento, 'chave_pix', 'Chave ainda nao configurada')) ; ?></code>
                            <?php if (! empty($pagamento['chave_pix'])){ ?>
                                <?php /* Copiar para a area de transferencia: JavaScript apenas UX. */ ?>
                                <button class="botao botao--fantasma botao--pequeno"
                                        type="button"
                                        data-copiar="pix-chave"
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

                    <?php if (! empty($pagamento['texto_instrucoes'])){ ?>
                        <div class="pix__instrucoes">
                            <h3 class="pix__subtitulo">Como pagar</h3>
                            <p><?php echo  nl2br(esc($pagamento['texto_instrucoes'])) ; ?></p>
                        </div>
                    <?php } ?>
                </div>

                <?php /* Aviso de seguranca. */ ?>
                <div class="alerta alerta--atencao">
                    <?php echo  icone_svg('escudo') ; ?>
                    <p>
                        <?php if ($renovacaoPendente){ ?>
                            Faca o pagamento e clique em
                            <strong>Informar pagamento</strong>. Assim que o
                            administrador confirmar, o acesso volta.
                        <?php } else { ?>
                            A liberacao e feita manualmente. Apos pagar, clique em
                            "Informar pagamento" e aguarde a confirmacao em ate
                            <strong>24 horas uteis</strong>.
                        <?php } ?>
                    </p>
                </div>

                <?php /* Formulario de aviso de pagamento. */ ?>
                <?php if ($temPendente){ ?>

                    <div class="alerta alerta--sucesso">
                        <?php echo  icone_svg('check') ; ?>
                        <p>
                            Ja recebemos o seu aviso de pagamento. Estamos analisando
                            e liberaremos o acesso em breve.
                        </p>
                    </div>

                <?php } else { ?>

                    <form class="formulario" method="post"
                          action="<?php echo  site_url('aguardando/informar-pagamento') ; ?>" novalidate>

                        <div class="campo">
                            <label class="campo__rotulo" for="observacao">
                                Observacao (opcional)
                            </label>
                            <textarea class="campo__entrada campo__entrada--area"
                                      id="observacao" name="observacao" rows="3"
                                      maxlength="1000"
                                      placeholder="Ex.: PIX enviado em 18/10 as 14h, nome do titular..."></textarea>
                        </div>

                        <button class="botao botao--primario botao--bloco botao--grande" type="submit">
                            Informar pagamento
                            <?php echo  icone_svg('seta') ; ?>
                        </button>
                    </form>

                <?php } ?>

            <?php } ?>

            <?php /* Sair (SOMENTE POST). */ ?>
            <footer class="cartao-aprovacao__rodape">
                <form method="post" action="<?php echo  site_url('logout') ; ?>" class="form-inline">
                    <button class="botao botao--fantasma botao--pequeno" type="submit">
                        Sair da conta
                    </button>
                </form>
            </footer>
        </div>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
