<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * O SQL de sanidade da Fase 9 (README, secao "Isolamento de vagas").
 *
 * Deve devolver 0 linhas: se devolver alguma, ha vaga repetida (ou
 * vazamento entre usuarios).
 */

chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER');
require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php';

$db = db_connect();

echo '=== 1) A mesma vaga nao pode aparecer duas vezes para o mesmo usuario ===' . PHP_EOL;

$duplicadas = $db->query(
    'SELECT v.usuario_id, v.job_id, COUNT(*) AS total
     FROM vagas v
     GROUP BY v.usuario_id, v.job_id
     HAVING total > 1'
)->getResultArray();

echo '  linhas: ' . count($duplicadas) . PHP_EOL;

foreach ($duplicadas as $linha) {
    echo '    usuario ' . $linha['usuario_id'] . ' | job ' . $linha['job_id'] . ' | ' . $linha['total'] . ' vez(es)' . PHP_EOL;
}

echo PHP_EOL . '=== 2) A mesma vaga (hash) nao pode aparecer para dois usuarios ===' . PHP_EOL;

$compartilhado = $db->query(
    'SELECT v.hash_unico, COUNT(DISTINCT v.usuario_id) AS usuarios, COUNT(*) AS total
     FROM vagas v
     GROUP BY v.hash_unico
     HAVING usuarios > 1
     LIMIT 10'
)->getResultArray();

echo '  linhas: ' . count($compartilhado) . PHP_EOL;

foreach ($compartilhado as $linha) {
    echo '    hash ' . substr((string) $linha['hash_unico'], 0, 12) . '... | ' . $linha['usuarios'] . ' usuario(s)' . PHP_EOL;
}

echo PHP_EOL . '=== 3) As restricoes unicas no banco ===' . PHP_EOL;

$indices = $db->query('SHOW INDEX FROM vagas')->getResultArray();

$unicas = [];

foreach ($indices as $indice) {
    $chave = (string) ($indice['Key_name'] ?? '');

    if ((int) ($indice['Non_unique'] ?? 1) === 0 && $chave !== 'PRIMARY') {
        $unicas[$chave][] = (string) ($indice['Column_name'] ?? '');
    }
}

foreach ($unicas as $chave => $colunas) {
    echo '  ' . $chave . ': ' . implode(' + ', $colunas) . PHP_EOL;
}

echo PHP_EOL . '====================================================' . PHP_EOL;

$ok = count($duplicadas) === 0 && count($compartilhado) === 0;

echo $ok ? 'SEM VAZAMENTO entre usuarios' : 'HA VAZAMENTO entre usuarios';
echo PHP_EOL . '====================================================' . PHP_EOL;

exit($ok ? 0 : 1);
