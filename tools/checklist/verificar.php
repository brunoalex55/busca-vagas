<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Verificador do checklist final do projeto.
 *
 * Roda as tres partes da verificacao (cada uma confere os itens de um
 * conjunto de secoes contra o codigo, as rotas, as views e o banco) e
 * depois gera o documento docs/checklist-final.md.
 *
 * Uso: php tools/checklist/verificar.php
 */

$pastaPartes = __DIR__;

echo "Verificando o checklist final...\n";

// Cada parte grava o resultado em writable/checklist/parteN.json.
foreach (['parte1.php', 'parte2.php', 'parte3.php'] as $parte) {
    require $pastaPartes . DIRECTORY_SEPARATOR . $parte;
}

// Gera o markdown final.
require $pastaPartes . DIRECTORY_SEPARATOR . 'gerar.php';
