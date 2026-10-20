<?php

/**
 * --------------------------------------------------------------------------
 * TELA: EXECUCAO MANUAL DO DIVULGA VAGAS (PAINEL DO USUARIO)
 * --------------------------------------------------------------------------
 *
 * O usuario pode forcar a busca de vagas e a candidatura pelo site do
 * Divulga Vagas, sem esperar o agendador.
 *
 * A execucao manual NUNCA contorna limite nenhum: ela passa pelos
 * mesmos limites do CRON (limite do bot, limite de e-mail, janela de
 * envio) e tem, alem disso, o proprio limite diario de execucoes.
 *
 * Todas as variaveis chegam prontas do controller
 * (PainelDivulgaVagas::index).
 */

// A view usa o layout do painel (menu, toasts e scripts).
$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Divulga Vagas';

$percentualBusca = 0;

if ((int) $limiteBusca['limite'] > 0) {
    $percentualBusca = min(100, (int) round((int) $limiteBusca['usadas'] * 100 / (int) $limiteBusca['limite']));
}

$percentualCandidatura = 0;

if ((int) $limiteCandidatura['limite'] > 0) {
    $percentualCandidatura = min(100, (int) round((int) $limiteCandidatura['usadas'] * 100 / (int) $limiteCandidatura['limite']));
}

