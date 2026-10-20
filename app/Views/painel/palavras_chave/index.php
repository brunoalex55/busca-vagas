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
 * VIEW: PAINEL - LISTA DE PALAVRAS-CHAVE
 * --------------------------------------------------------------------------
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Palavras-chave';
?>

<?php echo  $this->section('conteudo') ; ?>

<?php /* Cabecalho com resumo e botao de criacao. */ ?>
<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Palavras-chave</h2>
        <p class="painel__descricao">
            <?php echo  number_format($totalPalavras, 0, ',', '.') ; ?> palavra(s) cadastrada(s)
            e <?php echo  number_format($totalCombinacoes, 0, ',', '.') ; ?> combinacao(oes) de busca ativa(s).
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('painel/palavras-chave/nova') ; ?>">
            <?php echo  icone_svg('lupa') ; ?> Nova palavra-chave
        </a>
    </div>
</section>

<div class="alerta alerta--info">
    <?php echo  icone_svg('info') ; ?>
    <p>
        Cada cidade gera uma consulta independente. Quanto mais combinacoes,
        maior a sua cota automatica de consultas.
    </p>
</div>

<?php if ($palavras === []){ ?>

    <?php /* Estado vazio. */ ?>
    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('lupa') ; ?>
                <h3>Nenhuma palavra-chave cadastrada</h3>
                <p class="texto-suave">
                    Cadastre o cargo que voce busca e as cidades de interesse.
                    O sistema monta as consultas automaticamente.
                </p>
                <a class="botao botao--primario" href="<?php echo  site_url('painel/palavras-chave/nova') ; ?>">
                    <?php echo  icone_svg('lupa') ; ?> Cadastrar a primeira
                </a>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Termo</th>
                    <th scope="col">Cidades</th>
                    <th scope="col">Frequencia</th>
                    <th scope="col">Consultas</th>
                    <th scope="col">Ultima execucao</th>
                    <th scope="col">Status</th>
                    <th scope="col">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($palavras as $palavra){ ?>
                    <?php
                    // Cidades ficam salvas em JSON.
                    $cidades = json_decode(valor_ou($palavra, 'cidades', '[]'), true);

                    if (! is_array($cidades)) {
                        $cidades = [];
                    }

                    $cidades = array_values(array_filter($cidades));
                    $visiveis = array_slice($cidades, 0, 3);
                    $restantes = count($cidades) - count($visiveis);
                    $ativa = (int) $palavra['ativa'] === 1;
                    ?>
                    <tr>
                        <th scope="row">
                            <a href="<?php echo  site_url('painel/palavras-chave/' . (int) $palavra['id'] . '/editar') ; ?>">
                                <?php echo  esc($palavra['termo']) ; ?>
                            </a>
                            <?php if (inteiro_ou($palavra, 'remoto', 0) === 1){ ?>
                                <span class="selo selo--info">Remoto</span>
                            <?php } ?>
                        </th>

                        <td>
                            <div class="tags">
                                <?php foreach ($visiveis as $cidade){ ?>
                                    <span class="tag"><?php echo  esc($cidade) ; ?></span>
                                <?php } ?>

                                <?php if ($restantes > 0){ ?>
                                    <span class="tag tag--mais">+<?php echo  (int) $restantes ; ?></span>
                                <?php } ?>
                            </div>
                        </td>

                        <td>A cada <?php echo  (int) $palavra['frequencia_horas'] ; ?>h</td>
                        <td><?php echo  number_format(inteiro_ou($palavra, 'total_consultas', 0), 0, ',', '.') ; ?></td>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($palavra, 'ultima_execucao', null))) ; ?></td>

                        <td>
                            <span class="selo <?php echo escolher($ativa, 'selo--sucesso', 'selo--neutro'); ?>">
                                <?php echo escolher($ativa, 'Ativa', 'Pausada'); ?>
                            </span>
                        </td>

                        <td>
                            <div class="acoes-tabela">
                                <?php /* Editar (leitura). */ ?>
                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('painel/palavras-chave/' . (int) $palavra['id'] . '/editar') ; ?>">
                                    <?php echo  icone_svg('lapis') ; ?> <span class="visualmente-oculto">Editar</span>
                                </a>

                                <?php /* Pausar/reativar (escrita via POST). */ ?>
                                <form method="post"
                                      action="<?php echo  site_url('painel/palavras-chave/' . (int) $palavra['id'] . '/alternar') ; ?>"
                                      class="form-inline">
                                    <button type="submit" class="botao botao--fantasma botao--pequeno"
                                            title="<?php echo escolher($ativa, 'Pausar', 'Reativar'); ?>">
                                        <?php echo  icone_svg(escolher($ativa, 'pausa', 'play')) ; ?>
                                        <span class="visualmente-oculto"><?php echo escolher($ativa, 'Pausar', 'Reativar'); ?></span>
                                    </button>
                                </form>

                                <?php /* Excluir (escrita via POST, com confirmacao). */ ?>
                                <form method="post"
                                      action="<?php echo  site_url('painel/palavras-chave/' . (int) $palavra['id'] . '/excluir') ; ?>"
                                      class="form-inline"
                                      data-confirmar="Excluir a palavra-chave &quot;<?php echo  esc($palavra['termo']) ; ?>&quot;?">
                                    <button type="submit" class="botao botao--fantasma botao--pequeno botao--perigo">
                                        <?php echo  icone_svg('lixeira') ; ?> <span class="visualmente-oculto">Excluir</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php } ?>

<?php echo  $this->endSection() ; ?>
