<?php

/**
 * --------------------------------------------------------------------------
 * AJUDA DOS TESTES DA FASE 9 (HTTP)
 * --------------------------------------------------------------------------
 *
 * O PowerShell tem dificuldade com o escaped do "php -r" e com o
 * encoding dos acentos, entao as consultas ficam aqui.
 *
 * Uso:
 *   php tools/fase9-ajuda.php plano <usuario_id>
 *   php tools/fase9-ajuda.php criar-vaga
 *   php tools/fase9-ajuda.php apagar-vaga
 *   php tools/fase9-ajuda.php tela <url> <texto>
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$acao = $argv[1] ?? '';
$db = db_connect();

if ($acao === 'plano') {
    $usuarioId = (int) ($argv[2] ?? 8);
    $linha = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();

    echo (string) ($linha['plano_id'] ?? '');

    exit(0);
}

if ($acao === 'criar-vaga') {
    $db->table('vagas')->where('job_id', 'fase9-http')->delete();

    $db->table('vagas')->insert([
        'usuario_id' => 1,
        'job_id' => 'fase9-http',
        'hash_unico' => hash('sha256', 'fase9 http'),
        'titulo' => 'Vaga de teste do isolamento HTTP',
        'empresa' => 'Empresa Teste',
        'fonte' => 'Divulga Vagas',
        'provedor_vaga' => 'divulgavagas',
    ]);

    $linha = $db->table('vagas')->where('job_id', 'fase9-http')->get()->getRowArray();

    echo (string) $linha['id'];

    exit(0);
}

if ($acao === 'apagar-vaga') {
    $db->table('vagas')->where('job_id', 'fase9-http')->delete();

    echo 'ok';

    exit(0);
}

if ($acao === 'tela') {
    // A pagina e conferida por HTTP (com a sessao do admin).
    $url = (string) ($argv[2] ?? '');
    $texto = (string) ($argv[3] ?? '');

    $cookies = sys_get_temp_dir() . '/cookies-fase9.txt';

    @unlink($cookies);

    // O login do admin.
    $ch = curl_init('http://localhost:8080/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['email' => 'admin@admin.com', 'senha' => 'admin123']),
        CURLOPT_TIMEOUT => 60,
    ]);
    curl_exec($ch);
    curl_close($ch);

    // A pagina pedida.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookies,
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_TIMEOUT => 60,
    ]);
    $html = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // A resposta: o status e se o texto esta na pagina.
    echo $status . '|' . (strpos($html, $texto) !== false ? 'sim' : 'nao');

    exit(0);
}

echo 'acao desconhecida';

exit(1);
