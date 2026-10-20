<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: LANDING PAGE (INICIO)
 * --------------------------------------------------------------------------
 *
 * Pagina mais importante do site publico. Estrutura:
 *
 *   1. Hero com titulo, subtitulo, CTAs, prova social e mockup em CSS
 *   2. Como funciona (3 passos)
 *   3. Recursos (grade de cards)
 *   4. Por que escolher (numeros animados)
 *   5. Depoimentos
 *   6. Planos
 *   7. FAQ (details/summary, sem JavaScript obrigatorio)
 *   8. CTA final
 *
 * Todos os icones sao SVG inline. Nenhuma imagem externa e usada.
 */

// Herda o layout publico.
$this->extend('layouts/site');

// Dados do site.
$site = $configuracaoSite;

if ($site === null) {
    $site = configuracao_site();
}
?>

<?php echo  $this->section('conteudo') ; ?>

<?php /* =====================================================================
 * 1. HERO
 * ===================================================================== */ ?>
<section class="hero" aria-labelledby="hero-titulo">

    <?php /* Formas decorativas de fundo (CSS puro). */ ?>
    <div class="hero__blob hero__blob--1" aria-hidden="true"></div>
    <div class="hero__blob hero__blob--2" aria-hidden="true"></div>

    <div class="container hero__interno">

        <?php /* Texto principal. */ ?>
        <div class="hero__conteudo animar ao-rolar">

            <span class="etiqueta">
                <?php echo  icone_svg('raio') ; ?>
                Busca automatica no Google Jobs
            </span>

            <h1 class="hero__titulo" id="hero-titulo">
                Encontre seu emprego em
                <span class="texto-gradiente">piloto automatico</span>
                enquanto voce faz outras coisas
            </h1>

            <p class="hero__subtitulo">
                Nosso sistema busca vagas no Google Jobs, filtra as melhores e envia
                sua candidatura por e-mail automaticamente, com seu curriculo em
                anexo. Sem voce mover um dedo.
            </p>

            <div class="hero__acoes">
                <a class="botao botao--primario botao--grande" href="<?php echo  site_url('registro') ; ?>">
                    Comece gratis agora
                    <?php echo  icone_svg('seta') ; ?>
                </a>
                <a class="botao botao--secundario botao--grande" href="#como-funciona">
                    Ver como funciona
                </a>
            </div>

            <?php /* Prova social. */ ?>
            <div class="hero__prova">
                <div class="avatares" aria-hidden="true">
                    <span class="avatar avatar--1">AS</span>
                    <span class="avatar avatar--2">RC</span>
                    <span class="avatar avatar--3">ML</span>
                    <span class="avatar avatar--4">JP</span>
                </div>
                <div class="hero__prova-texto">
                    <div class="estrelas" aria-label="Avaliacao 5 de 5 estrelas">
                        <?php for ($i = 0; $i < 5; $i++){ ?>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
                            </svg>
                        <?php } ?>
                    </div>
                    <p>
                        Ja ajudou <strong><span class="contador" data-contador="1200">1.200</span>+</strong>
                        profissionais a encontrar emprego
                    </p>
                </div>
            </div>
        </div>

        <?php /* Mockup da interface, feito 100% em CSS. */ ?>
        <div class="hero__mockup animar ao-rolar" aria-hidden="true">
            <div class="mockup">

                <?php /* Barra superior do mockup. */ ?>
                <div class="mockup__topo">
                    <span class="mockup__bolinha mockup__bolinha--vermelha"></span>
                    <span class="mockup__bolinha mockup__bolinha--amarela"></span>
                    <span class="mockup__bolinha mockup__bolinha--verde"></span>
                    <span class="mockup__url">app.buscavagaspro.com.br/painel</span>
                </div>

                <div class="mockup__corpo">

                    <?php /* Menu lateral do mockup. */ ?>
                    <aside class="mockup__menu">
                        <span class="mockup__logo"></span>
                        <span class="mockup__item mockup__item--ativo"></span>
                        <span class="mockup__item"></span>
                        <span class="mockup__item"></span>
                        <span class="mockup__item"></span>
                    </aside>

                    <?php /* Conteudo do mockup. */ ?>
                    <div class="mockup__pagina">

                        <div class="mockup__cabecalho">
                            <span class="mockup__titulo-linha"></span>
                            <span class="mockup__botao"></span>
                        </div>

                        <div class="mockup__cartoes">
                            <div class="mockup__cartao">
                                <span class="mockup__cartao-rotulo">Envios hoje</span>
                                <strong class="mockup__cartao-valor">12 / 20</strong>
                                <span class="mockup__barra"><span style="width:60%"></span></span>
                            </div>
                            <div class="mockup__cartao">
                                <span class="mockup__cartao-rotulo">Vagas encontradas</span>
                                <strong class="mockup__cartao-valor">348</strong>
                                <span class="mockup__tendencia">+24 esta semana</span>
                            </div>
                            <div class="mockup__cartao">
                                <span class="mockup__cartao-rotulo">Candidaturas</span>
                                <strong class="mockup__cartao-valor">96</strong>
                                <span class="mockup__tendencia">18 entrevistas</span>
                            </div>
                        </div>

                        <div class="mockup__lista">
                            <div class="mockup__vaga">
                                <span class="mockup__vaga-icone"></span>
                                <div class="mockup__vaga-texto">
                                    <span class="mockup__vaga-titulo"></span>
                                    <span class="mockup__vaga-sub"></span>
                                </div>
                                <span class="mockup__selo mockup__selo--verde">Enviado</span>
                            </div>
                            <div class="mockup__vaga">
                                <span class="mockup__vaga-icone"></span>
                                <div class="mockup__vaga-texto">
                                    <span class="mockup__vaga-titulo"></span>
                                    <span class="mockup__vaga-sub"></span>
                                </div>
                                <span class="mockup__selo mockup__selo--amarelo">Aguardando</span>
                            </div>
                            <div class="mockup__vaga">
                                <span class="mockup__vaga-icone"></span>
                                <div class="mockup__vaga-texto">
                                    <span class="mockup__vaga-titulo"></span>
                                    <span class="mockup__vaga-sub"></span>
                                </div>
                                <span class="mockup__selo mockup__selo--roxo">Com e-mail</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 2. COMO FUNCIONA
 * ===================================================================== */ ?>
