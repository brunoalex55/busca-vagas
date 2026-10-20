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
 * VIEW: ADMIN - CONFIGURACAO DO SITE
 * --------------------------------------------------------------------------
 *
 * Duas frentes:
 *   1. identidade do site publico (textos, contatos, redes, integracoes,
 *      imagens) em um unico formulario;
 *   2. configuracao de pagamento (PIX) exibida na aprovacao.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Configuracao do site';

// Atalhos de secao.
$secoes = [
    'identidade'   => 'Identidade',
    'contato'      => 'Contato',
    'redes'        => 'Redes',
    'textos'       => 'Textos',
    'integracoes'  => 'Integracoes',
    'imagens'      => 'Imagens',
];

// Valor mensal formatado para o campo (ponto decimal).
$valorMensal = number_format((float) valor_ou($pagamento, 'valor_mensal', 0), 2, '.', '');

// Redes sociais exibidas no formulario.
$redes = [
    'facebook'     => 'Facebook',
    'instagram'    => 'Instagram',
    'twitter'      => 'X (Twitter)',
    'linkedin'     => 'LinkedIn',
    'youtube'      => 'YouTube',
    'tiktok'       => 'TikTok',
    'github'       => 'GitHub',
    'site_pessoal' => 'Site pessoal',
];
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Configuracao do site</h2>
        <p class="painel__descricao">Identidade, contatos, redes, textos e integracoes do site publico.</p>
    </div>
</section>

<nav class="abas abas--ancoras" aria-label="Secoes da configuracao">
    <?php foreach ($secoes as $id => $rotulo){ ?>
        <a class="aba" href="#<?php echo  esc($id) ; ?>"><?php echo  esc($rotulo) ; ?></a>
    <?php } ?>
</nav>

