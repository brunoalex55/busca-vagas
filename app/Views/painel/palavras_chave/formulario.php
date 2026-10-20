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
 * VIEW: PAINEL - FORMULARIO DE PALAVRA-CHAVE
 * --------------------------------------------------------------------------
 *
 * Usada tanto na criacao quanto na edicao ($modo).
 */

$this->extend('layouts/painel');

// Titulo conforme o modo.
$tituloPagina = 'Nova palavra-chave';

if ($modo === 'editar') {
    $tituloPagina = 'Editar palavra-chave';
}

// Acao do formulario.
$acao = site_url('painel/palavras-chave');

if ($modo === 'editar') {
    $acao = site_url('painel/palavras-chave/' . (int) $palavra['id'] . '/atualizar');
}

// Valores atuais (com prioridade para o que foi digitado).
$termo      = old('termo', valor_ou($palavra, 'termo', ''));
$cidades    = old('cidades', valor_ou($palavra, 'cidades', ''));
$remoto     = (int) old('remoto', valor_ou($palavra, 'remoto', 0)) === 1;
$frequencia = (int) old('frequencia_horas', valor_ou($palavra, 'frequencia_horas', 24));
$ativa      = (int) old('ativa', valor_ou($palavra, 'ativa', 1)) === 1;

// Opcoes de frequencia (em horas).
$opcoesFrequencia = [6, 12, 24, 48, 72, 168];

// Garante que a frequencia salva apareca na lista.
if (! in_array($frequencia, $opcoesFrequencia, true)) {
    $opcoesFrequencia[] = $frequencia;
    sort($opcoesFrequencia);
}
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao"><?php echo  esc($tituloPagina) ; ?></h2>
        <p class="painel__descricao">
            Informe o cargo desejado e as cidades. Cada cidade vira uma consulta.
        </p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--fantasma botao--pequeno" href="<?php echo  site_url('painel/palavras-chave') ; ?>">
            <?php echo  icone_svg('seta') ; ?> Voltar
        </a>
    </div>
</section>

<section class="cartao">
    <div class="cartao__corpo">
        <form class="formulario" method="post" action="<?php echo  esc($acao) ; ?>">

            <?php /* Termo buscado. */ ?>
            <div class="campo">
                <label class="campo__rotulo" for="termo">Cargo ou termo buscado</label>
                <input class="campo__entrada" type="text" id="termo" name="termo"
                       value="<?php echo  esc($termo) ; ?>" maxlength="150" required
                       placeholder="Ex.: Desenvolvedor PHP">
                <span class="campo__ajuda">
                    Use o termo mais parecido com o titulo das vagas que voce quer encontrar.
                </span>
            </div>

            <?php /* Cidades (uma por linha). */ ?>
            <div class="campo">
                <label class="campo__rotulo" for="cidades">Cidades (uma por linha)</label>
                <textarea class="campo__entrada campo__entrada--area" id="cidades" name="cidades"
                          rows="6" required
                          placeholder="Sao Paulo, SP&#10;Rio de Janeiro, RJ&#10;Remoto"><?php echo  esc($cidades) ; ?></textarea>
                <span class="campo__ajuda">
                    Ate 30 cidades. Voce tambem pode usar virgulas para separar.
                </span>
            </div>

            <?php /* Frequencia. */ ?>
            <div class="campo">
                <label class="campo__rotulo" for="frequencia_horas">Frequencia da busca</label>
                <select class="campo__entrada" id="frequencia_horas" name="frequencia_horas">
                    <?php foreach ($opcoesFrequencia as $horas){ ?>
                        <option value="<?php echo  (int) $horas ; ?>" <?php echo escolher($horas === $frequencia, 'selected', ''); ?>>
                            A cada <?php echo  (int) $horas ; ?> hora(s)
                        </option>
                    <?php } ?>
                </select>
            </div>

            <?php /* Trabalho remoto. */ ?>
            <label class="campo-check">
                <input type="checkbox" name="remoto" value="1" <?php echo escolher($remoto, 'checked', ''); ?>>
                <span>Incluir vagas de trabalho remoto</span>
            </label>

            <?php /* Estado (somente na edicao). */ ?>
            <?php if ($modo === 'editar'){ ?>
                <label class="campo-check">
                    <input type="checkbox" name="ativa" value="1" <?php echo escolher($ativa, 'checked', ''); ?>>
                    <span>Palavra-chave ativa (pausar interrompe novas buscas)</span>
                </label>
            <?php } ?>

            <div class="formulario__acoes">
                <a class="botao botao--fantasma" href="<?php echo  site_url('painel/palavras-chave') ; ?>">Cancelar</a>
                <button type="submit" class="botao botao--primario">
                    <?php echo  icone_svg('check') ; ?> <?php echo escolher($modo === 'editar', 'Salvar alteracoes', 'Criar palavra-chave'); ?>
                </button>
            </div>
        </form>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
