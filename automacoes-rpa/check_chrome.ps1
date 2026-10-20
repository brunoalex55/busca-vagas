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
# VERIFICACAO DO CHROME HEADLESS (WINDOWS)
# ---------------------------------------------------------------------------
#
# Confere se o servico do Chrome esta no ar e, se nao estiver, sobe de novo.
# E o Script de seguranca: o Agendador de Tarefas roda este arquivo de
# minuto em minuto, entao o servico se recupera sozinho se morrer.
#
# Uso:
#   powershell -ExecutionPolicy Bypass -File automacoes-rpa\check_chrome.ps1
#
# No Agendador de Tarefas do Windows, crie uma tarefa "a cada 1 minuto" com:
#   powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\check_chrome.ps1"
#
# ---------------------------------------------------------------------------

$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

$PastaRpa = Split-Path -Parent $MyInvocation.MyCommand.Path
$Raiz = Split-Path -Parent $PastaRpa
$PastaTrabalho = Join-Path $Raiz 'writable\automacoes-rpa\chrome'

$ArquivoLock = Join-Path $PastaTrabalho 'check_chrome.lock'
$DiretorioLogs = Join-Path $PastaTrabalho 'logs'
$ArquivoLog = Join-Path $DiretorioLogs ("chrome-check-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

New-Item -ItemType Directory -Force -Path $PastaTrabalho, $DiretorioLogs | Out-Null

# ---------------------------------------------------------------------------
# INSTANCIA UNICA (lock de arquivo)
# ---------------------------------------------------------------------------

$bloqueado = $false

try {
    $fluxo = [System.IO.File]::Open(
        $ArquivoLock,
        [System.IO.FileMode]::OpenOrCreate,
        [System.IO.FileAccess]::ReadWrite,
        [System.IO.FileShare]::None
    )
} catch {
    # Ja existe uma verificacao rodando: sai em silencio.
    $bloqueado = $true
    $fluxo = $null
}

if ($bloqueado) {
    exit 0
}

function Registrar($Mensagem) {
    try {
        $linha = '{0} | {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Mensagem
        Add-Content -Path $ArquivoLog -Value $linha -Encoding UTF8
    } catch {
        # Sem log nao impede a verificacao.
    }
}

try {
    # ---------------------------------------------------------------------------
    # VERIFICACAO
    # ---------------------------------------------------------------------------

    $arquivoConfig = Join-Path $PastaRpa 'config.ini'
    $endereco = 'http://127.0.0.1:9222'

    if (Test-Path $arquivoConfig) {
        $linha = Select-String -Path $arquivoConfig -Pattern '^endereco_cdp\s*=\s*(.+)$' | Select-Object -Last 1

        if ($linha) {
            $endereco = $linha.Matches[0].Groups[1].Value.Trim()
        }
    }

    # 1) O Chrome responde?
    try {
        $resposta = Invoke-WebRequest -Uri "$endereco/json/version" -UseBasicParsing -TimeoutSec 5

        if ($resposta.StatusCode -eq 200) {
            Registrar "OK | o Chrome responde em $endereco"
            exit 0
        }
    } catch {
        # Nao respondeu: segue para o reinicio.
    }

    # 2) Nao responde: reinicia o Chrome do SERVICO.
    #
    # IMPORTANTE: morre SO o Chrome do servico (o PID do arquivo de
    # controle). O Chrome que a pessoa esta usando na mao NAO e tocado:
    # o servico roda com um perfil proprio, em writable.
    Registrar 'RESTART | o Chrome nao respondeu'

    $pidSalvo = 0

    if (Test-Path $ArquivoPid) {
        $pidSalvo = [int](Get-Content $ArquivoPid -Raw).Trim()
    }

    if ($pidSalvo -gt 0) {
        $processo = Get-Process -Id $pidSalvo -ErrorAction SilentlyContinue

        if ($processo) {
            Stop-Process -Id $pidSalvo -Force -ErrorAction SilentlyContinue
            Registrar "RESTART | o Chrome do servico (pid $pidSalvo) foi encerrado"
        }
    }

    # Sobe de novo pelo script do servico.
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PastaRpa 'chrome_service.ps1') start >> $ArquivoLog 2>&1

    Registrar 'RESTART | o Chrome foi reiniciado'
} finally {
    if ($fluxo) {
        $fluxo.Close()
    }
}

exit 0
