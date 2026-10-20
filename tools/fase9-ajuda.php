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

if ($acao === 'pedido') {
    // O id do pedido de plano pendente mais recente (o teste do modal).
    $linha = $db->table('solicitacoes_plano')
        ->where('status', 'pendente')
        ->orderBy('id', 'DESC')
        ->get()
        ->getRowArray();

    if ($linha === null || $linha === []) {
        echo '0';
        exit(0);
    }

    echo (string) $linha['id'];

    exit(0);
}

if ($acao === 'criar-usuario-modal') {
    // O usuario de teste do modal (do painel do usuario).
    $email = 'fase9-modal@teste.local';

    $db->table('usuarios')->where('email', $email)->delete();

    $db->table('usuarios')->insert([
        'nome'          => 'Teste Modal',
        'email'         => $email,
        'senha'         => password_hash('teste123', PASSWORD_DEFAULT),
        'perfil'        => 'usuario',
        'status'        => 'ativo',
        'ativo'         => 1,
        'plano_id'      => 1,
        'criado_em'     => date('Y-m-d H:i:s'),
        'atualizado_em' => date('Y-m-d H:i:s'),
    ]);

    $linha = $db->table('usuarios')->where('email', $email)->get()->getRowArray();

    echo (string) $linha['id'];

    exit(0);
}

if ($acao === 'apagar-usuario-modal') {
    // Apaga o usuario de teste do modal (e os pedidos dele).
    $email = 'fase9-modal@teste.local';

    $linha = $db->table('usuarios')->where('email', $email)->get()->getRowArray();

    if ($linha !== null && $linha !== []) {
        $db->table('solicitacoes_plano')->where('usuario_id', (int) $linha['id'])->delete();
        $db->table('usuarios')->where('id', (int) $linha['id'])->delete();
    }

    echo 'ok';

    exit(0);
}

if ($acao === 'criar-usuario') {
    // Um usuario de teste no plano basico (com a senha conhecida).
    $email = 'fase9-http@busca-vagas.teste';

    $db->table('usuarios')->where('email', $email)->delete();
    $db->table('palavras_chave')->where('usuario_id', 1)->delete();

    $db->table('usuarios')->insert([
        'nome'     => 'Usuario Fase 9 HTTP',
        'email'    => $email,
        'senha'    => password_hash('teste-123', PASSWORD_BCRYPT),
        'perfil'   => 'usuario',
        'status'   => 'ativo',
        'plano_id' => 1,
        'ativo'    => 1,
    ]);

    $linha = $db->table('usuarios')->where('email', $email)->get()->getRowArray();

    echo (string) $linha['id'];

    exit(0);
}

if ($acao === 'palavras') {
    // Cria as palavras-chave de teste (a quantidade vem no argumento).
    $usuarioId = (int) ($argv[2] ?? 0);
    $quantidade = (int) ($argv[3] ?? 0);

    $db->table('palavras_chave')->where('usuario_id', $usuarioId)->delete();

    for ($i = 1; $i <= $quantidade; $i++) {
        $db->table('palavras_chave')->insert([
            'usuario_id' => $usuarioId,
            'termo'      => 'Palavra Fase 9 ' . $i,
            'cidades'    => json_encode(['Ponta Grossa - PR']),
            'idioma'     => 'pt',
            'pais'       => 'br',
            'ativa'      => 1,
        ]);
    }

    echo (string) $quantidade;

    exit(0);
}

if ($acao === 'max-usuarios') {
    // Muda o max_usuarios de um plano (so para o teste) e devolve o valor.
    $slug = (string) ($argv[2] ?? 'empresa');
    $novo = (int) ($argv[3] ?? 1);

    $plano = $db->table('planos')->where('slug', $slug)->get()->getRowArray();

    if ($plano === null || $plano === []) {
        echo '0';
        exit(0);
    }

    $antes = (int) $plano['max_usuarios'];

    $db->table('planos')->where('id', (int) $plano['id'])->update(['max_usuarios' => $novo]);

    echo (string) $antes;

    exit(0);
}

if ($acao === 'apagar-usuario') {
    $email = 'fase9-http@busca-vagas.teste';

    $linha = $db->table('usuarios')->where('email', $email)->get()->getRowArray();

    if ($linha !== null && $linha !== []) {
        $db->table('palavras_chave')->where('usuario_id', (int) $linha['id'])->delete();
        $db->table('usuarios')->where('id', (int) $linha['id'])->delete();
    }

    echo 'ok';

    exit(0);
}

echo 'acao desconhecida';

exit(1);
