<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: PREÇOS
 * --------------------------------------------------------------------------
 *
 * Tabela de planos, comparativo de recursos e duvidas sobre pagamento.
 *
 * Os planos mostrados aqui sao os MESMOS que o administrador configura
 * em /admin/planos (tabela `planos`): o que o site promete e o que o
 * sistema cobra. O nome tecnico do arquivo continua "precos.php" (por
 * compatibilidade), mas o texto exibido ao usuario e "Preços".
 */

$this->extend('layouts/site');

// Os planos exibidos no site.
// Cada item: o rotulo, o texto e a lista de recursos.
$planosSite = [
    [
        'slug'       => 'basico',
        'nome'       => 'Básico',
        'descricao'  => 'Para quem está comecando a busca.',
        'preco'      => '29,90',
        'destaque'   => false,
        'botao'      => 'Começar com o Básico',
        'botao_link' => site_url('registro') . '?plano=basico',
        'recursos'   => [
            ['texto' => '2 palavras-chave', 'disponivel' => true],
            ['texto' => '5 cidades por termo', 'disponivel' => true],
            ['texto' => '10 envios por dia', 'disponivel' => true],
            ['texto' => 'Envio automático: manual', 'disponivel' => true],
            ['texto' => 'Envio em massa', 'disponivel' => false],
            ['texto' => '1 usuário', 'disponivel' => true],
            ['texto' => 'Suporte por e-mail', 'disponivel' => true],
        ],
    ],
    [
        'slug'       => 'profissional',
        'nome'       => 'Profissional',
        'descricao'  => 'Para quem quer resultado rápido.',
        'preco'      => '49,90',
        'destaque'   => true,
        'botao'      => 'Quero o Profissional',
        'botao_link' => site_url('registro') . '?plano=profissional',
        'recursos'   => [
            ['texto' => 'Palavras-chave: Ilimitadas', 'disponivel' => true],
            ['texto' => 'Cidades por termo: Ilimitadas', 'disponivel' => true],
            ['texto' => '20 envios por dia', 'disponivel' => true],
            ['texto' => 'Envio automático ligado', 'disponivel' => true],
            ['texto' => 'Envio em massa', 'disponivel' => true],
            ['texto' => '1 usuário', 'disponivel' => true],
            ['texto' => 'Suporte por e-mail', 'disponivel' => true],
        ],
    ],
    [
        'slug'       => 'empresa',
        'nome'       => 'Empresa',
        'descricao'  => 'Para equipes de recrutamento.',
        'preco'      => '99,90',
        'destaque'   => false,
        'botao'      => 'Falar com vendas',
        'botao_link' => site_url('contato'),
        'recursos'   => [
            ['texto' => 'Palavras-chave: Ilimitadas', 'disponivel' => true],
            ['texto' => 'Cidades por termo: Ilimitadas', 'disponivel' => true],
            ['texto' => '50 envios por dia por usuário', 'disponivel' => true],
            ['texto' => 'Envio automático ligado', 'disponivel' => true],
            ['texto' => 'Envio em massa', 'disponivel' => true],
            ['texto' => 'Até 5 usuários', 'disponivel' => true],
            ['texto' => 'Suporte prioritário', 'disponivel' => true],
        ],
    ],
];
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

            <?php foreach ($planosSite as $plano) { ?>
                <?php if ($plano['destaque']) { ?>
                    <article class="plano plano--destaque animar ao-rolar">
                        <span class="plano__selo">Mais popular</span>
                <?php } else { ?>
                    <article class="plano animar ao-rolar">
                <?php } ?>

                        <header class="plano__cabecalho">
                            <h2 class="plano__nome"><?php echo esc($plano['nome']) ; ?></h2>
                            <p class="plano__descricao"><?php echo esc($plano['descricao']) ; ?></p>
                            <p class="plano__preco">
                                <span class="plano__moeda">R$</span>
                                <strong><?php echo esc(explode(',', $plano['preco'])[0]) ; ?></strong>
                                <span class="plano__centavos"><?php echo esc(explode(',', $plano['preco'])[1]) ; ?>/mes</span>
                            </p>
                        </header>

                        <ul class="plano__lista">
                            <?php foreach ($plano['recursos'] as $recurso) { ?>
                                <?php if ($recurso['disponivel']) { ?>
                                    <li><?php echo  icone_svg('check') ; ?> <?php echo esc($recurso['texto']) ; ?></li>
                                <?php } else { ?>
                                    <li class="plano__item-indisponivel">
                                        <?php echo  icone_svg('cadeado') ; ?> <?php echo esc($recurso['texto']) ; ?>
                                    </li>
                                <?php } ?>
                            <?php } ?>
                        </ul>

                        <a class="botao <?php if ($plano['destaque']) { echo 'botao--primario' ; } else { echo 'botao--secundario' ; } ?> botao--bloco"
                           href="<?php echo esc($plano['botao_link']) ; ?>">
                            <?php echo esc($plano['botao']) ; ?>
                        </a>
                    </article>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* Como contratar. */ ?>
<section class="secao secao--clara">
    <div class="container">
        <div class="caixa-caixa">
            <h2 class="secao__titulo">Como funciona a contratação</h2>
            <ol class="lista-passos">
                <li>
                    Escolha o plano aqui e crie sua conta (o plano escolhido já
                    vai junto no cadastro).
                </li>
                <li>
                    O administrador recebe o seu pedido e confirma o pagamento
                    do Pix.
                </li>
                <li>
                    Com a aprovação, a sua conta entra no plano escolhido e
                    os limites entram em vigor.
                </li>
                <li>
                    Quer trocar de plano? Fale com o administrador: a mudança é
                    feita no painel, sem precisar criar outra conta.
                </li>
            </ol>
        </div>
    </div>
</section>
