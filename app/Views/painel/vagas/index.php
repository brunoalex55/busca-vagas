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
 * VIEW: PAINEL - LISTA DE VAGAS
 * --------------------------------------------------------------------------
 *
 * Abas por provedor, filtros e paginacao.
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Vagas';

/**
 * Monta a query string preservando os filtros atuais.
 *
 * @param array<string, string> $extras
 */
$montarFiltros = static function (array $extras = []) use ($filtros): string {
    // Filtros que devem sobreviver a troca de aba.
    $parametros = [];

    if (! empty($filtros['busca'])) {
        $parametros['busca'] = $filtros['busca'];
    }

    if (! empty($filtros['com_email'])) {
        $parametros['com_email'] = '1';
    }

    if (! empty($filtros['ocultar_candidatadas'])) {
        $parametros['ocultar_candidatadas'] = '1';
    }

    return http_build_query(array_merge($parametros, $extras));
};

// URL de cada aba.
$urlAba = static function (string $aba) use ($montarFiltros): string {
    // "Todas" nao precisa aparecer na URL.
    $extras = ['fonte' => $aba];

    if ($aba === 'Todas') {
        $extras = [];
    }

    $query = $montarFiltros($extras);

    // Sem filtros: a URL fica sem interrogacao.
    if ($query === '') {
        return site_url('painel/vagas');
    }

    return site_url('painel/vagas') . '?' . $query;
};
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Vagas encontradas</h2>
        <p class="painel__descricao">
            <?php echo  number_format($total, 0, ',', '.') ; ?> vaga(s) nesta visualizacao
            &middot; <?php echo  number_format($totalFila, 0, ',', '.') ; ?> pronta(s) para candidatura por e-mail.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('painel/envio-massa') ; ?>">
            <?php echo  icone_svg('raio') ; ?> Central de envio
        </a>
    </div>
</section>

<?php /* Abas por provedor. */ ?>
<nav class="abas" aria-label="Filtrar por provedor">
    <?php foreach ($abas as $nome => $quantidade){ ?>
        <a class="aba<?php echo  escolher($filtros['fonte'] === $nome, ' aba--ativa', '') ; ?>"
           href="<?php echo  esc($urlAba((string) $nome)) ; ?>">
            <?php echo  esc($nome) ; ?>
            <span class="aba__contagem"><?php echo  number_format((int) $quantidade, 0, ',', '.') ; ?></span>
        </a>
    <?php } ?>
</nav>

<?php /* Filtros de busca. */ ?>
<form class="filtros" method="get" action="<?php echo  site_url('painel/vagas') ; ?>">
    <?php if ($filtros['fonte'] !== 'Todas'){ ?>
        <input type="hidden" name="fonte" value="<?php echo  esc($filtros['fonte']) ; ?>">
    <?php } ?>

    <div class="filtros__grade">
        <div class="campo">
            <label class="campo__rotulo" for="busca">Buscar</label>
            <input class="campo__entrada" type="search" id="busca" name="busca"
                   value="<?php echo  esc($filtros['busca']) ; ?>" placeholder="Titulo, empresa ou local">
        </div>

        <div class="filtros__checks">
            <label class="campo-check">
                <input type="checkbox" name="com_email" value="1" <?php echo  escolher($filtros['com_email'], 'checked', '') ; ?>>
                <span>Somente com e-mail</span>
            </label>

            <label class="campo-check">
                <input type="checkbox" name="ocultar_candidatadas" value="1"
                       <?php echo  escolher($filtros['ocultar_candidatadas'], 'checked', '') ; ?>>
                <span>Ocultar ja candidatadas</span>
            </label>
        </div>
    </div>

    <div class="filtros__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('painel/vagas') ; ?>">Limpar</a>
        <button type="submit" class="botao botao--primario botao--pequeno">
            <?php echo  icone_svg('lupa') ; ?> Filtrar
        </button>
    </div>
</form>

