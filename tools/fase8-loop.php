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
 * TESTE DO LOOP DE CANDIDATURA (Fase 8)
 * --------------------------------------------------------------------------
 *
 * O sistema tem de se candidatar a TODAS as vagas da fila, uma a uma,
 * sem precisar que o usuario clique de novo. Este teste:
 *
 *   1. cria 3 vagas de teste apontando para o site local (rapido);
 *   2. roda o comando de candidatura (o mesmo do CRON 2);
 *   3. confere que as 3 foram candidatadas e que o bot deixou a
 *      confirmacao (a prova de que o curriculo foi postado).
 *
 * Uso:
 *   php tools/fase8-loop.php
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';
require_once __DIR__ . '/fase8-site-local.php';

$falhas = 0;
$verificacoes = 0;

function checar(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas, $verificacoes;

    $verificacoes++;

    if ($ok) {
        echo '  PASS  ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
        return;
    }

    $falhas++;
    echo '  FALHA ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
}

// O site local de teste precisa estar no ar.
fase8_site_local_iniciar();

$vagas = model('VagaModel');
$db = db_connect();

$usuarioId = 1;
$quantidade = 3;
$marcador = 'loop-teste-';

// A limpa (o que sobrou de uma execucao anterior).
$db->table('vagas')->like('job_id', $marcador)->delete();

// O curriculo (o bot precisa de um PDF para anexar).
$pasta = dirname(__DIR__) . '/writable/uploads/curriculos/1';

if (! is_dir($pasta)) {
    mkdir($pasta, 0777, true);
}

$arquivo = $pasta . '/loop-teste.pdf';
$marca = dirname(__DIR__) . '/writable/uploads/curriculos/.loop-valor-anterior';
$marcaLinha = dirname(__DIR__) . '/writable/uploads/curriculos/.loop-linha-criada';

$atual = $db->table('configuracoes')->where('usuario_id', $usuarioId)->get()->getRowArray();

if ($atual === null || $atual === []) {
    $db->table('configuracoes')->insert(['usuario_id' => $usuarioId, 'curriculo_pdf' => null]);
    file_put_contents($marcaLinha, '1');
    file_put_contents($marca, '');
} elseif (! is_file($marca)) {
    file_put_contents($marca, (string) ($atual['curriculo_pdf'] ?? ''));
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
    . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
    . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
    . "trailer<</Root 1 0 R>>\n"
    . "%%EOF\n";

file_put_contents($arquivo, $pdf);

$db->table('configuracoes')->where('usuario_id', $usuarioId)->update(['curriculo_pdf' => '1/loop-teste.pdf']);

// As vagas de teste (todas apontam para o site local).
for ($i = 1; $i <= $quantidade; $i++) {
    $vagas->insert([
        'usuario_id'                      => $usuarioId,
        'job_id'                          => $marcador . $i,
        'hash_unico'                      => hash('sha256', 'loop ' . $i),
        'titulo'                          => 'Vaga de teste do loop ' . $i,
        'empresa'                         => 'Empresa de Teste',
        'localizacao'                     => 'Curitiba - PR',
        'fonte'                           => 'Divulga Vagas',
        'provedor_vaga'                   => 'divulgavagas',
        'vaga_id_divulgavagas'            => 'loop-' . $i,
        'url_envio_divulgavagas'          => 'http://127.0.0.1:8097/formulario.html',
        'candidatura_divulgavagas_status' => 'pendente',
        'candidatura_divulgavagas_tentativas' => 0,
    ]);
}

$ids = $db->table('vagas')->like('job_id', $marcador)->get()->getResultArray();

checar('as vagas de teste foram criadas', count($ids) === $quantidade, count($ids) . ' vaga(s)');

// O comando de candidatura (o mesmo do CRON 2).
$saida = [];
$codigo = 0;
exec('"' . PHP_BINARY . '" spark divulgavagas:candidatar --usuario=' . $usuarioId . ' --origem=manual_usuario 2>&1', $saida, $codigo);

$texto = implode("\n", $saida);

checar('o comando terminou sem erro', $codigo === 0, 'codigo ' . $codigo);

// As vagas: todas foram candidatadas?
$linhas = $db->table('vagas')->like('job_id', $marcador)->get()->getResultArray();

$enviadas = 0;
$confirmadas = 0;

foreach ($linhas as $linha) {
    if ($linha['candidatura_divulgavagas_status'] === 'enviada') {
        $enviadas++;
    }

    if ($linha['candidatura_divulgavagas_confirmado_em'] !== null) {
        $confirmadas++;
    }
}

checar('TODAS as vagas da fila foram candidatadas', $enviadas === $quantidade, $enviadas . ' de ' . $quantidade);
checar('o bot deixou a confirmacao em todas', $confirmadas === $quantidade, $confirmadas . ' de ' . $quantidade);

// A URL da confirmacao (a prova).
$comUrl = 0;

foreach ($linhas as $linha) {
    if (trim((string) $linha['candidatura_divulgavagas_confirmacao_url']) !== '') {
        $comUrl++;
    }
}

checar('a URL de confirmacao foi guardada', $comUrl === $quantidade, $comUrl . ' de ' . $quantidade);

// A limpeza.
$db->table('vagas')->like('job_id', $marcador)->delete();

if (is_file($marca)) {
    $anterior = trim((string) file_get_contents($marca));

    $db->table('configuracoes')
        ->where('usuario_id', $usuarioId)
        ->update(['curriculo_pdf' => $anterior === '' ? null : $anterior]);

    @unlink($marca);
}

if (is_file($arquivo)) {
    @unlink($arquivo);
}

if (is_file($marcaLinha)) {
    $db->table('configuracoes')->where('usuario_id', $usuarioId)->delete();

    @unlink($marcaLinha);
}

checar('a limpeza apaga as vagas de teste', (int) $db->table('vagas')->like('job_id', $marcador)->countAllResults() === 0);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
