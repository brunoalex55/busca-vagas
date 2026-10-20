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

# Todo o CSS do projeto (para saber quais classes existem).
$css = ''
foreach ($arquivo in @('site.css', 'componentes.css', 'base.css', 'painel.css', 'admin.css', 'variaveis.css', 'reset.css', 'responsivo.css')) {
    $css += [System.IO.File]::ReadAllText("$raiz\public\assets\css\$arquivo")
}

Write-Host '-- As classes de cada pagina publica --' -ForegroundColor Yellow

foreach ($rota in @('/', '/recursos', '/como-funciona', '/precos', '/sobre', '/contato', '/login', '/registro')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -UseBasicParsing -TimeoutSec 60
    } catch {
        Checar "GET $rota" $false "nao respondeu"
        continue
    }

    $html = $r.Content
    $classes = @{}

    foreach ($m in [regex]::Matches($html, 'class="([^"]+)"')) {
        foreach ($c in ($m.Groups[1].Value -split ' ')) {
            if ($c -ne '' -and $c -notmatch '\$' -and $c -notmatch '^\d') { $classes[$c] = $true }
        }
    }

    $faltando = @()

    foreach ($c in $classes.Keys) {
        # A barra de depuracao do CodeIgniter nao e do projeto.
        if ($c -like 'kint-*') { continue }

        # "animar" e uma classe de apoio (a animacao vem do "ao-rolar").
        if ($c -eq 'animar') { continue }

        if ($css -notmatch "\.$([regex]::Escape($c))[\s{,.:]") { $faltando += $c }
    }

    Checar "GET $rota" ($r.StatusCode -eq 200 -and $faltando.Count -eq 0) ("HTTP {0} | classes sem CSS: {1}" -f $r.StatusCode, ($faltando -join ', '))
}

Write-Host ''
Write-Host '-- As classes dos paineis (com o login) --' -ForegroundColor Yellow

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 60
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

foreach ($rota in @('/painel', '/painel/vagas', '/painel/palavras-chave', '/painel/configuracoes', '/painel/divulgavagas', '/admin', '/admin/usuarios', '/admin/usuarios/8', '/admin/planos', '/admin/cron', '/admin/logs')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 60
    } catch {
        Checar "GET $rota" $false "nao respondeu"
        continue
    }

    $html = $r.Content
    $classes = @{}

    foreach ($m in [regex]::Matches($html, 'class="([^"]+)"')) {
        foreach ($c in ($m.Groups[1].Value -split ' ')) {
            if ($c -ne '' -and $c -notmatch '\$' -and $c -notmatch '^\d') { $classes[$c] = $true }
        }
    }

    $faltando = @()

    foreach ($c in $classes.Keys) {
        if ($c -like 'kint-*') { continue }
        if ($c -eq 'animar') { continue }

        if ($css -notmatch "\.$([regex]::Escape($c))[\s{,.:]") { $faltando += $c }
    }

    Checar "GET $rota" ($r.StatusCode -eq 200 -and $faltando.Count -eq 0) ("HTTP {0} | classes sem CSS: {1}" -f $r.StatusCode, ($faltando -join ', '))
}

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