<?php if ($vagas === []){ ?>

    <?php /* Estado vazio. */ ?>
    <section class="cartao">
        <div class="cartao__corpo">
            <div class="estado-vazio">
                <?php echo  icone_svg('documento') ; ?>
                <h3>Nenhuma vaga encontrada</h3>
                <p class="texto-suave">
                    Ajuste os filtros ou aguarde a proxima execucao da busca.
                </p>
            </div>
        </div>
    </section>

<?php } else { ?>

    <div class="lista-vagas">
        <?php foreach ($vagas as $vaga){ ?>
            <?php
            // Estado da vaga.
            $candidatada = inteiro_ou($vaga, 'ja_candidatado', 0) === 1;
            $temEmail    = trim(texto_ou($vaga, 'email_contato', '')) !== '';
            $urlOriginal = url_segura(valor_ou($vaga, 'url_original', null));
            ?>
            <article class="vaga-item">

                <div class="vaga-item__topo">
                    <div class="vaga-item__identidade">
                        <h3 class="vaga-item__titulo">
                            <a href="<?php echo  site_url('painel/vagas/' . (int) $vaga['id']) ; ?>">
                                <?php echo  esc(primeiro_valor($vaga['titulo'], 'Vaga sem titulo')) ; ?>
                            </a>
                        </h3>
                        <p class="vaga-item__empresa">
                            <?php echo  esc(primeiro_valor($vaga['empresa'], 'Empresa nao informada')) ; ?>
                            <?php if (! empty($vaga['localizacao'])){ ?>
                                &middot; <?php echo  esc($vaga['localizacao']) ; ?>
                            <?php } ?>
                        </p>
                    </div>

                    <span class="selo selo--neutro"><?php echo  esc(primeiro_valor($vaga['fonte'], 'Outros')) ; ?></span>
                </div>

                <?php if (! empty($vaga['descricao'])){ ?>
                    <p class="vaga-item__descricao">
                        <?php echo  esc(resumir_texto(strip_tags($vaga['descricao']), 200)) ; ?>
                    </p>
                <?php } ?>

                <div class="vaga-item__meta">
                    <?php if (! empty($vaga['salario'])){ ?>
                        <span class="vaga-item__dado"><?php echo  icone_svg('raio') ; ?> <?php echo  esc($vaga['salario']) ; ?></span>
                    <?php } ?>

                    <?php if (! empty($vaga['tipo_contrato'])){ ?>
                        <span class="vaga-item__dado"><?php echo  esc($vaga['tipo_contrato']) ; ?></span>
                    <?php } ?>

                    <?php if (! empty($vaga['publicado_em'])){ ?>
                        <span class="vaga-item__dado">
                            <?php echo  icone_svg('calendario') ; ?> <?php echo  esc(formatar_data_hora($vaga['publicado_em'], false)) ; ?>
                        </span>
                    <?php } ?>

                    <?php if ($temEmail){ ?>
                        <span class="selo selo--sucesso">Com e-mail</span>
                    <?php } else { ?>
                        <span class="selo selo--neutro">Sem e-mail</span>
                    <?php } ?>

                    <?php if ($candidatada){ ?>
                        <span class="selo selo--info">Candidatada</span>
                    <?php } ?>

                    <?php /* Selos da candidatura pelo site do Divulga Vagas. */ ?>
                    <?php if (texto_ou($vaga, 'provedor_vaga') === 'divulgavagas'){ ?>
                        <?php echo  selo_divulgavagas($vaga) ; ?>
                    <?php } ?>
                </div>

                <div class="vaga-item__acoes">
                    <a class="botao botao--secundario botao--pequeno"
                       href="<?php echo  site_url('painel/vagas/' . (int) $vaga['id']) ; ?>">
                        Ver detalhes
                    </a>

                    <?php if ($urlOriginal !== null){ ?>
                        <a class="botao botao--fantasma botao--pequeno"
                           href="<?php echo  esc($urlOriginal) ; ?>" target="_blank" rel="noopener noreferrer">
                            Abrir vaga <?php echo  icone_svg('seta') ; ?>
                        </a>
                    <?php } ?>

                    <?php if (! $candidatada && $temEmail){ ?>
                        <form method="post"
                              action="<?php echo  site_url('painel/vagas/' . (int) $vaga['id'] . '/marcar-candidatado') ; ?>"
                              class="form-inline"
                              data-confirmar="Marcar esta vaga como candidatada?">
                            <button type="submit" class="botao botao--fantasma botao--pequeno">
                                <?php echo  icone_svg('check') ; ?> Marcar candidatada
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>

    <?php /* Paginacao. */ ?>
    <?php echo  $this->include('partials/paginacao') ; ?>

<?php } ?>

<?php echo  $this->endSection() ; ?>
