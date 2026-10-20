# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('start', 'stop', 'status')]
    [string]$Action
)

$ErrorActionPreference = 'Stop'

$mariaRoot = 'C:\Users\Rafael Albanese\AppData\Local\MariaDB'
$project   = Split-Path -Parent $PSScriptRoot
$myIni     = Join-Path $mariaRoot 'data\my.ini'
$port      = 3306

function Test-MariaListening {
    return [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
}

function Get-DbPassword {
    $line = Get-Content (Join-Path $project '.env') |
        Where-Object { $_ -match '^database\.default\.password\s*=' } |
        Select-Object -First 1

    if (-not $line) { throw "database.default.password nao encontrado no .env" }

    return (($line -split '=', 2)[1]).Trim()
}

switch ($Action) {

    'start' {
        if (Test-MariaListening) {
            Write-Host "MariaDB ja esta em execucao na porta $port."
            exit 0
        }

        Start-Process -FilePath (Join-Path $mariaRoot 'bin\mariadbd.exe') `
            -ArgumentList "--defaults-file=`"$myIni`"", '--console' `
            -WindowStyle Hidden `
            -RedirectStandardError  (Join-Path $mariaRoot 'data\server.err') `
            -RedirectStandardOutput (Join-Path $mariaRoot 'data\server.out')

        for ($i = 0; $i -lt 40; $i++) {
            Start-Sleep -Seconds 1
            if (Test-MariaListening) { break }
        }

        if (Test-MariaListening) {
            Write-Host "MariaDB iniciado em 127.0.0.1:$port"
        } else {
            Write-Error "MariaDB nao subiu. Veja $mariaRoot\data\server.err"
            exit 1
        }
    }

    'stop' {
        if (-not (Test-MariaListening)) {
            Write-Host "MariaDB nao esta em execucao."
            exit 0
        }

        $pw = Get-DbPassword
        & (Join-Path $mariaRoot 'bin\mariadb-admin.exe') `
            --protocol=TCP -h 127.0.0.1 -P $port -u ci_employer --password="$pw" shutdown | Out-Null

        for ($i = 0; $i -lt 30; $i++) {
            Start-Sleep -Seconds 1
            if (-not (Test-MariaListening)) { break }
        }

        if (Test-MariaListening) {
            Write-Error "MariaDB nao parou."
            exit 1
        }

        Write-Host "MariaDB parado."
    }

    'status' {
        if (Test-MariaListening) {
            $v = & (Join-Path $mariaRoot 'bin\mariadb.exe') `
                --protocol=TCP -h 127.0.0.1 -P $port -u ci_employer `
                --password="$(Get-DbPassword)" -N -B -e "SELECT VERSION();"

            Write-Host "MariaDB rodando em 127.0.0.1:$port (versao $v)"
        } else {
            Write-Host "MariaDB parado."
        }
    }
}
