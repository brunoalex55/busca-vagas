<?php

/**
 * Testa o ProvedorDivulgaVagas com a estrutura nova (automacoes-rpa).
 */

chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER');
require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php';
require_once __DIR__ . '/fase8-site-local.php';

// O site local de teste precisa estar no ar.
fase8_site_local_iniciar();

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

echo PHP_EOL . '-- ProvedorDivulgaVagas (estrutura nova) --' . PHP_EOL;

$provedor = new App\Libraries\ProvedorDivulgaVagas();

checar('a pasta do bot e automacoes-rpa/divulgavagas', strpos($provedor->pastaBot(), 'automacoes-rpa') !== false, $provedor->pastaBot());
checar('o bot.py existe nessa pasta', is_file($provedor->pastaBot() . '/bot.py'));
checar('a pasta de trabalho fica em writable', strpos($provedor->pastaTrabalho(), 'writable') !== false, $provedor->pastaTrabalho());
checar('a pasta de trabalho existe', is_dir($provedor->pastaTrabalho()));

// A URL de busca montada.
$url = $provedor->montarUrlBusca('Auxiliar Administrativo', 'Ponta Grossa', 'PR');
checar('URL de busca com + e UF', strpos($url, 'filt_buscar=Auxiliar+Administrativo') !== false && strpos($url, 'estado=PR') !== false && strpos($url, 'cidade=Ponta+Grossa') !== false, $url);

// O Python em uso.
$python = $provedor->caminhoPython();
checar('o Python foi encontrado', $python !== '', $python === '' ? '(nao encontrado)' : $python);

// Chamada real contra o site local (o servico do Chrome precisa estar no ar).
$urlLocal = 'http://127.0.0.1:8097/listagem.html';

if ($python !== '') {
    $resultado = $provedor->buscar($urlLocal, 8);

    checar('a chamada ao bot volta sucesso', (bool) $resultado['sucesso'], (string) ($resultado['erro'] ?? ''));
    checar('a chamada traz as vagas', count($resultado['dados']['vagas'] ?? []) === 3, count($resultado['dados']['vagas'] ?? []) . ' vaga(s)');

    if (isset($resultado['dados']['vagas'][0])) {
        $vaga = $resultado['dados']['vagas'][0];

        checar('a vaga tem o id', ($vaga['vaga_id'] ?? '') === '34762271', (string) ($vaga['vaga_id'] ?? ''));
        checar('a vaga tem a descricao', ($vaga['descricao'] ?? '') !== '', substr((string) ($vaga['descricao'] ?? ''), 0, 50));
        checar('a descricao esta sem caractere invisivel', strpos((string) ($vaga['descricao'] ?? ''), "\u{200b}") === false);
        checar('a vaga tem a url de envio', ($vaga['url_envio'] ?? '') !== '', (string) ($vaga['url_envio'] ?? ''));
    }
} else {
    avisar('Python nao encontrado: as chamadas reais nao foram testadas');
}

// Candidatar: o curriculo tem que existir.
$resultado = $provedor->candidatar('http://127.0.0.1:8097/formulario.html', 'C:/inexistente/curriculo.pdf', 8);
checar('candidatar sem curriculo: falha controlada', $resultado['sucesso'] === false, (string) $resultado['erro']);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
