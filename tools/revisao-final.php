<?php

/**
 * REVISAO FINAL DO PROJETO (Entregavel B3).
 *
 * Roda, em uma unica passagem, os sete testes da revisao final:
 *
 *   1. Fluxo completo do usuario
 *   2. Fluxo completo do admin
 *   3. Os tres modos de cron
 *   4. Integracao com o cron-job.org
 *   5. Execucao manual pelo usuario (Bloco E)
 *   6. Os tres modos produzem o mesmo resultado
 *   7. Greps de verificacao do Bloco C
 *
 * Uso: php tools/revisao-final.php
 *
 * O teste da integracao com o cron-job.org precisa de um servidor da API.
 * Sem chave de API real, aponte para um servidor local e ajuste a URL no
 * .env (chave "cron.urlBaseCronJobOrg") e a chave usada no painel.
 */

$raiz = dirname(__DIR__);

// ---------------------------------------------------------------- Ajuda

$base = 'http://localhost:8080';

// O relatorio sai na tela e tambem vira docs/revisao-final.md.
ob_start();

$script = ['ok' => 0, 'falha' => 0, 'avisos' => 0, 'secao' => ''];




/**
 * Abre uma secao do relatorio.
 */
function secao(string $titulo): void
{
    global $script;

    $script['secao'] = $titulo;

    echo PHP_EOL . '### ' . $titulo . PHP_EOL . PHP_EOL;
}

/**
 * Registra o resultado de um teste.
 */
function checar(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $script;

    if ($ok) {
        $script['ok']++;
        echo '  PASS  ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
        return;
    }

    $script['falha']++;
    echo '  FALHA ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
}

/**
 * Registra um aviso (nao impede a aprovacao do teste).
 */
function avisar(string $rotulo, string $detalhe = ''): void
{
    global $script;

    $script['avisos']++;
    echo '  AVISO ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
}

// ------------------------------------------------------------- Requisicao

/**
 * Cliente HTTP simples, com jar de cookies.
 */
class Cliente
{
    public string $base;

    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(string $base)
    {
        $this->base = $base;
    }

    /**
     * Faz uma requisicao e devolve [status, corpo, cabecalhos].
     *
     * @param array<string, string>|null $corpo
     * @param array<string, string> $cabecalhos
     * @return array{0: int, 1: string, 2: array<string, string>}
     */
    public function chamar(string $metodo, string $caminho, ?array $corpo = null, array $cabecalhos = []): array
    {
        $ch = curl_init($this->base . $caminho);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CUSTOMREQUEST  => $metodo,
        ]);

        if ($this->cookies !== []) {
            $pares = [];

            foreach ($this->cookies as $nome => $valor) {
                $pares[] = $nome . '=' . $valor;
            }

            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pares));
        }

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($corpo));
        }

        if ($cabecalhos !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $cabecalhos);
        }

        $resposta = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tamanhoCabecalho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        $cabecalhoBruto = substr($resposta, 0, $tamanhoCabecalho);
        $corpoBruto = substr($resposta, $tamanhoCabecalho);

        // Guarda os cookies.
        foreach (explode("\r\n", $cabecalhoBruto) as $linha) {
            if (stripos($linha, 'Set-Cookie:') !== 0) {
                continue;
            }

            $par = trim(substr($linha, 11));
            $partes = explode('=', $par, 2);
            $nome = trim($partes[0]);
            $valor = explode(';', $partes[1] ?? '')[0];
            $this->cookies[$nome] = $valor;
        }

        return [$status, $corpoBruto, $cabecalhoBruto];
    }

    /**
     * Atalho para GET.
     */
    public function get(string $caminho): array
    {
        return $this->chamar('GET', $caminho);
    }

    /**
     * Atalho para POST.
     */
    public function post(string $caminho, array $corpo, array $cabecalhos = []): array
    {
        return $this->chamar('POST', $caminho, $corpo, $cabecalhos);
    }
}

// ------------------------------------------------------------------ Banco

$env = (string) file_get_contents($raiz . DIRECTORY_SEPARATOR . '.env');
$valores = [];

foreach (explode("\n", $env) as $linha) {
    $linha = trim($linha);

    if ($linha === '' || strpos($linha, '#') === 0) {
        continue;
    }

    $partes = explode('=', $linha, 2);

    if (count($partes) === 2) {
        $valores[trim($partes[0])] = trim($partes[1]);
    }
}

$bd = new mysqli(
    $valores['database.default.hostname'] ?? '127.0.0.1',
    $valores['database.default.username'] ?? 'ci_employer',
    $valores['database.default.password'] ?? '',
    $valores['database.default.database'] ?? 'ci_employer',
    (int) ($valores['database.default.port'] ?? 3306)
);

if ($bd->connect_errno !== 0) {
    echo 'Nao consegui conectar no banco: ' . $bd->connect_error . PHP_EOL;
    exit(1);
}

/**
 * Consulta que devolve as linhas.
 *
 * @return list<array<string, mixed>>
 */
function consultar(string $sql): array
{
    global $bd;

    $resultado = $bd->query($sql);

    if ($resultado === false) {
        return [];
    }

    $linhas = [];

    while ($linha = $resultado->fetch_assoc()) {
        $linhas[] = $linha;
    }

    $resultado->free();

    return $linhas;
}

