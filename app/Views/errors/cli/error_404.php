<?php

/*
 * Mensagem de erro 404 para a linha de comando, mantida como esta.
 * O projeto usa apenas PHP puro: nenhuma biblioteca e nenhum framework
 * de linha de comando sao carregados aqui.
 */

use CodeIgniter\CLI\CLI;

CLI::error('ERROR: ' . $code);
CLI::write($message);
CLI::newLine();