<section class="secao" id="como-funciona" aria-labelledby="titulo-como-funciona">
    <div class="container">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Simples do inicio ao fim</span>
            <h2 class="secao__titulo" id="titulo-como-funciona">
                Tres passos e o sistema trabalha por voce
            </h2>
            <p class="secao__subtitulo">
                Voce configura uma vez. Depois, todos os dias, as vagas chegam e as
                candidaturas saem sozinhas.
            </p>
        </header>

        <div class="passos">

            <article class="passo animar ao-rolar">
                <span class="passo__numero">01</span>
                <span class="passo__icone"><?php echo  icone_svg('lupa') ; ?></span>
                <h3 class="passo__titulo">Cadastre suas palavras-chave e cidades</h3>
                <p class="passo__texto">
                    Diga o que voce procura e onde. O sistema cria uma busca para cada
                    combinacao de termo e cidade.
                </p>
            </article>

            <article class="passo animar ao-rolar">
                <span class="passo__numero">02</span>
                <span class="passo__icone"><?php echo  icone_svg('documento') ; ?></span>
                <h3 class="passo__titulo">Configure seu curriculo e e-mail</h3>
                <p class="passo__texto">
                    Envie seu PDF e o seu APP PASSWORD do Gmail. Tudo fica criptografado
                    e e usado somente por voce.
                </p>
            </article>

            <article class="passo animar ao-rolar">
                <span class="passo__numero">03</span>
                <span class="passo__icone"><?php echo  icone_svg('raio') ; ?></span>
                <h3 class="passo__titulo">Deixe o sistema trabalhar por voce</h3>
                <p class="passo__texto">
                    As vagas aparecem no painel e as candidaturas sao enviadas uma a uma,
                    com pausas seguras.
                </p>
            </article>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 3. RECURSOS
 * ===================================================================== */ ?>
