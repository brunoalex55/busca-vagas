<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 *
 * A versao minima do PHP e a primeira corrigida dos dois avisos de
 * execucao remota de codigo publicados em 21/10/2026:
 *
 *   CVE-2026-109618 (GHSA-w8hw-w9fx-j79w) - corrupcao de memoria no OOM;
 *   CVE-2026-109619 (GHSA-6wmf-4rv5-p3rp) - estado global velho.
 *
 * Cada serie tem a sua versao corrigida (o aviso e do PHP 8.2.35,
 * 8.3.36, 8.4.27 e 8.5.12), entao a conferenca e feita por serie:
 * uma serie sem a versao corrigida nao roda o sistema.
 *
 * Se voce atualizar esta lista, atualize tambem o `spark`.
 */

$minPhpVersion = '8.2.35'; // If you update this, don't forget to update `spark`.

$versoesCorrigidas = [
    '8.2' => '8.2.35',
    '8.3' => '8.3.36',
    '8.4' => '8.4.27',
    '8.5' => '8.5.12',
];

$serieDoPhp = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$versaoCorrigida = $versoesCorrigidas[$serieDoPhp] ?? $minPhpVersion;

if (version_compare(PHP_VERSION, $versaoCorrigida, '<')) {
    $message = sprintf(
        'Este projeto exige o PHP %s ou mais novo (a serie %s foi corrigida em %s). Versao atual: %s',
        $minPhpVersion,
        $serieDoPhp,
        $versaoCorrigida,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
