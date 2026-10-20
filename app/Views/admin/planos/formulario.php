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
 * VIEW: ADMIN - FORMULARIO DE PLANO
 * --------------------------------------------------------------------------
 *
 * Criacao e edicao de um plano.
 *
 * Os campos que podem ser ilimitados (palavras-chave e cidades por
 * termo) ficam em branco quando o plano nao tem limite: em branco
 * significa ILIMITADO.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Novo plano';

if ($modo === 'editar') {
    $tituloPagina = 'Editar plano';
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao"><?php echo esc($tituloPagina) ; ?></h2>
        <p class="painel__descricao">
            As regras deste plano valem para todos os usuarios nele.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('admin/planos') ; ?>">
            Voltar
        </a>
    </div>
</section>

<form method="post" action="<?php echo  site_url('admin/planos/salvar') ; ?>" class="formulario">
    <input type="hidden" name="id" value="<?php echo esc((int) $plano['id']) ; ?>">

    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Identificacao</h3>
        </div>

        <div class="cartao__corpo">
            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="nome">Nome do plano</label>
                    <input class="campo__entrada" type="text" id="nome" name="nome" required
                           value="<?php echo esc(texto_ou($plano, 'nome', '')) ; ?>"
                           placeholder="Profissional">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="slug">Identificador (slug)</label>
                    <input class="campo__entrada" type="text" id="slug" name="slug" required
                           value="<?php echo esc(texto_ou($plano, 'slug', '')) ; ?>"
                           placeholder="profissional">
                    <p class="campo__ajuda">Letras minusculas, numeros e underscore. E o que aparece na URL.</p>
                </div>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="descricao">Descricao</label>
                <textarea class="campo__entrada" id="descricao" name="descricao" rows="2"
                          placeholder="Para quem quer resultado rapido."><?php echo esc(texto_ou($plano, 'descricao', '')) ; ?></textarea>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="preco_mensal">Preco mensal (R$)</label>
                <input class="campo__entrada" type="text" id="preco_mensal" name="preco_mensal"
                       value="<?php echo esc(texto_ou($plano, 'preco_mensal', '')) ; ?>"
                       placeholder="49,90">
                <p class="campo__ajuda">Apenas para exibicao: o projeto nao tem gateway de pagamento.</p>
            </div>
        </div>
    </section>

    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Limites</h3>
        </div>

        <div class="cartao__corpo">
            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="max_palavras_chave">Palavras-chave</label>
                    <input class="campo__entrada" type="number" min="0" id="max_palavras_chave"
                           name="max_palavras_chave"
                           value="<?php echo esc(texto_ou($plano, 'max_palavras_chave', '')) ; ?>">
                    <p class="campo__ajuda">
                        Deixe em branco para <strong>ILIMITADO</strong>.
                    </p>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="max_cidades_por_termo">Cidades por termo</label>
                    <input class="campo__entrada" type="number" min="0" id="max_cidades_por_termo"
                           name="max_cidades_por_termo"
                           value="<?php echo esc(texto_ou($plano, 'max_cidades_por_termo', '')) ; ?>">
                    <p class="campo__ajuda">
                        Deixe em branco para <strong>ILIMITADO</strong>.
                    </p>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="limite_envios_dia">Envios por dia</label>
                    <input class="campo__entrada" type="number" min="1" max="1000" id="limite_envios_dia"
                           name="limite_envios_dia" required
                           value="<?php echo esc((int) texto_ou($plano, 'limite_envios_dia', 10)) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="max_usuarios">Usuarios no plano</label>
                    <input class="campo__entrada" type="number" min="1" id="max_usuarios"
                           name="max_usuarios" required
                           value="<?php echo esc((int) texto_ou($plano, 'max_usuarios', 1)) ; ?>">
                    <p class="campo__ajuda">Quantas contas podem usar este plano ao mesmo tempo.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Recursos e exibicao</h3>
        </div>

        <div class="cartao__corpo">
            <div class="formulario__grade">
                <div class="campo">
                    <label class="campo__rotulo" for="suporte">Suporte</label>
                    <select class="campo__entrada" id="suporte" name="suporte">
                        <?php foreach ($suportes as $valor => $rotulo){ ?>
                            <option value="<?php echo esc($valor) ; ?>"
                                    <?php echo  escolher(texto_ou($plano, 'suporte', 'email') === $valor, 'selected', '') ; ?>>
                                <?php echo esc($rotulo) ; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="ordem">Ordem de exibicao</label>
                    <input class="campo__entrada" type="number" min="0" id="ordem" name="ordem"
                           value="<?php echo esc((int) texto_ou($plano, 'ordem', 0)) ; ?>">
                </div>
            </div>

            <div class="linha-selos">
                <label class="selo-seletor">
                    <input type="checkbox" id="envio_automatico" name="envio_automatico" value="1"
                        <?php echo  escolher((int) texto_ou($plano, 'envio_automatico', 0) === 1, 'checked', '') ; ?>>
                    Envio automatico
                </label>

                <label class="selo-seletor">
                    <input type="checkbox" id="envio_em_massa" name="envio_em_massa" value="1"
                        <?php echo  escolher((int) texto_ou($plano, 'envio_em_massa', 0) === 1, 'checked', '') ; ?>>
                    Envio em massa
                </label>

                <label class="selo-seletor">
                    <input type="checkbox" id="destaque" name="destaque" value="1"
                        <?php echo  escolher((int) texto_ou($plano, 'destaque', 0) === 1, 'checked', '') ; ?>>
                    Destacar como "Mais popular"
                </label>

                <label class="selo-seletor">
                    <input type="checkbox" id="ativo" name="ativo" value="1"
                        <?php echo  escolher((int) texto_ou($plano, 'ativo', 1) === 1, 'checked', '') ; ?>>
                    Plano ativo
                </label>
            </div>
        </div>
    </section>

    <div class="formulario__acoes">
        <button type="submit" class="botao botao--primario">
            <?php echo  icone_svg('check') ; ?> Salvar plano
        </button>

        <a class="botao botao--fantasma" href="<?php echo  site_url('admin/planos') ; ?>">Cancelar</a>
    </div>
</form>

<?php echo  $this->endSection() ; ?>
