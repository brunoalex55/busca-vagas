<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: RECURSOS
 * --------------------------------------------------------------------------
 *
 * Detalha cada recurso do sistema, com foco em beneficio para o usuario.
 */

$this->extend('layouts/site');

$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}
?>

<?php echo  $this->section('conteudo') ; ?>

<?php /* Cabecalho interno da pagina. */ ?>
<section class="pagina-topo">
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>
    <div class="container pagina-topo__interno">
        <nav class="migalhas" aria-label="Voce esta aqui">
            <a href="<?php echo  site_url('/') ; ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <span>Recursos</span>
        </nav>
        <h1 class="pagina-topo__titulo">
            Recursos feitos para <span class="texto-gradiente">acelerar sua contratacao</span>
        </h1>
        <p class="pagina-topo__subtitulo">
            Cada detalhe do sistema existe para reduzir o esforco de se candidatar e
            aumentar o numero de oportunidades reais na sua frente.
        </p>
        <div class="pagina-topo__acoes">
            <a class="botao botao--primario botao--grande" href="<?php echo  site_url('registro') ; ?>">
                Comece gratis agora
            </a>
            <a class="botao botao--secundario botao--grande" href="<?php echo  site_url('precos') ; ?>">
                Ver planos
            </a>
        </div>
    </div>
</section>

<?php /* Lista detalhada de recursos. */ ?>
<section class="secao">
    <div class="container">
        <div class="grade-cartoes grade-cartoes--detalhada">

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('lupa') ; ?></span>
                <h2 class="cartao-recurso__titulo">Busca automatica no Google Jobs</h2>
                <p class="cartao-recurso__texto">
                    O sistema consulta o Google Jobs com as suas palavras-chave e
                    cidades. Cada combinacao de termo e cidade gera uma busca propria,
                    garantindo cobertura ampla sem voce abrir um site por vez.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Varias cidades por palavra-chave</li>
                    <li><?php echo  icone_svg('check') ; ?> Filtro para vagas remotas</li>
                    <li><?php echo  icone_svg('check') ; ?> Rotina automatica no servidor</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('email') ; ?></span>
                <h2 class="cartao-recurso__titulo">Candidatura por e-mail</h2>
                <p class="cartao-recurso__texto">
                    Quando a vaga tem um e-mail de contato, o sistema monta o assunto e o
                    corpo da mensagem com os seus dados e envia o curriculo em PDF anexo.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Variaveis automaticas no texto</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio manual com 1 clique</li>
                    <li><?php echo  icone_svg('check') ; ?> Registro de cada candidatura</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('relogio') ; ?></span>
                <h2 class="cartao-recurso__titulo">Envio automatico com pausas</h2>
                <p class="cartao-recurso__texto">
                    Voce define quantos e-mails por dia, em quais horarios e em quais dias
                    da semana. O sistema envia um a um, com intervalos aleatorios entre o
                    minimo e o maximo configurados.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Limite diario configuravel</li>
                    <li><?php echo  icone_svg('check') ; ?> Horario de inicio e fim</li>
                    <li><?php echo  icone_svg('check') ; ?> Dias da semana selecionaveis</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('alvo') ; ?></span>
                <h2 class="cartao-recurso__titulo">Extracao de e-mail em duas etapas</h2>
                <p class="cartao-recurso__texto">
                    Primeiro o sistema procura e-mails na descricao da vaga e nos links de
                    candidatura. Se nao encontrar, tenta acessar a pagina original da vaga.
                    Tudo com filtro de e-mails invalidos e prioridade para contatos de RH.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Prioridade para rh@ e recrutamento@</li>
                    <li><?php echo  icone_svg('check') ; ?> Lista de prefixos descartados</li>
                    <li><?php echo  icone_svg('check') ; ?> Badge de confianca no painel</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('escudo') ; ?></span>
                <h2 class="cartao-recurso__titulo">Seguranca por usuario</h2>
                <p class="cartao-recurso__texto">
                    Nao existe conta de e-mail compartilhada. Cada usuario cadastra o
                    proprio Gmail e o proprio APP PASSWORD, armazenado com criptografia
                    AES-256-CBC.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> APP PASSWORD nunca exibido</li>
                    <li><?php echo  icone_svg('check') ; ?> Senha de acesso com bcrypt</li>
                    <li><?php echo  icone_svg('check') ; ?> Sessao nativa do CodeIgniter</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('documento') ; ?></span>
                <h2 class="cartao-recurso__titulo">Curriculo sempre em anexo</h2>
                <p class="cartao-recurso__texto">
                    Envie seu PDF uma unica vez. Todas as candidaturas saem com o arquivo
                    anexado, sem selecao manual e sem esquecimento.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Upload com validacao no servidor</li>
                    <li><?php echo  icone_svg('check') ; ?> Arquivo fora do diretorio publico</li>
                    <li><?php echo  icone_svg('check') ; ?> Substituicao pelo painel</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('raio') ; ?></span>
                <h2 class="cartao-recurso__titulo">Painel organizado por fonte</h2>
                <p class="cartao-recurso__texto">
                    As vagas aparecem em abas: com e-mail, Indeed, LinkedIn, Catho, Gupy e
                    outros. Dentro de cada aba, as oportunidades com e-mail sempre vem
                    primeiro.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Ordenacao por prioridade</li>
                    <li><?php echo  icone_svg('check') ; ?> Badges de status</li>
                    <li><?php echo  icone_svg('check') ; ?> Sem vagas duplicadas</li>
                </ul>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('site') ; ?></span>
                <h2 class="cartao-recurso__titulo">Relatorios e historico</h2>
                <p class="cartao-recurso__texto">
                    Acompanhe quantos e-mails foram enviados, por qual origem e o resultado
                    de cada candidatura. Voce sabe exatamente o que o sistema fez por voce.
                </p>
                <ul class="lista-checagem">
                    <li><?php echo  icone_svg('check') ; ?> Envios por manual, massa e automatico</li>
                    <li><?php echo  icone_svg('check') ; ?> Historico de consultas</li>
                    <li><?php echo  icone_svg('check') ; ?> Cota mensal transparente</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<?php /* CTA. */ ?>
<section class="cta-final">
    <div class="container cta-final__interno animar ao-rolar">
        <h2 class="cta-final__titulo">Pronto para comecar?</h2>
        <p class="cta-final__subtitulo">
            Crie sua conta e configure tudo em menos de dez minutos.
        </p>
        <a class="botao botao--claro botao--grande" href="<?php echo  site_url('registro') ; ?>">
            Comece gratis agora <?php echo  icone_svg('seta') ; ?>
        </a>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
