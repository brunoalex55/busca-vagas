<?php

/**
 * --------------------------------------------------------------------------
 * DADOS DO TESTE DO FILTRO POR PROVEDOR (Fase 8.6)
 * --------------------------------------------------------------------------
 *
 * Cria (e limpa) dois envios de e-mail de teste, um de cada provedor, para
 * o filtro de provedor dos logs do admin ser conferido de verdade.
 *
 * Uso:
 *   php tools/fase8-logs-dados.php criar
 *   php tools/fase8-logs-dados.php limpar
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$acao = $argv[1] ?? 'criar';

$emails = model('EmailEnviadoModel');

// O admin (usuario 1) e os identificadores dos registros de teste.
$usuarioId = 1;
$marcador = 'e2e-provedor';

// Remove os registros de uma execucao anterior.
$emails->db->table('emails_enviados')
    ->like('assunto', $marcador)
    ->delete();

if ($acao === 'limpar') {
    echo "envios de teste removidos" . PHP_EOL;

    exit(0);
}

// Um envio de cada provedor.
foreach (['serpapi', 'divulgavagas'] as $provedor) {
    $emails->insert([
        'usuario_id'    => $usuarioId,
        'vaga_id'       => null,
        'destinatario'  => 'teste-' . $provedor . '@empresa-teste.com.br',
        'assunto'       => $marcador . ' ' . $provedor,
        'origem'        => 'manual',
        'status'        => 'enviado',
        'mensagem_erro' => null,
        'enviado_em'    => date('Y-m-d H:i:s'),
        'provedor'      => $provedor,
    ]);
}

echo "2 envios de teste criados (um de cada provedor)" . PHP_EOL;
