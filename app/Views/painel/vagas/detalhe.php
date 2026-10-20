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
 * VIEW: PAINEL - DETALHE DA VAGA
 * --------------------------------------------------------------------------
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Detalhe da vaga';

// Estados uteis.
$candidatada = inteiro_ou($vaga, 'ja_candidatado', 0) === 1;
$temEmail    = trim(texto_ou($vaga, 'email_contato', '')) !== '';
$urlOriginal = url_segura(valor_ou($vaga, 'url_original', null));
$urlCandidatura = url_segura(valor_ou($vaga, 'url_candidatura', null));

// Rotulos da origem do e-mail.
$origensEmail = [
    'descricao'     => 'Texto da vaga (confiavel)',
    'apply_options' => 'Links de candidatura',
    'pagina'        => 'Pagina da vaga (nao confirmado)',
];

// Descricao limpa (sem HTML do provedor), preservando quebras.
$descricao = nl2br(esc(trim(strip_tags(texto_ou($vaga, 'descricao', '')))));
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao"><?php echo  esc(primeiro_valor($vaga['titulo'], 'Vaga sem titulo')) ; ?></h2>
        <p class="painel__descricao">
            <?php echo  esc(primeiro_valor($vaga['empresa'], 'Empresa nao informada')) ; ?>
            <?php if (! empty($vaga['localizacao'])){ ?>
                &middot; <?php echo  esc($vaga['localizacao']) ; ?>
            <?php } ?>
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('painel/vagas') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Voltar
        </a>
    </div>
</section>

<?php /* Selos de estado. */ ?>
<div class="detalhe-selos">
    <span class="selo selo--neutro"><?php echo  esc(primeiro_valor($vaga['fonte'], 'Outros')) ; ?></span>

    <?php if ($temEmail){ ?>
        <span class="selo selo--sucesso">Com e-mail</span>
    <?php } else { ?>
        <span class="selo selo--neutro">Sem e-mail</span>
    <?php } ?>

    <?php if ($candidatada){ ?>
        <span class="selo selo--info">Candidatada</span>
    <?php } ?>

    <?php if (! empty($vaga['e_prioritaria'])){ ?>
        <span class="selo selo--primario">Prioritaria</span>
    <?php } ?>
</div>

