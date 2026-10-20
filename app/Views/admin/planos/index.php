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
 * VIEW: ADMIN - PLANOS
 * --------------------------------------------------------------------------
 *
 * Listagem dos planos com as caracteristicas de cada um e quantos
 * usuarios estao nele.
 *
 * As regras que valem para todo o sistema (quantas palavras-chave, quantas
 * cidades por termo, quantos envios por dia) sao as mesmas que o site
 * publico promete em /precos.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Planos';

// O texto de um limite que pode ser ilimitado (NULL).
$textoIlimitado = 'Ilimitadas';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Planos</h2>
        <p class="painel__descricao">
            As regras que o site publico promete. O plano
            <strong>Básico</strong> e o padrao de quem ainda nao tem plano.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('admin/planos/formulario') ; ?>">
            <?php echo  icone_svg('raio') ; ?> Novo plano
        </a>
    </div>
</section>

<?php /* Resumo por plano. */ ?>
<div class="linha-selos">
    <span class="selo selo--neutro">Usuarios: <?php echo  (int) $resumo['total'] ; ?></span>
    <span class="selo selo--info">Básico: <?php echo  (int) $resumo['basico'] ; ?></span>
    <span class="selo selo--primario">Profissional: <?php echo  (int) $resumo['profissional'] ; ?></span>
    <span class="selo selo--sucesso">Empresa: <?php echo  (int) $resumo['empresa'] ; ?></span>
    <?php if ((int) $resumo['sem_plano'] > 0){ ?>
        <span class="selo selo--aviso">Sem plano: <?php echo  (int) $resumo['sem_plano'] ; ?></span>
    <?php } ?>
</div>

<?php if ($planos === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('raio') ; ?>
                <h3>Nenhum plano cadastrado</h3>
                <p class="texto-suave">Cadastre os planos para o sistema saber as regras de cada um.</p>
                <a class="botao botao--primario" href="<?php echo  site_url('admin/planos/formulario') ; ?>">Cadastrar o primeiro plano</a>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">Palavras-chave</th>
                    <th scope="col">Cidades/termo</th>
                    <th scope="col">Envios/dia</th>
                    <th scope="col">Automatico</th>
                    <th scope="col">Em massa</th>
                    <th scope="col">Usuarios</th>
                    <th scope="col">Suporte</th>
                    <th scope="col">Status</th>
                    <th scope="col">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planos as $plano){ ?>
                    <tr>
                        <th scope="row">
                            <span class="item-lista__titulo"><?php echo esc($plano['nome']) ; ?></span>
                            <span class="item-lista__meta">
                                <?php echo esc($plano['slug']) ; ?>
                                <?php if (valor_ou($plano, 'preco_mensal', null) !== null){ ?>
                                    | R$ <?php echo esc(number_format((float) $plano['preco_mensal'], 2, ',', '.')) ; ?>/mes
                                <?php } ?>
                            </span>
                        </th>

                        <td>
                            <?php if (valor_ou($plano, 'max_palavras_chave', null) === null){ ?>
                                <span class="selo selo--sucesso"><?php echo esc($textoIlimitado) ; ?></span>
                            <?php } else { ?>
                                <?php echo esc((int) $plano['max_palavras_chave']) ; ?>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if (valor_ou($plano, 'max_cidades_por_termo', null) === null){ ?>
                                <span class="selo selo--sucesso"><?php echo esc($textoIlimitado) ; ?></span>
                            <?php } else { ?>
                                <?php echo esc((int) $plano['max_cidades_por_termo']) ; ?>
                            <?php } ?>
                        </td>

                        <td><?php echo esc((int) $plano['limite_envios_dia']) ; ?></td>

                        <td>
                            <?php if ((int) $plano['envio_automatico'] === 1){ ?>
                                <span class="selo selo--sucesso">Ligado</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">Manual</span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ((int) $plano['envio_em_massa'] === 1){ ?>
                                <span class="selo selo--sucesso">Sim</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">Nao</span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php echo esc((int) $plano['usuarios']) ; ?> de <?php echo esc((int) $plano['max_usuarios']) ; ?>
                            <?php if ((int) $plano['usuarios'] >= (int) $plano['max_usuarios']){ ?>
                                <span class="selo selo--aviso">Esgotado</span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ($plano['suporte'] === 'prioritario'){ ?>
                                Prioritario
                            <?php } else { ?>
                                E-mail
                            <?php } ?>
                        </td>

                        <td>
                            <?php if ((int) $plano['ativo'] === 1){ ?>
                                <span class="selo selo--sucesso">Ativo</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">Inativo</span>
                            <?php } ?>
                            <?php if ((int) $plano['destaque'] === 1){ ?>
                                <span class="selo selo--primario">Destaque</span>
                            <?php } ?>
                        </td>

                        <td>
                            <div class="acoes-tabela">
                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('admin/planos/formulario/' . (int) $plano['id']) ; ?>">
                                    Editar
                                </a>

                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('admin/planos/' . $plano['slug'] . '/usuarios') ; ?>">
                                    Usuarios
                                </a>

                                <?php if ((int) $plano['ativo'] === 1){ ?>
                                    <form method="post"
                                          action="<?php echo  site_url('admin/planos/desativar/' . (int) $plano['id']) ; ?>"
                                          data-confirmar="Desativar o plano <?php echo esc($plano['nome']) ; ?>?">
                                        <button type="submit" class="botao botao--fantasma botao--pequeno">
                                            Desativar
                                        </button>
                                    </form>
                                <?php } else { ?>
                                    <form method="post"
                                          action="<?php echo  site_url('admin/planos/reativar/' . (int) $plano['id']) ; ?>"
                                          data-confirmar="Reativar o plano <?php echo esc($plano['nome']) ; ?>?">
                                        <button type="submit" class="botao botao--fantasma botao--pequeno">
                                            Reativar
                                        </button>
                                    </form>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<?php echo  $this->endSection() ; ?>