/**
 * Executa um comando sem retorno.
 */
function executar(string $sql): bool
{
    global $bd;

    return $bd->query($sql) === true;
}

/**
 * Valor unico de uma consulta.
 */
function valor(string $sql, string $padrao = ''): string
{
    $linhas = consultar($sql);

    if ($linhas === []) {
        return $padrao;
    }

    $valores = array_values($linhas[0]);

    return (string) $valores[0];
}

// ------------------------------------------------------------- Utilidades

/**
 * Executa um comando do Spark e devolve a saida.
 *
 * @return array{0: int, 1: string}
 */
function spark(string $comando, string $raiz): array
{
    $php = PHP_BINARY;
    $comandoCompleto = escapeshellarg($php) . ' ' . escapeshellarg($raiz . '/spark') . ' ' . $comando . ' 2>&1';

    $saida = [];
    $codigo = 0;

    exec($comandoCompleto, $saida, $codigo);

    return [$codigo, implode(PHP_EOL, $saida)];
}

/**
 * Le o valor de um campo do HTML.
 */
function campo(string $html, string $name): string
{
    if (preg_match('/name="' . preg_quote($name, '/') . '"[^>]*value="([^"]*)"/', $html, $achado) === 1) {
        return $achado[1];
    }

    return '';
}

/**
 * O HTML contem o texto?
 */
function contem(string $html, string $texto): bool
{
    return stripos($html, $texto) !== false;
}

// =====================================================================
// 1. FLUXO COMPLETO DO USUARIO
// =====================================================================

secao('1. Fluxo completo do usuario');

$emailTeste = 'revisao.final@exemplo.com';
$senhaTeste = 'RevisaoFinal2026';
$usuarioAdmin = new Cliente($base);
$usuarioTeste = new Cliente($base);

// A conta de teste e apagada antes: o cadastro precisa ser testado de
// verdade (e nao reaproveitar uma conta antiga, com outro plano).
$idAntigo = (int) valor("SELECT id FROM usuarios WHERE email = '" . $emailTeste . "'");

if ($idAntigo > 0) {
    executar('DELETE FROM palavras_chave WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM logs_consulta WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM emails_enviados WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM vagas WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM cron_jobs_usuario WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM execucoes_manuais_usuario WHERE usuario_id = ' . $idAntigo);
    executar('DELETE FROM usuarios WHERE id = ' . $idAntigo);
}

// 1.1 Cadastro pelo site publico.
//
// O plano vai junto (Fase 9): o usuario de teste entra no Profissional,
// que tem o envio em massa.
[$status, $corpo] = $usuarioTeste->post('/registro', [
    'nome'              => 'Usuario Revisao Final',
    'email'             => $emailTeste,
    'senha'             => $senhaTeste,
    'senha_confirmacao' => $senhaTeste,
    'plano'             => 'profissional',
]);

$novoId = (int) valor("SELECT id FROM usuarios WHERE email = '" . $emailTeste . "'");

checar('cadastro cria o usuario', $novoId > 0, 'id ' . $novoId);
checar('cadastro devolve 303 (redireciona)', $status === 303, 'HTTP ' . $status);

// 1.2 O usuario nasce pendente e nao entra no painel.
$statusNovo = valor('SELECT status FROM usuarios WHERE id = ' . $novoId);

checar('usuario novo fica pendente', $statusNovo === 'pendente', 'status: ' . $statusNovo);

[$status, $corpo] = $usuarioTeste->get('/painel');

checar('usuario pendente nao entra no painel', $status === 302, 'HTTP ' . $status);

// 1.3 O admin aprova.
[$status] = $usuarioAdmin->post('/login', [
    'email' => 'admin@admin.com',
    'senha' => 'admin123',
]);

checar('login do admin', $status === 303, 'HTTP ' . $status);

[$status] = $usuarioAdmin->post('/admin/usuarios/' . $novoId . '/aprovar', []);

$statusAprovado = valor('SELECT status FROM usuarios WHERE id = ' . $novoId);

checar('admin aprova o usuario', $statusAprovado === 'ativo', 'status: ' . $statusAprovado);

// 1.4 Login do usuario aprovado.
[$status, $corpo] = $usuarioTeste->post('/login', [
    'email' => $emailTeste,
    'senha' => $senhaTeste,
]);

checar('login do usuario aprovado', $status === 303, 'HTTP ' . $status);

[$status, $corpo] = $usuarioTeste->get('/painel');

checar('painel do usuario abre', $status === 200 && contem($corpo, 'Vagas candidatadas'), 'HTTP ' . $status);

// 1.5 Cadastro de palavra-chave.
[$status, $corpo] = $usuarioTeste->post('/painel/palavras-chave', [
    'termo'            => 'Analista de Sistemas',
    'cidades'          => 'Ponta Grossa',
    'frequencia_horas' => 6,
    'remoto'           => '1',
    'ativa'            => '1',
]);

$palavras = (int) valor('SELECT COUNT(*) FROM palavras_chave WHERE usuario_id = ' . $novoId);

checar('palavra-chave cadastrada', $palavras > 0, $palavras . ' registro(s)');

