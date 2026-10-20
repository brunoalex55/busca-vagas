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
 * DADOS DO TESTE DE PONTA A PONTA (Fase 8.5)
 * --------------------------------------------------------------------------
 *
 * Prepara (e limpa) a vaga que o teste de ponta a ponta usa:
 *
 *   - a vaga aponta para o FORMULARIO do site local de teste, para o
 *     bot se candidatar sem tocar no site real;
 *   - a vaga nao tem e-mail de contacto (entao o teste exercita so o
 *     caminho do bot, sem depender do SMTP);
 *   - a vaga fica com a situacao "pendente", pronta para o botao
 *     "Candidatar agora" do painel.
 *
 * Uso:
 *   php tools/fase8-e2e-dados.php criar
 *   php tools/fase8-e2e-dados.php estado
 *   php tools/fase8-e2e-dados.php limpar
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$acao = $argv[1] ?? 'criar';

$vagas = model('VagaModel');

// O teste entra como admin (usuario 1).
$usuarioId = 1;
$jobId = 'dv-e2e-pendente';

// O formulario do site local de teste.
$urlEnvio = 'http://127.0.0.1:8097/formulario.html';

if ($acao === 'limpar') {
    $vagas->db->table('vagas')->where('job_id', $jobId)->delete();

    // Devolve o curriculo do usuario ao estado anterior.
    $arquivo = dirname(__DIR__) . '/writable/uploads/curriculos/1/e2e-teste.pdf';
    $marca = dirname(__DIR__) . '/writable/uploads/curriculos/.e2e-valor-anterior';

    if (is_file($marca)) {
        $anterior = trim((string) file_get_contents($marca));

        $vagas->db->table('configuracoes')
            ->where('usuario_id', $usuarioId)
            ->update(['curriculo_pdf' => $anterior === '' ? null : $anterior]);

        @unlink($marca);
    }

    if (is_file($arquivo)) {
        @unlink($arquivo);
    }

    // Se o teste criou a linha de configuracao do admin, ela some.
    $marcaLinha = dirname(__DIR__) . '/writable/uploads/curriculos/.e2e-linha-criada';

    if (is_file($marcaLinha)) {
        $vagas->db->table('configuracoes')->where('usuario_id', $usuarioId)->delete();

        @unlink($marcaLinha);
    }

    echo "vaga de teste removida" . PHP_EOL;

    exit(0);
}

if ($acao === 'estado') {
    $linha = $vagas->db->table('vagas')->where('job_id', $jobId)->get()->getRowArray();

    if ($linha === null) {
        echo "vaga de teste: nao existe" . PHP_EOL;

        exit(0);
    }

    echo 'vaga de teste: id=' . $linha['id']
        . ' | status=' . $linha['candidatura_divulgavagas_status']
        . ' | tentativas=' . $linha['candidatura_divulgavagas_tentativas']
        . ' | erro=' . (string) $linha['candidatura_divulgavagas_erro'] . PHP_EOL;

    exit(0);
}

// Cria (ou recria) a vaga de teste.
$vagas->db->table('vagas')->where('job_id', $jobId)->delete();

// O bot precisa de um curriculo em PDF para anexar.
//
// O teste cria um PDF de mentira em writable/uploads/curriculos e
// aponta a configuracao do usuario para ele. O valor anterior fica
// guardado em um arquivo de marca (e e devolvido na limpeza).
//
// Se o usuario ainda nao tem linha em configuracoes, o teste cria uma
// (com os padroes) e apaga no final.
$pasta = dirname(__DIR__) . '/writable/uploads/curriculos/1';

if (! is_dir($pasta)) {
    mkdir($pasta, 0777, true);
}

$arquivo = $pasta . '/e2e-teste.pdf';
$marca = dirname(__DIR__) . '/writable/uploads/curriculos/.e2e-valor-anterior';
$marcaLinha = dirname(__DIR__) . '/writable/uploads/curriculos/.e2e-linha-criada';

$atual = $vagas->db->table('configuracoes')
    ->where('usuario_id', $usuarioId)
    ->get()
    ->getRowArray();

if ($atual === null || $atual === []) {
    // O usuario nao tinha configuracao: o teste cria uma e marca isso.
    $vagas->db->table('configuracoes')->insert([
        'usuario_id' => $usuarioId,
        'curriculo_pdf' => null,
    ]);

    file_put_contents($marcaLinha, '1');
    file_put_contents($marca, '');
} elseif (! is_file($marca)) {
    $anterior = (string) ($atual['curriculo_pdf'] ?? '');

    file_put_contents($marca, $anterior);
}

// Um PDF minimo (o bot so precisa de um arquivo anexavel).
$pdf = "%PDF-1.4\n"
    . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
    . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
    . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
    . "trailer<</Root 1 0 R>>\n"
    . "%%EOF\n";

file_put_contents($arquivo, $pdf);

$vagas->db->table('configuracoes')
    ->where('usuario_id', $usuarioId)
    ->update(['curriculo_pdf' => '1/e2e-teste.pdf']);

$vagas->insert([
    'usuario_id'                      => $usuarioId,
    'job_id'                          => $jobId,
    'hash_unico'                      => hash('sha256', 'e2e pendente'),
    'titulo'                          => 'Vaga de ponta a ponta (bot do Divulga Vagas)',
    'empresa'                         => 'Empresa de Teste E2E',
    'localizacao'                     => 'Curitiba - PR',
    'fonte'                           => 'Divulga Vagas',
    'provedor_vaga'                   => 'divulgavagas',
    'vaga_id_divulgavagas'            => 'e2e-1',
    'url_envio_divulgavagas'          => $urlEnvio,
    'candidatura_divulgavagas_status' => 'pendente',
    'candidatura_divulgavagas_tentativas' => 0,
]);

$id = (int) $vagas->db->insertID();

echo 'vaga de teste criada: id=' . $id . PHP_EOL;
