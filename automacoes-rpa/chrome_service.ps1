# ---------------------------------------------------------------------------
# SERVICO DO CHROME HEADLESS (WINDOWS)
# ---------------------------------------------------------------------------
#
# Sobe, para e reinicia o Chrome headless que as automacoes RPA usam.
#
# O Chrome e um SERVICO UNICO e persistente:
#   - fica no ar com o perfil em writable\automacoes-rpa\chrome\sessions;
#   - escuta a porta CDP (http://127.0.0.1:9222);
#   - os bots (Divulga Vagas, Gupy, LinkedIn...) so abrem e fecham abas.
#
# Uso:
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\chrome_service.ps1 start
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\chrome_service.ps1 stop
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\chrome_service.ps1 restart
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\chrome_service.ps1 status
#
# No Agendador de Tarefas do Windows, crie uma tarefa "ao logon" com:
#   powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\chrome_service.ps1" start
#
# ---------------------------------------------------------------------------

param(
    [Parameter(Position = 0)]
    [ValidateSet('start', 'stop', 'restart', 'status')]
    [string] $Acao = 'start'
)

$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

# Pasta das automacoes RPA (a mesma deste arquivo).
$PastaRpa = Split-Path -Parent $MyInvocation.MyCommand.Path

# Raiz do projeto (a pasta imediatamente acima).
$Raiz = Split-Path -Parent $PastaRpa

# Pasta de trabalho (perfil do Chrome, logs e PID): fora do versionamento.
$PastaTrabalho = Join-Path $Raiz 'writable\automacoes-rpa\chrome'

