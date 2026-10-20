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
 * VIEW: PAINEL - VISAO GERAL
 * --------------------------------------------------------------------------
 *
 * Dashboard real do usuario, montado com dados do banco:
 *   - cards de vagas, candidaturas, cota de consultas e envios do dia;
 *   - card de execucoes manuais (Bloco E);
 *   - checklist de prontidao para o envio automatico;
 *   - ultimas vagas, envios e consultas.
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Visao geral';

// Primeiro nome para a saudacao.
$nomeSaudacao = $nomeUsuario;

if ($nomeSaudacao === null || $nomeSaudacao === '') {
    $nomeSaudacao = 'Usuario';
}

$primeiroNome = explode(' ', trim(texto_ou($usuario, 'nome', $nomeSaudacao)))[0];

// Percentual do limite diario de envios.
$percentualEnvios = 0;

if ($limiteDiario > 0) {
    $percentualEnvios = min(100, (int) round($enviosHoje * 100 / $limiteDiario));
}

// Dados das execucoes manuais, calculados pelo controller.
$limiteManuais    = (int) $execucoesManuais['limite'];
$usadasManuais    = (int) $execucoesManuais['usadas'];
$restantesManuais = (int) $execucoesManuais['restantes'];

// O admin nao tem limite, entao os botoes dele ficam sempre liberados.
$manuaisDisponiveis = $execucoesManuais['pode'];

if ($ehAdmin) {
    $manuaisDisponiveis = true;
}

// Execucao manual desligada pelo admin (limite zero).
$manuaisDesligadas = $execucoesManuais['motivo'] === 'desabilitado';

// Percentual do limite de execucoes manuais (so faz sentido para o usuario).
$percentualManuais = 0;

if (! $ehAdmin && $limiteManuais > 0) {
    $percentualManuais = (int) round($usadasManuais * 100 / $limiteManuais);

    if ($percentualManuais > 100) {
        $percentualManuais = 100;
    }
}

// Concordancia do texto do card.
$rotuloExecucoes = 'execucoes manuais';

if ($usadasManuais === 1) {
    $rotuloExecucoes = 'execucao manual';
}
?>

<?php echo  $this->section('conteudo') ; ?>

<?php /* Cabecalho da pagina com acoes rapidas. */ ?>
<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Ola, <?php echo  esc($primeiroNome) ; ?></h2>
        <p class="painel__descricao">Este e o resumo da sua busca de vagas.</p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('painel/vagas') ; ?>">
            <?php echo  icone_svg('documento') ; ?> Ver vagas
        </a>
        <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('painel/palavras-chave/nova') ; ?>">
            <?php echo  icone_svg('lupa') ; ?> Nova palavra-chave
        </a>
    </div>
</section>

