<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: COMO FUNCIONA
 * --------------------------------------------------------------------------
 *
 * Passo a passo detalhado, com exemplos e explicacao das regras de envio.
 */

$this->extend('layouts/site');
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="pagina-topo">
    <div class="hero__blob hero__blob--2" aria-hidden="true"></div>
    <div class="container pagina-topo__interno">
        <nav class="migalhas" aria-label="Voce esta aqui">
            <a href="<?php echo  site_url('/') ; ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <span>Como funciona</span>
        </nav>
        <h1 class="pagina-topo__titulo">
            Do cadastro ao envio: <span class="texto-gradiente">tudo explicado</span>
        </h1>
        <p class="pagina-topo__subtitulo">
            Veja exatamente o que acontece em cada etapa, desde a criacao da conta
            ate o e-mail sair com o seu curriculo em anexo.
        </p>
    </div>
</section>

<?php /* Passo 1. */ ?>
<section class="secao">
    <div class="container">
        <div class="bloco-passos">

            <article class="bloco-passo animar ao-rolar">
                <div class="bloco-passo__marca">
                    <span class="bloco-passo__numero">1</span>
                    <span class="bloco-passo__linha" aria-hidden="true"></span>
                </div>
                <div class="bloco-passo__conteudo">
                    <h2 class="bloco-passo__titulo">Crie sua conta e libere o acesso</h2>
                    <p class="bloco-passo__texto">
                        O cadastro e gratuito. Voce cai na tela de aguardando aprovacao,
                        onde ve a chave PIX e as instrucoes. Depois de pagar, clique em
                        "Informar pagamento" e aguarde a liberacao manual do nosso time.
                    </p>
                    <ul class="lista-checagem">
                        <li><?php echo  icone_svg('check') ; ?> Cadastro com e-mail e senha</li>
                        <li><?php echo  icone_svg('check') ; ?> Pagamento por PIX</li>
                        <li><?php echo  icone_svg('check') ; ?> Liberacao em ate 24 horas uteis</li>
                    </ul>
                </div>
            </article>

            <?php /* Passo 2. */ ?>
            <article class="bloco-passo animar ao-rolar">
                <div class="bloco-passo__marca">
                    <span class="bloco-passo__numero">2</span>
                    <span class="bloco-passo__linha" aria-hidden="true"></span>
                </div>
                <div class="bloco-passo__conteudo">
                    <h2 class="bloco-passo__titulo">Cadastre palavras-chave e cidades</h2>
                    <p class="bloco-passo__texto">
                        Informe o cargo que voce busca e uma cidade por linha. O sistema
                        cria automaticamente uma busca para cada combinacao. Voce tambem
                        pode marcar a opcao de vagas remotas.
                    </p>
                    <ul class="lista-checagem">
                        <li><?php echo  icone_svg('check') ; ?> Exemplo de termo: "Desenvolvedor PHP"</li>
                        <li><?php echo  icone_svg('check') ; ?> Exemplo de cidades: "Sao Paulo", "Campinas"</li>
                        <li><?php echo  icone_svg('check') ; ?> Quantas palavras-chave quiser</li>
                    </ul>
                </div>
            </article>

            <?php /* Passo 3. */ ?>
            <article class="bloco-passo animar ao-rolar">
                <div class="bloco-passo__marca">
                    <span class="bloco-passo__numero">3</span>
                    <span class="bloco-passo__linha" aria-hidden="true"></span>
                </div>
                <div class="bloco-passo__conteudo">
                    <h2 class="bloco-passo__titulo">Configure e-mail, APP PASSWORD e curriculo</h2>
                    <p class="bloco-passo__texto">
                        Cadastre o seu Gmail, gere um APP PASSWORD na conta Google e cole no
                        painel. Envie o curriculo em PDF e ajuste o assunto e o corpo padrao
                        da mensagem.
                    </p>
                    <ul class="lista-checagem">
                        <li><?php echo  icone_svg('check') ; ?> APP PASSWORD criptografado (AES-256-CBC)</li>
                        <li><?php echo  icone_svg('check') ; ?> Curriculo em PDF fora do diretorio publico</li>
                        <li><?php echo  icone_svg('check') ; ?> Variaveis {titulo_vaga} e {empresa} no texto</li>
                    </ul>
                </div>
            </article>

            <?php /* Passo 4. */ ?>
            <article class="bloco-passo animar ao-rolar">
                <div class="bloco-passo__marca">
                    <span class="bloco-passo__numero">4</span>
                    <span class="bloco-passo__linha" aria-hidden="true"></span>
                </div>
                <div class="bloco-passo__conteudo">
                    <h2 class="bloco-passo__titulo">Defina os limites de envio</h2>
                    <p class="bloco-passo__texto">
                        Escolha quantos e-mails por dia, o intervalo minimo e maximo entre
                        envios, o horario permitido e os dias da semana. Essas regras
                        protegem a sua conta Gmail.
                    </p>
                    <ul class="lista-checagem">
                        <li><?php echo  icone_svg('check') ; ?> Padrao: 20 e-mails por dia</li>
                        <li><?php echo  icone_svg('check') ; ?> Intervalo aleatorio entre 3 e 7 minutos</li>
                        <li><?php echo  icone_svg('check') ; ?> Envio apenas em dias uteis, das 8h as 20h</li>
                    </ul>
                </div>
            </article>

            <?php /* Passo 5. */ ?>
            <article class="bloco-passo animar ao-rolar">
                <div class="bloco-passo__marca">
                    <span class="bloco-passo__numero">5</span>
                    <span class="bloco-passo__linha" aria-hidden="true"></span>
                </div>
                <div class="bloco-passo__conteudo">
                    <h2 class="bloco-passo__titulo">Ative o envio automatico e acompanhe</h2>
                    <p class="bloco-passo__texto">
                        Com o envio automatico ligado, o sistema busca as vagas, encontra os
                        e-mails de contato e envia uma candidatura por vez. Voce acompanha
                        tudo pelos cards do painel.
                    </p>
                    <ul class="lista-checagem">
                        <li><?php echo  icone_svg('check') ; ?> Envios hoje / limite diario</li>
                        <li><?php echo  icone_svg('check') ; ?> Proximo envio em minutos</li>
                        <li><?php echo  icone_svg('check') ; ?> Historico completo de candidaturas</li>
                    </ul>
                </div>
            </article>
        </div>
    </div>
