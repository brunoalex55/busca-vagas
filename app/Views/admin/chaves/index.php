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
 * VIEW: ADMIN - CHAVES SERPAPI
 * --------------------------------------------------------------------------
 *
 * Listagem das chaves de busca com uso, saldo e situacao.
 * A chave em si aparece sempre mascarada.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Chaves';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Chaves de busca</h2>
        <p class="painel__descricao">
            Saldo total disponivel: <strong><?php echo  number_format((int) $saldo, 0, ',', '.') ; ?></strong> consulta(s).
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('admin/chaves/nova') ; ?>">
            <?php echo  icone_svg('raio') ; ?> Nova chave
        </a>
    </div>
</section>

<?php /* Resumo por situacao. */ ?>
<div class="linha-selos">
    <span class="selo selo--neutro">Total: <?php echo  (int) $resumo['total'] ; ?></span>
    <span class="selo selo--sucesso">Ativas: <?php echo  (int) $resumo['ativas'] ; ?></span>
    <span class="selo selo--aviso">Esgotadas: <?php echo  (int) $resumo['esgotadas'] ; ?></span>
    <span class="selo selo--neutro">Desativadas: <?php echo  (int) $resumo['desativadas'] ; ?></span>
    <span class="selo selo--erro">Invalidas: <?php echo  (int) $resumo['invalidas'] ; ?></span>
</div>

<?php if ($chaves === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('raio') ; ?>
                <h3>Nenhuma chave cadastrada</h3>
                <p class="texto-suave">Sem chaves, o CRON 1 nao consegue buscar vagas nos provedores.</p>
                <a class="botao botao--primario" href="<?php echo  site_url('admin/chaves/nova') ; ?>">Cadastrar a primeira chave</a>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Ordem</th>
                    <th scope="col">Apelido</th>
                    <th scope="col">Chave</th>
                    <th scope="col">Uso</th>
                    <th scope="col">Situacao</th>
                    <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chaves as $chave){ ?>
                    <?php
                    $id      = (int) $chave['id'];
                    $ativa   = (int) $chave['ativa'] === 1;
                    $esgotou = (int) $chave['saldo'] <= 0;
                    $motivo  = texto_ou($chave, 'motivo_inativacao', '');

                    // Situacao visual da chave.
                    if ($ativa) {
                        [$classeSelo, $rotulo] = ['selo--sucesso', 'Ativa'];
                    } elseif ($esgotou) {
                        [$classeSelo, $rotulo] = ['selo--aviso', 'Esgotada'];
                    } elseif ($motivo === 'invalida') {
                        [$classeSelo, $rotulo] = ['selo--erro', 'Invalida'];
                    } else {
                        [$classeSelo, $rotulo] = ['selo--neutro', 'Desativada'];
                    }
                    ?>
                    <tr>
                        <td><?php echo  (int) $chave['ordem'] ; ?></td>
                        <th scope="row"><?php echo  esc($chave['apelido']) ; ?></th>
                        <td><code class="codigo-mascarado"><?php echo  esc($chave['chave_mascarada']) ; ?></code></td>
                        <td>
                            <?php echo  (int) $chave['consultas_usadas'] ; ?> / <?php echo  (int) $chave['quantidade_consultas'] ; ?>
                            <span class="barra-progresso barra-progresso--fina" role="img"
                                  aria-label="<?php echo  (int) $chave['percentual'] ; ?>% utilizada">
                                <span class="barra-progresso__valor" style="width: <?php echo  (int) $chave['percentual'] ; ?>%"></span>
                            </span>
                        </td>
                        <td><span class="selo <?php echo  $classeSelo ; ?>"><?php echo  $rotulo ; ?></span></td>
                        <td>
                            <div class="acoes-tabela">
                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('admin/chaves/' . $id . '/editar') ; ?>">
                                    <?php echo  icone_svg('lapis') ; ?> Editar
                                </a>

                                <?php if ($ativa){ ?>
                                    <form method="post" action="<?php echo  site_url('admin/chaves/' . $id . '/desativar') ; ?>"
                                          data-confirmar="Desativar esta chave?">
                                        <button type="submit" class="botao botao--fantasma botao--pequeno">
                                            <?php echo  icone_svg('pausa') ; ?>
                                        </button>
                                    </form>
                                <?php } else { ?>
                                    <form method="post" action="<?php echo  site_url('admin/chaves/' . $id . '/ativar') ; ?>">
                                        <button type="submit" class="botao botao--fantasma botao--pequeno">
                                            <?php echo  icone_svg('play') ; ?>
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
