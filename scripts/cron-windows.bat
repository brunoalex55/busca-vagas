@echo off
REM Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
REM
REM Autor: Bruno Alexandre
REM
REM Pix (doacao): bruno@brunoalexandre.com
REM
REM Este projeto e open source. Doe se quiser manter o projeto vivo.
REM
REM Mais informacoes no README.

REM =====================================================================
REM AGENDAMENTO DE TAREFAS (WINDOWS)
REM =====================================================================
REM
REM Executa uma tarefa do CI_EMPLOYER pela linha de comando, sem depender
REM de URL. Este e o caminho oficial de agendamento no Windows (Agendador
REM de Tarefas) e cobre o CRON 1 e o CRON 2.
REM
REM O script NAO decide o que roda: ele apenas chama os comandos Spark,
REM que ja seguem as regras do sistema (cotas, limites por execucao, paus
REM entre consultas, modo de agendamento escolhido no painel).
REM
REM Uso:
REM   scripts\cron-windows.bat vagas              REM CRON 1: busca de vagas
REM   scripts\cron-windows.bat emails             REM CRON 2: envio automatico
REM   scripts\cron-windows.bat vagas 8            REM somente o usuario #8
REM   scripts\cron-windows.bat vagas --simular    REM mostra o que sera feito
REM
REM No Agendador de Tarefas, use como programa:
REM   "%RAIZ%\scripts\cron-windows.bat" vagas
REM e marque "Iniciar em" como a pasta do projeto.
REM
REM ---------------------------------------------------------------------

setlocal EnableDelayedExpansion

REM Os caminhos do proprio script sao capturados ANTES de qualquer goto:
REM dentro de um bloco ( ), o cmd perde o caminho do %0 e o %~dp0 passa a
REM apontar para o diretorio atual.
set "PASTA_SCRIPT=%~dp0"
set "NOME_SCRIPT=%~nx0"

set "TAREFA="
set "USUARIO="
set "USUARIO_ROTULO=todos"
set "SIMULAR=nao"
set "ARQUIVO_TMP=%TEMP%\ci-employer-cron-%RANDOM%.txt"

REM ---------------------------------------------------------------------
REM ARGUMENTOS
REM ---------------------------------------------------------------------

:parse_args
if "%~1"=="" goto args_prontos
if /i "%~1"=="--simular" (
    set "SIMULAR=sim"
    shift
    goto parse_args
)
if "%TAREFA%"=="" (
    set "TAREFA=%~1"
    shift
    goto parse_args
)
if "%USUARIO%"=="" (
    set "USUARIO=%~1"
    shift
    goto parse_args
)
echo Argumento inesperado: %~1 1>&2
exit /b 2

:args_prontos

if /i "%TAREFA%"=="vagas" goto tarefa_ok
if /i "%TAREFA%"=="emails" goto tarefa_ok
echo Uso: %NOME_SCRIPT% {vagas^|emails} [usuarioId] [--simular] 1>&2
exit /b 2

:tarefa_ok

REM Usuario opcional: apenas digitos (validacao sem eco do valor).
if "%USUARIO%"=="" goto usuario_ok
set "USUARIO_VALIDO=sim"
for /f "delims=0123456789" %%a in ("%USUARIO%") do set "USUARIO_VALIDO=nao"
if "%USUARIO_VALIDO%"=="sim" goto usuario_ok
echo Usuario invalido: use apenas numeros, por exemplo 8. 1>&2
exit /b 2

:usuario_ok
if not "%USUARIO%"=="" set "USUARIO_ROTULO=%USUARIO%"

REM ---------------------------------------------------------------------
REM CAMINHOS
REM ---------------------------------------------------------------------

REM Raiz do projeto: a pasta imediatamente acima de "scripts".
set "RAIZ=%PASTA_SCRIPT%.."
for %%i in ("%RAIZ%") do set "RAIZ=%%~fi"

set "DIRETORIO_LOGS=%RAIZ%\writable\logs"

REM ---------------------------------------------------------------------
REM INTERPRETADOR PHP
REM ---------------------------------------------------------------------
REM
REM Ordem de descoberta:
REM   1. variavel de ambiente PHP_CLI;
REM   2. cron.caminhoPhpCli no .env do projeto (mesma chave do painel);
REM   3. php.cmd da raiz do projeto (o PHP portatil desta maquina);
REM   4. php no PATH do sistema.

set "PHP_CLI=%PHP_CLI%"

if defined PHP_CLI goto php_pronto

