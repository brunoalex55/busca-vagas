<?php
/**
 * --------------------------------------------------------------------------
 * PARTIAL: PAGINACAO COMPARTILHADA
 * --------------------------------------------------------------------------
 *
 * Paginacao manual, montada em PHP e sem JavaScript.
 * Usada pelo painel do usuario e pelo painel administrativo.
 *
 * Variaveis esperadas (enviadas pela view que inclui este arquivo):
 *   $pagina       -> pagina atual
 *   $totalPaginas -> total de paginas
 *   $rotaBase     -> rota base (ex.: painel/vagas ou admin/usuarios)
 *   $filtros      -> filtros que precisam ser preservados na URL
 */

// Normaliza os filtros recebidos.
$parametros = [];

if (isset($filtros) && is_array($filtros)) {
    $parametros = $filtros;
}

// Rota base com fallback (a view que inclui este arquivo pode nao enviar a variavel).
$rotaBaseAtual = 'painel';

if (isset($rotaBase) && $rotaBase !== '') {
    $rotaBaseAtual = $rotaBase;
}

$rotaBase = $rotaBaseAtual;

/**
 * Monta a URL de uma pagina preservando os filtros atuais.
 *
 * @param int $numero Numero da pagina.
 */
$urlPagina = static function (int $numero) use ($rotaBase, $parametros): string {
    // Monta a query string com os filtros + a pagina.
    $parametros['pagina'] = $numero;

    return site_url($rotaBase . '?' . http_build_query($parametros));
};

// Pagina atual e total.
$paginaAtual  = max(1, (int) $pagina);
$totalPaginas = max(1, (int) $totalPaginas);

// Nada a paginar.
if ($totalPaginas <= 1) {
    return;
}

// Janela de paginas ao redor da atual.
$inicio = max(1, $paginaAtual - 2);
$fim    = min($totalPaginas, $paginaAtual + 2);
?>

<nav class="paginacao" aria-label="Paginacao">

    <?php /* Voltar uma pagina. */ ?>
    <?php if ($paginaAtual > 1){ ?>
        <a class="paginacao__item" href="<?php echo  esc($urlPagina($paginaAtual - 1)) ; ?>" rel="prev">Anterior</a>
    <?php } else { ?>
        <span class="paginacao__item paginacao__item--inativo" aria-disabled="true">Anterior</span>
    <?php } ?>

    <?php /* Primeira pagina + reticencias. */ ?>
    <?php if ($inicio > 1){ ?>
        <a class="paginacao__item<?php echo  escolher($paginaAtual === 1, ' paginacao__item--atual', '') ; ?>"
           href="<?php echo  esc($urlPagina(1)) ; ?>">1</a>

        <?php if ($inicio > 2){ ?>
            <span class="paginacao__reticencias" aria-hidden="true">...</span>
        <?php } ?>
    <?php } ?>

    <?php /* Paginas da janela. */ ?>
    <?php for ($numero = $inicio; $numero <= $fim; $numero++){ ?>
        <?php if ($numero === $paginaAtual){ ?>
            <span class="paginacao__item paginacao__item--atual" aria-current="page"><?php echo  $numero ; ?></span>
        <?php } else { ?>
            <a class="paginacao__item" href="<?php echo  esc($urlPagina($numero)) ; ?>"><?php echo  $numero ; ?></a>
        <?php } ?>
    <?php } ?>

    <?php /* Ultima pagina + reticencias. */ ?>
    <?php if ($fim < $totalPaginas){ ?>
        <?php if ($fim < $totalPaginas - 1){ ?>
            <span class="paginacao__reticencias" aria-hidden="true">...</span>
        <?php } ?>

        <a class="paginacao__item<?php echo escolher($paginaAtual === $totalPaginas, ' paginacao__item--atual', ''); ?>"
           href="<?php echo  esc($urlPagina($totalPaginas)) ; ?>"><?php echo  $totalPaginas ; ?></a>
    <?php } ?>

    <?php /* Avancar uma pagina. */ ?>
    <?php if ($paginaAtual < $totalPaginas){ ?>
        <a class="paginacao__item" href="<?php echo  esc($urlPagina($paginaAtual + 1)) ; ?>" rel="next">Proxima</a>
    <?php } else { ?>
        <span class="paginacao__item paginacao__item--inativo" aria-disabled="true">Proxima</span>
    <?php } ?>

</nav>
