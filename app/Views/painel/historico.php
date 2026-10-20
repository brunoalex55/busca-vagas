<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: PAINEL - HISTORICO
 * --------------------------------------------------------------------------
 *
 * Duas abas: consultas ao provedor e candidaturas enviadas.
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Historico';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Historico</h2>
        <p class="painel__descricao">Acompanhe cada consulta feita e cada candidatura enviada.</p>
    </div>
</section>

<?php /* Abas. */ ?>
<nav class="abas" aria-label="Tipo de historico">
    <a class="aba<?php echo escolher($aba === 'consultas', ' aba--ativa', ''); ?>"
       href="<?php echo  site_url('painel/historico?aba=consultas') ; ?>">
        Consultas
        <span class="aba__contagem"><?php echo  number_format((int) $totais['consultas'], 0, ',', '.') ; ?></span>
    </a>
    <a class="aba<?php echo escolher($aba === 'envios', ' aba--ativa', ''); ?>"
       href="<?php echo  site_url('painel/historico?aba=envios') ; ?>">
        Envios
        <span class="aba__contagem"><?php echo  number_format((int) $totais['envios'], 0, ',', '.') ; ?></span>
    </a>
</nav>

<?php if ($registros === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg(escolher($aba === 'envios', 'email', 'lupa')) ; ?>
                <h3>Nenhum registro por aqui</h3>
                <p class="texto-suave">
                    <?php echo escolher($aba === 'envios', 'As candidaturas enviadas aparecerao nesta aba.', 'As consultas ao provedor aparecerao nesta aba.'); ?>
                </p>
            </div>
        </div>
    </section>

<?php } elseif ($aba === 'consultas'){ ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Termo</th>
                    <th scope="col">Cidade</th>
                    <th scope="col">Origem</th>
                    <th scope="col">Vagas</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro){ ?>
                    <?php $ok = valor_ou($registro, 'status', '') === 'sucesso'; ?>
                    <tr>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($registro, 'consumido_em', null))) ; ?></td>
                        <th scope="row"><?php echo  esc(primeiro_valor($registro['termo'], 'Consulta')) ; ?></th>
                        <td><?php echo  esc(primeiro_valor($registro['cidade_consultada'], '-')) ; ?></td>
                        <td><?php echo  esc(primeiro_valor($registro['origem'], '-')) ; ?></td>
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
                    <th scope="col">Destinatario</th>
                    <th scope="col">Assunto</th>
                    <th scope="col">Vaga</th>
                    <th scope="col">Origem</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro){ ?>
                    <?php $ok = valor_ou($registro, 'status', '') === 'enviado'; ?>
                    <tr>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($registro, 'enviado_em', null))) ; ?></td>
                        <th scope="row"><?php echo  esc(primeiro_valor($registro['destinatario'], '-')) ; ?></th>
                        <td><?php echo  esc(resumir_texto(texto_ou($registro, 'assunto', ''), 60)) ; ?></td>
                        <td><?php echo  esc(primeiro_valor(resumir_texto(texto_ou($registro, 'vaga_titulo', ''), 60), '-')) ; ?></td>
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

<?php /* Paginacao (preserva a aba). */ ?>
<?php echo  $this->include('partials/paginacao') ; ?>

<?php echo  $this->endSection() ; ?>
