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
 * VIEW: ADMIN - FICHA DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Dados da conta, cota de consultas, configuracao de envio, movimentacao
 * recente e as acoes administrativas (aprovar, bloquear, limites, perfil).
 */

$this->extend('layouts/admin');

// Dados da conta.
$id       = (int) $usuario['id'];
$status   = (string) $usuario['status'];
$eAdmin   = $usuario['perfil'] === 'admin';
$ligado   = (int) $usuario['envio_automatico'] === 1;

// Titulo exibido no topo.
$tituloPagina = 'Usuario: ' . $usuario['nome'];

// Selo de situacao.
[$classeSelo, $rotuloSelo] = match ($status) {
    'ativo'     => ['selo--sucesso', 'Ativo'],
    'bloqueado' => ['selo--erro', 'Bloqueado'],
    default     => ['selo--aviso', 'Pendente'],
};

// Rota base das acoes.
$base = site_url('admin/usuarios/' . $id);

// Execucoes manuais do usuario (dados enviados pelo controller).
$limiteManuais    = (int) $execucoesManuais['limite'];
$usadasManuais    = (int) $execucoesManuais['usadas'];
$restantesManuais = (int) $execucoesManuais['restantes'];

// Situacao do limite.
$manuaisDisponiveis   = (bool) $execucoesManuais['pode'];
$manuaisDesabilitadas = $execucoesManuais['motivo'] === 'desabilitado';

// Administradores nao tem limite de execucoes manuais.
if ($eAdmin) {
    $manuaisDisponiveis   = true;
    $manuaisDesabilitadas = false;
}

// Percentual do limite consumido.
$percentualManuais = 0;

