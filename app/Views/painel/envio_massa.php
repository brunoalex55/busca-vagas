<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: PAINEL - ENVIO EM MASSA
 * --------------------------------------------------------------------------
 *
 * Central de envio: estado do robo, limite diario, janela de horario,
 * checklist de prontidao e fila priorizada de vagas.
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Envio em massa';

// Percentual do limite diario.
$percentual = 0;

if ($limiteDiario > 0) {
    $percentual = min(100, (int) round($enviosHoje * 100 / $limiteDiario));
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Envio em massa</h2>
        <p class="painel__descricao">
            O robo envia uma candidatura por vez, com pausas entre elas, para proteger sua conta Gmail.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('painel/configuracoes') ; ?>">
            <?php echo  icone_svg('escudo') ; ?> Configuracoes
        </a>
    </div>
</section>

<?php /* Ligar/desligar o envio automatico. */ ?>
<section class="cartao cartao--estado">
    <div class="cartao__corpo cartao__corpo--linha">
        <div class="estado-envio">
            <span class="estado-envio__icone <?php echo escolher($envioAutomatico, 'estado-envio__icone--ligado', ''); ?>">
                <?php echo  icone_svg('raio') ; ?>
            </span>
            <div>
                <h3 class="estado-envio__titulo">
                    Envio automatico <?php echo escolher($envioAutomatico, 'ligado', 'desligado'); ?>
                </h3>
                <p class="texto-suave texto-pequeno">
                    <?php echo escolher($envioAutomatico, 'O robo esta autorizado a enviar candidaturas na janela configurada.', 'Ligue para que o robo comece a enviar as candidaturas da fila.'); ?>
                </p>
            </div>
        </div>

        <form method="post" action="<?php echo  site_url('painel/envio-massa/alternar-automatico') ; ?>">
            <button type="submit" class="botao <?php echo escolher($envioAutomatico, 'botao--fantasma', 'botao--primario'); ?>">
                <?php echo  escolher($envioAutomatico, icone_svg('pausa') . ' Desligar', icone_svg('play') . ' Ligar envio') ; ?>
            </button>
        </form>
    </div>
</section>

<?php /* Metricas do envio. */ ?>
<div class="grade-metricas">
    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__rotulo">Envios hoje</span>
        <strong class="cartao-metrica__valor">
            <?php echo  number_format($enviosHoje, 0, ',', '.') ; ?>
            <small class="cartao-metrica__fracao">/ <?php echo  number_format($limiteDiario, 0, ',', '.') ; ?></small>
        </strong>
        <span class="barra-progresso" role="img" aria-label="<?php echo  esc($percentual) ; ?>% do limite diario">
            <span class="barra-progresso__valor" style="width: <?php echo  (int) $percentual ; ?>%"></span>
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__rotulo">Restantes hoje</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format($restantesHoje, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Dentro do limite diario configurado.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__rotulo">Vagas na fila</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format($totalFila, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Vagas com e-mail e ainda nao candidatadas.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__rotulo">Janela de envio</span>
        <strong class="cartao-metrica__valor cartao-metrica__valor--texto">
            <?php echo  esc(substr(texto_ou($configuracao, 'horario_inicio_envio', '08:00'), 0, 5)) ; ?>
            -
            <?php echo  esc(substr(texto_ou($configuracao, 'horario_fim_envio', '20:00'), 0, 5)) ; ?>
        </strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  esc(rotulo_dias_semana(texto_ou($configuracao, 'dias_semana_permitidos', ''))) ; ?>
        </span>
    </article>
</div>

<?php /* Estado da janela de envio agora. */ ?>
<div class="alerta <?php echo  escolher($janela['aberta'], 'alerta--sucesso', 'alerta--atencao') ; ?>">
    <?php echo  icone_svg(escolher($janela['aberta'], 'check', 'relogio')) ; ?>
    <p>
        <strong><?php echo  esc($janela['dia_atual']) ; ?>, <?php echo  esc($janela['hora_atual']) ; ?>.</strong>
        <?php echo  esc($janela['motivo']) ; ?>
    </p>
</div>

<div class="grade grade--painel">

    <?php /* Checklist de prontidao. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Prontidao para envio</h3>
        </div>
        <div class="cartao__corpo">
            <ul class="checklist">
                <?php foreach ($verificacoes as $item){ ?>
                    <li class="checklist__item">
                        <span class="checklist__icone <?php echo  escolher($item['ok'], 'checklist__icone--ok', 'checklist__icone--pendente') ; ?>">
                            <?php echo  icone_svg(escolher($item['ok'], 'check', 'info')) ; ?>
                        </span>
                        <div>
                            <strong><?php echo  esc($item['rotulo']) ; ?></strong>
                            <p class="texto-suave texto-pequeno"><?php echo  esc($item['detalhe']) ; ?></p>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </section>

    <?php /* Como funciona. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Como o envio funciona</h3>
        </div>
        <div class="cartao__corpo">
            <ul class="lista-checagem">
                <li><?php echo  icone_svg('check') ; ?> Uma candidatura por vez, com intervalo de
                    <?php echo  inteiro_ou($configuracao, 'intervalo_minimo_segundos', 180) ; ?>s a
                    <?php echo  inteiro_ou($configuracao, 'intervalo_maximo_segundos', 420) ; ?>s.</li>
                <li><?php echo  icone_svg('check') ; ?> Respeita o limite de <?php echo  (int) $limiteDiario ; ?> envio(s) por dia.</li>
                <li><?php echo  icone_svg('check') ; ?> Somente dentro da janela de horario e dos dias escolhidos.</li>
                <li><?php echo  icone_svg('check') ; ?> Nunca reenvia para uma vaga ja candidatada.</li>
                <li><?php echo  icone_svg('check') ; ?> Usa o seu curriculo em PDF como anexo.</li>
            </ul>
        </div>
    </section>
</div>

<?php /* Fila priorizada. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Proximas vagas da fila</h3>
        <a class="cartao__link" href="<?php echo  site_url('painel/vagas?com_email=1&ocultar_candidatadas=1') ; ?>">
            Ver todas
        </a>
    </div>
    <div class="cartao__corpo">
        <?php if ($fila === []){ ?>
            <div class="estado-vazio estado-vazio--compacto">
                <?php echo  icone_svg('documento') ; ?>
                <p>Nenhuma vaga pronta para envio no momento.</p>
                <p class="texto-suave texto-pequeno">
                    A fila se forma quando a busca encontra vagas com e-mail de contato.
                </p>
            </div>
        <?php } else { ?>
            <ul class="lista-limpa">
                <?php foreach ($fila as $vaga){ ?>
                    <li class="item-lista item-lista--linha">
                        <div>
                            <a class="item-lista__titulo" href="<?php echo  site_url('painel/vagas/' . (int) $vaga['id']) ; ?>">
                                <?php echo  esc(primeiro_valor($vaga['titulo'], 'Vaga sem titulo')) ; ?>
                            </a>
                            <p class="item-lista__meta">
                                <?php echo  esc(primeiro_valor($vaga['empresa'], 'Empresa nao informada')) ; ?>
                                <?php if (! empty($vaga['localizacao'])){ ?>
                                    &middot; <?php echo  esc($vaga['localizacao']) ; ?>
                                <?php } ?>
                            </p>
                        </div>
                        <span class="selo selo--neutro"><?php echo  esc(primeiro_valor($vaga['fonte'], 'Outros')) ; ?></span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
