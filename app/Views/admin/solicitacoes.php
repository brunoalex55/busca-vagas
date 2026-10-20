<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - SOLICITACOES DE LIBERACAO
 * --------------------------------------------------------------------------
 *
 * Fila de solicitacoes enviadas pelos usuarios pendentes. Aprovar libera
 * a conta; rejeitar mantem o usuario pendente para corrigir e reenviar.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Solicitacoes';

// Rota base para as abas e a paginacao.
$rotaBase = 'admin/solicitacoes';

// Abas com contagem.
$abas = [
    ''          => ['rotulo' => 'Todas',     'quantidade' => (int) $contagens['total']],
    'pendente'  => ['rotulo' => 'Pendentes', 'quantidade' => (int) $contagens['pendente']],
    'aprovada'  => ['rotulo' => 'Aprovadas', 'quantidade' => (int) $contagens['aprovada']],
    'rejeitada' => ['rotulo' => 'Rejeitadas','quantidade' => (int) $contagens['rejeitada']],
];

// Selo por status da solicitacao.
$seloSolicitacao = static function (string $status): array {
    return match ($status) {
        'aprovada'  => ['selo--sucesso', 'Aprovada'],
        'rejeitada' => ['selo--erro', 'Rejeitada'],
        default     => ['selo--aviso', 'Pendente'],
    };
};
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Solicitacoes</h2>
        <p class="painel__descricao">
            <?php echo  number_format((int) $total, 0, ',', '.') ; ?> solicitacao(oes) encontrada(s).
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('admin/usuarios') ; ?>">
            <?php echo  icone_svg('usuario') ; ?> Ver usuarios
        </a>
    </div>
</section>

<?php /* Abas por status. */ ?>
<nav class="abas" aria-label="Filtrar por status">
    <?php foreach ($abas as $valor => $aba){ ?>
        <a class="aba<?php echo  escolher($filtros['status'] === $valor, ' aba--ativa', '') ; ?>"
           href="<?php echo  site_url($rotaBase . escolher($valor !== '', '?status=' . $valor, '')) ; ?>">
            <?php echo  esc($aba['rotulo']) ; ?>
            <span class="aba__contagem"><?php echo  number_format($aba['quantidade'], 0, ',', '.') ; ?></span>
        </a>
    <?php } ?>
</nav>

<?php /* Busca. */ ?>
<form class="filtros" method="get" action="<?php echo  site_url($rotaBase) ; ?>">
    <?php if ($filtros['status'] !== ''){ ?>
        <input type="hidden" name="status" value="<?php echo  esc($filtros['status']) ; ?>">
    <?php } ?>

    <div class="filtros__grade">
        <div class="campo">
            <label class="campo__rotulo" for="busca">Buscar usuario</label>
            <input class="campo__entrada" type="search" id="busca" name="busca"
                   value="<?php echo  esc($filtros['busca']) ; ?>" placeholder="Nome ou e-mail">
        </div>
    </div>

    <div class="filtros__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url($rotaBase) ; ?>">Limpar</a>
        <button type="submit" class="botao botao--primario botao--pequeno">
            <?php echo  icone_svg('lupa') ; ?> Filtrar
        </button>
    </div>
</form>

<?php if ($solicitacoes === []){ ?>

    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('documento') ; ?>
                <h3>Nenhuma solicitacao por aqui</h3>
                <p class="texto-suave">As solicitacoes de liberacao aparecerao nesta fila.</p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="tabela-responsiva">
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Observacao</th>
                    <th scope="col">Status</th>
                    <th scope="col">Respondido em</th>
                    <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitacoes as $solicitacao){ ?>
                    <?php
                    $id     = (int) $solicitacao['id'];
                    $status = (string) $solicitacao['status'];
                    [$classeSelo, $rotulo] = $seloSolicitacao($status);
                    ?>
                    <tr>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($solicitacao, 'criado_em', null))) ; ?></td>
                        <th scope="row">
                            <?php if (! empty($solicitacao['usuario_id'])){ ?>
                                <a href="<?php echo  site_url('admin/usuarios/' . (int) $solicitacao['usuario_id']) ; ?>">
                                    <?php echo  esc(valor_ou($solicitacao, 'usuario_nome', '#' . (int) $solicitacao['usuario_id'])) ; ?>
                                </a>
                                <span class="texto-suave texto-pequeno celula-bloco">
                                    <?php echo  esc(valor_ou($solicitacao, 'usuario_email', '')) ; ?>
                                </span>
                            <?php } else { ?>
                                Conta removida
                            <?php } ?>
                        </th>
                        <td><?php echo  esc(primeiro_valor(resumir_texto(texto_ou($solicitacao, 'observacao', ''), 100), '-')) ; ?></td>
                        <td><span class="selo <?php echo  $classeSelo ; ?>"><?php echo  $rotulo ; ?></span></td>
                        <td><?php echo  esc(formatar_data_hora(valor_ou($solicitacao, 'respondido_em', null))) ; ?></td>
                        <td>
                            <?php if ($status === 'pendente'){ ?>
                                <div class="acoes-tabela">
                                    <form method="post" action="<?php echo  site_url('admin/solicitacoes/' . $id . '/aprovar') ; ?>">
                                        <button type="submit" class="botao botao--primario botao--pequeno">
                                            <?php echo  icone_svg('check') ; ?> Aprovar
                                        </button>
                                    </form>

                                    <form method="post" action="<?php echo  site_url('admin/solicitacoes/' . $id . '/rejeitar') ; ?>"
                                          data-confirmar="Rejeitar esta solicitacao? O usuario continua pendente.">
                                        <button type="submit" class="botao botao--fantasma botao--perigo botao--pequeno">
                                            <?php echo  icone_svg('lixeira') ; ?> Rejeitar
                                        </button>
                                    </form>
                                </div>
                            <?php } else { ?>
                                <span class="texto-suave texto-pequeno">-</span>
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
