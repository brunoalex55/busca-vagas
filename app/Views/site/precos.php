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
 * VIEW: PRECOS
 * --------------------------------------------------------------------------
 *
 * Tabela de planos, comparativo de recursos e duvidas sobre pagamento.
 * Nao existe tabela de planos no banco: os valores sao de marketing.
 */

$this->extend('layouts/site');
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="pagina-topo">
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>
    <div class="container pagina-topo__interno">
        <nav class="migalhas" aria-label="Voce esta aqui">
            <a href="<?php echo  site_url('/') ; ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <span>Preços</span>
        </nav>
        <h1 class="pagina-topo__titulo">
            Planos claros, <span class="texto-gradiente">sem fidelidade</span>
        </h1>
        <p class="pagina-topo__subtitulo">
            Sem fidelidade. Cancele quando quiser.
        </p>
    </div>
</section>

<?php /* Planos. */ ?>
<section class="secao">
    <div class="container">
        <div class="planos">

            <article class="plano animar ao-rolar">
                <header class="plano__cabecalho">
                    <h2 class="plano__nome">Básico</h2>
                    <p class="plano__descricao">Para quem está começando a busca.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>29</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> 2 palavras-chave</li>
                    <li><?php echo  icone_svg('check') ; ?> 5 cidades por termo</li>
                    <li><?php echo  icone_svg('check') ; ?> 10 envios por dia</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio automático: manual</li>
                    <li class="plano__item-indisponivel"><?php echo  icone_svg('cadeado') ; ?> Envio em massa</li>
                    <li><?php echo  icone_svg('check') ; ?> 1 usuário</li>
                    <li><?php echo  icone_svg('check') ; ?> Suporte por e-mail</li>
                </ul>
                <a class="botao botao--secundario botao--bloco" href="<?php echo  site_url('registro') . '?plano=basico' ; ?>">
                    Começar com o Básico
                </a>
            </article>

            <article class="plano plano--destaque animar ao-rolar">
                <span class="plano__selo">Mais popular</span>
                <header class="plano__cabecalho">
                    <h2 class="plano__nome">Profissional</h2>
                    <p class="plano__descricao">Para quem quer resultado rápido.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>49</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> Palavras-chave: Ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> Cidades por termo: Ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> 20 envios por dia</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio automático ligado</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio em massa</li>
                    <li><?php echo  icone_svg('check') ; ?> 1 usuário</li>
                    <li><?php echo  icone_svg('check') ; ?> Suporte por e-mail</li>
                </ul>
                <a class="botao botao--primario botao--bloco" href="<?php echo  site_url('registro') . '?plano=profissional' ; ?>">
                    Quero o Profissional
                </a>
            </article>

            <article class="plano animar ao-rolar">
                <header class="plano__cabecalho">
                    <h2 class="plano__nome">Empresa</h2>
                    <p class="plano__descricao">Para equipes de recrutamento.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>99</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> Palavras-chave: Ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> Cidades por termo: Ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> 50 envios por dia por usuário</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio automático ligado</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio em massa</li>
                    <li><?php echo  icone_svg('check') ; ?> Até 5 usuários</li>
                    <li><?php echo  icone_svg('check') ; ?> Suporte prioritário</li>
                </ul>
                <a class="botao botao--secundario botao--bloco" href="<?php echo  site_url('contato') ; ?>">
                    Falar com vendas
                </a>
            </article>
        </div>

        <p class="planos__aviso">
            <?php echo  icone_svg('escudo') ; ?>
            Pagamento via PIX. Liberação manual em até 24 horas úteis.
        </p>
    </div>
</section>

<?php /* Comparativo. */ ?>
<section class="secao secao--alternada">
    <div class="container">
        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Comparativo</span>
            <h2 class="secao__titulo">O que muda entre os planos</h2>
        </header>

        <div class="tabela-responsiva animar ao-rolar">
            <table class="tabela">
                <caption class="sr-only">Comparativo de recursos entre os planos</caption>
                <thead>
                    <tr>
                        <th scope="col">Recurso</th>
                        <th scope="col">Básico</th>
                        <th scope="col">Profissional</th>
                        <th scope="col">Empresa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Palavras-chave</th>
                        <td>2</td>
                        <td>Ilimitadas</td>
                        <td>Ilimitadas</td>
                    </tr>
                    <tr>
                        <th scope="row">Cidades por termo</th>
                        <td>5</td>
                        <td>Ilimitadas</td>
                        <td>Ilimitadas</td>
                    </tr>
                    <tr>
                        <th scope="row">Envios por dia</th>
                        <td>10</td>
                        <td>20</td>
                        <td>50 por usuário</td>
                    </tr>
                    <tr>
                        <th scope="row">Envio automático</th>
                        <td>Manual</td>
                        <td>Ligado</td>
                        <td>Ligado</td>
                    </tr>
                    <tr>
                        <th scope="row">Envio em massa</th>
                        <td>&mdash;</td>
                        <td><?php echo  icone_svg('check') ; ?></td>
                        <td><?php echo  icone_svg('check') ; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Usuários</th>
                        <td>1</td>
                        <td>1</td>
                        <td>Até 5</td>
                    </tr>
                    <tr>
                        <th scope="row">Suporte</th>
                        <td>E-mail</td>
                        <td>E-mail</td>
                        <td>Prioritário</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php /* Duvidas sobre pagamento. */ ?>
<section class="secao">
    <div class="container container--estreito">
        <header class="secao__cabecalho animar ao-rolar">
            <h2 class="secao__titulo">Duvidas sobre pagamento</h2>
        </header>

        <div class="faq">
            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Como faco o pagamento?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        O pagamento e via PIX. Depois de criar a conta, voce ve a chave na
                        tela de aguardando aprovacao e clica em "Informar pagamento".
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Em quanto tempo o acesso e liberado?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        A liberacao e manual e acontece em ate 24 horas uteis apos a
                        confirmacao do pagamento.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Existe fidelidade ou multa?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Nao. Voce pode cancelar quando quiser, sem multa e sem burocracia.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Posso mudar de plano depois?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Sim. Basta falar com o nosso time e ajustamos o seu plano para o
                        proximo ciclo.
                    </p>
                </div>
            </details>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="container cta-final__interno animar ao-rolar">
        <h2 class="cta-final__titulo">Comece pelo plano que cabe no seu bolso</h2>
        <p class="cta-final__subtitulo">
            Sem contrato. Cancele quando quiser.
        </p>
        <a class="botao botao--claro botao--grande" href="<?php echo  site_url('registro') ; ?>">
            Criar minha conta <?php echo  icone_svg('seta') ; ?>
        </a>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