</section>

<?php /* Explicacao das regras. */ ?>
<section class="secao secao--alternada">
    <div class="container">
        <header class="secao__cabecalho animar ao-rolar">
            <span class="secao__etiqueta">Transparencia</span>
            <h2 class="secao__titulo">Por que os envios tem pausas?</h2>
            <p class="secao__subtitulo">
                Nosso compromisso e com a sua conta. Enviar tudo de uma vez e o caminho
                mais rapido para o Google bloquear o seu Gmail.
            </p>
        </header>

        <div class="grade-cartoes">
            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('relogio') ; ?></span>
                <h3 class="cartao-recurso__titulo">Um e-mail por ciclo</h3>
                <p class="cartao-recurso__texto">
                    Cada execucao envia no maximo um e-mail por usuario. O proximo so sai
                    quando o intervalo minimo tiver passado.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('escudo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Limite diario respeitado</h3>
                <p class="cartao-recurso__texto">
                    Atingiu o limite? O sistema para e retoma no dia seguinte, com o
                    contador zerado automaticamente.
                </p>
            </article>

            <article class="cartao-recurso animar ao-rolar">
                <span class="cartao-recurso__icone"><?php echo  icone_svg('alvo') ; ?></span>
                <h3 class="cartao-recurso__titulo">Sem reenvio</h3>
                <p class="cartao-recurso__texto">
                    Cada vaga recebe uma candidatura por usuario. Depois disso ela fica
                    marcada e nao entra mais na fila.
                </p>
            </article>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="container cta-final__interno animar ao-rolar">
        <h2 class="cta-final__titulo">Vamos comecar?</h2>
        <p class="cta-final__subtitulo">
            Em poucos minutos voce tem o sistema inteiro configurado.
        </p>
        <a class="botao botao--claro botao--grande" href="<?php echo  site_url('registro') ; ?>">
            Criar minha conta <?php echo  icone_svg('seta') ; ?>
        </a>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
