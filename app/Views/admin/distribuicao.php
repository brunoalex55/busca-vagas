<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - DISTRIBUICAO DE CONSULTAS
 * --------------------------------------------------------------------------
 *
 * Mostra como o saldo das chaves e dividido entre os usuarios ativos,
 * seguindo as regras de peso por combinacao, teto e piso.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Distribuicao';

// Indexa os usuarios por ID para cruzar com a distribuicao.
$mapaUsuarios = [];

foreach ($usuarios as $usuario) {
    $mapaUsuarios[(int) $usuario['id']] = $usuario;
}

// Itens da distribuicao (id => dados).
$itens = valor_ou($distribuicao, 'usuarios', []);
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Distribuicao de consultas</h2>
        <p class="painel__descricao">
            O saldo das chaves e repartido automaticamente por peso (combinacoes ativas),
            com teto de 3x a media. O limite manual tem prioridade.
        </p>
    </div>

    <div class="painel__acoes">
        <form method="post" action="<?php echo  site_url('admin/distribuicao/recalcular') ; ?>"
              data-confirmar="Reiniciar o ciclo mensal das chaves? Chaves esgotadas voltam a ficar ativas.">
            <button type="submit" class="botao botao--secundario botao--pequeno">
                <?php echo  icone_svg('relogio') ; ?> Reiniciar ciclo mensal
            </button>
        </form>
    </div>
</section>

<?php /* Resumo do calculo. */ ?>
<div class="grade-metricas">
    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('raio') ; ?></span>
        <span class="cartao-metrica__rotulo">Saldo disponivel</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $distribuicao['total_disponivel'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Somatorio do saldo das chaves.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--roxo"><?php echo  icone_svg('alvo') ; ?></span>
        <span class="cartao-metrica__rotulo">Pool automatico</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((float) $distribuicao['pool_automatico'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Parte dividida por peso (sem os limites manuais).</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--verde"><?php echo  icone_svg('usuario') ; ?></span>
        <span class="cartao-metrica__rotulo">Media por usuario</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((float) $distribuicao['media'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe"><?php echo  count($itens) ; ?> usuario(s) ativo(s) na divisao.</span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('info') ; ?></span>
        <span class="cartao-metrica__rotulo">Teto por usuario</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $distribuicao['teto'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">Nenhum usuario recebe mais que o teto.</span>
    </article>
</div>

<?php if ($itens === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('alvo') ; ?>
                <h3>Nada a distribuir</h3>
                <p class="texto-suave">
                    Nao ha usuarios ativos ou chaves com saldo. Cadastre chaves e aprove contas.
                </p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Usuario</th>
                    <th scope="col">Combinacoes</th>
                    <th scope="col">Modo</th>
                    <th scope="col">Cota</th>
                    <th scope="col">Usado no mes</th>
                    <th scope="col">Restante</th>
                    <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $id => $item){ ?>
                    <?php
                    $usuario  = valor_ou($mapaUsuarios, (string) (int) $id, null);
                    $cota     = (int) $item['cota'];
                    $usada    = inteiro_ou($usuario, 'consultas_usadas_mes', 0);
                    $restante = max(0, $cota - $usada);
                    ?>
                    <tr>
                        <th scope="row">
                            <?php if ($usuario !== null){ ?>
                                <a href="<?php echo  site_url('admin/usuarios/' . (int) $id) ; ?>"><?php echo  esc($usuario['nome']) ; ?></a>
                                <span class="texto-suave texto-pequeno celula-bloco"><?php echo  esc($usuario['email']) ; ?></span>
                            <?php } else { ?>
                                #<?php echo  (int) $id ; ?>
                            <?php } ?>
                        </th>
                        <td><?php echo  (int) $item['combinacoes'] ; ?></td>
                        <td>
                            <?php if ($item['manual'] !== null){ ?>
                                <span class="selo selo--aviso">Manual (<?php echo  (int) $item['manual'] ; ?>)</span>
                            <?php } else { ?>
                                <span class="selo selo--neutro">Automatico</span>
                            <?php } ?>
                        </td>
                        <td><?php echo  number_format($cota, 0, ',', '.') ; ?></td>
                        <td><?php echo  number_format($usada, 0, ',', '.') ; ?></td>
                        <td><?php echo  number_format($restante, 0, ',', '.') ; ?></td>
                        <td>
                            <div class="acoes-tabela">
                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('admin/usuarios/' . (int) $id) ; ?>">
                                    <?php echo  icone_svg('alvo') ; ?> Ajustar limite
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php } ?>

<?php echo  $this->endSection() ; ?>
