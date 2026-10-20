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
 * PARTIAL: MENSAGENS FLASH (TOASTS)
 * --------------------------------------------------------------------------
 *
 * Exibe as mensagens gravadas na sessao com:
 *   session()->setFlashdata('sucesso', '...')
 *   session()->setFlashdata('erro', '...')
 *   ->with('erros', [...])  (erros de validacao)
 *
 * O fechamento automatico e feito por JavaScript vanilla, apenas
 * como melhoria visual. As mensagens continuam legiveis mesmo sem JS.
 */

// Mensagem de sucesso.
$sucesso = session()->getFlashdata('sucesso');

// Mensagem de erro geral.
$erro = session()->getFlashdata('erro');

// Lista de erros de validacao.
$erros = session()->getFlashdata('erros');
?>
<div class="toasts" id="toasts" aria-live="polite" aria-atomic="true">

    <?php if (! empty($sucesso)){ ?>
        <div class="toast toast--sucesso" role="status">
            <span class="toast__icone"><?php echo  icone_svg('check') ; ?></span>
            <p class="toast__texto"><?php echo  esc($sucesso) ; ?></p>
            <button class="toast__fechar" type="button" aria-label="Fechar mensagem">&times;</button>
        </div>
    <?php } ?>

    <?php if (! empty($erro)){ ?>
        <div class="toast toast--erro" role="alert">
            <span class="toast__icone">!</span>
            <p class="toast__texto"><?php echo  esc($erro) ; ?></p>
            <button class="toast__fechar" type="button" aria-label="Fechar mensagem">&times;</button>
        </div>
    <?php } ?>

    <?php if (! empty($erros) && is_array($erros)){ ?>
        <div class="toast toast--erro" role="alert">
            <span class="toast__icone">!</span>
            <div class="toast__texto">
                <?php foreach ($erros as $mensagem){ ?>
                    <p><?php echo  esc($mensagem) ; ?></p>
                <?php } ?>
            </div>
            <button class="toast__fechar" type="button" aria-label="Fechar mensagem">&times;</button>
        </div>
    <?php } ?>

</div>
