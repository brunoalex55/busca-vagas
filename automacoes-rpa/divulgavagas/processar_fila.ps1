# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

# ---------------------------------------------------------------------------
# DISPARO DA FILA DO DIVULGA VAGAS (WINDOWS)
# ---------------------------------------------------------------------------
#
# Roda o comando do CI4 do segundo provedor pela linha de comando. O
# script NAO decide o que roda: ele apenas chama o comando Spark, que ja
# segue as regras do sistema (limites do bot, limite de e-mail, janela de
# envio e as situacoes da candidatura).
#
# Uso:
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\divulgavagas\processar_fila.ps1 -modo buscar-vagas
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\divulgavagas\processar_fila.ps1 -modo candidatar
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\divulgavagas\processar_fila.ps1 -modo candidatar -usuario 8
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\divulgavagas\processar_fila.ps1 -modo buscar-vagas -simular
#
# No Agendador de Tarefas do Windows:
#   Tarefa "CRON 1 do Divulga Vagas" (a cada 30 min):
#     powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\divulgavagas\processar_fila.ps1" -modo buscar-vagas
#
#   Tarefa "CRON 2 do Divulga Vagas" (a cada 5 min):
#     powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\divulgavagas\processar_fila.ps1" -modo candidatar
#
# ---------------------------------------------------------------------------

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('buscar-vagas', 'candidatar')]
    [string] $Modo,

    [int] $Usuario = 0,

    [switch] $Simular
)

$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

# Pasta do provedor (a mesma deste arquivo).
$PastaProvedor = Split-Path -Parent $MyInvocation.MyCommand.Path

# Pasta das automacoes RPA (um nivel acima).
$PastaRpa = Split-Path -Parent $PastaProvedor

# Raiz do projeto (um nivel acima das automacoes).
$Raiz = Split-Path -Parent $PastaRpa

$PastaTrabalho = Join-Path $Raiz 'writable\automacoes-rpa\divulgavagas'
$DiretorioLogs = Join-Path $PastaTrabalho 'logs'
$ArquivoLog = Join-Path $DiretorioLogs ("cron-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

# ---------------------------------------------------------------------------
# COMANDO
# ---------------------------------------------------------------------------

switch ($Modo) {
    'buscar-vagas' { $Comando = 'divulgavagas:buscar-vagas' }
    'candidatar' { $Comando = 'divulgavagas:candidatar' }
}

$Argumentos = @('spark', $Comando)

if ($Usuario -gt 0) {
    $Argumentos += "--usuario=$Usuario"
}

# ---------------------------------------------------------------------------
# INTERPRETADOR PHP
# ---------------------------------------------------------------------------

# Ordem de descoberta:
#   1. variavel de ambiente PHP_CLI;
#   2. cron.caminhoPhpCli no .env do projeto (mesma chave usada pelo painel);
#   3. php no PATH do sistema.

$PhpCli = $env:PHP_CLI

if (-not $PhpCli) {
    $arquivoEnv = Join-Path $Raiz '.env'

    if (Test-Path $arquivoEnv) {
        $linha = Select-String -Path $arquivoEnv -Pattern '^cron\.caminhoPhpCli\s*=\s*(.+)$' | Select-Object -Last 1

        if ($linha) {
            $PhpCli = $linha.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'")
        }
    }
}

if (-not $PhpCli) {
    $achado = Get-Command php -ErrorAction SilentlyContinue

    if ($achado) {
        $PhpCli = $achado.Source
    }
}

if (-not $PhpCli) {
    Write-Error 'PHP nao encontrado. Defina PHP_CLI ou cron.caminhoPhpCli no .env.'
    exit 3
}

# ---------------------------------------------------------------------------
# EXECUCAO
# ---------------------------------------------------------------------------

if ($Simular) {
    Write-Host "Raiz do projeto : $Raiz"
    Write-Host "Interpretador   : $PhpCli"
    Write-Host "Comando         : $PhpCli $($Argumentos -join ' ')"
    Write-Host "Log             : $ArquivoLog"
    exit 0
}

Set-Location $Raiz

# O log e importante para auditoria, mas nao pode impedir a tarefa.
try {
    New-Item -ItemType Directory -Force -Path $DiretorioLogs | Out-Null
} catch {
    Write-Warning "Sem acesso a $DiretorioLogs. Esta execucao nao sera registrada em log."
    $ArquivoLog = ''
}

function Registrar($Mensagem) {
    if ($ArquivoLog) {
        try {
            Add-Content -Path $ArquivoLog -Value $Mensagem -Encoding UTF8
        } catch {
            # Sem log nao impede a tarefa.
        }
    }
}

$inicio = Get-Date
$segundosInicio = [int]$inicio.Subtract((Get-Date '2000-01-01')).TotalSeconds

Registrar ("{0} | inicio | comando={1} | usuario={2}" -f $inicio.ToString('yyyy-MM-dd HH:mm:ss'), $Comando, $(if ($Usuario -gt 0) { $Usuario } else { 'todos' }))

# A execucao pode terminar com codigo diferente de zero (ex.: limite do bot).
$saida = & $PhpCli @Argumentos 2>&1
$codigo = $LASTEXITCODE

if ($saida) {
    Registrar ($saida -join "`n")
}

$fim = Get-Date
$segundosFim = [int]$fim.Subtract((Get-Date '2000-01-01')).TotalSeconds
$duracao = $segundosFim - $segundosInicio

Registrar ("{0} | fim | comando={1} | codigo={2} | duracao={3}s" -f $fim.ToString('yyyy-MM-dd HH:mm:ss'), $Comando, $codigo, $duracao)

# Repete a saida no console.
if ($saida) {
    $saida | ForEach-Object { Write-Host $_ }
}

exit $codigo
