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

$r = Invoke-WebRequest -Uri "$base/precos" -UseBasicParsing -TimeoutSec 60
$html = $r.Content

Write-Host '-- A estrutura da pagina --' -ForegroundColor Yellow

Checar 'a pagina abre' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode) | $($r.RawContentLength) bytes"
Checar 'o topo da pagina' ($html.Contains('pagina-topo'))
Checar 'os 3 cards de plano' (([regex]::Matches($html, '<article class="plano')).Count -eq 3) "$(([regex]::Matches($html, '<article class=""plano""')).Count) card(s)"
Checar 'o aviso do PIX' ($html.Contains('planos__aviso'))
Checar 'o comparativo (tabela)' ($html.Contains('O que muda entre os planos'))
Checar 'o FAQ' ($html.Contains('faq__item'))
Checar 'o CTA final' ($html.Contains('cta-final'))
Checar 'o item indisponivel (com a classe)' ($html.Contains('plano__item-indisponivel'))

Write-Host ''
Write-Host '-- Os textos --' -ForegroundColor Yellow

Checar 'o titulo tem "Preços" (com cedilha)' ($html.Contains('Preços'))
Checar 'o subtitulo tem "Sem fidelidade. Cancele quando quiser."' ($html.Contains('Sem fidelidade. Cancele quando quiser.'))
Checar 'o plano basico' ($html.Contains('Básico'))
Checar 'o plano profissional' ($html.Contains('Profissional'))
Checar 'o plano empresa' ($html.Contains('Empresa'))
Checar 'o "Mais popular"' ($html.Contains('Mais popular'))
Checar 'o texto "Ilimitadas"' ($html.Contains('Ilimitadas'))
Checar 'o travessao do recurso ausente (a tabela comparativa)' ($html.Contains('&mdash;'))
Checar 'o "50 por usuário"' ($html.Contains('50 por usuário'))
Checar 'o "Até 5"' ($html.Contains('Até 5'))
Checar 'o "Prioritário"' ($html.Contains('Prioritário'))
Checar 'o "Suporte por e-mail"' ($html.Contains('Suporte por e-mail'))

Write-Host ''
Write-Host '-- Os links do cadastro (com o plano) --' -ForegroundColor Yellow

Checar 'o basico leva ?plano=basico' ($html.Contains('registro?plano=basico'))
Checar 'o profissional leva ?plano=profissional' ($html.Contains('registro?plano=profissional'))

Write-Host ''
Write-Host "-- as classes do CSS que a pagina usa --"

$css = [System.IO.File]::ReadAllText("$raiz\public\assets\css\site.css")
$componentes = [System.IO.File]::ReadAllText("$raiz\public\assets\css\componentes.css")
$base = [System.IO.File]::ReadAllText("$raiz\public\assets\css\base.css")

$classes = @{}

foreach ($m in [regex]::Matches($html, 'class="([^"]+)"')) {
    foreach ($c in ($m.Groups[1].Value -split ' ')) {
        if ($c -ne '' -and $c -notmatch '\$') { $classes[$c] = $true }
    }
}

$faltando = @()

foreach ($c in $classes.Keys) {
    # A barra de depuracao do CodeIgniter (kint-*) nao e do projeto.
    if ($c -like 'kint-*') { continue }

    # "animar" e uma classe de apoio (a animacao vem do "ao-rolar").
    if ($c -eq 'animar') { continue }

    $achou = ($css -match "\.$([regex]::Escape($c))[\s{,.:]") -or ($componentes -match "\.$([regex]::Escape($c))[\s{,.:]") -or ($base -match "\.$([regex]::Escape($c))[\s{,.:]")

    if (-not $achou) { $faltando += $c }
}

Checar 'toda classe da pagina existe no CSS' ($faltando.Count -eq 0) ($faltando -join ', ')

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