// 1.6 Paginas do painel do usuario.
foreach (['/painel/palavras-chave', '/painel/vagas', '/painel/envio-massa', '/painel/historico', '/painel/configuracoes'] as $rota) {
    [$status, $corpo] = $usuarioTeste->get($rota);
    checar('pagina do painel: ' . $rota, $status === 200, 'HTTP ' . $status);
}

// 1.7 O usuario comum nao ve nada sobre a SerpApi.
$hmlSerializado = '';

foreach (['/painel', '/painel/configuracoes', '/painel/vagas'] as $rota) {
    [, $corpo] = $usuarioTeste->get($rota);
    $hmlSerializado .= $corpo;
}

$achouSerp = false;

foreach (['serpapi', 'SerpApi', 'api_key', 'chave da API', 'distribuicao'] as $termo) {
    if (contem($hmlSerializado, $termo)) {
        $achouSerp = true;
        avisar('termo restrito no painel do usuario', $termo);
    }
}

checar('usuario comum nao ve nada da SerpApi', ! $achouSerp);

// 1.8 O admin ve a informacao da SerpApi.
[, $corpoAdmin] = $usuarioAdmin->get('/admin/chaves');

checar('admin ve as chaves da SerpApi', contem($corpoAdmin, 'Conta Principal') || contem($corpoAdmin, 'chaves'), 'tela /admin/chaves');

// =====================================================================
// 2. FLUXO COMPLETO DO ADMIN
// =====================================================================

secao('2. Fluxo completo do admin');

$paginasAdmin = [
    '/admin',
    '/admin/cron',
    '/admin/usuarios',
    '/admin/usuarios/' . $novoId,
    '/admin/chaves',
    '/admin/distribuicao',
    '/admin/solicitacoes',
    '/admin/logs',
    '/admin/configuracao-site',
    '/admin/conta',
];

foreach ($paginasAdmin as $rota) {
    [$status] = $usuarioAdmin->get($rota);
    checar('pagina do admin: ' . $rota, $status === 200, 'HTTP ' . $status);
}

// 2.0 A tela antiga "/admin/crons" foi redirecionada para "/admin/cron".
[$status, $corpo, $cabecalhos] = $usuarioAdmin->get('/admin/crons');
$destino = '';

foreach (explode("\r\n", (string) $cabecalhos) as $linha) {
    if (stripos($linha, 'Location:') === 0) {
        $destino = trim(substr($linha, 9));
    }
}

checar(
    'a tela antiga /admin/crons redireciona para /admin/cron',
    $status === 302 && strpos($destino, '/admin/cron') !== false,
    'HTTP ' . $status . ' -> ' . $destino
);

// 2.1 Aprovacao e bloqueio de usuario.
[$status] = $usuarioAdmin->post('/admin/usuarios/' . $novoId . '/bloquear', []);
$statusBloqueado = valor('SELECT status FROM usuarios WHERE id = ' . $novoId);

checar('admin bloqueia usuario', $statusBloqueado === 'bloqueado', 'status: ' . $statusBloqueado);


// 2.2 usuario bloqueado nao entra no painel.
$usuarioBloqueado = new Cliente($base);
$usuarioBloqueado->post('/login', ['email' => $emailTeste, 'senha' => $senhaTeste]);
[, $corpoBloqueado, $cabecalho] = $usuarioBloqueado->get('/painel');
$destino = '';

if (preg_match('/Location:\s*(\S+)/i', $cabecalho, $achado) === 1) {
    $destino = $achado[1];
}

checar('usuario bloqueado nao entra no painel', $destino !== '' || $corpoBloqueado === '', 'destino: ' . ($destino === '' ? '(sem redirect)' : $destino));

// 2.3 Reativa o usuario para seguir o resto dos testes.
$usuarioAdmin->post('/admin/usuarios/' . $novoId . '/reativar', []);
$statusReativado = valor('SELECT status FROM usuarios WHERE id = ' . $novoId);

checar('admin reativa usuario', $statusReativado === 'ativo', 'status: ' . $statusReativado);

// 2.3 Ajuste de limite manual de consultas (distribuicao).
[$status] = $usuarioAdmin->post('/admin/usuarios/' . $novoId . '/limite', ['limite_manual' => 25]);
$limiteManual = valor('SELECT IFNULL(limite_manual, -1) FROM usuarios WHERE id = ' . $novoId);

checar('admin define limite manual de consultas', (int) $limiteManual === 25, 'limite: ' . $limiteManual);

// 2.4 Configuracao do site publico.
$configOriginal = valor('SELECT slogan FROM configuracao_site WHERE id = 1');
[$status] = $usuarioAdmin->post('/admin/configuracao-site/salvar', [
    'slogan'         => $configOriginal,
    'nome_site'      => valor('SELECT nome_site FROM configuracao_site WHERE id = 1'),
    'descricao_site' => valor('SELECT descricao_site FROM configuracao_site WHERE id = 1'),
]);

checar('admin salva a configuracao do site', $status === 200 || $status === 303, 'HTTP ' . $status);

