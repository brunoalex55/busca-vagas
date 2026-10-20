<?php

/**
 * Prepara (e limpa) as vagas de teste dos selos do Divulga Vagas.
 *
 * Uso:
 *   php tools/fase8-selos-dados.php criar
 *   php tools/fase8-selos-dados.php limpar
 *
 * A preparacao e a limpeza ficam fora do teste HTTP porque um script que
 * carrega o CI4 e faz requisicoes HTTP ao mesmo tempo perde a sessao
 * (o CI4 segura o arquivo de sessao do usuario logado).
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$acao = $argv[1] ?? 'criar';

$vagas = model('VagaModel');

// O teste entra como admin (usuario 1), entao as vagas de teste sao dele.
$usuarioId = 1;

// As seis situacoes da candidatura pelo site.
$situacoes = [
    'pendente'       => 'Divulga Vagas - aguardando bot',
    'pendente_email' => 'Divulga Vagas - aguardando e-mail',
    'enviada'        => 'Divulga Vagas - enviada (site)',
    'enviada_email'  => 'Divulga Vagas - enviada (site + e-mail)',
    'erro'           => 'Divulga Vagas - erro',
    'cloudflare'     => 'Divulga Vagas - Cloudflare',
];

if ($acao === 'limpar') {
    foreach (array_keys($situacoes) as $situacao) {
        $vagas->db->table('vagas')->where('job_id', 'dv-selo-' . $situacao)->delete();
    }

    echo "vagas de teste removidas" . PHP_EOL;

    exit(0);
}

foreach ($situacoes as $situacao => $rotulo) {
    $vagas->insert([
        'usuario_id'                      => $usuarioId,
        'job_id'                          => 'dv-selo-' . $situacao,
        'hash_unico'                      => hash('sha256', 'selo ' . $situacao),
        'titulo'                          => 'Vaga de teste do selo ' . $situacao,
        'empresa'                         => 'Empresa de Teste',
        'localizacao'                     => 'Curitiba - PR',
        'fonte'                           => 'Divulga Vagas',
        'email_contato'                   => 'selo@empresa-teste.com.br',
        'provedor_vaga'                   => 'divulgavagas',
        'vaga_id_divulgavagas'            => (string) crc32($situacao),
        'candidatura_divulgavagas_status' => $situacao,
        'candidatura_divulgavagas_tentativas' => $situacao === 'erro' ? 3 : 0,
        'candidatura_divulgavagas_erro'   => $situacao === 'erro' ? 'falha de teste' : null,
    ]);
}

echo count($situacoes) . " vagas de teste criadas" . PHP_EOL;
