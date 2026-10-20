<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - USUARIOS DO PLANO
 * --------------------------------------------------------------------------
 *
 * Quem esta no plano e o que o plano significa para cada um.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Usuarios do plano';

// O texto de um limite que pode ser ilimitado (NULL).
$textoIlimitado = 'Ilimitadas';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">
            <?php echo esc($plano['nome']) ; ?>
        </h2>
        <p class="painel__descricao">
            <?php echo esc(count($usuarios)) ; ?> usuario(s) neste plano.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/planos') ; ?>">
            Voltar
        </a>
    </div>
</section>

<?php /* As caracteristicas do plano. */ ?>
<div class="linha-selos">
    <span class="selo selo--neutro">
        Palavras-chave:
        <?php if (valor_ou($plano, 'max_palavras_chave', null) === null){ ?>
            <?php echo esc($textoIlimitado) ; ?>
        <?php } else { ?>
            <?php echo esc((int) $plano['max_palavras_chave']) ; ?>
        <?php } ?>
    </span>

    <span class="selo selo--neutro">
        Cidades por termo:
        <?php if (valor_ou($plano, 'max_cidades_por_termo', null) === null){ ?>
            <?php echo esc($textoIlimitado) ; ?>
        <?php } else { ?>
            <?php echo esc((int) $plano['max_cidades_por_termo']) ; ?>
        <?php } ?>
    </span>

    <span class="selo selo--info"><?php echo esc((int) $plano['limite_envios_dia']) ; ?> envios/dia</span>

    <span class="selo selo--neutro">Ate <?php echo esc((int) $plano['max_usuarios']) ; ?> usuario(s)</span>
</div>

<?php if ($usuarios === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('usuario') ; ?>
                <h3>Nenhum usuario neste plano</h3>
                <p class="texto-suave">Atribua o plano pela tela de detalhe do usuario.</p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Usuario</th>
                    <th scope="col">Situacao</th>
                    <th scope="col">Limite manual</th>
                    <th scope="col">Desde</th>
                    <th scope="col">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario){ ?>
                    <tr>
                        <th scope="row">
                            <span class="item-lista__titulo"><?php echo esc($usuario['nome']) ; ?></span>
                            <span class="item-lista__meta"><?php echo esc($usuario['email']) ; ?></span>
                        </th>

                        <td>
                            <?php if ($usuario['status'] === 'ativo'){ ?>
                                <span class="selo selo--sucesso">Ativo</span>
                            <?php } elseif ($usuario['status'] === 'pendente'){ ?>
                                <span class="selo selo--aviso">Pendente</span>
                            <?php } else { ?>
                                <span class="selo selo--erro">Bloqueado</span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if (valor_ou($usuario, 'limite_manual', null) !== null){ ?>
                                <span class="selo selo--aviso">
                                    <?php echo esc((int) $usuario['limite_manual']) ; ?>
                                </span>
                            <?php } else { ?>
                                <span class="texto-suave texto-pequeno">-</span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if (valor_ou($usuario, 'plano_inicio_em', null) !== null){ ?>
                                <?php echo esc(formatar_data_hora($usuario['plano_inicio_em'], false)) ; ?>
                            <?php } else { ?>
                                <span class="texto-suave texto-pequeno">-</span>
                            <?php } ?>
                        </td>

                        <td>
                            <a class="botao botao--fantasma botao--pequeno"
                               href="<?php echo  site_url('admin/usuarios/' . (int) $usuario['id']) ; ?>">
                                Abrir
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<?php echo  $this->endSection() ; ?>