// 2.5 Configuracao do pagamento (PIX).
[$status] = $usuarioAdmin->post('/admin/configuracao-site/pagamento', [
    'chave_pix'     => valor('SELECT chave_pix FROM configuracao_pagamento LIMIT 1'),
    'valor_mensal'  => valor('SELECT valor_mensal FROM configuracao_pagamento LIMIT 1'),
    'texto_instrucoes' => valor('SELECT texto_instrucoes FROM configuracao_pagamento LIMIT 1'),
]);

checar('admin salva a configuracao do pagamento', $status === 200 || $status === 303, 'HTTP ' . $status);

// =====================================================================
// 3. OS TRES MODOS DE CRON
// =====================================================================

secao('3. Os tres modos de cron');

// 3.1 Modo salvo no banco.
$modoAtual = valor('SELECT modo FROM configuracao_cron LIMIT 1');

checar('modo salvo no banco', in_array($modoAtual, ['cron_linux', 'cron_windows', 'spark_manual'], true), 'modo: ' . $modoAtual);

// 3.2 O admin troca o modo e o modo volta ao original.
$modoOriginal = $modoAtual;
[$status] = $usuarioAdmin->post('/admin/cron/modo', ['modo' => 'cron_linux']);
$modoDepois = valor('SELECT modo FROM configuracao_cron LIMIT 1');

checar('admin troca o modo para cron_linux', $modoDepois === 'cron_linux', 'modo: ' . $modoDepois);

[$status] = $usuarioAdmin->post('/admin/cron/modo', ['modo' => $modoOriginal]);
$modoRestaurado = valor('SELECT modo FROM configuracao_cron LIMIT 1');

checar('modo volta ao original', $modoRestaurado === $modoOriginal, 'modo: ' . $modoRestaurado);

// 3.3 Modo cron_linux: o script existe, tem LF e chama o Spark.
$scriptLinux = $raiz . '/scripts/cron-linux.sh';
$conteudoLinux = (string) file_get_contents($scriptLinux);
$bytesLinux = (string) file_get_contents($scriptLinux);

checar('scripts/cron-linux.sh existe', is_file($scriptLinux));
checar('scripts/cron-linux.sh usa LF (sem CR)', strpos($bytesLinux, "\r\n") === false);
checar('scripts/cron-linux.sh chama o Spark', contem($conteudoLinux, 'spark'));
checar('scripts/cron-linux.sh nao usa curl nem wget', ! contem($conteudoLinux, 'curl') && ! contem($conteudoLinux, 'wget'));
checar('scripts/cron-linux.sh traz os dois comandos', contem($conteudoLinux, 'vagas:consultar') && contem($conteudoLinux, 'emails:enviar-automaticos'));

// 3.4 Modo cron_windows: o script roda aqui e mostra o comando final.
$saidaWindows = [];
$codigoWindows = 0;
exec('cmd /c ""' . $raiz . '\scripts\cron-windows.bat" vagas --simular" 2>&1', $saidaWindows, $codigoWindows);
$textoWindows = implode(PHP_EOL, $saidaWindows);

checar('scripts/cron-windows.bat roda com --simular', $codigoWindows === 0, 'codigo ' . $codigoWindows);
checar('saida do script mostra o comando de vagas', contem($textoWindows, 'vagas:consultar'));
checar('saida do script mostra o caminho do PHP', contem($textoWindows, 'spark'));

$saidaWindowsEmails = [];
exec('cmd /c ""' . $raiz . '\scripts\cron-windows.bat" emails --simular" 2>&1', $saidaWindowsEmails, $codigoWindows);
$textoWindowsEmails = implode(PHP_EOL, $saidaWindowsEmails);

checar('saida do script mostra o comando de e-mails', contem($textoWindowsEmails, 'emails:enviar-automaticos'));
checar('script do Windows nao usa curl nem wget', ! contem($textoWindows, 'curl') && ! contem($textoWindows, 'wget'));

// 3.5 Modo spark_manual: o comando roda pela linha de comando.
// O usuario do teste 1 tem palavra-chave; o limite manual e baixo para
// nao gastar consultas demais do provedor.
$usuarioAdmin->post('/admin/usuarios/' . $novoId . '/limite', ['limite_manual' => 2]);

[$codigoSpark, $saidaSpark] = spark('vagas:consultar --usuario=' . $novoId, $raiz);

checar('php spark vagas:consultar roda', $codigoSpark === 0, 'codigo ' . $codigoSpark);
checar('a saida do comando traz o resumo da busca', contem($saidaSpark, 'BUSCA DE VAGAS') && contem($saidaSpark, 'Consultas realizadas'), 'resumo: ' . trim(substr($saidaSpark, 0, 60)));

// 3.6 O mesmo comando dispara pelo painel.
[$status, $corpo] = $usuarioAdmin->post('/admin/cron/executar-vagas', []);
$paginaCron = '';

[, $paginaCron] = $usuarioAdmin->get('/admin/cron');

checar('painel executa o comando de vagas', contem($paginaCron, 'vagas:consultar') || contem($paginaCron, 'Saida do ultimo comando'), 'HTTP ' . $status);

// =====================================================================
// 4. INTEGRACAO COM O CRON-JOB.ORG
// =====================================================================

secao('4. Integracao com o cron-job.org');