<?php /* Cards de metrica. */ ?>
<div class="grade-metricas">

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('documento') ; ?></span>
        <span class="cartao-metrica__rotulo">Vagas encontradas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format($totalVagas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  escolher($totalVagas > 0, 'Total acumulado na sua conta.', 'Nenhuma vaga ainda.') ; ?>
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--verde"><?php echo  icone_svg('check') ; ?></span>
        <span class="cartao-metrica__rotulo">Vagas candidatadas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format($totalCandidaturasFeitas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            Vagas em que voce ja se candidatou (pelo site ou por e-mail).
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('email') ; ?></span>
        <span class="cartao-metrica__rotulo">Candidaturas por e-mail</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format($totalCandidaturas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">E-mails de candidatura enviados e confirmados.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--roxo"><?php echo  icone_svg('alvo') ; ?></span>
        <span class="cartao-metrica__rotulo">Cota de consultas</span>
        <strong class="cartao-metrica__valor">
            <?php echo  number_format($cota['usada'], 0, ',', '.') ; ?>
            <small class="cartao-metrica__fracao">/ <?php echo  number_format($cota['cota'], 0, ',', '.') ; ?></small>
        </strong>

        <span class="barra-progresso" role="img"
              aria-label="<?php echo  esc($cota['percentual']) ; ?>% da cota utilizada">
            <span class="barra-progresso__valor" style="width: <?php echo  (int) $cota['percentual'] ; ?>%"></span>
        </span>

        <span class="cartao-metrica__detalhe">
            <?php if ($cota['cota'] > 0){ ?>
                <?php echo  number_format($cota['restante'], 0, ',', '.') ; ?> consulta(s) restante(s) neste mes.
            <?php } else { ?>
                Aguardando liberacao de consultas pelo administrador.
            <?php } ?>
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('raio') ; ?></span>
        <span class="cartao-metrica__rotulo">Envios hoje</span>
        <strong class="cartao-metrica__valor">
            <?php echo  number_format($enviosHoje, 0, ',', '.') ; ?>
            <small class="cartao-metrica__fracao">/ <?php echo  number_format($limiteDiario, 0, ',', '.') ; ?></small>
        </strong>

        <span class="barra-progresso" role="img"
              aria-label="<?php echo  esc($percentualEnvios) ; ?>% do limite diario utilizado">
            <span class="barra-progresso__valor" style="width: <?php echo  (int) $percentualEnvios ; ?>%"></span>
        </span>

        <span class="cartao-metrica__detalhe">
            <?php echo escolher($envioAutomatico, 'Envio automatico ligado.', 'Envio automatico desligado.'); ?>
        </span>
    </article>
</div>

<?php /* O plano do usuario e o quanto ele ja usou. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Meu plano</h3>
        <span class="selo selo--primario"><?php echo esc($plano['nome']) ; ?></span>
    </div>

    <div class="cartao__corpo">
        <div class="grade-metricas">
            <?php
            // O limite de palavras-chave (NULL = ilimitado).
            $limitePalavras = $limitesPlano['max_palavras_chave']['valor'];
            $usoPalavras = (int) $usoPlano['palavras_chave'];
            $percentualPalavras = 0;

            if ($limitePalavras !== null && (int) $limitePalavras > 0) {
                $percentualPalavras = min(100, (int) round($usoPalavras * 100 / (int) $limitePalavras));
            }

            // O limite de envios por dia.
            $limiteEnvios = (int) $limitesPlano['limite_envios_dia']['valor'];
            $usoEnvios = (int) $usoPlano['envios_hoje'];
            $percentualEnviosPlano = 0;

            if ($limiteEnvios > 0) {
                $percentualEnviosPlano = min(100, (int) round($usoEnvios * 100 / $limiteEnvios));
            }

            // O limite de cidades por termo (NULL = ilimitado).
            $limiteCidades = $limitesPlano['max_cidades_por_termo']['valor'];
            ?>

            <article class="cartao cartao-metrica">
                <span class="cartao-metrica__rotulo">Palavras-chave</span>
                <strong class="cartao-metrica__valor">
                    <?php echo esc($usoPalavras) ; ?>
                    <small class="cartao-metrica__fracao">
                        / <?php
                        if ($limitePalavras === null) {
                            echo esc('Ilimitadas') ;
                        } else {
                            echo esc((int) $limitePalavras) ;
                        }
                        ?>
                    </small>
                </strong>

                <?php if ($limitePalavras !== null){ ?>
                    <span class="barra-progresso" role="img"
                          aria-label="<?php echo esc($percentualPalavras) ; ?>% das palavras-chave usadas">
                        <span class="barra-progresso__valor" style="width: <?php echo esc($percentualPalavras) ; ?>%"></span>
                    </span>
                <?php } ?>

                <span class="cartao-metrica__detalhe">
                    <?php echo esc($limitesPlano['max_palavras_chave']['rotulo']) ; ?>
                </span>
            </article>

            <article class="cartao cartao-metrica">
                <span class="cartao-metrica__rotulo">Cidades por termo</span>
                <strong class="cartao-metrica__valor">
                    <?php
                    if ($limiteCidades === null) {
                        echo esc('Ilimitadas') ;
                    } else {
                        echo esc((int) $limiteCidades) ;
                    }
                    ?>
                </strong>
                <span class="cartao-metrica__detalhe">
                    <?php echo esc($limitesPlano['max_cidades_por_termo']['rotulo']) ; ?>
                </span>
            </article>

            <article class="cartao cartao-metrica">
                <span class="cartao-metrica__rotulo">Envios por dia</span>
                <strong class="cartao-metrica__valor">
                    <?php echo esc($usoEnvios) ; ?>
                    <small class="cartao-metrica__fracao">/ <?php echo esc($limiteEnvios) ; ?></small>
                </strong>

                <span class="barra-progresso" role="img"
                      aria-label="<?php echo esc($percentualEnviosPlano) ; ?>% dos envios de hoje">
                    <span class="barra-progresso__valor" style="width: <?php echo esc($percentualEnviosPlano) ; ?>%"></span>
                </span>

                <span class="cartao-metrica__detalhe">
                    <?php echo esc($limitesPlano['limite_envios_dia']['rotulo']) ; ?>
                </span>
            </article>
        </div>

        <?php /* O aviso do limite sobrescrito pelo admin. */ ?>
        <?php if ($limitesPlano['limite_envios_dia']['origem'] === 'manual'){ ?>
            <div class="alerta alerta--atencao">
                <?php echo icone_svg('info'); ?>
                <p>
                    <strong>Limite personalizado:</strong> o administrador defini um limite
                    manual para a sua conta, que vale mais que o do plano.
                </p>
            </div>
        <?php } ?>

        <?php /* O pedido de plano em andamento (o aviso fica no cartao). */ ?>
        <?php
        // O pedido que esta na fila do administrador (null quando nao ha).
        $pedidoEmAndamento = null;

        if (isset($pedidoPendente) && is_array($pedidoPendente)) {
            $pedidoEmAndamento = $pedidoPendente;
        }
        ?>

        <?php if ($pedidoEmAndamento !== null){ ?>
            <div class="alerta alerta--info">
                <?php echo icone_svg('info'); ?>
                <p>
                    <strong>Pedido em analise:</strong> voce pediu o plano
                    <strong><?php echo esc(texto_ou($pedidoEmAndamento, 'plano_nome', 'selecionado')) ; ?></strong>.
                    O administrador vai analisar e liberar o plano em breve.
                </p>

                <form method="post" action="<?php echo site_url('painel/plano/cancelar') ; ?>">
                    <button class="botao botao--fantasma botao--pequeno" type="submit">
                        Cancelar pedido
                    </button>
                </form>
            </div>
        <?php } ?>

        <div class="linha-acoes">
            <button class="botao botao--primario botao--pequeno" type="button"
                    data-modal-abrir="modal-plano">
                <?php echo icone_svg('seta'); ?> Fazer upgrade de plano
            </button>

            <a class="botao botao--secundario botao--pequeno" href="<?php echo site_url('precos') ; ?>">
                <?php echo icone_svg('site'); ?> Ver os planos
            </a>
        </div>
    </div>
</section>

<?php /* Execucoes manuais do usuario (Bloco E). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Execucoes manuais</h3>

        <?php if ($ehAdmin) { ?>
            <span class="selo selo--admin">Sem limite</span>
        <?php } elseif ($manuaisDesligadas) { ?>
            <span class="selo selo--erro">Desabilitado</span>
        <?php } elseif ($manuaisDisponiveis) { ?>
            <span class="selo selo--sucesso">Disponivel</span>
        <?php } else { ?>
            <span class="selo selo--aviso">Limite atingido</span>
        <?php } ?>
    </div>

    <div class="cartao__corpo">
        <?php if ($ehAdmin) { ?>
            <p class="texto-suave texto-pequeno">
                O administrador nao tem limite de execucoes manuais.
            </p>
        <?php } elseif ($manuaisDesligadas) { ?>
            <div class="alerta alerta--info">
                <?php echo icone_svg('info'); ?>
                <p>
                    Execucoes manuais desabilitadas pelo admin.
                    O sistema executa automaticamente via cron.
                </p>
            </div>
        <?php } else { ?>
            <div class="grade grade--painel">
                <div class="bloco-resumo">
                    <span class="bloco-resumo__rotulo">Execucoes manuais hoje</span>
                    <span class="bloco-resumo__valor">
                        <?php echo $usadasManuais; ?> / <?php echo $limiteManuais; ?>
                    </span>
                </div>

                <div class="bloco-resumo">
                    <span class="bloco-resumo__rotulo">Restam</span>
                    <span class="bloco-resumo__valor"><?php echo $restantesManuais; ?></span>
                </div>
            </div>

            <span class="barra-progresso" role="img"
                  aria-label="<?php echo $percentualManuais; ?>% do limite diario de execucoes manuais utilizado">
                <span class="barra-progresso__valor" style="width: <?php echo $percentualManuais; ?>%"></span>
            </span>

            <p class="texto-suave texto-pequeno">
                Voce ja fez <?php echo $usadasManuais; ?> <?php echo $rotuloExecucoes; ?> hoje.
                <?php if ($restantesManuais === 0) { ?>
                    O limite de hoje foi atingido.
                <?php } else { ?>
                    Restam <?php echo $restantesManuais; ?> para hoje.
                <?php } ?>
            </p>
        <?php } ?>

        <div class="linha-acoes">
            <form method="post" action="<?php echo site_url('painel/executar-vagas'); ?>"
                  data-confirmar="Executar a busca de vagas agora? Isso consome uma das suas execucoes manuais do dia.">
                <button type="submit" class="botao botao--secundario botao--pequeno"
                        <?php if (! $manuaisDisponiveis) { echo 'disabled'; } ?>>
                    <?php echo icone_svg('lupa'); ?> Executar busca de vagas agora
                </button>
            </form>

            <form method="post" action="<?php echo site_url('painel/executar-emails'); ?>"
                  data-confirmar="Executar o envio de e-mails agora? Isso consome uma das suas execucoes manuais do dia.">
                <button type="submit" class="botao botao--fantasma botao--pequeno"
                        <?php if (! $manuaisDisponiveis) { echo 'disabled'; } ?>>
                    <?php echo icone_svg('email'); ?> Executar envio de e-mails agora
                </button>
            </form>
        </div>

        <?php if (! $ehAdmin) { ?>
            <p class="texto-suave texto-pequeno">
                As execucoes manuais sao limitadas pelo admin para evitar sobrecarga.
                O sistema tambem executa automaticamente via cron.
            </p>
        <?php } ?>
    </div>
</section>

<?php /* As candidaturas realizadas (todos os provedores). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Candidaturas realizadas</h3>
        <a class="cartao__link" href="<?php echo  site_url('painel/divulgavagas/historico') ; ?>">Ver o historico</a>
    </div>

    <div class="cartao__corpo">
        <?php if (empty($candidaturasFeitas)){ ?>
            <p class="texto-suave">
                Voce ainda nao se candidatou a nenhuma vaga. Assim que uma candidatura
                for confirmada (pelo site ou por e-mail), ela aparece aqui.
            </p>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Vaga</th>
                            <th scope="col">Empresa</th>
                            <th scope="col">Como foi feita</th>
                            <th scope="col">Confirmacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidaturasFeitas as $candidatura){ ?>
                            <?php
                            // A candidatura pelo site (o bot) e a de e-mail.
                            $peloSite = in_array(texto_ou($candidatura, 'candidatura_divulgavagas_status'), ['enviada', 'enviada_email'], true);
                            $porEmail = (int) texto_ou($candidatura, 'ja_candidatado', 0) === 1;
                            $confirmacao = texto_ou($candidatura, 'candidatura_divulgavagas_confirmado_em', '');

                            if ($confirmacao === '') {
                                $confirmacao = texto_ou($candidatura, 'candidatura_divulgavagas_em', '');
                            }
                            ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?php echo  site_url('painel/vagas/' . (int) $candidatura['id']) ; ?>">
                                        <?php echo  esc(primeiro_valor($candidatura['titulo'], 'Vaga sem titulo')) ; ?>
                                    </a>
                                    <span class="item-lista__meta"><?php echo  esc(primeiro_valor($candidatura['localizacao'], '-')) ; ?></span>
                                </th>
                                <td><?php echo  esc(primeiro_valor($candidatura['empresa'], '-')) ; ?></td>
                                <td>
                                    <?php if ($peloSite){ ?>
                                        <span class="selo selo--divulgavagas-enviada">Pelo site</span>
                                    <?php } ?>
                                    <?php if ($porEmail){ ?>
                                        <span class="selo selo--sucesso">Por e-mail</span>
                                    <?php } ?>
                                    <?php if (! $peloSite && ! $porEmail){ ?>
                                        <span class="selo selo--neutro">-</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($confirmacao !== ''){ ?>
                                        <span class="selo selo--sucesso">Confirmada</span>
                                        <span class="item-lista__meta">
                                            <?php echo  esc(formatar_data_hora($confirmacao, false)) ; ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="texto-suave texto-pequeno">-</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Checklist de prontidao. */ ?>
<?php if ($pendencias > 0){ ?>
    <div class="alerta alerta--atencao">
        <?php echo  icone_svg('info') ; ?>
        <p>
            Sua conta ainda tem <?php echo  (int) $pendencias ; ?> pendencia(s) para o envio automatico funcionar.
            <a href="<?php echo  site_url('painel/configuracoes') ; ?>">Revisar configuracoes</a>.
        </p>
    </div>
<?php } ?>

<?php /* Duas colunas: ultimas vagas e ultimos envios. */ ?>
<div class="grade grade--painel">

    <?php /* Ultimas vagas. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Ultimas vagas</h3>
            <a class="cartao__link" href="<?php echo  site_url('painel/vagas') ; ?>">Ver todas</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($vagasRecentes === []){ ?>
                <div class="estado-vazio estado-vazio--compacto">
                    <?php echo  icone_svg('documento') ; ?>
                    <p>Nenhuma vaga encontrada ainda.</p>
                    <p class="texto-suave texto-pequeno">
                        Cadastre palavras-chave para que o robo comece a buscar.
                    </p>
                </div>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($vagasRecentes as $vaga){ ?>
                        <li class="item-lista">
                            <a class="item-lista__titulo"
                               href="<?php echo  site_url('painel/vagas/' . (int) $vaga['id']) ; ?>">
                                <?php echo  esc(primeiro_valor($vaga['titulo'], 'Vaga sem titulo')) ; ?>
                            </a>
                            <p class="item-lista__meta">
                                <?php echo  esc(primeiro_valor($vaga['empresa'], 'Empresa nao informada')) ; ?>
                                <?php if (! empty($vaga['localizacao'])){ ?>
                                    &middot; <?php echo  esc($vaga['localizacao']) ; ?>
                                <?php } ?>
                            </p>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <?php /* Ultimos envios. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Ultimos envios</h3>
            <a class="cartao__link" href="<?php echo  site_url('painel/historico?aba=envios') ; ?>">Ver historico</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($enviosRecentes === []){ ?>
                <div class="estado-vazio estado-vazio--compacto">
                    <?php echo  icone_svg('email') ; ?>
                    <p>Nenhuma candidatura enviada ainda.</p>
                    <p class="texto-suave texto-pequeno">
                        Configure o e-mail e o curriculo para comecar.
                    </p>
                </div>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($enviosRecentes as $envio){ ?>
                        <li class="item-lista">
                            <span class="item-lista__titulo"><?php echo  esc(primeiro_valor($envio['destinatario'], 'Sem destinatario')) ; ?></span>
                            <p class="item-lista__meta">
                                <?php echo  esc(primeiro_valor($envio['assunto'], 'Sem assunto')) ; ?>
                                &middot; <?php echo  esc(formatar_data_hora(valor_ou($envio, 'enviado_em', null))) ; ?>
                            </p>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>
</div>

<?php /* Ultimas consultas. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Ultimas consultas ao provedor</h3>
        <a class="cartao__link" href="<?php echo  site_url('painel/historico') ; ?>">Ver historico</a>
    </div>
    <div class="cartao__corpo">
        <?php if ($consultasRecentes === []){ ?>
            <div class="estado-vazio estado-vazio--compacto">
                <?php echo  icone_svg('lupa') ; ?>
                <p>Nenhuma consulta registrada ainda.</p>
            </div>
        <?php } else { ?>
            <ul class="lista-limpa">
                <?php foreach ($consultasRecentes as $consulta){ ?>
                    <li class="item-lista">
                        <span class="item-lista__titulo">
                            <?php echo  esc(primeiro_valor($consulta['termo'], 'Consulta')) ; ?>
                            <?php if (! empty($consulta['cidade_consultada'])){ ?>
                                <span class="texto-suave">em <?php echo  esc($consulta['cidade_consultada']) ; ?></span>
                            <?php } ?>
                        </span>
                        <p class="item-lista__meta">
                            <?php echo  inteiro_ou($consulta, 'total_vagas_retornadas', 0) ; ?> vaga(s)
                            &middot; <?php echo  esc(formatar_data_hora(valor_ou($consulta, 'consumido_em', null))) ; ?>
                        </p>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</section>


<?php /* O modal do pedido de plano (upgrade). */ ?>
<div class="modal" id="modal-plano" aria-hidden="true" role="dialog"
     aria-modal="true" aria-labelledby="modal-plano-titulo">

    <div class="modal__janela">
        <header class="modal__cabecalho">
            <div>
                <h3 class="modal__titulo" id="modal-plano-titulo">Fazer upgrade de plano</h3>
                <p class="modal__subtitulo">
                    Escolha o plano desejado. O pedido vai para o administrador,
                    que confirma o pagamento e libera o plano na sua conta.
                </p>
            </div>

            <button class="modal__fechar" type="button" data-modal-fechar="modal-plano"
                    aria-label="Fechar">
                <?php echo icone_svg('fechar'); ?>
            </button>
        </header>

        <div class="modal__corpo">
            <?php
            // Os planos que o usuario pode pedir.
            $listaPlanos = [];

            if (isset($planosDisponiveis) && is_array($planosDisponiveis)) {
                $listaPlanos = $planosDisponiveis;
            }

            // O id do plano atual (para ja vir selecionado).
            $planoAtualId = (int) valor_ou($plano, 'id', 0);
            ?>

            <form class="formulario" method="post"
                  action="<?php echo site_url('painel/plano/solicitar') ; ?>">

                <div class="campo">
                    <label class="campo__rotulo" for="plano_id">Plano desejado</label>
                    <select class="campo__entrada" id="plano_id" name="plano_id">
                        <?php foreach ($listaPlanos as $planoDisponivel) { ?>
                            <?php
                            // O id do plano da lista.
                            $idDisponivel = (int) valor_ou($planoDisponivel, 'id', 0);

                            // O preco (pode ser vazio).
                            $preco = valor_ou($planoDisponivel, 'preco_mensal', null);
                            $precoTexto = 'sob consulta';

                            if ($preco !== null) {
                                $precoTexto = 'R$ ' . number_format((float) $preco, 2, ',', '.');
                            }
                            ?>
                            <option value="<?php echo esc($idDisponivel) ; ?>"
                                    <?php if ($idDisponivel === $planoAtualId){ ?>selected<?php } ?>>
                                <?php echo esc(texto_ou($planoDisponivel, 'nome', 'Plano')) ; ?> - <?php echo esc($precoTexto) ; ?>/mes
                            </option>
                        <?php } ?>
                    </select>
                    <span class="campo__ajuda">
                        O plano so passa a valer depois que o administrador
                        confirmar o pagamento.
                    </span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="plano_mensagem">Observacao (opcional)</label>
                    <textarea class="campo__entrada campo__entrada--area"
                              id="plano_mensagem" name="mensagem" rows="3"
                              maxlength="1000"
                              placeholder="Ex.: ja fiz o PIX de hoje..."></textarea>
                </div>

                <div class="modal__rodape">
                    <button class="botao botao--secundario" type="button" data-modal-fechar="modal-plano">
                        Cancelar
                    </button>

                    <button class="botao botao--primario" type="submit">
                        <?php echo icone_svg('seta'); ?> Pedir este plano
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php echo  $this->endSection() ; ?>
