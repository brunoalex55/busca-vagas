<?php

/**
 * --------------------------------------------------------------------------
 * AJUDA DO TESTE DO CRON-JOB.ORG (Fase 8)
 * --------------------------------------------------------------------------
 *
 * Consultas e ajustes que o teste (PowerShell) precisa fazer no banco:
 *
 *   php tools/fase8-cronjob-dados.php chave [valor]
 *   php tools/fase8-cronjob-dados.php jobs        (quantos jobs tem id no servico)
 *   php tools/fase8-cronjob-dados.php por-tipo     (jobs por tipo)
 *   php tools/fase8-cronjob-dados.php token [usuario]
 *   php tools/fase8-cronjob-dados.php zerar-requisicoes
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$acao = $argv[1] ?? '';
$db = db_connect()->table('configuracao_cron');

if ($acao === 'chave') {
    // Sem valor: le a chave atual. Com valor: grava.
    if (count($argv) < 3) {
        $linha = $db->get()->getRowArray();

        echo $linha === null ? '' : (string) ($linha['api_key_cronjob'] ?? '') . PHP_EOL;

        exit(0);
    }

    $db->update(['api_key_cronjob' => $argv[2]]);

    echo 'chave gravada' . PHP_EOL;

    exit(0);
}

if ($acao === 'chave-apagar') {
    $db->update(['api_key_cronjob' => null]);

    echo 'chave removida' . PHP_EOL;

    exit(0);
}

if ($acao === 'zerar-requisicoes') {
    $db->update(['requisicoes_api_hoje' => 0]);

    echo 'contador zerado' . PHP_EOL;

    exit(0);
}

if ($acao === 'jobs') {
    $conexao = db_connect();

    echo (int) $conexao->table('cron_jobs_usuario')->where('cronjob_org_job_id >', 0)->countAllResults() . PHP_EOL;

    exit(0);
}

if ($acao === 'por-tipo') {
    $conexao = db_connect();

    $linhas = $conexao->table('cron_jobs_usuario')
        ->select('tipo, COUNT(*) AS qtd')
        ->groupBy('tipo')
        ->get()
        ->getResultArray();

    foreach ($linhas as $linha) {
        echo $linha['tipo'] . '=' . $linha['qtd'] . ' ';
    }

    echo PHP_EOL;

    exit(0);
}

if ($acao === 'token') {
    $usuarioId = (int) ($argv[2] ?? 8);

    $linha = db_connect()->table('cron_jobs_usuario')->where('usuario_id', $usuarioId)->get()->getRowArray();

    echo $linha === null ? '' : (string) $linha['token_externo'] . PHP_EOL;

    exit(0);
}

echo 'acao desconhecida' . PHP_EOL;

exit(1);