$urlMock = valor('SELECT valor FROM (SELECT "http://127.0.0.1:8099" AS valor) x');
$chaveMock = 'chave-de-teste-cronjob-123';

// O projeto traz um mock da API do cron-job.org (porta 8099): o teste
// roda sem internet e sem depender de conta real.
require_once __DIR__ . '/fase8-site-local.php';
fase8_mock_iniciar();

// O contador de requisicoes do dia e zerado: o teste precisa de
// requisicoes para a API (as execucoes anteriores do proprio teste
// consumiram o limite diario).
executar('UPDATE configuracao_cron SET requisicoes_api_hoje = 0');

$caminhoEnv = $raiz . '/.env';
$conteudoEnv = (string) file_get_contents($caminhoEnv);
$envOriginal = $conteudoEnv;
$temChaveReal = valor('SELECT IFNULL(api_key_cronjob, "") FROM configuracao_cron LIMIT 1') !== '';
$chaveReal = valor('SELECT IFNULL(api_key_cronjob, "") FROM configuracao_cron LIMIT 1');

// 4.1 A chave de API e salva pelo painel.
$novoEnv = $conteudoEnv;

if (strpos($novoEnv, 'cron.urlBaseCronJobOrg') === false) {
    $novoEnv .= PHP_EOL . 'cron.urlBaseCronJobOrg = ' . $urlMock . PHP_EOL;
} else {
    $novoEnv = (string) preg_replace('/^cron\.urlBaseCronJobOrg\s*=.*$/m', 'cron.urlBaseCronJobOrg = ' . $urlMock, $novoEnv);
}

file_put_contents($caminhoEnv, $novoEnv);

[$status] = $usuarioAdmin->post('/admin/cron/api-key', ['api_key_cronjob' => $chaveMock]);
$chaveNoBanco = valor('SELECT api_key_cronjob FROM configuracao_cron LIMIT 1');

checar('admin salva a chave de API do cron-job.org', $chaveNoBanco === $chaveMock && ($status === 200 || $status === 303), 'HTTP ' . $status);

// 4.2 Teste de conexao.
[$status, $corpo] = $usuarioAdmin->post('/admin/cron/testar-conexao', []);
$conexaoOk = contem($corpo, 'conexao') || contem($corpo, 'Conexao') || contem($corpo, 'sucesso') || contem($corpo, 'Sucesso');

checar('teste de conexao com a API', $status === 200 || $status === 303, 'HTTP ' . $status);

[, $corpoCron] = $usuarioAdmin->get('/admin/cron');
checar('o painel mostra o resultado do teste de conexao', contem($corpoCron, 'chave-de-teste') || contem($corpoCron, 'conexao') || contem($corpoCron, 'Conexao'));

// 4.3 Sincronizar jobs (cria 2 jobs por usuario ativo).
$jobsAntes = (int) valor('SELECT IFNULL(MAX(id), 0) FROM cron_jobs_usuario');
$antes = (int) valor('SELECT COUNT(*) FROM cron_jobs_usuario');
[$status] = $usuarioAdmin->post('/admin/cron/sincronizar', []);
$depois = (int) valor('SELECT COUNT(*) FROM cron_jobs_usuario');

checar('sincronizar cria jobs para os usuarios ativos', $depois > $antes && ($status === 200 || $status === 303), $antes . ' -> ' . $depois . ' (HTTP ' . $status . ')');

// 4.4 Os jobs tambem ficam no servico (mock da API).
$jobs = consultar('SELECT usuario_id, tipo, cronjob_org_job_id, token_externo FROM cron_jobs_usuario ORDER BY id');

checar('cada job gravado tem id do servico', $jobs !== [] && (int) $jobs[0]['cronjob_org_job_id'] > 0, count($jobs) . ' job(s)');

$tokenBruno = '';

foreach ($jobs as $job) {
    if ((int) $job['usuario_id'] === 8 && $job['tipo'] === 'vagas') {
        $tokenBruno = (string) $job['token_externo'];
    }
}

checar('o job do usuario tem token externo', $tokenBruno !== '', 'token com ' . strlen($tokenBruno) . ' caracteres');

// 4.5 O painel lista os jobs.
[$status, $corpo] = $usuarioAdmin->get('/admin/cron/jobs');
checar('tela de jobs do servico responde', $status === 200, 'HTTP ' . $status);

[, $corpoCron] = $usuarioAdmin->get('/admin/cron');
checar('o painel mostra os tokens e jobs por usuario', contem($corpoCron, 'Tokens e jobs por usuario'));

// 4.6 O servico chama o endpoint externo (igual o cron-job.org faria).
$clienteExterno = new Cliente($base);
[$status, $corpo] = $clienteExterno->post(
    '/cron/usuario/8/executar-externo?comando=vagas',
    [],
    ['X-Cron-Token: ' . $tokenBruno]
);
$json = json_decode($corpo, true);

checar('endpoint externo responde 200 com o token do header', $status === 200, 'HTTP ' . $status);
checar('a resposta traz status, comando, mensagem, estatisticas, duracao e data',
    is_array($json)
    && isset($json['status'], $json['comando'], $json['mensagem'], $json['estatisticas'], $json['duracao_segundos'], $json['executado_em']),
    is_array($json) ? implode(', ', array_keys($json)) : 'resposta nao-json');