<section class="secao secao--alternada" id="recursos" aria-labelledby="titulo-recursos">
    <div class="container">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Tudo em um so lugar</span>
            <h2 class="secao__titulo" id="titulo-recursos">
                Recursos que economizam horas da sua semana
            </h2>
            <p class="secao__subtitulo">
                Da busca da vaga ao envio do curriculo: o sistema cobre cada etapa da
                candidatura.
            </p>
        </header>

        <div class="grade-cartoes">

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('lupa') ; ?></span>
                <h3 class="cartao-recurso__titulo">Busca automatica no Google Jobs</h3>
                <p class="cartao-recurso__texto">
                    Consultas reais no maior agregador de vagas do mundo, com filtro por
                    cidade, termo e trabalho remoto.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('email') ; ?></span>
                <h3 class="cartao-recurso__titulo">Candidatura por e-mail com 1 clique</h3>
                <p class="cartao-recurso__texto">
                    Encontrou uma vaga com e-mail? Um clique e o curriculo sai com assunto
                    e corpo prontos.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('relogio') ; ?></span>
                <h3 class="cartao-recurso__titulo">Envio automatico</h3>
                <p class="cartao-recurso__texto">
                    Voce nao precisa nem abrir o painel. O sistema envia no horario que
                    voce definiu, respeitando os limites.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('alvo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Extracao inteligente de e-mails</h3>
                <p class="cartao-recurso__texto">
                    O sistema procura o e-mail de RH na descricao e nos links de
                    candidatura, dando prioridade aos contatos certos.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('escudo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Seu e-mail, sua conta</h3>
                <p class="cartao-recurso__texto">
                    Nada de conta compartilhada. Cada usuario usa o proprio Gmail e o
                    proprio APP PASSWORD, criptografado no banco.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('documento') ; ?></span>
                <h3 class="cartao-recurso__titulo">Curriculo anexado automaticamente</h3>
                <p class="cartao-recurso__texto">
                    Seu PDF vai em anexo em todas as candidaturas, sem voce precisar
                    selecionar o arquivo de novo.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('raio') ; ?></span>
                <h3 class="cartao-recurso__titulo">Filtro por cidade, remoto e termo</h3>
                <p class="cartao-recurso__texto">
                    Busque por varias cidades ao mesmo tempo e destaque as vagas remotas
                    quando for o seu caso.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('site') ; ?></span>
                <h3 class="cartao-recurso__titulo">Painel com todas as vagas</h3>
                <p class="cartao-recurso__texto">
                    Abas por fonte, badges de status e historico completo de candidaturas
                    em um painel limpo.
                </p>
            </article>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 4. POR QUE ESCOLHER (NUMEROS)
 * ===================================================================== */ ?>
<section class="secao secao--numeros" aria-labelledby="titulo-numeros">
    <div class="container">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta secao__etiqueta--clara">Resultado de verdade</span>
            <h2 class="secao__titulo secao__titulo--claro" id="titulo-numeros">
                Menos tempo procurando. Mais tempo vivendo.
            </h2>
        </header>

        <div class="numeros">

            <div class="numero-destaque animar ao-rolar">
                <strong class="numero-destaque__valor">
                    <span class="contador" data-contador="20">20</span>h
                </strong>
                <span class="numero-destaque__rotulo">economizadas por semana</span>
            </div>

            <div class="numero-destaque animar ao-rolar">
                <strong class="numero-destaque__valor">
                    <span class="contador" data-contador="5">5</span>x
                </strong>
                <span class="numero-destaque__rotulo">mais chances de entrevista</span>
            </div>

            <div class="numero-destaque animar ao-rolar">
                <strong class="numero-destaque__valor">
                    <span class="contador" data-contador="50">50</span>
                </strong>
                <span class="numero-destaque__rotulo">vagas por dia, sem esforco</span>
            </div>

            <div class="numero-destaque animar ao-rolar">
                <strong class="numero-destaque__valor">
                    <span class="contador" data-contador="98">98</span>%
                </strong>
                <span class="numero-destaque__rotulo">dos usuarios recomendam</span>
            </div>
        </div>

        <div class="beneficios">
            <article class="beneficio animar ao-rolar">
                <span class="beneficio__icone"><?php echo  icone_svg('relogio') ; ?></span>
                <h3 class="beneficio__titulo">Pare de repetir o mesmo formulario</h3>
                <p class="beneficio__texto">
                    Preencher cadastro em dez sites diferentes todos os dias cansa. O
                    sistema elimina essa etapa.
                </p>
            </article>

            <article class="beneficio animar ao-rolar">
                <span class="beneficio__icone"><?php echo  icone_svg('alvo') ; ?></span>
                <h3 class="beneficio__titulo">Foque nas vagas certas</h3>
                <p class="beneficio__texto">
                    A priorizacao coloca primeiro as vagas com e-mail e das fontes mais
                    relevantes.
                </p>
            </article>

            <article class="beneficio animar ao-rolar">
                <span class="beneficio__icone"><?php echo  icone_svg('escudo') ; ?></span>
                <h3 class="beneficio__titulo">Sua conta protegida</h3>
                <p class="beneficio__texto">
                    Limites diarios, horarios e intervalos aleatorios evitam que o Google
                    bloqueie o seu Gmail.
                </p>
            </article>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 5. DEPOIMENTOS
 * ===================================================================== */ ?>
<section class="secao" id="depoimentos" aria-labelledby="titulo-depoimentos">
    <div class="container">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Prova social</span>
            <h2 class="secao__titulo" id="titulo-depoimentos">
                Quem usa, recomenda
            </h2>
            <p class="secao__subtitulo">
                Historicos reais de quem parou de perder tempo em formularios.
            </p>
        </header>

        <div class="depoimentos">

            <article class="depoimento animar ao-rolar">
                <div class="estrelas" aria-label="5 de 5 estrelas">
                    <?php for ($i = 0; $i < 5; $i++){ ?>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
                        </svg>
                    <?php } ?>
                </div>
                <blockquote class="depoimento__texto">
                    "Eu gastava duas horas por dia procurando vaga e enviando curriculo.
                    Hoje abro o painel uma vez por dia e vejo tudo o que o sistema ja fez."
                </blockquote>
                <footer class="depoimento__autor">
                    <span class="avatar avatar--1" aria-hidden="true">AS</span>
                    <div>
                        <strong>Ana Souza</strong>
                        <span>Analista de Marketing</span>
                    </div>
                </footer>
            </article>

            <article class="depoimento animar ao-rolar">
                <div class="estrelas" aria-label="5 de 5 estrelas">
                    <?php for ($i = 0; $i < 5; $i++){ ?>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
                        </svg>
                    <?php } ?>
                </div>
                <blockquote class="depoimento__texto">
                    "O que mais gostei foi o controle. Eu defino quantos e-mails por dia e
                    em qual horario. O sistema respeita e eu fico tranquilo."
                </blockquote>
                <footer class="depoimento__autor">
                    <span class="avatar avatar--2" aria-hidden="true">RC</span>
                    <div>
                        <strong>Rafael Costa</strong>
                        <span>Desenvolvedor Back-end</span>
                    </div>
                </footer>
            </article>

            <article class="depoimento animar ao-rolar">
                <div class="estrelas" aria-label="5 de 5 estrelas">
                    <?php for ($i = 0; $i < 5; $i++){ ?>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
                        </svg>
                    <?php } ?>
                </div>
                <blockquote class="depoimento__texto">
                    "Consegui tres entrevistas na primeira semana. A parte de separar as
                    vagas que tem e-mail fez toda a diferenca."
                </blockquote>
                <footer class="depoimento__autor">
                    <span class="avatar avatar--3" aria-hidden="true">ML</span>
                    <div>
                        <strong>Mariana Lima</strong>
                        <span>Analista de RH</span>
                    </div>
                </footer>
            </article>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 6. PLANOS
 * ===================================================================== */ ?>
<section class="secao secao--alternada" id="planos" aria-labelledby="titulo-planos">
    <div class="container">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Planos</span>
            <h2 class="secao__titulo" id="titulo-planos">
                Escolha o plano do seu momento
            </h2>
            <p class="secao__subtitulo">
                Sem fidelidade. Cancele quando quiser.
            </p>
        </header>

        <div class="planos">

            <?php /* Plano Basico. */ ?>
            <article class="plano animar ao-rolar">
                <header class="plano__cabecalho">
                    <h3 class="plano__nome">Basico</h3>
                    <p class="plano__descricao">Para quem esta comecando a busca.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>29</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> Ate 2 palavras-chave</li>
                    <li><?php echo  icone_svg('check') ; ?> Ate 5 cidades por termo</li>
                    <li><?php echo  icone_svg('check') ; ?> 10 envios por dia</li>
                    <li><?php echo  icone_svg('check') ; ?> Busca diaria automatica</li>
                    <li><?php echo  icone_svg('check') ; ?> Painel completo</li>
                </ul>
                <a class="botao botao--secundario botao--bloco" href="<?php echo  site_url('registro') ; ?>">
                    Comecar com Basico
                </a>
            </article>

            <?php /* Plano Profissional (destaque). */ ?>
            <article class="plano plano--destaque animar ao-rolar">
                <span class="plano__selo">Mais popular</span>
                <header class="plano__cabecalho">
                    <h3 class="plano__nome">Profissional</h3>
                    <p class="plano__descricao">Para quem quer resultado rapido.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>49</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> Palavras-chave ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> Cidades ilimitadas</li>
                    <li><?php echo  icone_svg('check') ; ?> 20 envios por dia</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio automatico ligado</li>
                    <li><?php echo  icone_svg('check') ; ?> Envio em massa</li>
                    <li><?php echo  icone_svg('check') ; ?> Historico completo</li>
                </ul>
                <a class="botao botao--primario botao--bloco" href="<?php echo  site_url('registro') ; ?>">
                    Quero o Profissional
                </a>
            </article>

            <?php /* Plano Empresa. */ ?>
            <article class="plano animar ao-rolar">
                <header class="plano__cabecalho">
                    <h3 class="plano__nome">Empresa</h3>
                    <p class="plano__descricao">Para equipes de recrutamento.</p>
                    <p class="plano__preco">
                        <span class="plano__moeda">R$</span>
                        <strong>99</strong>
                        <span class="plano__centavos">,90/mes</span>
                    </p>
                </header>
                <ul class="plano__lista">
                    <li><?php echo  icone_svg('check') ; ?> Tudo do Profissional</li>
                    <li><?php echo  icone_svg('check') ; ?> Ate 5 usuarios</li>
                    <li><?php echo  icone_svg('check') ; ?> 50 envios por dia por usuario</li>
                    <li><?php echo  icone_svg('check') ; ?> Suporte prioritario</li>
                    <li><?php echo  icone_svg('check') ; ?> Relatorios consolidados</li>
                </ul>
                <a class="botao botao--secundario botao--bloco" href="<?php echo  site_url('contato') ; ?>">
                    Falar com vendas
                </a>
            </article>
        </div>

        <p class="planos__aviso">
            <?php echo  icone_svg('escudo') ; ?>
            Pagamento via PIX. Liberacao manual em ate 24 horas uteis.
        </p>
    </div>
</section>

<?php /* =====================================================================
 * 7. FAQ
 * ===================================================================== */ ?>
<section class="secao" id="faq" aria-labelledby="titulo-faq">
    <div class="container container--estreito">

        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Duvidas frequentes</span>
            <h2 class="secao__titulo" id="titulo-faq">
                Perguntas que todo mundo faz
            </h2>
        </header>

        <?php /* Accordion nativo: funciona sem JavaScript. */ ?>
        <div class="faq">

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    O sistema envia e-mail usando a minha conta do Gmail?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Sim, e somente a sua. Voce cadastra o seu e-mail e o seu APP
                        PASSWORD no painel. O valor fica criptografado no banco e e usado
                        apenas no momento do envio. Nao existe conta global do sistema.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    O Google pode bloquear minha conta?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        O risco existe em qualquer envio em massa. Por isso o sistema envia
                        um e-mail por vez, com intervalos aleatorios, limite diario, horario
                        permitido e dias da semana. Recomendamos usar uma conta dedicada.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    De onde vem as vagas?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Das buscas no Google Jobs, que agregam anuncios de portais como
                        Indeed, LinkedIn, Catho e Gupy. O sistema organiza tudo em abas por
                        fonte, priorizando as vagas com e-mail.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Preciso deixar o computador ligado?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Nao. As buscas e os envios automaticos rodam no servidor, em
                        horarios programados. Voce pode fechar o navegador.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Posso cancelar quando quiser?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Sim. Nao ha fidelidade nem multa. Voce usa enquanto fizer sentido
                        para a sua busca.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Meus dados ficam seguros?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        A senha de acesso usa hash bcrypt, o APP PASSWORD usa criptografia
                        AES-256-CBC e o curriculo fica fora do diretorio publico. Ninguem,
                        nem o administrador, ve o seu APP PASSWORD.
                    </p>
                </div>
            </details>

            <details class="faq__item animar ao-rolar">
                <summary class="faq__pergunta">
                    Como faco para comecar?
                    <span class="faq__icone" aria-hidden="true"></span>
                </summary>
                <div class="faq__resposta">
                    <p>
                        Crie sua conta gratis, faca o PIX e informe o pagamento. Assim que o
                        acesso for liberado, voce ja pode cadastrar palavras-chave e ativar
                        o envio automatico.
                    </p>
                </div>
            </details>
        </div>
    </div>
</section>

<?php /* =====================================================================
 * 8. CTA FINAL
 * ===================================================================== */ ?>
<section class="cta-final" aria-labelledby="titulo-cta">
    <div class="container cta-final__interno animar ao-rolar">
        <h2 class="cta-final__titulo" id="titulo-cta">
            Pronto para transformar sua busca por emprego?
        </h2>
        <p class="cta-final__subtitulo">
            Comece hoje e deixe a tecnologia trabalhar pela sua carreira.
            Sem contrato. Cancele quando quiser.
        </p>
        <div class="cta-final__acoes">
            <a class="botao botao--claro botao--grande" href="<?php echo  site_url('registro') ; ?>">
                Comece gratis agora
                <?php echo  icone_svg('seta') ; ?>
            </a>
            <a class="botao botao--contorno-claro botao--grande" href="<?php echo  site_url('precos') ; ?>">
                Ver planos
            </a>
        </div>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