$ArquivoPid = Join-Path $PastaTrabalho 'chrome_service.pid'
$ArquivoLock = Join-Path $PastaTrabalho 'check_chrome.lock'
$DiretorioLogs = Join-Path $PastaTrabalho 'logs'
$ArquivoLog = Join-Path $DiretorioLogs ("chrome-service-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

# ---------------------------------------------------------------------------
# PYTHON
# ---------------------------------------------------------------------------

# Ordem de descoberta:
#   1. variavel de ambiente PYTHON_BOT;
#   2. python e python3 no PATH do sistema.

$Python = $env:PYTHON_BOT

if (-not $Python) {
    foreach ($candidato in @('python', 'python3')) {
        $achado = Get-Command $candidato -ErrorAction SilentlyContinue

        if ($achado) {
            $Python = $achado.Source
            break
        }
    }
}

if (-not $Python) {
    Write-Error 'Python nao encontrado. Instale o Python 3 ou defina PYTHON_BOT.'
    exit 3
}

# ---------------------------------------------------------------------------
# AJUDA
# ---------------------------------------------------------------------------

function Registrar($Mensagem) {
    try {
        if (-not (Test-Path $DiretorioLogs)) {
            New-Item -ItemType Directory -Force -Path $DiretorioLogs | Out-Null
        }

        $linha = '{0} | {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Mensagem
        Add-Content -Path $ArquivoLog -Value $linha -Encoding UTF8
    } catch {
        # Sem log nao impede o servico.
    }
}

function ObterEnderecoCdp {
    $arquivoConfig = Join-Path $PastaRpa 'config.ini'

    if (Test-Path $arquivoConfig) {
        $linha = Select-String -Path $arquivoConfig -Pattern '^endereco_cdp\s*=\s*(.+)$' | Select-Object -Last 1

        if ($linha) {
            return $linha.Matches[0].Groups[1].Value.Trim()
        }
    }

    return 'http://127.0.0.1:9222'
}

# O Chrome responde na porta do CDP?
function CdpResponde {
    try {
        $resposta = Invoke-WebRequest -Uri "$(ObterEnderecoCdp)/json/version" -UseBasicParsing -TimeoutSec 5
        return ($resposta.StatusCode -eq 200)
    } catch {
        return $false
    }
}

# O processo do Chrome esta vivo?
function ProcessoVivo {
    if (-not (Test-Path $ArquivoPid)) {
        return $false
    }

    $pidSalvo = (Get-Content $ArquivoPid -Raw).Trim()

    if (-not $pidSalvo) {
        return $false
    }

    $processo = Get-Process -Id ([int]$pidSalvo) -ErrorAction SilentlyContinue

    return ($null -ne $processo)
}

# ---------------------------------------------------------------------------
# ACOES
# ---------------------------------------------------------------------------

function AcaoStart {
    # Instancia unica: se ja responde, nao abre outro.
    if (CdpResponde) {
        Registrar 'start | o Chrome ja esta no ar'
        Write-Host 'O Chrome ja esta no ar.'
        return 0
    }

    New-Item -ItemType Directory -Force -Path $PastaTrabalho, $DiretorioLogs | Out-Null

    # PID antigo de um processo morto: remove.
    if ((Test-Path $ArquivoPid) -and -not (ProcessoVivo)) {
        Remove-Item $ArquivoPid -Force
    }

    Registrar 'start | subindo o Chrome headless'

    # O servico escreve o PID do Chrome no arquivo de controle.
    $processo = Start-Process -FilePath $Python `
        -ArgumentList @((Join-Path $PastaRpa 'chrome_service.py'), 'run') `
        -WorkingDirectory $PastaRpa `
        -WindowStyle Hidden `
        -PassThru `
        -RedirectStandardError (Join-Path $DiretorioLogs 'servico.err.log') `
        -RedirectStandardOutput (Join-Path $DiretorioLogs 'servico.out.log')

    # Espera a porta CDP responder (ate 60 segundos).
    for ($i = 0; $i -lt 60; $i++) {
        if (CdpResponde) {
            $pidChrome = '?'

            if (Test-Path $ArquivoPid) {
                $pidChrome = (Get-Content $ArquivoPid -Raw).Trim()
            }

            Registrar "start | Chrome no ar (pid $pidChrome)"
            Write-Host "Chrome no ar (pid $pidChrome)."
            return 0
        }

        Start-Sleep -Seconds 1
    }

    Registrar 'start | o Chrome nao respondeu a tempo'
    Write-Error "O Chrome nao respondeu na porta do CDP. Veja $ArquivoLog."
    return 1
}

function AcaoStop {
    if (-not (ProcessoVivo)) {
        Remove-Item $ArquivoPid -Force -ErrorAction SilentlyContinue
        Registrar 'stop | o Chrome nao estava no ar'
        Write-Host 'O Chrome nao estava no ar.'
        return 0
    }

    $pidSalvo = (Get-Content $ArquivoPid -Raw).Trim()
    Registrar "stop | encerrando o Chrome (pid $pidSalvo)"

    Stop-Process -Id ([int]$pidSalvo) -Force -ErrorAction SilentlyContinue

    # Espera o processo sair.
    for ($i = 0; $i -lt 20; $i++) {
        if (-not (ProcessoVivo)) {
            break
        }

        Start-Sleep -Seconds 1
    }

    Remove-Item $ArquivoPid -Force -ErrorAction SilentlyContinue
    Registrar 'stop | Chrome encerrado'
    Write-Host 'Chrome encerrado.'
    return 0
}

function AcaoStatus {
    if (CdpResponde) {
        $pidSalvo = '?'

        if (Test-Path $ArquivoPid) {
            $pidSalvo = (Get-Content $ArquivoPid -Raw).Trim()
        }

        Write-Host "Chrome no ar (pid $pidSalvo)."
        return 0
    }

    Write-Host 'Chrome fora do ar.'
    return 1
}

# ---------------------------------------------------------------------------
# ENTRADA
# ---------------------------------------------------------------------------

switch ($Acao) {
    'start' { exit (AcaoStart) }
    'stop' { exit (AcaoStop) }
    'restart' {
        AcaoStop | Out-Null
        Start-Sleep -Seconds 2
        exit (AcaoStart)
    }
    'status' { exit (AcaoStatus) }
}