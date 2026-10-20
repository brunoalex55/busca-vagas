<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - LOGS
 * --------------------------------------------------------------------------
 *
 * Auditoria em duas abas (consultas e envios), com filtros e paginacao.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Logs';

// Opcoes de origem conforme a aba.
$origens = ['manual' => 'Manual', 'manual_usuario' => 'Manual do usuario', 'cron' => 'Cron'];

if ($aba === 'envios') {
    $origens = ['manual' => 'Manual', 'massa' => 'Em massa', 'automatico' => 'Automatico'];
}

// Opcoes de status conforme a aba.
$statusDisponiveis = ['sucesso', 'sem_resultados', 'erro_http', 'erro_api', 'chave_invalida', 'limite_atingido'];

if ($aba === 'envios') {
    $statusDisponiveis = ['enviado', 'erro_destinatario', 'erro_credencial', 'erro_anexo', 'erro_smtp'];
}

// Provedor da vaga (as duas abas tem o filtro).
$provedores = [
    'serpapi'      => 'SerpApi (Google Jobs)',
    'divulgavagas' => 'Divulga Vagas',
];
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Logs</h2>
        <p class="painel__descricao">
            <?php echo  number_format((int) $total, 0, ',', '.') ; ?> registro(s) nesta aba.
        </p>
    </div>
</section>

<?php /* Abas. */ ?>
<nav class="abas" aria-label="Tipo de log">
    <a class="aba<?php echo escolher($aba === 'consultas', ' aba--ativa', ''); ?>"
       href="<?php echo  site_url('admin/logs?aba=consultas') ; ?>">
        Consultas
        <span class="aba__contagem"><?php echo  number_format((int) $totais['consultas'], 0, ',', '.') ; ?></span>
    </a>
    <a class="aba<?php echo escolher($aba === 'envios', ' aba--ativa', ''); ?>"
       href="<?php echo  site_url('admin/logs?aba=envios') ; ?>">
        Envios
        <span class="aba__contagem"><?php echo  number_format((int) $totais['envios'], 0, ',', '.') ; ?></span>
    </a>
</nav>

<?php /* Resumo rapido. */ ?>
<div class="linha-selos">
    <?php if ($aba === 'envios'){ ?>
        <span class="selo selo--neutro">Total: <?php echo  (int) $resumo['total'] ; ?></span>
        <span class="selo selo--sucesso">Enviados: <?php echo  (int) $resumo['enviados'] ; ?></span>
        <span class="selo selo--erro">Falhas: <?php echo  (int) $resumo['falhas'] ; ?></span>
        <span class="selo selo--admin">Hoje: <?php echo  (int) $resumo['hoje'] ; ?></span>
    <?php } else { ?>
        <span class="selo selo--neutro">Total: <?php echo  (int) $resumo['total'] ; ?></span>
        <span class="selo selo--sucesso">Sucesso: <?php echo  (int) $resumo['sucesso'] ; ?></span>
        <span class="selo selo--erro">Falhas: <?php echo  (int) $resumo['falhas'] ; ?></span>
    <?php } ?>
</div>