<div class="grade grade--painel">

    <?php /* Coluna principal: descricao. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Descricao da vaga</h3>
        </div>
        <div class="cartao__corpo">
            <?php if (trim(texto_ou($vaga, 'descricao', '')) === ''){ ?>
                <p class="texto-suave">O anuncio nao trouxe descricao.</p>
            <?php } else { ?>
                <div class="prosa"><?php echo  $descricao ; ?></div>
            <?php } ?>
        </div>
    </section>

    <?php /* Coluna lateral: dados e acoes. */ ?>
    <div class="coluna-lateral">

        <section class="cartao">
            <div class="cartao__cabecalho">
                <h3 class="cartao__titulo">Dados da vaga</h3>
            </div>
            <div class="cartao__corpo">
                <dl class="lista-dados">
                    <dt>Fonte</dt>
                    <dd><?php echo  esc(primeiro_valor($vaga['fonte'], 'Nao informada')) ; ?></dd>

                    <dt>Salario</dt>
                    <dd><?php echo  esc(primeiro_valor($vaga['salario'], 'Nao informado')) ; ?></dd>

                    <dt>Tipo de contrato</dt>
                    <dd><?php echo  esc(primeiro_valor($vaga['tipo_contrato'], 'Nao informado')) ; ?></dd>

                    <dt>Publicada em</dt>
                    <dd><?php echo  esc(formatar_data_hora(valor_ou($vaga, 'publicado_em', null), false)) ; ?></dd>

                    <dt>Cidade consultada</dt>
                    <dd><?php echo  esc(primeiro_valor($vaga['cidade_consultada'], 'Nao informada')) ; ?></dd>

                    <dt>Encontrada em</dt>
                    <dd><?php echo  esc(formatar_data_hora(valor_ou($vaga, 'criado_em', null))) ; ?></dd>
                </dl>
            </div>
        </section>

        <section class="cartao">
            <div class="cartao__cabecalho">
                <h3 class="cartao__titulo">Contato</h3>
            </div>
            <div class="cartao__corpo">
                <?php if ($temEmail){ ?>
                    <p class="texto-suave texto-pequeno">E-mail principal:</p>
                    <p class="email-destaque"><?php echo  esc($vaga['email_contato']) ; ?></p>

                    <?php if (! empty($vaga['origem_email'])){ ?>
                        <p class="texto-suave texto-pequeno">
                            <?php
                            // Rotulo da origem do e-mail (pode nao existir).
                            $origem = 'Nao informada';

                            if (isset($origensEmail[$vaga['origem_email']])) {
                                $origem = $origensEmail[$vaga['origem_email']];
                            }
                            ?>
                            Origem: <?php echo  esc($origem) ; ?>
                        </p>
                    <?php } ?>

                    <?php if (valor_ou($vaga, 'emails_encontrados', []) !== []){ ?>
                        <div class="tags">
                            <?php foreach ($vaga['emails_encontrados'] as $email){ ?>
                                <span class="tag"><?php echo  esc($email) ; ?></span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="alerta alerta--info">
                        <?php echo  icone_svg('info') ; ?>
                        <p>
                            Esta vaga nao expos um e-mail. Candidate-se pelo site da vaga.
                        </p>
                    </div>
                <?php } ?>
            </div>
        </section>

        <?php /* Acoes. */ ?>
        <section class="cartao">
            <div class="cartao__cabecalho">
                <h3 class="cartao__titulo">Acoes</h3>
            </div>
            <div class="cartao__corpo">
                <?php if ($urlOriginal !== null){ ?>
                    <a class="botao botao--secundario botao--bloco"
                       href="<?php echo  esc($urlOriginal) ; ?>" target="_blank" rel="noopener noreferrer">
                        Abrir anuncio <?php echo  icone_svg('seta') ; ?>
                    </a>
                <?php } ?>

                <?php if ($urlCandidatura !== null){ ?>
                    <a class="botao botao--fantasma botao--bloco"
                       href="<?php echo  esc($urlCandidatura) ; ?>" target="_blank" rel="noopener noreferrer">
                        Candidatar-se pelo site
                    </a>
                <?php } ?>

                <?php if (! $candidatada){ ?>
                    <form method="post"
                          action="<?php echo  site_url('painel/vagas/' . (int) $vaga['id'] . '/marcar-candidatado') ; ?>"
                          data-confirmar="Marcar esta vaga como candidatada?">
                        <button type="submit" class="botao botao--primario botao--bloco">
                            <?php echo  icone_svg('check') ; ?> Marcar como candidatada
                        </button>
                    </form>
                <?php } else { ?>
                    <div class="alerta alerta--sucesso">
                        <?php echo  icone_svg('check') ; ?>
                        <p>Esta vaga ja esta marcada como candidatada.</p>
                    </div>
                <?php } ?>

                <?php /* Candidatura pelo bot do Divulga Vagas. */ ?>
                <?php if (texto_ou($vaga, 'provedor_vaga') === 'divulgavagas'){ ?>
                    <?php echo  selo_divulgavagas($vaga) ; ?>

                    <?php /* A prova: o site confirmou o envio do curriculo? */ ?>
                    <?php $confirmadoEm = texto_ou($vaga, 'candidatura_divulgavagas_confirmado_em', ''); ?>
                    <?php $confirmacaoUrl = texto_ou($vaga, 'candidatura_divulgavagas_confirmacao_url', ''); ?>
                    <?php if ($confirmadoEm !== ''){ ?>
                        <div class="alerta alerta--sucesso">
                            <?php echo  icone_svg('check') ; ?>
                            <p>
                                O site confirmou esta candidatura em
                                <?php echo  esc(formatar_data_hora($confirmadoEm, false)) ; ?>.
                                <?php if ($confirmacaoUrl !== ''){ ?>
                                    <a href="<?php echo  esc($confirmacaoUrl) ; ?>" target="_blank" rel="noopener noreferrer">
                                        Ver a pagina de sucesso
                                    </a>.
                                <?php } ?>
                            </p>
                        </div>
                    <?php } ?>

                    <?php if (texto_ou($vaga, 'candidatura_divulgavagas_status', 'pendente') !== 'enviada'
                        && texto_ou($vaga, 'candidatura_divulgavagas_status', 'pendente') !== 'enviada_email'){ ?>
                        <form method="post"
                              action="<?php echo  site_url('painel/divulgavagas/candidatar-vaga/' . (int) $vaga['id']) ; ?>"
                              data-confirmar="Candidatar-se agora pelo site do Divulga Vagas?">
                            <button type="submit" class="botao botao--secundario botao--bloco">
                                <?php echo  icone_svg('raio') ; ?> Candidatar a esta vaga
                            </button>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>
    </div>
</div>

<?php echo  $this->endSection() ; ?>