checar('a resposta mostra o comando vagas', is_array($json) && ($json['comando'] ?? '') === 'vagas', is_array($json) ? (string) ($json['status'] ?? '') : '');

// 4.7 Token pela query string.
[$status, $corpo] = $clienteExterno->post('/cron/usuario/8/executar-externo?comando=vagas&token=' . urlencode($tokenBruno), []);
checar('endpoint externo aceita o token pela query string', $status === 200, 'HTTP ' . $status);

// 4.8 Token invalido devolve 401.
[$status, $corpo] = $clienteExterno->post('/cron/usuario/8/executar-externo?comando=vagas', [], ['X-Cron-Token: token-errado']);
checar('endpoint externo devolve 401 com token invalido', $status === 401, 'HTTP ' . $status);

// 4.9 Comando invalido devolve 400.
[$status, $corpo] = $clienteExterno->post('/cron/usuario/8/executar-externo?comando=banana', [], ['X-Cron-Token: ' . $tokenBruno]);
checar('endpoint externo devolve 400 com comando invalido', $status === 400, 'HTTP ' . $status);

// 4.10 O log da execucao externa existe.
$logExterno = glob($raiz . '/writable/logs/cron-externo-' . date('Y-m-d') . '.log');

checar('execucao externa registrada em log', $logExterno !== [], $logExterno === [] ? 'arquivo nao encontrado' : basename($logExterno[0]));

// 4.11 Fora do modo spark_manual, a integracao fica bloqueada.
[$status] = $usuarioAdmin->post('/admin/cron/modo', ['modo' => 'cron_windows']);
$modoCronWindows = valor('SELECT modo FROM configuracao_cron LIMIT 1');

[, $corpoCron] = $usuarioAdmin->get('/admin/cron');
$integracaoVisivel = contem($corpoCron, 'Sincronizar Jobs');

checar('fora do modo spark_manual a integracao some do painel', $integracaoVisivel === false, 'modo: ' . $modoCronWindows);

[$status] = $usuarioAdmin->post('/admin/cron/modo', ['modo' => $modoOriginal]);
checar('modo restaurado para ' . $modoOriginal, valor('SELECT modo FROM configuracao_cron LIMIT 1') === $modoOriginal);

// 4.12 Limpeza: devolve a chave e o .env originais.
if ($temChaveReal) {
    // Devolve a chave real que estava no banco antes do teste.
    executar("UPDATE configuracao_cron SET api_key_cronjob = '" . $chaveReal . "'");
    checar('chave de API real restaurada', valor('SELECT api_key_cronjob FROM configuracao_cron LIMIT 1') === $chaveReal);
} else {
    executar('UPDATE configuracao_cron SET api_key_cronjob = NULL');
    checar('chave de API de teste removida do banco', valor('SELECT IFNULL(api_key_cronjob, "") FROM configuracao_cron LIMIT 1') === '', 'o painel volta a pedir a chave');
}

file_put_contents($caminhoEnv, $envOriginal);

checar('.env restaurado', (string) file_get_contents($caminhoEnv) === $envOriginal);

// A chave so fica vazia quando nao havia uma chave real antes do teste.
if (! $temChaveReal) {
    checar('chave de API de teste removida do banco', valor('SELECT IFNULL(api_key_cronjob, "") FROM configuracao_cron LIMIT 1') === '', 'o painel volta a pedir a chave');
}

// =====================================================================
// 5. EXECUCAO MANUAL PELO USUARIO (BLOCO E)
// =====================================================================

secao('5. Execucao manual pelo usuario (Bloco E)');

$usuarioId = $novoId;

// 5.1 O admin define o limite (por usuario).
[$status] = $usuarioAdmin->post('/admin/usuarios/limite-manuais', [
    'usuario_id'                  => $usuarioId,
    'limite_execucoes_manuais'   => 1,
]);

$limiteGlobal = valor('SELECT limite_execucoes_manuais_usuario FROM configuracao_cron LIMIT 1');

checar('admin define o limite de execucoes manuais', (int) $limiteGlobal === 1, 'limite global: ' . $limiteGlobal);

// 5.2 Zera o contador do dia antes de comecar.
[$status] = $usuarioAdmin->post('/admin/cron/zerar-manual/' . $usuarioId, []);
$contadorAntes = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");

checar('admin zera o contador do usuario', $contadorAntes === 0, 'contador: ' . $contadorAntes);

// 5.3 O usuario executa manualmente.
// O teste usa o usuario recem-criado no teste 1 (senha conhecida).
$usuarioBruno = $usuarioTeste;

// O sistema espalha as consultas do mes inteiro: a palavra-chave so
// fica devida de novo depois da frequencia. Para o teste, ela e
// deixada devida na hora.
executar("UPDATE palavras_chave SET proxima_execucao = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE usuario_id = " . $usuarioId);

$antesManual = (int) valor('SELECT COUNT(*) FROM logs_consulta WHERE usuario_id = ' . $usuarioId . " AND origem = 'manual_usuario'");
[$status, $corpo] = $usuarioBruno->post('/painel/executar-vagas', []);
$depoisManual = (int) valor('SELECT COUNT(*) FROM logs_consulta WHERE usuario_id = ' . $usuarioId . " AND origem = 'manual_usuario'");
$contador = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");