<?php /* Filtros. */ ?>
<form class="filtros" method="get" action="<?php echo  site_url('admin/logs') ; ?>">
    <input type="hidden" name="aba" value="<?php echo  esc($aba) ; ?>">

    <div class="filtros__grade">
        <div class="campo">
            <label class="campo__rotulo" for="busca">Buscar</label>
            <input class="campo__entrada" type="search" id="busca" name="busca"
                   value="<?php echo  esc($filtros['busca']) ; ?>"
                   placeholder="<?php echo escolher($aba === 'envios', 'Usuario ou destinatario', 'Usuario ou termo'); ?>">
        </div>

        <div class="campo">
            <label class="campo__rotulo" for="status">Status</label>
            <select class="campo__entrada" id="status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statusDisponiveis as $status){ ?>
                    <option value="<?php echo  esc($status) ; ?>" <?php echo  escolher($filtros['status'] === $status, 'selected', '') ; ?>>
                        <?php echo  esc($status) ; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="campo">
            <label class="campo__rotulo" for="origem">Origem</label>
            <select class="campo__entrada" id="origem" name="origem">
                <option value="">Todas</option>
                <?php foreach ($origens as $valor => $rotulo){ ?>
                    <option value="<?php echo  esc($valor) ; ?>" <?php echo  escolher($filtros['origem'] === $valor, 'selected', '') ; ?>>
                        <?php echo  esc($rotulo) ; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <?php /* Provedor (de onde veio a vaga: SerpApi ou Divulga Vagas). */ ?>
        <div class="campo">
            <label class="campo__rotulo" for="provedor">Provedor</label>
            <select class="campo__entrada" id="provedor" name="provedor">
                <option value="">Todos</option>
                <?php foreach ($provedores as $valor => $rotulo){ ?>
                    <option value="<?php echo  esc($valor) ; ?>" <?php echo  escolher($filtros['provedor'] === $valor, 'selected', '') ; ?>>
                        <?php echo  esc($rotulo) ; ?>
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="filtros__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/logs?aba=' . $aba) ; ?>">Limpar</a>
        <button type="submit" class="botao botao--primario botao--pequeno">
            <?php echo  icone_svg('lupa') ; ?> Filtrar
        </button>
    </div>
</form>

<?php if ($registros === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg(escolher($aba === 'envios', 'email', 'lupa')) ; ?>
                <h3>Nenhum registro encontrado</h3>
                <p class="texto-suave">Ajuste os filtros ou aguarde novas execucoes.</p>
            </div>
        </div>
    </section>

<?php } elseif ($aba === 'consultas'){ ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Termo</th>
                    <th scope="col">Cidade</th>
                    <th scope="col">Provedor</th>
                    <th scope="col">Chave</th>
                    <th scope="col">Vagas</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro){ ?>
                    <?php $ok = valor_ou($registro, 'status', '') === 'sucesso'; ?>
                    <tr>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($registro, 'consumido_em', null))) ; ?></td>
                        <th scope="row"><?php echo  esc(valor_ou($registro, 'usuario_nome', '#' . (int) $registro['usuario_id'])) ; ?></th>
                        <td><?php echo  esc(primeiro_valor($registro['termo'], '-')) ; ?></td>
                        <td><?php echo  esc(primeiro_valor($registro['cidade_consultada'], '-')) ; ?></td>
                        <td>
                            <?php if (valor_ou($registro, 'provedor', 'serpapi') === 'divulgavagas'){ ?>
                                <span class="selo selo--divulgavagas-enviada">Divulga Vagas</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">SerpApi</span>
                            <?php } ?>
                        </td>
                        <td><?php echo  esc(valor_ou($registro, 'chave_apelido', '-')) ; ?></td>
                        <td><?php echo  number_format(inteiro_ou($registro, 'total_vagas_retornadas', 0), 0, ',', '.') ; ?></td>
                        <td>
                            <?php if ($ok){ ?>
                                <span class="selo selo--sucesso">Sucesso</span>
                            <?php } else { ?>
                                <span class="selo selo--erro" title="<?php echo  esc(valor_ou($registro, 'mensagem_erro', '')) ; ?>">
                                    <?php echo  esc(primeiro_valor($registro['status'], 'Erro')) ; ?>
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Destinatario</th>
                    <th scope="col">Assunto</th>
                    <th scope="col">Provedor</th>
                    <th scope="col">Origem</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro){ ?>
                    <?php $ok = valor_ou($registro, 'status', '') === 'enviado'; ?>
                    <tr>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($registro, 'enviado_em', null))) ; ?></td>
                        <th scope="row"><?php echo  esc(valor_ou($registro, 'usuario_nome', '#' . (int) $registro['usuario_id'])) ; ?></th>
                        <td><?php echo  esc(primeiro_valor($registro['destinatario'], '-')) ; ?></td>
                        <td><?php echo  esc(resumir_texto(texto_ou($registro, 'assunto', ''), 60)) ; ?></td>
                        <td>
                            <?php if (valor_ou($registro, 'provedor', 'serpapi') === 'divulgavagas'){ ?>
                                <span class="selo selo--divulgavagas-enviada">Divulga Vagas</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">SerpApi</span>
                            <?php } ?>
                        </td>
                        <td><?php echo  esc(primeiro_valor($registro['origem'], '-')) ; ?></td>
                        <td>
                            <?php if ($ok){ ?>
                                <span class="selo selo--sucesso">Enviado</span>
                            <?php } else { ?>
                                <span class="selo selo--erro" title="<?php echo  esc(valor_ou($registro, 'mensagem_erro', '')) ; ?>">
                                    <?php echo  esc(primeiro_valor($registro['status'], 'Erro')) ; ?>
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php } ?>

<?php echo  $this->include('partials/paginacao') ; ?>

<?php echo  $this->endSection() ; ?>