if exist "%RAIZ%\.env" (
    findstr /r /c:"^cron\.caminhoPhpCli" "%RAIZ%\.env" > "%ARQUIVO_TMP%" 2>nul
)

if not exist "%ARQUIVO_TMP%" goto testar_php_cmd
set /p VALOR_ENV=<"%ARQUIVO_TMP%"
del "%ARQUIVO_TMP%" >nul 2>&1
if not defined VALOR_ENV goto testar_php_cmd
set "VALOR=%VALOR_ENV:"=%"
set "VALOR=%VALOR:'=%"
set "VALOR=%VALOR:\\=%"
if not exist "%VALOR%" goto testar_php_cmd
set "PHP_CLI=%VALOR%"
goto php_pronto

:testar_php_cmd
if exist "%RAIZ%\php.cmd" (
    set "PHP_CLI=%RAIZ%\php.cmd"
    goto php_pronto
)
for /f "delims=" %%p in ('where php 2^>nul') do (
    if not defined PHP_CLI set "PHP_CLI=%%p"
)
goto php_pronto

:php_pronto
if not defined PHP_CLI (
    echo PHP nao encontrado. Defina PHP_CLI ou cron.caminhoPhpCli no .env. 1>&2
    exit /b 3
)

REM ---------------------------------------------------------------------
REM COMANDO
REM ---------------------------------------------------------------------

set "COMANDO=vagas:consultar"

if /i "%TAREFA%"=="emails" set "COMANDO=emails:enviar-automaticos"

set "ARGUMENTOS=spark %COMANDO%"

if not "%USUARIO%"=="" set "ARGUMENTOS=%ARGUMENTOS% --usuario=%USUARIO%"

if "%SIMULAR%"=="sim" goto simular

REM ---------------------------------------------------------------------
REM EXECUCAO
REM ---------------------------------------------------------------------

if not exist "%DIRETORIO_LOGS%" mkdir "%DIRETORIO_LOGS%" >nul 2>&1

REM Data e hora geradas pelo proprio PHP: independem do idioma do SO.
call "%PHP_CLI%" -r "echo date('Y-m-d').' '.date('H:i:s');" > "%ARQUIVO_TMP%"
set /p MARCAS=<"%ARQUIVO_TMP%"
del "%ARQUIVO_TMP%" >nul 2>&1

REM A data e o nome do arquivo; o inicio completo vai para o log.
set "DATA=%MARCAS:~0,10%"
set "INICIO=%MARCAS%"

set "ARQUIVO_LOG=%DIRETORIO_LOGS%\cron-%DATA%.log"
set "ARQUIVO_SAIDA=%TEMP%\ci-employer-cron-%RANDOM%.txt"

REM As pipes sao escapadas com ^ para nao virarem pipe do proprio cmd.
>> "%ARQUIVO_LOG%" echo %INICIO% ^| inicio ^| tarefa=%TAREFA% ^| comando=%COMANDO% ^| usuario=%USUARIO_ROTULO%

cd /d "%RAIZ%"

call "%PHP_CLI%" %ARGUMENTOS% > "%ARQUIVO_SAIDA%" 2>&1
set "CODIGO=%ERRORLEVEL%"

if exist "%ARQUIVO_SAIDA%" (
    type "%ARQUIVO_SAIDA%" >> "%ARQUIVO_LOG%"
    type "%ARQUIVO_SAIDA%"
    del "%ARQUIVO_SAIDA%" >nul 2>&1
)

call "%PHP_CLI%" -r "echo date('Y-m-d H:i:s');" > "%ARQUIVO_TMP%"
set /p FIM=<"%ARQUIVO_TMP%"
del "%ARQUIVO_TMP%" >nul 2>&1

call "%PHP_CLI%" -r "echo (int) max(0, time() - strtotime($argv[1]));" "%INICIO%" > "%ARQUIVO_TMP%"
set /p DURACAO=<"%ARQUIVO_TMP%"
del "%ARQUIVO_TMP%" >nul 2>&1
if not defined DURACAO set "DURACAO=0"

>> "%ARQUIVO_LOG%" echo %FIM% ^| fim ^| tarefa=%TAREFA% ^| codigo=%CODIGO% ^| duracao=%DURACAO%s

exit /b %CODIGO%

REM ---------------------------------------------------------------------
REM MODO DE ENSAIO
REM ---------------------------------------------------------------------

:simular
echo Raiz do projeto : %RAIZ%
echo Interpretador   : %PHP_CLI%
echo Comando         : %PHP_CLI% %ARGUMENTOS%
echo Log             : %DIRETORIO_LOGS%\cron-AAAA-MM-DD.log
exit /b 0