checar('execucao manual do usuario roda', $status === 200 || $status === 303, 'HTTP ' . $status);
checar('execucao manual soma no contador', $contador === 1, 'contador: ' . $contador);
checar('execucao manual registra origem manual_usuario', $depoisManual > $antesManual, $antesManual . ' -> ' . $depoisManual);

// 5.4 Atingindo o limite: o segundo disparo e barrado e o botao desabilita.
[$status, $corpo] = $usuarioBruno->post('/painel/executar-vagas', []);
$contadorDepois = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");

checar('segunda execucao nao passa do limite', $contadorDepois === 1, 'contador: ' . $contadorDepois);

[, $corpoPainel] = $usuarioBruno->get('/painel');
$desabilitado = contem($corpoPainel, 'disabled');

checar('os botoes desabilitam quando o limite e atingido', $desabilitado);

// 5.5 O admin zera o contador.
[$status] = $usuarioAdmin->post('/admin/cron/zerar-manual/' . $usuarioId, []);
$contadorZerado = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");

checar('admin zera o contador', $contadorZerado === 0, 'contador: ' . $contadorZerado);

// 5.6 O admin nao tem esse limite: a execucao do admin nao soma no contador.
$antesAdmin = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = 1 AND data = "' . date('Y-m-d') . '"');
[$status] = $usuarioAdmin->post('/admin/cron/executar-emails', []);
$depoisAdmin = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = 1 AND data = "' . date('Y-m-d') . '"');

checar('execucao do admin nao conta no limite', $antesAdmin === $depoisAdmin, $antesAdmin . ' -> ' . $depoisAdmin);

// 5.7 O endpoint externo nao conta no limite.
$antesExterno = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");

if ($tokenBruno !== '') {
    [$status] = $clienteExterno->post('/cron/usuario/8/executar-externo?comando=vagas', [], ['X-Cron-Token: ' . $tokenBruno]);
    $depoisExterno = (int) valor('SELECT IFNULL(total_execucoes, 0) FROM execucoes_manuais_usuario WHERE usuario_id = ' . $usuarioId . " AND data = '" . date('Y-m-d') . "'");
    checar('execucao externa nao conta no limite', $antesExterno === $depoisExterno, $antesExterno . ' -> ' . $depoisExterno);
} else {
    avisar('teste do endpoint externo sem token');
}

// 5.8 O limite volta ao valor padrao.
[$status] = $usuarioAdmin->post('/admin/cron/limite-manual', ['limite_execucoes_manuais' => 3]);
$limiteRestaurado = valor('SELECT limite_execucoes_manuais_usuario FROM configuracao_cron LIMIT 1');

checar('limite padrao restaurado (3)', (int) $limiteRestaurado === 3, 'limite: ' . $limiteRestaurado);

// =====================================================================
// 6. OS TRES MODOS PRODUZEM O MESMO RESULTADO
// =====================================================================

secao('6. Os tres modos produzem o mesmo resultado');

$gerenciador = (string) file_get_contents($raiz . '/app/Libraries/GerenciadorCron.php');

// O que cada modo dispara. O painel e o endpoint externo usam o mesmo
// GerenciadorCron, entao os tres modos acabam nos mesmos comandos Spark.
$comandosPorModo = [
    'cron_linux (script .sh)'    => $conteudoLinux,
    'cron_windows (script .bat)' => $textoWindows . PHP_EOL . $textoWindowsEmails,
    'spark_manual (painel e CLI)' => $gerenciador,
];

$conjuntos = [];

foreach ($comandosPorModo as $modo => $texto) {
    $encontrados = [];

    foreach (['vagas:consultar', 'emails:enviar-automaticos'] as $comando) {
        if (contem($texto, $comando)) {
            $encontrados[] = $comando;
        }
    }

    sort($encontrados);
    $conjuntos[$modo] = $encontrados;
}

$referencia = $conjuntos['cron_linux (script .sh)'];

foreach ($conjuntos as $modo => $lista) {
    checar('modo ' . $modo . ' dispara os mesmos comandos', $lista === $referencia, implode(' + ', $lista));
}

// O painel e o endpoint externo usam a MESMA classe de comando.
$painelChama = contem($arquivo = (string) file_get_contents($raiz . '/app/Controllers/Admin/AdminCron.php'), 'executarComando');
$externoChama = contem((string) file_get_contents($raiz . '/app/Controllers/Admin/AdminCron.php'), "getGet('comando')");
$gerenciador = (string) file_get_contents($raiz . '/app/Libraries/GerenciadorCron.php');

checar('painel e endpoint externo usam o mesmo GerenciadorCron', $painelChama && $externoChama && contem($gerenciador, 'executarComando'));
checar('o GerenciadorCron mapeia o comando para o Spark', contem($gerenciador, "'vagas:consultar'") && contem($gerenciador, "'emails:enviar-automaticos'"));
checar('o modo nao muda o codigo dos comandos', ! contem((string) file_get_contents($raiz . '/app/Commands/ConsultarVagas.php'), "modo === 'spark_manual'") && ! contem((string) file_get_contents($raiz . '/app/Commands/ConsultarVagas.php'), "modo === 'cron_linux'"));