// Abre a secao de conteudo do layout do painel.
echo $this->section('conteudo');
?>
<div class="card-execucao-manual">

    <!-- ================= BUSCAR VAGAS AGORA ================= -->
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Buscar vagas agora</h3>
            <span class="selo selo--primario">CRON 1</span>
        </div>

        <div class="cartao__corpo">
            <p class="cartao__descricao">
                A busca manual processa <strong>uma combinacao</strong> (termo + cidade)
                por vez. Ela respeita os mesmos limites do agendador.
            </p>

            <form method="post" action="<?php echo esc(site_url('painel/divulgavagas/buscar-manual')) ; ?>"
                  class="formulario" data-execucao-divulgavagas>
                <div class="formulario__grade">
                    <div class="campo">
                        <label class="campo__rotulo" for="palavra_chave_id">Palavra-chave</label>
                        <select class="campo__entrada" id="palavra_chave_id" name="palavra_chave_id" required>
                            <?php foreach ($palavras as $palavra){ ?>
                                <option value="<?php echo esc((int) $palavra['id']) ; ?>">
                                    <?php echo esc($palavra['termo']) ; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label class="campo__rotulo" for="cidade">Cidade</label>
                        <input class="campo__entrada" type="text" id="cidade" name="cidade"
                               list="cidades-divulgavagas"
                               placeholder="Cidade da busca" required>
                        <datalist id="cidades-divulgavagas">
                            <?php foreach ($palavras as $palavra){ ?>
                                <?php foreach ($palavra['cidades'] as $cidade){ ?>
                                    <option value="<?php echo esc($cidade) ; ?>"></option>
                                <?php } ?>
                            <?php } ?>
                        </datalist>
                    </div>
                </div>

                <div class="formulario__acoes">
                    <button type="submit" class="botao botao--primario"
                            <?php
                        // Sem limite disponivel, o botao fica desabilitado.
                        if (! $limiteBusca['pode']) {
                            echo 'disabled';
                        }
                        ?>>
                        <?php echo icone_svg('lupa') ; ?> Executar busca agora
                    </button>
                </div>
            </form>

            <!-- Barra do limite diario. -->
            <div class="barra-limite-manual">
                <div class="barra-limite-manual__topo">
                    <span>Buscas manuais hoje</span>
                    <strong><?php echo esc((int) $limiteBusca['usadas']) ; ?> / <?php echo esc((int) $limiteBusca['limite']) ; ?></strong>
                </div>
                <div class="barra-progresso" role="img"
                     aria-label="<?php echo esc($percentualBusca) ; ?>% das buscas manuais usadas">
                    <span class="barra-progresso__valor" style="width: <?php echo esc($percentualBusca) ; ?>%"></span>
                </div>
                <?php if (! $limiteBusca['pode']){ ?>
                    <p class="barra-limite-manual__aviso">
                        Limite diario de buscas manuais atingido. Renova amanha.
                    </p>
                <?php } ?>
            </div>
        </div>
    </section>

    <!-- ================= CANDIDATAR-SE AGORA ================= -->
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Candidatar-se agora</h3>
            <span class="selo selo--primario">CRON 2</span>
        </div>

        <div class="cartao__corpo">
            <p class="cartao__descricao">
                O bot se candidata pelo site, <strong>uma vaga de cada vez, ate a fila
                acabar</strong> (nao precisa clicar de novo para a proxima). Quando a
                vaga tem e-mail de contacto, o e-mail tambem e enviado.
            </p>
            <p class="texto-suave texto-pequeno">
                A execucao roda em segundo plano: o resultado aparece no historico e
                nos selos de cada vaga.
            </p>

            <form method="post" action="<?php echo esc(site_url('painel/divulgavagas/candidatar-manual')) ; ?>"
                  class="formulario" data-execucao-divulgavagas>
                <div class="formulario__acoes">
                    <button type="submit" class="botao botao--primario"
                            <?php
                        // Sem limite disponivel, o botao fica desabilitado.
                        if (! $limiteCandidatura['pode']) {
                            echo 'disabled';
                        }
                        ?>>
                        <?php echo icone_svg('documento') ; ?> Candidatar em todas as vagas pendentes
                    </button>
                </div>
            </form>

            <div class="barra-limite-manual">
                <div class="barra-limite-manual__topo">
                    <span>Candidaturas manuais hoje</span>
                    <strong><?php echo esc((int) $limiteCandidatura['usadas']) ; ?> / <?php echo esc((int) $limiteCandidatura['limite']) ; ?></strong>
                </div>
                <div class="barra-progresso" role="img"
                     aria-label="<?php echo esc($percentualCandidatura) ; ?>% das candidaturas manuais usadas">
                    <span class="barra-progresso__valor" style="width: <?php echo esc($percentualCandidatura) ; ?>%"></span>
                </div>
                <?php if (! $limiteCandidatura['pode']){ ?>
                    <p class="barra-limite-manual__aviso">
                        Limite diario de candidaturas manuais atingido. Renova amanha.
                    </p>
                <?php } ?>
            </div>

            <?php if (! $limiteEmail['pode']){ ?>
                <div class="alerta alerta--info">
                    <?php echo icone_svg('info') ; ?>
                    <p>
                        E-mail nao sera enviado agora: <?php echo esc((string) $limiteEmail['mensagem']) ; ?>.
                        A candidatura pelo site sera feita normalmente.
                    </p>
                </div>
            <?php } ?>
        </div>
    </section>

    <!-- ================= SITUACAO DAS CANDIDATURAS ================= -->
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Suas candidaturas no site</h3>
            <a class="cartao__link" href="<?php echo esc(site_url('painel/vagas?fonte=Divulga+Vagas')) ; ?>">Ver as vagas</a>
        </div>

        <div class="cartao__corpo">
            <div class="grade-metricas">
                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Aguardando o bot</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'pendente', 0)) ; ?></strong>
                </article>

                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Enviadas pelo site</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'enviada', 0)) ; ?></strong>
                </article>

                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Site + e-mail</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'enviada_email', 0)) ; ?></strong>
                </article>

                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Aguardando e-mail</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'pendente_email', 0)) ; ?></strong>
                </article>

                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Com erro</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'erro', 0)) ; ?></strong>
                </article>

                <article class="cartao cartao-metrica">
                    <span class="cartao-metrica__rotulo">Bloqueio</span>
                    <strong class="cartao-metrica__valor"><?php echo esc(inteiro_ou($situacoes, 'cloudflare', 0)) ; ?></strong>
                </article>
            </div>

            <?php if ($ultima !== null){ ?>
                <p class="texto-suave texto-pequeno">
                    Ultima execucao: <?php echo esc(formatar_data_hora(texto_ou($ultima, 'iniciado_em'), false)) ; ?>
                    (<?php echo esc(texto_ou($ultima, 'status')) ; ?>).
                </p>
            <?php } ?>
        </div>
    </section>

    <!-- ================= HISTORICO ================= -->
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Historico</h3>
            <a class="cartao__link" href="<?php echo esc(site_url('painel/divulgavagas/historico')) ; ?>">Ver tudo</a>
        </div>

        <div class="cartao__corpo historico-execucoes">
            <?php if ($historico === []){ ?>
                <p class="texto-suave">Nenhuma execucao ainda.</p>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($historico as $execucao){ ?>
                        <?php
                        // Rotulo do tipo da execucao.
                        $rotuloExecucao = 'Candidatura';

                        if (texto_ou($execucao, 'tipo') === 'buscar') {
                            $rotuloExecucao = 'Busca';
                        }
                        ?>
                        <li class="item-lista item-lista--linha">
                            <div class="item-lista__texto">
                                <span class="item-lista__titulo"><?php echo esc($rotuloExecucao) ; ?></span>
                                <p class="item-lista__meta">
                                    <?php echo esc(formatar_data_hora(texto_ou($execucao, 'iniciado_em'), false)) ; ?>
                                    | <?php echo esc(texto_ou($execucao, 'status')) ; ?>
                                </p>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>
</div>

<?php
// Fecha a secao de conteudo.
echo $this->endSection();
?>
