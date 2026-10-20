# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

$ErrorActionPreference = 'Continue'

$base = 'http://localhost:8080'
$raiz = 'C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER'
$php = 'C:\Users\Rafael Albanese\AppData\Local\php84\php.exe'

$falhas = 0
$total = 0

function Checar($rotulo, $ok, $detalhe = '') {
    if ($ok) { Write-Host ("  PASS  {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })) }
    else { Write-Host ("  FALHA {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })); $script:falhas++ }
    $script:total++
}

# Prepara as vagas de teste (em outro processo, para nao disputar a sessao).
& $php "$raiz\tools\fase8-selos-dados.php" criar | Out-Null

try {
    # Login do admin.
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 30 -MaximumRedirection 5

    Write-Host '-- Listagem com a aba do provedor --' -ForegroundColor Yellow

    $r = Invoke-WebRequest -Uri "$base/painel/vagas?fonte=Divulga+Vagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $html = $r.Content

    Checar 'a listagem abre' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode) | $($r.RawContentLength) bytes"
    Checar 'a aba "Divulga Vagas" aparece' ($html.Contains('Divulga Vagas'))

    # Rotulo => classe CSS do selo.
    $selos = [ordered]@{
        'Divulga Vagas - aguardando bot'    = 'selo--divulgavagas-pendente'
        'Divulga Vagas - aguardando e-mail' = 'selo--divulgavagas-espera'
        'Divulga Vagas - enviada (site)'    = 'selo--divulgavagas-enviada'
        'Divulga Vagas - enviada (site + e-mail)' = 'badge-duplo-envio'
        'Divulga Vagas - erro'              = 'selo--divulgavagas-erro'
        'Divulga Vagas - Cloudflare'        = 'selo--divulgavagas-cloudflare'
    }

    foreach ($rotulo in $selos.Keys) {
        $classe = $selos[$rotulo]
        Checar "a listagem mostra o selo: $rotulo" ($html.Contains($rotulo) -and $html.Contains($classe)) "classe $classe"
    }

    Write-Host ''
    Write-Host '-- Detalhe da vaga --' -ForegroundColor Yellow

    # A listagem geral (para achar o id da vaga pendente).
    $r = Invoke-WebRequest -Uri "$base/painel/vagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $html = $r.Content

    $idPendente = ''

    if ($html -match '/painel/vagas/(\d+)"[^>]*>\s*Vaga de teste do selo pendente\s*</a>') {
        $idPendente = $Matches[1]
    }

    Checar 'a vaga de teste pendente aparece na listagem' ($idPendente -ne '') "id $idPendente"

    if ($idPendente -ne '') {
        $r = Invoke-WebRequest -Uri "$base/painel/vagas/$idPendente" -WebSession $session -UseBasicParsing -TimeoutSec 30
        $html = $r.Content

        Checar 'o detalhe mostra o botao Candidatar a esta vaga' ($html.Contains('Candidatar a esta vaga'))
        Checar 'o botao Candidatar a esta vaga e um POST' ($html -match '(?s)<form[^>]*method="post"[^>]*candidatar-vaga')
        Checar 'o detalhe mostra o selo de aguardando bot' ($html.Contains('selo--divulgavagas-pendente'))
    }
} finally {
    # Limpa as vagas de teste.
    & $php "$raiz\tools\fase8-selos-dados.php" limpar | Out-Null
}

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