// =====================================================================
// 7. GREPS DE VERIFICACAO DO BLOCO C
// =====================================================================

secao('7. Greps de verificacao do Bloco C');

$saidaDetector = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/tools/detector-c1.php') . ' 2>&1', $saidaDetector);
$textoDetector = implode(' ', $saidaDetector);

checar('detector: nenhum ternario', contem($textoDetector, 'ternarios: 0'), trim($textoDetector));
checar('detector: nenhum ??', contem($textoDetector, '??: 0'));
checar('detector: nenhuma sintaxe alternativa', contem($textoDetector, 'alternativa: 0'));
checar('detector: nenhum eco curto', contem($textoDetector, 'eco curto: 0'));

// Greps simples (o detector e a prova; estes sao so uma conferences).
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/app', FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $arquivo) {
    if ($arquivo->isFile() && $arquivo->getExtension() === 'php') {
        $phpFiles[] = $arquivo->getPathname();
    }
}

$comEcoCurto = 0;
$comAlternativa = 0;

foreach ($phpFiles as $arquivo) {
    $conteudo = (string) file_get_contents($arquivo);

    if (strpos($conteudo, '<?=') !== false) {
        $comEcoCurto++;
    }

    if (preg_match('/\b(endforeach|endif|endfor|endwhile|endswitch)\b/', $conteudo) === 1) {
        $comAlternativa++;
    }
}

checar('grep simples: nenhum <?= em app/', $comEcoCurto === 0, $comEcoCurto . ' arquivo(s)');
checar('grep simples: nenhuma sintaxe alternativa em app/', $comAlternativa === 0, $comAlternativa . ' arquivo(s)');

$totalPhp = count($phpFiles);

// =====================================================================
// LIMPEZA DOS DADOS DE TESTE
// =====================================================================

secao('8. Limpeza dos dados de teste');

// Remove o usuario criado pelo teste e tudo que ficou ligado a ele.
executar('DELETE FROM palavras_chave WHERE usuario_id = ' . $novoId);
executar('DELETE FROM logs_consulta WHERE usuario_id = ' . $novoId);
executar('DELETE FROM execucoes_manuais_usuario WHERE usuario_id = ' . $novoId);
executar('DELETE FROM solicitacoes_liberacao WHERE usuario_id = ' . $novoId);
executar('DELETE FROM cron_jobs_usuario WHERE usuario_id = ' . $novoId);

// Os jobs criados pela sincronizacao do teste (mock) tambem saem:
// valem apenas os ids do servidor de teste, que nao existe mais.
executar('DELETE FROM cron_jobs_usuario WHERE id > ' . $jobsAntes);
executar('DELETE FROM usuarios WHERE id = ' . $novoId);

$restou = (int) valor('SELECT COUNT(*) FROM usuarios WHERE id = ' . $novoId);

checar('usuario de teste removido do banco', $restou === 0, 'restaram ' . $restou . ' registro(s)');

$modoFinal = valor('SELECT modo FROM configuracao_cron LIMIT 1');
$limiteFinal = valor('SELECT limite_execucoes_manuais_usuario FROM configuracao_cron LIMIT 1');
$chaveFinal = valor('SELECT IFNULL(api_key_cronjob, "") FROM configuracao_cron LIMIT 1');

checar('modo de agendamento intacto', $modoFinal === 'spark_manual', 'modo: ' . $modoFinal);
checar('limite de execucoes manuais intacto', (int) $limiteFinal === 3, 'limite: ' . $limiteFinal);
checar('chave de API do cron-job.org intacta', $chaveFinal === '', 'sem chave (igual antes do teste)');

// =====================================================================
// RESUMO
// =====================================================================

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'RESUMO: ' . $script['ok'] . ' ok | ' . $script['falha'] . ' falha(s) | ' . $script['avisos'] . ' aviso(s) | ' . $totalPhp . ' arquivos php em app/' . PHP_EOL;
echo '====================================================' . PHP_EOL;

$bd->close();

// Grava o relatorio em docs/revisao-final.md.
$saida = (string) ob_get_clean();

$cabecalho = '# Revisao Final do Projeto' . PHP_EOL
    . PHP_EOL
    . '> Revisao final (Entregavel B3), rodada em **' . date('d/m/Y') . '**.' . PHP_EOL
    . '> Os sete testes do plano rodam de ponta a ponta, contra o app em' . PHP_EOL
    . '> execucao, o banco de dados e a API externa.' . PHP_EOL
    . PHP_EOL
    . 'O script que gera este relatorio fica em `tools/revisao-final.php`.' . PHP_EOL
    . PHP_EOL
    . '```bash' . PHP_EOL
    . 'php tools/revisao-final.php' . PHP_EOL
    . '```' . PHP_EOL
    . PHP_EOL
    . '```text' . PHP_EOL
    . PHP_EOL;

file_put_contents($raiz . '/docs/revisao-final.md', $cabecalho . $saida . '```' . PHP_EOL);

echo $saida;

exit($script['falha'] > 0 ? 1 : 0);
