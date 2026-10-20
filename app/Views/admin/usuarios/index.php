<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - LISTA DE USUARIOS
 * --------------------------------------------------------------------------
 *
 * Listagem com filtros (busca, status, perfil, envio automatico), a cota
 * calculada de cada conta e a paginacao.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Usuarios';

/**
 * Devolve a classe e o rotulo do selo conforme o status da conta.
 */
$seloStatus = static function (string $status): array {
    return match ($status) {
        'ativo'     => ['selo--sucesso', 'Ativo'],
        'bloqueado' => ['selo--erro', 'Bloqueado'],
        default     => ['selo--aviso', 'Pendente'],
    };
};

// Ha algum filtro ativo? (para mostrar o botao Limpar)
$temFiltro = $filtros['busca'] !== '' || $filtros['status'] !== ''
    || $filtros['perfil'] !== '' || $filtros['envio'] !== '';
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Usuarios</h2>
        <p class="painel__descricao">
            <?php echo  number_format((int) $total, 0, ',', '.') ; ?> conta(s) encontrada(s).
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('admin/solicitacoes') ; ?>">
            <?php echo  icone_svg('documento') ; ?> Solicitacoes
        </a>
    </div>
</section>

<?php /* Resumo por status. */ ?>
<div class="linha-selos">
    <span class="selo selo--neutro">Total: <?php echo  (int) $estatisticas['total'] ; ?></span>
    <span class="selo selo--sucesso">Ativos: <?php echo  (int) $estatisticas['ativo'] ; ?></span>
    <span class="selo selo--aviso">Pendentes: <?php echo  (int) $estatisticas['pendente'] ; ?></span>
    <span class="selo selo--erro">Bloqueados: <?php echo  (int) $estatisticas['bloqueado'] ; ?></span>
    <span class="selo selo--admin">Admins: <?php echo  (int) $estatisticas['admins'] ; ?></span>
</div>

<?php /* Filtros. */ ?>
<form class="filtros" method="get" action="<?php echo  site_url('admin/usuarios') ; ?>">
    <div class="filtros__grade">
        <div class="campo">
            <label class="campo__rotulo" for="busca">Buscar</label>
            <input class="campo__entrada" type="search" id="busca" name="busca"
                   value="<?php echo  esc($filtros['busca']) ; ?>" placeholder="Nome ou e-mail">
        </div>

        <div class="campo">
            <label class="campo__rotulo" for="status">Situacao</label>
            <select class="campo__entrada" id="status" name="status">
                <option value="">Todas</option>
                <option value="ativo" <?php echo  escolher($filtros['status'] === 'ativo', 'selected', '') ; ?>>Ativo</option>
                <option value="pendente" <?php echo  escolher($filtros['status'] === 'pendente', 'selected', '') ; ?>>Pendente</option>
                <option value="bloqueado" <?php echo  escolher($filtros['status'] === 'bloqueado', 'selected', '') ; ?>>Bloqueado</option>
            </select>
        </div>

        <div class="campo">
            <label class="campo__rotulo" for="perfil">Perfil</label>
            <select class="campo__entrada" id="perfil" name="perfil">
                <option value="">Todos</option>
                <option value="admin" <?php echo  escolher($filtros['perfil'] === 'admin', 'selected', '') ; ?>>Administrador</option>
                <option value="usuario" <?php echo  escolher($filtros['perfil'] === 'usuario', 'selected', '') ; ?>>Usuario</option>
            </select>
        </div>

        <div class="filtros__checks">
            <label class="campo-check">
                <input type="checkbox" name="envio" value="1" <?php echo  escolher($filtros['envio'] === '1', 'checked', '') ; ?>>
                <span>Somente envio automatico ligado</span>
            </label>
        </div>
    </div>

    <div class="filtros__acoes">
        <?php if ($temFiltro){ ?>
            <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/usuarios') ; ?>">Limpar</a>
        <?php } ?>
        <button type="submit" class="botao botao--primario botao--pequeno">
            <?php echo  icone_svg('lupa') ; ?> Filtrar
        </button>
    </div>
</form>

<?php if ($usuarios === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('usuario') ; ?>
                <h3>Nenhuma conta encontrada</h3>
                <p class="texto-suave">Ajuste os filtros ou aguarde novos cadastros.</p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Conta</th>
                    <th scope="col">Perfil</th>
                    <th scope="col">Situacao</th>
                    <th scope="col">Cota do mes</th>
                    <th scope="col">Envio auto</th>
                    <th scope="col">Cadastro</th>
                    <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $conta){ ?>
                    <?php
                    $id        = (int) $conta['id'];
                    $cota      = inteiro_ou($distribuicao['usuarios'][$id], 'cota', 0);
                    $usada     = (int) $conta['consultas_usadas_mes'];
                    [$classeSelo, $rotuloSelo] = $seloStatus((string) $conta['status']);
                    ?>
                    <tr>
                        <th scope="row">
                            <a href="<?php echo  site_url('admin/usuarios/' . $id) ; ?>"><?php echo  esc($conta['nome']) ; ?></a>
                            <span class="texto-suave texto-pequeno celula-bloco"><?php echo  esc($conta['email']) ; ?></span>
                        </th>
                        <td>
                            <span class="selo <?php echo  escolher($conta['perfil'] === 'admin', 'selo--admin', 'selo--neutro') ; ?>">
                                <?php echo  escolher($conta['perfil'] === 'admin', 'Admin', 'Usuario') ; ?>
                            </span>
                        </td>
                        <td>
                            <span class="selo <?php echo  $classeSelo ; ?>"><?php echo  $rotuloSelo ; ?></span>
                            <?php if ((int) $conta['ativo'] === 0){ ?>
                                <span class="selo selo--erro">Inativo</span>
                            <?php } ?>
                            <?php /* O plano pedido pelo usuario (o admin precisa saber). */ ?>
                            <?php $planoConta = texto_ou($conta, 'plano_nome', ''); ?>
                            <?php if ($planoConta !== ''){ ?>
                                <span class="selo selo--primario"><?php echo esc($planoConta) ; ?></span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php echo  number_format($usada, 0, ',', '.') ; ?> / <?php echo  number_format($cota, 0, ',', '.') ; ?>
                            <?php if ($conta['limite_manual'] !== null){ ?>
                                <span class="selo selo--aviso texto-pequeno">manual</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php echo  escolher((int) $conta['envio_automatico'] === 1,
                                '<span class="selo selo--sucesso">Ligado</span>',
                                '<span class="selo selo--neutro">Desligado</span>') ; ?>
                        </td>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($conta, 'criado_em', null), false)) ; ?></td>
                        <td>
                            <div class="acoes-tabela">
                                <a class="botao botao--fantasma botao--pequeno"
                                   href="<?php echo  site_url('admin/usuarios/' . $id) ; ?>">
                                    <?php echo  icone_svg('lapis') ; ?> Ficha
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php } ?>

<?php echo  $this->include('partials/paginacao') ; ?>

<?php echo  $this->endSection() ; ?>
