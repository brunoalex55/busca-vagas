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
 * VIEW: ADMIN - EXECUCAO MANUAL DO DIVULGA VAGAS
 * --------------------------------------------------------------------------
 *
 * O administrador dispara a busca e a candidatura do segundo provedor
 * (Divulga Vagas). O alvo pode ser "todos os usuarios" ou um usuario
 * especifico.
 *
 * A execucao manual NUNCA contorna limite nenhum: ela passa pelos
 * mesmos limites do CRON (limite do bot de cada usuario, limite de
 * e-mail e janela de envio) e tem, alem disso, o proprio limite diario
 * de execucoes do administrador.
 *
 * Todas as variaveis chegam prontas do controller
 * (AdminDivulgaVagas::index).
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Divulga Vagas';

// Percentual usado (para a barra de limite).
$percentualBusca = 0;

if ((int) $limites['buscas']['limite'] > 0) {
    $percentualBusca = min(100, (int) round(
        (int) $limites['buscas']['usadas'] * 100 / (int) $limites['buscas']['limite']
    ));
}

$percentualCandidatura = 0;

if ((int) $limites['candidaturas']['limite'] > 0) {
    $percentualCandidatura = min(100, (int) round(
        (int) $limites['candidaturas']['usadas'] * 100 / (int) $limites['candidaturas']['limite']
    ));
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Divulga Vagas (segundo provedor)</h2>
        <p class="painel__descricao">
            Disparo manual da busca e da candidatura pelo site do Divulga Vagas.
            Os agendadores (CRON 1 e CRON 2) continuam funcionando sozinhos.
        </p>
    </div>
</section>

<?php /* O alvo da execucao (todos ou um usuario). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Alvo da execucao</h3>
    </div>

    <div class="cartao__corpo">
        <form method="post" id="formulario-alvo" class="formulario">
            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="alvo">Quem executa</label>
                    <select class="campo__entrada" id="alvo" name="alvo">
                        <option value="todos">Todos os usuarios ativos</option>
                        <?php foreach ($usuarios as $usuario){ ?>
                            <option value="<?php echo esc((int) $usuario['id']) ; ?>">
                                <?php echo esc($usuario['nome']) ; ?> (<?php echo esc($usuario['email']) ; ?>)
                            </option>
                        <?php } ?>
                    </select>
                    <p class="campo__ajuda">
                        Com um usuario especifico, valem as palavras-chave e os
                        limites desse usuario.
                    </p>
                </div>
            </div>
        </form>
    </div>
</section>

<?php /* Buscar vagas agora (CRON 1). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Buscar vagas agora</h3>
        <span class="selo selo--primario">CRON 1</span>
    </div>

    <div class="cartao__corpo">
        <p class="cartao__descricao">
            Cada usuario ativo tem as suas palavras-chave e cidades. O bot
            consulta o site do Divulga Vagas para cada combinacao.
        </p>

        <form method="post" action="<?php echo esc(site_url('admin/divulgavagas/buscar-manual')) ; ?>"
              class="formulario" data-execucao-divulgavagas>
            <input type="hidden" name="alvo" id="alvo-buscar">
            <input type="hidden" name="usuario_id" id="usuario-buscar">

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"
                        <?php if (! $limites['buscas']['pode']) { echo 'disabled'; } ?>>
                    <?php echo icone_svg('lupa') ; ?> Executar busca agora
                </button>
            </div>
        </form>

        <div class="barra-limite-manual">
            <div class="barra-limite-manual__topo">
                <span>Buscas manuais do admin hoje</span>
                <strong><?php echo esc((int) $limites['buscas']['usadas']) ; ?> / <?php echo esc((int) $limites['buscas']['limite']) ; ?></strong>
            </div>
            <div class="barra-progresso" role="img"
                 aria-label="<?php echo esc($percentualBusca) ; ?>% das buscas usadas">
                <span class="barra-progresso__valor" style="width: <?php echo esc($percentualBusca) ; ?>%"></span>
            </div>
            <?php if (! $limites['buscas']['pode']){ ?>
                <p class="barra-limite-manual__aviso">
                    Limite diario atingido (ou execucao manual desligada). Renova amanha.
                </p>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* Candidatar-se agora (CRON 2). */ ?>
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
            A execucao roda em segundo plano: o resultado aparece no historico.
        </p>

        <form method="post" action="<?php echo esc(site_url('admin/divulgavagas/candidatar-manual')) ; ?>"
              class="formulario" data-execucao-divulgavagas>
            <input type="hidden" name="alvo" id="alvo-candidatar">
            <input type="hidden" name="usuario_id" id="usuario-candidatar">

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"
                        <?php if (! $limites['candidaturas']['pode']) { echo 'disabled'; } ?>>
                    <?php echo icone_svg('documento') ; ?> Candidatar em todas as vagas pendentes
                </button>
            </div>
        </form>

        <div class="barra-limite-manual">
            <div class="barra-limite-manual__topo">
                <span>Candidaturas manuais do admin hoje</span>
                <strong><?php echo esc((int) $limites['candidaturas']['usadas']) ; ?> / <?php echo esc((int) $limites['candidaturas']['limite']) ; ?></strong>
            </div>
            <div class="barra-progresso" role="img"
                 aria-label="<?php echo esc($percentualCandidatura) ; ?>% das candidaturas usadas">
                <span class="barra-progresso__valor" style="width: <?php echo esc($percentualCandidatura) ; ?>%"></span>
            </div>
            <?php if (! $limites['candidaturas']['pode']){ ?>
                <p class="barra-limite-manual__aviso">
                    Limite diario atingido (ou execucao manual desligada). Renova amanha.
                </p>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* A situacao em todos os usuarios. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Situacao das candidaturas (todos os usuarios)</h3>
        <a class="cartao__link" href="<?php echo esc(site_url('admin/divulgavagas/historico')) ; ?>">Ver o historico</a>
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
    </div>
</section>

<?php /* Os limites globais. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Limites das execucoes manuais</h3>
    </div>

    <div class="cartao__corpo">
        <form method="post" action="<?php echo esc(site_url('admin/divulgavagas/limites')) ; ?>" class="formulario">
            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="admin_max_buscas_manuais_dia">Buscas por dia</label>
                    <input class="campo__entrada" type="number" min="0" max="1000"
                           id="admin_max_buscas_manuais_dia" name="admin_max_buscas_manuais_dia"
                           value="<?php echo esc(inteiro_ou($configuracao, 'admin_max_buscas_manuais_dia', 10)) ; ?>">
                    <p class="campo__ajuda">Zero desliga a execucao manual.</p>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="admin_max_candidaturas_manuais_dia">Candidaturas por dia</label>
                    <input class="campo__entrada" type="number" min="0" max="1000"
                           id="admin_max_candidaturas_manuais_dia" name="admin_max_candidaturas_manuais_dia"
                           value="<?php echo esc(inteiro_ou($configuracao, 'admin_max_candidaturas_manuais_dia', 20)) ; ?>">
                    <p class="campo__ajuda">Zero desliga a execucao manual.</p>
                </div>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--secundario">
                    <?php echo icone_svg('escudo') ; ?> Salvar limites
                </button>
            </div>
        </form>
    </div>
</section>

<?php /* As ultimas execucoes. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Ultimas execucoes</h3>
        <a class="cartao__link" href="<?php echo esc(site_url('admin/divulgavagas/historico')) ; ?>">Ver tudo</a>
    </div>

    <div class="cartao__corpo historico-execucoes">
        <?php if ($execucoes === []){ ?>
            <p class="texto-suave">Nenhuma execucao ainda.</p>
        <?php } else { ?>
            <ul class="lista-limpa">
                <?php foreach ($execucoes as $execucao){ ?>
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
                                | <?php echo esc(texto_ou($execucao, 'origem')) ; ?>
                                | <?php echo esc(texto_ou($execucao, 'status')) ; ?>
                            </p>
                        </div>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