<?php /* Formulario principal (textos + imagens). */ ?>
<form class="formulario" method="post" enctype="multipart/form-data"
      action="<?php echo  site_url('admin/configuracao-site/salvar') ; ?>">

    <?php /* IDENTIDADE. */ ?>
    <section class="cartao" id="identidade">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Identidade e SEO</h3>
        </div>
        <div class="cartao__corpo">
            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="nome_site">Nome do site</label>
                    <input class="campo__entrada" type="text" id="nome_site" name="nome_site" maxlength="120" required
                           value="<?php echo  esc(old('nome_site', valor_ou($site, 'nome_site', ''))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="slogan">Slogan</label>
                    <input class="campo__entrada" type="text" id="slogan" name="slogan" maxlength="200"
                           value="<?php echo  esc(old('slogan', valor_ou($site, 'slogan', ''))) ; ?>">
                </div>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="descricao_site">Descricao (meta description)</label>
                <input class="campo__entrada" type="text" id="descricao_site" name="descricao_site" maxlength="255"
                       value="<?php echo  esc(old('descricao_site', valor_ou($site, 'descricao_site', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="palavras_chave_seo">Palavras-chave de SEO</label>
                <input class="campo__entrada" type="text" id="palavras_chave_seo" name="palavras_chave_seo" maxlength="500"
                       value="<?php echo  esc(old('palavras_chave_seo', valor_ou($site, 'palavras_chave_seo', ''))) ; ?>">
                <span class="campo__ajuda">Separe por virgula.</span>
            </div>
        </div>
    </section>

    <?php /* CONTATO. */ ?>
    <section class="cartao" id="contato">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Contato</h3>
        </div>
        <div class="cartao__corpo">
            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="email_contato">E-mail de contato</label>
                    <input class="campo__entrada" type="email" id="email_contato" name="email_contato" maxlength="180"
                           value="<?php echo  esc(old('email_contato', valor_ou($site, 'email_contato', ''))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="telefone_contato">Telefone</label>
                    <input class="campo__entrada" type="tel" id="telefone_contato" name="telefone_contato" maxlength="40"
                           data-mascara="telefone"
                           value="<?php echo  esc(old('telefone_contato', valor_ou($site, 'telefone_contato', ''))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="whatsapp_contato">WhatsApp</label>
                    <input class="campo__entrada" type="tel" id="whatsapp_contato" name="whatsapp_contato" maxlength="40"
                           data-mascara="telefone"
                           value="<?php echo  esc(old('whatsapp_contato', valor_ou($site, 'whatsapp_contato', ''))) ; ?>">
                    <span class="campo__ajuda">Apenas numeros, com DDD.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="endereco">Endereco</label>
                    <input class="campo__entrada" type="text" id="endereco" name="endereco" maxlength="255"
                           value="<?php echo  esc(old('endereco', valor_ou($site, 'endereco', ''))) ; ?>">
                </div>
            </div>
        </div>
    </section>

    <?php /* REDES SOCIAIS. */ ?>
    <section class="cartao" id="redes">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Redes sociais</h3>
        </div>
        <div class="cartao__corpo">
            <div class="formulario--duas-colunas">
                <?php foreach ($redes as $chave => $rotulo){ ?>
                    <?php $campo = 'link_' . $chave; ?>
                    <div class="campo">
                        <label class="campo__rotulo" for="<?php echo  esc($campo) ; ?>"><?php echo  esc($rotulo) ; ?></label>
                        <input class="campo__entrada" type="url" id="<?php echo  esc($campo) ; ?>" name="<?php echo  esc($campo) ; ?>"
                               maxlength="255" placeholder="https://"
                               value="<?php echo  esc(old($campo, valor_ou($site, $campo, ''))) ; ?>">
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <?php /* TEXTOS. */ ?>
    <section class="cartao" id="textos">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Textos institucionais</h3>
        </div>
        <div class="cartao__corpo">
            <div class="campo">
                <label class="campo__rotulo" for="texto_rodape">Texto do rodape</label>
                <input class="campo__entrada" type="text" id="texto_rodape" name="texto_rodape" maxlength="500"
                       value="<?php echo  esc(old('texto_rodape', valor_ou($site, 'texto_rodape', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="texto_sobre">Texto da pagina "Sobre"</label>
                <textarea class="campo__entrada campo__entrada--area" id="texto_sobre" name="texto_sobre"
                          rows="8"><?php echo  esc(old('texto_sobre', valor_ou($site, 'texto_sobre', ''))) ; ?></textarea>
            </div>
        </div>
    </section>

    <?php /* INTEGRACOES. */ ?>
    <section class="cartao" id="integracoes">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Integracoes</h3>
        </div>
        <div class="cartao__corpo">
            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="google_analytics_id">ID do Google Analytics</label>
                    <input class="campo__entrada" type="text" id="google_analytics_id" name="google_analytics_id"
                           maxlength="60" placeholder="G-XXXXXXXXXX"
                           value="<?php echo  esc(old('google_analytics_id', valor_ou($site, 'google_analytics_id', ''))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="google_search_console_code">Codigo do Search Console</label>
                    <input class="campo__entrada" type="text" id="google_search_console_code"
                           name="google_search_console_code" maxlength="2000"
                           value="<?php echo  esc(old('google_search_console_code', valor_ou($site, 'google_search_console_code', ''))) ; ?>">
                    <span class="campo__ajuda">Apenas o valor da meta tag de verificacao.</span>
                </div>
            </div>
        </div>
    </section>

    <?php /* IMAGENS (upload). */ ?>
    <section class="cartao" id="imagens">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Imagens</h3>
            <span class="selo selo--neutro">PNG, JPG, WEBP, GIF &middot; ate 2 MB</span>
        </div>
        <div class="cartao__corpo">
            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="logo">Logo</label>
                    <input class="campo__entrada" type="file" id="logo" name="logo"
                           accept="image/png,image/jpeg,image/webp,image/gif"
                           data-nome-alvo="logo-nome">
                    <span class="campo__ajuda" id="logo-nome">Deixe vazio para manter o atual.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="favicon">Favicon</label>
                    <input class="campo__entrada" type="file" id="favicon" name="favicon"
                           accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico"
                           data-nome-alvo="favicon-nome">
                    <span class="campo__ajuda" id="favicon-nome">Use PNG ou ICO.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="imagem_og">Imagem de compartilhamento (Open Graph)</label>
                    <input class="campo__entrada" type="file" id="imagem_og" name="imagem_og"
                           accept="image/png,image/jpeg,image/webp,image/gif"
                           data-nome-alvo="og-nome">
                    <span class="campo__ajuda" id="og-nome">Recomendado: 1200 x 630.</span>
                </div>
            </div>
        </div>
    </section>

    <div class="formulario__acoes formulario__acoes--fixas">
        <button type="submit" class="botao botao--primario">
            <?php echo  icone_svg('check') ; ?> Salvar configuracao do site
        </button>
    </div>
</form>

<?php /* Imagens atuais (com remocao em formularios proprios). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Imagens atuais</h3>
    </div>
    <div class="cartao__corpo">
        <div class="grade grade--tres">
            <?php
            $imagensAtuais = [
                'logo'      => ['rotulo' => 'Logo',            'campo' => valor_ou($site, 'logo', null)],
                'favicon'   => ['rotulo' => 'Favicon',         'campo' => valor_ou($site, 'favicon', null)],
                'imagem_og' => ['rotulo' => 'Compartilhamento', 'campo' => valor_ou($site, 'imagem_og', null)],
            ];
            ?>
            <?php foreach ($imagensAtuais as $chave => $dados){ ?>
                <div class="bloco-imagem">
                    <span class="bloco-imagem__rotulo"><?php echo  esc($dados['rotulo']) ; ?></span>

                    <?php if (! empty($dados['campo'])){ ?>
                        <img class="bloco-imagem__previa" src="<?php echo  esc(base_url((string) $dados['campo'])) ; ?>"
                             alt="<?php echo  esc($dados['rotulo']) ; ?>">

                        <form method="post" action="<?php echo  site_url('admin/configuracao-site/remover-imagem') ; ?>"
                              data-confirmar="Remover a imagem de <?php echo  esc(mb_strtolower($dados['rotulo'])) ; ?>?">
                            <input type="hidden" name="campo" value="<?php echo  esc($chave) ; ?>">
                            <button type="submit" class="botao botao--fantasma botao--perigo botao--pequeno">
                                <?php echo  icone_svg('lixeira') ; ?> Remover
                            </button>
                        </form>
                    <?php } else { ?>
                        <p class="texto-suave texto-pequeno">Nenhuma imagem cadastrada.</p>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* CONFIGURACAO DE PAGAMENTO. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Pagamento (PIX)</h3>
    </div>
    <div class="cartao__corpo">
        <form class="formulario" method="post" action="<?php echo  site_url('admin/configuracao-site/pagamento') ; ?>">
            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="chave_pix">Chave PIX</label>
                    <input class="campo__entrada" type="text" id="chave_pix" name="chave_pix" maxlength="255" required
                           value="<?php echo  esc(old('chave_pix', valor_ou($pagamento, 'chave_pix', ''))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="valor_mensal">Valor mensal (R$)</label>
                    <input class="campo__entrada" type="text" id="valor_mensal" name="valor_mensal" maxlength="12" required
                           value="<?php echo  esc(old('valor_mensal', $valorMensal)) ; ?>">
                    <span class="campo__ajuda">Use ponto ou virgula (ex.: 49.90).</span>
                </div>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="texto_instrucoes">Instrucoes de liberacao</label>
                <textarea class="campo__entrada campo__entrada--area" id="texto_instrucoes" name="texto_instrucoes"
                          rows="6"><?php echo  esc(old('texto_instrucoes', valor_ou($pagamento, 'texto_instrucoes', ''))) ; ?></textarea>
                <span class="campo__ajuda">Aparece para o usuario na tela de aprovacao.</span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario">
                    <?php echo  icone_svg('check') ; ?> Salvar pagamento
                </button>
            </div>
        </form>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
