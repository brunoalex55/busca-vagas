# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

<#
.SYNOPSIS
    Sobe um servidor de teste do projeto em segundo plano.

.DESCRIPTION
    Os servidores de teste (o site local do Divulga Vagas e o mock da
    API do cron-job.org) sao usados pelas suites de teste para nao
    depender da internet.

    O processo e iniciado escondido e solto (nao espera nem prende o
    console de quem chamou).

    Sem argumentos, o Python e lido do .env do projeto (a chave
    divulgavagas.python) e o script fica ao lado deste arquivo.

.PARAMETER Python
    Caminho do Python (o que tem o Playwright instalado).

.PARAMETER Script
    Caminho do script do servidor de teste.
#>
param(
    [string]$Python = '',
    [string]$Script = ''
)

$ErrorActionPreference = 'Stop'

# A pasta do projeto (a pasta deste script).
$raiz = Split-Path -Parent $PSScriptRoot

# O script do servidor (ao lado deste arquivo).
if ($Script -eq '') {
    $Script = Join-Path $PSScriptRoot 'teste-site-local.py'
}

# O Python: o informado ou o do .env do projeto.
if ($Python -eq '') {
    $env = Join-Path $raiz '.env'

    if (Test-Path $env) {
        foreach ($linha in Get-Content $env) {
            $linha = $linha.Trim()

            if ($linha.StartsWith('divulgavagas.python')) {
                $partes = $linha.Split('=', 2)

                if ($partes.Count -eq 2) {
                    $Python = $partes[1].Trim().Trim('"', "'")
                }
            }
        }
    }
}

if ($Python -eq '') {
    Write-Host 'Python nao encontrado (informe o caminho como argumento)'
    exit 1
}

if (-not (Test-Path $Python)) {
    Write-Host "Python nao encontrado: $Python"
    exit 1
}

if (-not (Test-Path $Script)) {
    Write-Host "Script nao encontrado: $Script"
    exit 1
}

# Solta o processo: a janela fica escondida e quem chamou nao espera.
Start-Process -FilePath $Python -ArgumentList "`"$Script`"" -WindowStyle Hidden

Write-Host "servidor de teste iniciando em segundo plano"
