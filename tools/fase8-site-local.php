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
 * AJUDA: SITE LOCAL DE TESTE (Fase 8)
 * --------------------------------------------------------------------------
 *
 * O bot precisa de um site para testar. Este site local espelha a
 * estrutura do Divulga Vagas (listagem, pagina da vaga, aviso, formulario
 * e pagina de sucesso) e roda em uma porta propria, sem tocar na internet.
 *
 * Uso (nos testes):
 *   require_once __DIR__ . '/fase8-site-local.php';
 *   fase8_site_local_iniciar();   // sobe, se ja nao estiver no ar
 *   ...
 *   fase8_site_local_parar();     // derruba
 */

const FASE8_SITE_PORTA = 8097;
const FASE8_SITE_HOST = '127.0.0.1';

// A porta do mock do cron-job.org (usado pela revisao final).
const FASE8_MOCK_PORTA = 8099;

/**
 * O site local esta respondendo?
 */
function fase8_site_local_ativo(): bool
{
    return fase8_porta_ativa(FASE8_SITE_PORTA, '/listagem.html');
}

/**
 * O mock do cron-job.org esta respondendo?
 */
function fase8_mock_ativo(): bool
{
    return fase8_porta_ativa(FASE8_MOCK_PORTA, '/jobs');
}

/**
 * A porta responde alguma coisa?
 */
function fase8_porta_ativa(int $porta, string $caminho): bool
{
    $endereco = 'http://' . FASE8_SITE_HOST . ':' . $porta . $caminho;

    try {
        $contexto = stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => true],
        ]);

        $resposta = @file_get_contents($endereco, false, $contexto);

        return $resposta !== false;
    } catch (Throwable $erro) {
        return false;
    }
}

/**
 * Sobe um script Python de teste em segundo plano.
 *
 * @param string $script Caminho do script Python.
 */
function fase8_subir_script(string $script): bool
{
    if (! is_file($script)) {
        return false;
    }

    $python = fase8_site_local_python();

    if ($python === '') {
        return false;
    }

    // Sobe em segundo plano (o processo fica sozinho).
    //
    // O script de cada sistema (.ps1 no Windows, .sh no Linux) solta o
    // processo: quem chamou nao espera e o console nao fica preso.
    $log = dirname(__DIR__) . '/writable/logs/site-local.log';

    if (! is_dir(dirname($log))) {
        @mkdir(dirname($log), 0777, true);
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $auxiliar = dirname(__DIR__) . '/tools/subir-servico-teste.ps1';
        $comando = 'powershell -NoProfile -ExecutionPolicy Bypass -File "'
            . $auxiliar . '" "' . $python . '" "' . $script . '"';
    } else {
        $auxiliar = dirname(__DIR__) . '/tools/subir-servico-teste.sh';
        $comando = 'bash "' . $auxiliar . '" "' . $python . '" "' . $script . '" > "' . $log . '" 2>&1 &';
    }

    $descritores = [
        0 => ['file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $log, 'a'],
        2 => ['file', $log, 'a'],
    ];

    $tubos = [];
    $processo = proc_open($comando, $descritores, $tubos, dirname(__DIR__));

    // O processo auxiliar termina na hora (o site continua rodando).
    if (is_resource($processo)) {
        foreach ($tubos as $tubo) {
            if (is_resource($tubo)) {
                fclose($tubo);
            }
        }
    }

    // Espera a porta responder (ate 15 segundos).
    for ($i = 0; $i < 15; $i++) {
        usleep(500000);

        if (fase8_site_local_ativo()) {
            return true;
        }
    }

    return false;
}

/**
 * Sobe o site local (se ele ja nao estiver no ar).
 */
function fase8_site_local_iniciar(): bool
{
    // Ja esta no ar: nada a fazer.
    if (fase8_site_local_ativo()) {
        return true;
    }

    return fase8_subir_script(dirname(__DIR__) . '/tools/teste-site-local.py');
}

/**
 * Sobe o mock do cron-job.org (se ele ja nao estiver no ar).
 */
function fase8_mock_iniciar(): bool
{
    // Ja esta no ar: nada a fazer.
    if (fase8_mock_ativo()) {
        return true;
    }

    $iniciou = fase8_subir_script(dirname(__DIR__) . '/tools/teste-cronjob-mock.py');

    if (! $iniciou) {
        return false;
    }

    // Espera a porta do mock responder.
    for ($i = 0; $i < 15; $i++) {
        usleep(500000);

        if (fase8_mock_ativo()) {
            return true;
        }
    }

    return false;
}

/**
 * Derruba o site local e o mock do cron-job.org.
 */
function fase8_site_local_parar(): void
{
    foreach ([FASE8_SITE_PORTA, FASE8_MOCK_PORTA] as $porta) {
        if (DIRECTORY_SEPARATOR === '\\') {
            $comando = 'for /f "tokens=5" %i in (\'netstat -ano ^| findstr ":' . $porta . '"\') do @taskkill /PID %i /F >NUL 2>&1';
        } else {
            $comando = 'pkill -f teste-site-local.py; pkill -f teste-cronjob-mock.py';
        }

        exec($comando);
    }
}

/**
 * Descobre o Python (a configuracao do projeto ou o PATH).
 */
function fase8_site_local_python(): string
{
    // O caminho configurado no .env do projeto.
    $env = dirname(__DIR__) . '/.env';

    if (is_file($env)) {
        foreach (explode("\n", (string) file_get_contents($env)) as $linha) {
            $linha = trim($linha);

            if (strpos($linha, 'divulgavagas.python') !== 0) {
                continue;
            }

            // O valor vem depois do "=" (com as aspas opcionais).
            $partes = explode('=', $linha, 2);

            if (count($partes) !== 2) {
                continue;
            }

            $valor = trim(trim($partes[1]), "\"'");
            $valor = str_replace('\\', '/', $valor);

            if ($valor !== '' && is_file($valor)) {
                return $valor;
            }
        }
    }

    // O PATH do sistema.
    foreach (['python3', 'python'] as $candidato) {
        $achado = @shell_exec('command -v ' . $candidato . ' 2>/dev/null');

        if (is_string($achado) && trim($achado) !== '') {
            return trim($achado);
        }
    }

    return '';
}
