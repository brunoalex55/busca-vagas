<?php

/**
 * --------------------------------------------------------------------------
 * TESTE: PLANOS E ISOLAMENTO DE VAGAS (FASE 9)
 * --------------------------------------------------------------------------
 *
 * Confere, no nivel do sistema:
 *
 *   1. a tabela de planos existe com os 3 planos e os valores da Fase 9;
 *   2. a ordem de prioridade dos limites (manual > plano > configuracao >
 *      padrao) e o NULL como ilimitado;
 *   3. o bloqueio de plano (palavras-chave, cidades, envio automatico e
 *      envio em massa);
 *   4. o isolamento das vagas (a vaga de outro usuario nao aparece e o
 *      acesso indevido fica registrado no log).
 *
 * Uso:
 *   php tools/fase9-testes.php
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

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

$db = db_connect();
$resolvedor = new App\Libraries\ResolvedorPlano();

echo PHP_EOL . '-- 1. A tabela de planos --' . PHP_EOL;

$planos = $db->table('planos')->orderBy('ordem', 'ASC')->get()->getResultArray();

checar('existem 3 planos', count($planos) === 3, count($planos) . ' plano(s)');

$porSlug = [];

foreach ($planos as $plano) {
    $porSlug[(string) $plano['slug']] = $plano;
}

checar('o plano basico existe', isset($porSlug['basico']));
checar('o plano profissional existe', isset($porSlug['profissional']));
checar('o plano empresa existe', isset($porSlug['empresa']));

if (isset($porSlug['basico'])) {
    $basico = $porSlug['basico'];
    checar('basico: 2 palavras-chave', (int) $basico['max_palavras_chave'] === 2);
    checar('basico: 5 cidades por termo', (int) $basico['max_cidades_por_termo'] === 5);
    checar('basico: 10 envios por dia', (int) $basico['limite_envios_dia'] === 10);
    checar('basico: envio automatico manual', (int) $basico['envio_automatico'] === 0);
    checar('basico: sem envio em massa', (int) $basico['envio_em_massa'] === 0);
    checar('basico: 1 usuario', (int) $basico['max_usuarios'] === 1);
    checar('basico: suporte por e-mail', (string) $basico['suporte'] === 'email');
}

if (isset($porSlug['profissional'])) {
    $profissional = $porSlug['profissional'];
    checar('profissional: palavras-chave ilimitadas', $profissional['max_palavras_chave'] === null);
    checar('profissional: cidades ilimitadas', $profissional['max_cidades_por_termo'] === null);
    checar('profissional: 20 envios por dia', (int) $profissional['limite_envios_dia'] === 20);
    checar('profissional: envio automatico ligado', (int) $profissional['envio_automatico'] === 1);
    checar('profissional: envio em massa', (int) $profissional['envio_em_massa'] === 1);
    checar('profissional: 1 usuario', (int) $profissional['max_usuarios'] === 1);
    checar('profissional: destaque (Mais popular)', (int) $profissional['destaque'] === 1);
}

if (isset($porSlug['empresa'])) {
    $empresa = $porSlug['empresa'];
    checar('empresa: 50 envios por dia', (int) $empresa['limite_envios_dia'] === 50);
    checar('empresa: ate 5 usuarios', (int) $empresa['max_usuarios'] === 5);
    checar('empresa: suporte prioritario', (string) $empresa['suporte'] === 'prioritario');
}

echo PHP_EOL . '-- 2. A resolucao de limites --' . PHP_EOL;

// Um usuario de teste (sem plano: cai no basico).
$emailTeste = 'fase9-planos@busca-vagas.teste';

$db->table('usuarios')->where('email', $emailTeste)->delete();

$db->table('usuarios')->insert([
    'nome'     => 'Usuario Fase 9',
    'email'    => $emailTeste,
    'senha'    => password_hash('teste-123', PASSWORD_BCRYPT),
    'perfil'   => 'usuario',
    'status'   => 'ativo',
    'plano_id' => null,
    'ativo'    => 1,
]);

$usuarioId = (int) $db->table('usuarios')->where('email', $emailTeste)->get()->getRowArray()['id'];

// Sem plano: o basico.
$plano = $resolvedor->obterPlanoDoUsuario($usuarioId);
checar('sem plano, o fallback e o basico', (string) $plano['slug'] === 'basico', (string) $plano['slug']);

// Os limites vem do plano.
$origem = $resolvedor->resolverLimiteComOrigem('limite_envios_dia', $usuarioId);
checar('o limite de envios vem do plano', $origem['origem'] === 'plano', $origem['rotulo']);
checar('o valor do plano e o correto', (int) $origem['valor'] === 10);

// Com o limite manual: ele vence.
$db->table('usuarios')->where('id', $usuarioId)->update(['limite_manual' => 77]);

$origemManual = $resolvedor->resolverLimiteComOrigem('limite_envios_dia', $usuarioId);
checar('o limite manual vence o plano', $origemManual['origem'] === 'manual', $origemManual['rotulo']);
checar('o valor manual e o correto', (int) $origemManual['valor'] === 77);

// Sem o limite manual: o plano volta a valer.
$db->table('usuarios')->where('id', $usuarioId)->update(['limite_manual' => null]);

// O NULL e ilimitado.
$db->table('usuarios')->where('id', $usuarioId)->update(['plano_id' => (int) $porSlug['profissional']['id']]);

$ilimitado = $resolvedor->resolverLimite('max_palavras_chave', $usuarioId);
checar('NULL no plano = ilimitado', $ilimitado === null, var_export($ilimitado, true));

// O plano profissional libera o envio automatico e o em massa.
checar('o profissional permite envio automatico', $resolvedor->podeUsarEnvioAutomatico($usuarioId));
checar('o profissional permite envio em massa', $resolvedor->podeUsarEnvioEmMassa($usuarioId));

// O basico bloqueia.
$db->table('usuarios')->where('id', $usuarioId)->update(['plano_id' => (int) $porSlug['basico']['id']]);

checar('o basico bloqueia o envio automatico', $resolvedor->podeUsarEnvioAutomatico($usuarioId) === false);
checar('o basico bloqueia o envio em massa', $resolvedor->podeUsarEnvioEmMassa($usuarioId) === false);

echo PHP_EOL . '-- 3. O bloqueio de palavras-chave --' . PHP_EOL;

// O basico permite 2 palavras-chave.
$db->table('palavras_chave')->where('usuario_id', $usuarioId)->delete();

for ($i = 1; $i <= 2; $i++) {
    $db->table('palavras_chave')->insert([
        'usuario_id' => $usuarioId,
        'termo'      => 'Palavra ' . $i,
        'cidades'    => json_encode(['Ponta Grossa - PR']),
        'idioma'     => 'pt',
        'pais'       => 'br',
        'ativa'      => 1,
    ]);
}

$permitido = $resolvedor->podeAdicionarPalavraChave($usuarioId);
checar('com 2 de 2 palavras, nao pode mais', $permitido['pode'] === false, 'restantes: ' . var_export($permitido['restantes'], true));

// Apagando uma, pode de novo.
$db->table('palavras_chave')->where('usuario_id', $usuarioId)->delete();

$permitido = $resolvedor->podeAdicionarPalavraChave($usuarioId);
checar('com a fila vazia, pode criar', $permitido['pode'] === true, 'restantes: ' . var_export($permitido['restantes'], true));

// E o limite de cidades por termo (5 no basico).
$cidades = ['A', 'B', 'C', 'D', 'E', 'F'];
$limiteCidades = $resolvedor->resolverLimite('max_cidades_por_termo', $usuarioId);
checar('o basico permite 5 cidades por termo', (int) $limiteCidades === 5, (string) $limiteCidades);

echo PHP_EOL . '-- 4. O isolamento das vagas --' . PHP_EOL;

// A vaga de teste pertence ao usuario de teste.
$db->table('vagas')->where('job_id', 'fase9-isolamento')->delete();

$db->table('vagas')->insert([
    'usuario_id' => $usuarioId,
    'job_id'     => 'fase9-isolamento',
    'hash_unico' => hash('sha256', 'fase9 isolamento'),
    'titulo'     => 'Vaga de teste do isolamento',
    'empresa'    => 'Empresa Teste',
    'fonte'      => 'Divulga Vagas',
    'provedor_vaga' => 'divulgavagas',
]);

$vagaId = (int) $db->table('vagas')->where('job_id', 'fase9-isolamento')->get()->getRowArray()['id'];

// O dono encontra.
checar('o dono encontra a vaga', obterVagaDoUsuario($vagaId, $usuarioId) !== null);

// Outro usuario nao encontra.
checar('outro usuario nao encontra a vaga', obterVagaDoUsuario($vagaId, 1) === null);

// E o 404 acontece.
$deu404 = false;

try {
    obterVagaDoUsuarioOu404($vagaId, 1);
} catch (\CodeIgniter\Exceptions\PageNotFoundException $erro) {
    $deu404 = true;
}

checar('outro usuario recebe 404', $deu404);

// O log de acesso indevido foi gravado.
$log = WRITEPATH . 'logs/acesso-indevido-' . date('Y-m-d') . '.log';
checar('o log de acesso indevido existe', is_file($log), $log);

if (is_file($log)) {
    $conteudo = (string) file_get_contents($log);
    checar('o log tem o id da vaga', strpos($conteudo, 'vaga=' . $vagaId) !== false);
    checar('o log tem o dono real', strpos($conteudo, 'dono=' . $usuarioId) !== false);
}

// A limpeza.
$db->table('vagas')->where('job_id', 'fase9-isolamento')->delete();
$db->table('palavras_chave')->where('usuario_id', $usuarioId)->delete();
$db->table('usuarios')->where('email', $emailTeste)->delete();

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