if (! $eAdmin && $limiteManuais > 0) {
    $percentualManuais = (int) round($usadasManuais * 100 / $limiteManuais);

    if ($percentualManuais > 100) {
        $percentualManuais = 100;
    }
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">
            <?php echo  esc($usuario['nome']) ; ?>
            <span class="selo <?php echo  $classeSelo ; ?>"><?php echo  $rotuloSelo ; ?></span>
            <?php if ($eAdmin){ ?><span class="selo selo--admin">Admin</span><?php } ?>
        </h2>
        <p class="painel__descricao"><?php echo  esc($usuario['email']) ; ?></p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/usuarios') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Voltar
        </a>

        <?php /* Acoes de situacao (sempre POST). */ ?>
        <?php if ($status !== 'ativo'){ ?>
            <form method="post" action="<?php echo  esc($base . '/aprovar') ; ?>">
                <button type="submit" class="botao botao--primario botao--pequeno">
                    <?php echo  icone_svg('check') ; ?> Aprovar
                </button>
            </form>
        <?php } else { ?>
            <form method="post" action="<?php echo  esc($base . '/bloquear') ; ?>"
                  data-confirmar="Bloquear esta conta? O usuario perdera o acesso.">
                <button type="submit" class="botao botao--fantasma botao--perigo botao--pequeno">
                    <?php echo  icone_svg('cadeado') ; ?> Bloquear
                </button>
            </form>
        <?php } ?>

        <?php if ($status === 'bloqueado'){ ?>
            <form method="post" action="<?php echo  esc($base . '/reativar') ; ?>">
                <button type="submit" class="botao botao--secundario botao--pequeno">
                    <?php echo  icone_svg('raio') ; ?> Reativar
                </button>
            </form>
        <?php } ?>

        <?php /* O bloqueio por falta de pagamento (a conta para e o
               usuario volta a ver os dados do PIX para renovar). */ ?>
        <?php if ($status === 'ativo'){ ?>
            <form method="post" action="<?php echo  esc($base . '/bloquear-pagamento') ; ?>"
                  data-confirmar="Bloquear esta conta por falta de pagamento? O usuario vera os dados do PIX para renovar.">
                <input type="hidden" name="motivo" value="Falta de pagamento da mensalidade.">
                <button type="submit" class="botao botao--perigo botao--pequeno">
                    <?php echo  icone_svg('escudo') ; ?> Bloquear por pagamento
                </button>
            </form>
        <?php } ?>
    </div>
</section>

<?php /* O aviso do bloqueio por pagamento (com os dados para cobrar). */ ?>
<?php if ((int) valor_ou($usuario, 'bloqueado_por_pagamento', 0) === 1){ ?>
    <div class="alerta alerta--erro">
        <?php echo  icone_svg('escudo') ; ?>
        <p>
            <strong>Conta bloqueada por falta de pagamento.</strong>
            <?php echo  esc(texto_ou($usuario, 'motivo_bloqueio', 'Falta de pagamento da mensalidade.')) ; ?>
            <br>
            Chave PIX: <code><?php echo  esc(texto_ou($pagamento, 'chave_pix', 'nao configurada')) ; ?></code>
            &middot; Valor: R$ <?php echo  esc(number_format((float) valor_ou($pagamento, 'valor_mensal', 0), 2, ',', '.')) ; ?>/mes
        </p>

        <form method="post" action="<?php echo  esc($base . '/cobrar-pagamento') ; ?>">
            <button type="submit" class="botao botao--primario botao--pequeno">
                <?php echo  icone_svg('check') ; ?> Pagamento confirmado
            </button>
        </form>
    </div>
<?php } ?>

<?php /* Cards de metrica. */ ?>
<div class="grade-metricas">
    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--roxo"><?php echo  icone_svg('alvo') ; ?></span>
        <span class="cartao-metrica__rotulo">Cota do mes</span>
        <strong class="cartao-metrica__valor">
            <?php echo  number_format((int) $cota['usada'], 0, ',', '.') ; ?>
            <small class="cartao-metrica__fracao">/ <?php echo  number_format((int) $cota['cota'], 0, ',', '.') ; ?></small>
        </strong>
        <span class="barra-progresso" role="img" aria-label="<?php echo  (int) $cota['percentual'] ; ?>% da cota utilizada">
            <span class="barra-progresso__valor" style="width: <?php echo  (int) $cota['percentual'] ; ?>%"></span>
        </span>
        <span class="cartao-metrica__detalhe">
            <?php echo  (int) $cota['combinacoes'] ; ?> combinacao(oes) &middot;
            <?php echo  escolher($cota['manual'], 'limite manual', 'cota automatica') ; ?>
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('documento') ; ?></span>
        <span class="cartao-metrica__rotulo">Vagas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $totalVagas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Total armazenado para esta conta.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--verde"><?php echo  icone_svg('email') ; ?></span>
        <span class="cartao-metrica__rotulo">Candidaturas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $totalEnvios, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">E-mails registrados (todas as origens).</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('lupa') ; ?></span>
        <span class="cartao-metrica__rotulo">Consultas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $totalConsultas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Consultas ao provedor de vagas.</span>
    </article>
</div>

<div class="grade grade--painel">

    <?php /* Dados e acoes da conta. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Conta e permissoes</h3>
        </div>
        <div class="cartao__corpo">
            <dl class="lista-definicao">
                <div>
                    <dt>Cadastro</dt>
                    <dd><?php echo  esc(formatar_data_hora(valor_ou($usuario, 'criado_em', null))) ; ?></dd>
                </div>
                <div>
                    <dt>Ultima busca</dt>
                    <dd><?php echo  esc(formatar_data_hora(valor_ou($usuario, 'ultima_execucao_busca_vagas', null))) ; ?></dd>
                </div>
                <div>
                    <dt>Ultimo envio automatico</dt>
                    <dd><?php echo  esc(formatar_data_hora(valor_ou($usuario, 'ultima_execucao_envio_automatico', null))) ; ?></dd>
                </div>
                <div>
                    <dt>Mes de referencia</dt>
                    <dd><?php echo  esc(texto_ou($usuario, 'mes_referencia', '-')) ; ?></dd>
                </div>
            </dl>

            <?php /* Perfil de acesso. */ ?>
            <form class="formulario" method="post" action="<?php echo  esc($base . '/perfil') ; ?>">
                <div class="campo">
                    <label class="campo__rotulo" for="perfil">Perfil de acesso</label>
                    <select class="campo__entrada" id="perfil" name="perfil">
                        <option value="usuario" <?php echo  escolher(! $eAdmin, 'selected', '') ; ?>>Usuario comum</option>
                        <option value="admin" <?php echo escolher($eAdmin, 'selected', ''); ?>>Administrador</option>
                    </select>
                    <span class="campo__ajuda">Promover concede acesso total ao painel administrativo.</span>
                </div>
                <div class="formulario__acoes">
                    <button type="submit" class="botao botao--secundario botao--pequeno">
                        <?php echo  icone_svg('escudo') ; ?> Salvar perfil
                    </button>
                </div>
            </form>

            <?php /* O plano do usuario e os limites que valem para ele. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Plano e limites</h3>
        <span class="selo selo--primario"><?php echo esc($plano['nome']) ; ?></span>
    </div>

    <div class="cartao__corpo">
        <?php /* O aviso do limite manual (ele vence o plano). */ ?>
        <?php if (valor_ou($usuario, 'limite_manual', null) !== null){ ?>
            <div class="alerta alerta--atencao">
                <?php echo icone_svg('info'); ?>
                <p>
                    Este usuario possui limite manual que sobrescreve o plano.
                </p>
            </div>
        <?php } ?>

        <?php /* Os limites efetivos, com a origem de cada um. */ ?>
        <div class="linha-selos">
            <span class="selo selo--neutro">
                Palavras-chave:
                <?php if ($limites['max_palavras_chave']['valor'] === null){ ?>
                    Ilimitadas
                <?php } else { ?>
                    <?php echo esc((int) $limites['max_palavras_chave']['valor']) ; ?>
                <?php } ?>
                (<?php echo esc($limites['max_palavras_chave']['rotulo']) ; ?>)
            </span>

            <span class="selo selo--neutro">
                Cidades por termo:
                <?php if ($limites['max_cidades_por_termo']['valor'] === null){ ?>
                    Ilimitadas
                <?php } else { ?>
                    <?php echo esc((int) $limites['max_cidades_por_termo']['valor']) ; ?>
                <?php } ?>
                (<?php echo esc($limites['max_cidades_por_termo']['rotulo']) ; ?>)
            </span>

            <span class="selo selo--info">
                <?php echo esc((int) $limites['limite_envios_dia']['valor']) ; ?> envios/dia
                (<?php echo esc($limites['limite_envios_dia']['rotulo']) ; ?>)
            </span>
        </div>

        <?php /* Trocar o plano. */ ?>
        <form class="formulario" method="post"
              action="<?php echo esc(site_url('admin/usuarios/' . (int) $usuario['id'] . '/atribuir-plano')) ; ?>">
            <div class="campo">
                <label class="campo__rotulo" for="plano_id">Plano do usuario</label>
                <select class="campo__entrada" id="plano_id" name="plano_id">
                    <?php foreach ($planosAtivos as $item){ ?>
                        <?php
                        // O plano atual (para marcar a opcao escolhida).
                        $planoAtualId = (int) valor_ou($plano, 'id', 0);
                        ?>
                        <option value="<?php echo esc((int) $item['id']) ; ?>"
                                <?php echo  escolher((int) $item['id'] === $planoAtualId, 'selected', '') ; ?>>
                            <?php echo esc($item['nome']) ; ?>
                            (ate <?php echo esc((int) $item['max_usuarios']) ; ?> usuario(s))
                        </option>
                    <?php } ?>
                </select>
                <span class="campo__ajuda">
                    O plano define as regras do usuario. A troca vale na hora.
                </span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario botao--pequeno">
                    <?php echo icone_svg('check') ; ?> Atribuir plano
                </button>
            </div>
        </form>

        <?php /* Voltar para o basico e limpar o limite manual. */ ?>
        <div class="linha-acoes">
            <form method="post"
                  action="<?php echo esc(site_url('admin/usuarios/' . (int) $usuario['id'] . '/remover-plano')) ; ?>"
                  data-confirmar="Voltar este usuario para o plano basico?">
                <button type="submit" class="botao botao--fantasma botao--pequeno">
                    <?php echo icone_svg('relogio') ; ?> Voltar para o Basico
                </button>
            </form>

            <form class="formulario" method="post"
                  action="<?php echo esc(site_url('admin/usuarios/' . (int) $usuario['id'] . '/salvar-limite-manual')) ; ?>">
                <div class="campo">
                    <label class="campo__rotulo" for="limite_manual_plano">Limite manual (sobrescreve o plano)</label>
                    <input class="campo__entrada" type="number" id="limite_manual_plano" name="limite_manual"
                           min="0" max="100000"
                           value="<?php
                        // O valor do limite manual (vazio quando ele nao existe).
                        $limiteManual = valor_ou($usuario, 'limite_manual', null);
                        $limiteManualTexto = '';

                        if ($limiteManual !== null) {
                            $limiteManualTexto = (string) (int) $limiteManual;
                        }
                        echo esc($limiteManualTexto) ;
                        ?>"
                           placeholder="Vazio = usa o plano">
                </div>
                <div class="formulario__acoes">
                    <button type="submit" class="botao botao--secundario botao--pequeno">
                        <?php echo icone_svg('escudo') ; ?> Salvar limite manual
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php /* Limite manual de consultas. */ ?>
            <form class="formulario" method="post" action="<?php echo  esc($base . '/limite') ; ?>">
                <div class="campo">
                    <label class="campo__rotulo" for="limite_manual">Limite manual de consultas</label>
                    <input class="campo__entrada" type="number" id="limite_manual" name="limite_manual"
                           min="0" max="100000"
                           value="<?php echo  escolher(valor_ou($usuario, 'limite_manual', null) !== null, (int) valor_ou($usuario, 'limite_manual', 0), '') ; ?>"
                           placeholder="Vazio = automatico">
                    <span class="campo__ajuda">Deixe vazio para voltar a divisao automatica entre os usuarios.</span>
                </div>
                <div class="formulario__acoes">
                    <button type="submit" class="botao botao--secundario botao--pequeno">
                        <?php echo  icone_svg('alvo') ; ?> Salvar limite
                    </button>
                </div>
            </form>

            <?php /* Envio automatico e reinicio do contador. */ ?>
            <div class="linha-acoes">
                <form method="post" action="<?php echo  esc($base . '/envio-automatico') ; ?>">
                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                        <?php echo  icone_svg(escolher($ligado, 'pausa', 'play')) ; ?>
                        <?php echo escolher($ligado, 'Desligar envio automatico', 'Ligar envio automatico'); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo  esc($base . '/reiniciar-consultas') ; ?>"
                      data-confirmar="Zerar o contador de consultas deste mes?">
                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                        <?php echo  icone_svg('relogio') ; ?> Zerar consultas do mes
                    </button>
                </form>
            </div>
        </div>
    </section>

    <?php /* Configuracao de envio (sem segredos). */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Configuracao de envio</h3>
            <span class="selo <?php echo  escolher($configuracao['tem_app_password'], 'selo--sucesso', 'selo--aviso') ; ?>">
                SMTP <?php echo  escolher($configuracao['tem_app_password'], 'pronto', 'incompleto') ; ?>
            </span>
        </div>
        <div class="cartao__corpo">
            <dl class="lista-definicao">
                <div>
                    <dt>E-mail remetente</dt>
                    <dd><?php echo  esc(texto_ou($configuracao, 'email_remetente', 'nao informado')) ; ?></dd>
                </div>
                <div>
                    <dt>Nome do remetente</dt>
                    <dd><?php echo  esc(texto_ou($configuracao, 'nome_remetente', 'nao informado')) ; ?></dd>
                </div>
                <div>
                    <dt>APP PASSWORD</dt>
                    <dd><?php echo  escolher($configuracao['tem_app_password'], 'cadastrada (oculta)', 'nao cadastrada') ; ?></dd>
                </div>
                <div>
                    <dt>Curriculo</dt>
                    <dd><?php echo  escolher($configuracao['tem_curriculo'], 'PDF enviado', 'sem curriculo') ; ?></dd>
                </div>
                <div>
                    <dt>Limite diario</dt>
                    <dd><?php echo  inteiro_ou($configuracao, 'limite_envios_dia', 0) ; ?> envio(s)</dd>
                </div>
            </dl>

            <p class="texto-suave texto-pequeno">
                Por seguranca, a APP PASSWORD nunca e exibida &mdash; nem para o administrador.
            </p>
        </div>
    </section>
</div>

<?php /* Execucoes manuais do usuario (Bloco E). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Execucoes manuais</h3>

        <?php if ($eAdmin) { ?>
            <span class="selo selo--admin">Sem limite</span>
        <?php } elseif ($manuaisDesabilitadas) { ?>
            <span class="selo selo--erro">Desabilitadas</span>
        <?php } elseif ($manuaisDisponiveis) { ?>
            <span class="selo selo--sucesso">Disponivel</span>
        <?php } else { ?>
            <span class="selo selo--aviso">Limite atingido</span>
        <?php } ?>
    </div>

    <div class="cartao__corpo">
        <?php if ($eAdmin) { ?>
            <p class="texto-suave texto-pequeno">
                Este usuario e administrador e nao tem limite de execucoes manuais.
            </p>
        <?php } else { ?>
            <div class="grade grade--painel">
                <div class="bloco-resumo">
                    <span class="bloco-resumo__rotulo">Hoje</span>
                    <span class="bloco-resumo__valor"><?php echo $usadasManuais; ?> / <?php echo $limiteManuais; ?></span>
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

            <?php if ($manuaisDesabilitadas) { ?>
                <div class="alerta alerta--info">
                    <?php echo icone_svg('info'); ?>
                    <p>
                        Execucoes manuais desabilitadas pelo admin.
                        O sistema executa automaticamente via cron.
                    </p>
                </div>
            <?php } elseif (! $manuaisDisponiveis) { ?>
                <div class="alerta alerta--atencao">
                    <?php echo icone_svg('info'); ?>
                    <p>Este usuario atingiu o limite de execucoes manuais de hoje.</p>
                </div>
            <?php } ?>
        <?php } ?>

        <div class="linha-acoes">
            <form method="post" action="<?php echo esc($base . '/zerar-manuais'); ?>"
                  data-confirmar="Zerar o contador de execucoes manuais de hoje deste usuario?">
                <button type="submit" class="botao botao--fantasma botao--pequeno">
                    <?php echo icone_svg('relogio'); ?> Zerar contador
                </button>
            </form>
        </div>

        <?php /* Limite global de execucoes manuais (vale para todos os usuarios). */ ?>
        <form class="formulario" method="post" action="<?php echo esc(site_url('admin/usuarios/limite-manuais')); ?>">
            <input type="hidden" name="usuario_id" value="<?php echo $id; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="limite_execucoes_manuais">Limite diario de execucoes manuais</label>
                <input class="campo__entrada" type="number" id="limite_execucoes_manuais"
                       name="limite_execucoes_manuais" min="0" max="100"
                       value="<?php echo $limiteGlobalManuais; ?>">
                <span class="campo__ajuda">
                    Vale para todos os usuarios comuns (0 desabilita). Administradores nao tem limite.
                </span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--secundario botao--pequeno">
                    <?php echo icone_svg('alvo'); ?> Salvar limite global
                </button>
            </div>
        </form>
    </div>
</section>

<?php /* Palavras-chave. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Palavras-chave (<?php echo  count($palavras) ; ?>)</h3>
    </div>
    <div class="cartao__corpo">
        <?php if ($palavras === []){ ?>
            <p class="texto-suave">Nenhuma palavra-chave cadastrada.</p>
        <?php } else { ?>
            <ul class="lista-limpa">
                <?php foreach ($palavras as $palavra){ ?>
                    <li class="item-lista item-lista--linha">
                        <div class="item-lista__texto">
                            <span class="item-lista__titulo"><?php echo  esc($palavra['termo']) ; ?></span>
                            <p class="item-lista__meta"><?php echo  esc(resumir_texto((string) $palavra['cidades'], 90)) ; ?></p>
                        </div>
                        <span class="selo <?php echo  escolher((int) $palavra['ativa'] === 1, 'selo--sucesso', 'selo--neutro') ; ?>">
                            <?php echo  escolher((int) $palavra['ativa'] === 1, 'Ativa', 'Pausada') ; ?>
                        </span>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</section>

<?php /* Solicitacoes de liberacao. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Solicitacoes de liberacao</h3>
    </div>
    <div class="cartao__corpo">
        <?php if ($solicitacoesUsuario === []){ ?>
            <p class="texto-suave">Nenhuma solicitacao registrada.</p>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Observacao</th>
                            <th scope="col">Status</th>
                            <th scope="col">Respondido em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitacoesUsuario as $solicitacao){ ?>
                            <tr>
                                <td><?php echo  esc(formatar_data_hora(valor_ou($solicitacao, 'criado_em', null))) ; ?></td>
                                <td><?php echo  esc(primeiro_valor(resumir_texto(texto_ou($solicitacao, 'observacao', ''), 80), '-')) ; ?></td>
                                <td><span class="selo selo--neutro"><?php echo  esc($solicitacao['status']) ; ?></span></td>
                                <td><?php echo  esc(formatar_data_hora(valor_ou($solicitacao, 'respondido_em', null))) ; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Movimentacao recente. */ ?>
<div class="grade grade--painel">
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Consultas recentes</h3>
            <a class="cartao__link" href="<?php echo  site_url('admin/logs?aba=consultas&busca=' . rawurlencode((string) $usuario['email'])) ; ?>">Ver logs</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($logsRecentes === []){ ?>
                <p class="texto-suave">Nenhuma consulta registrada.</p>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($logsRecentes as $log){ ?>
                        <li class="item-lista">
                            <span class="item-lista__titulo">
                                <?php echo  esc(primeiro_valor($log['termo'], 'Consulta')) ; ?>
                                <span class="texto-suave"><?php echo  esc(primeiro_valor($log['cidade_consultada'], '')) ; ?></span>
                            </span>
                            <p class="item-lista__meta">
                                <?php echo  esc($log['status']) ; ?> &middot; <?php echo  inteiro_ou($log, 'total_vagas_retornadas', 0) ; ?> vaga(s)
                                &middot; <?php echo  esc(formatar_data_hora(valor_ou($log, 'consumido_em', null))) ; ?>
                            </p>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Envios recentes</h3>
            <a class="cartao__link" href="<?php echo  site_url('admin/logs?aba=envios&busca=' . rawurlencode((string) $usuario['email'])) ; ?>">Ver logs</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($enviosRecentes === []){ ?>
                <p class="texto-suave">Nenhum envio registrado.</p>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($enviosRecentes as $envio){ ?>
                        <li class="item-lista">
                            <span class="item-lista__titulo"><?php echo  esc(primeiro_valor($envio['destinatario'], 'Sem destinatario')) ; ?></span>
                            <p class="item-lista__meta">
                                <?php echo  esc($envio['status']) ; ?> &middot; <?php echo  esc(formatar_data_hora(valor_ou($envio, 'enviado_em', null))) ; ?>
                            </p>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>
</div>

<?php echo  $this->endSection() ; ?>
