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

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$falhas = 0
$total = 0

function Checar($rotulo, $ok, $detalhe = '') {
    if ($ok) { Write-Host ("  PASS  {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })) }
    else { Write-Host ("  FALHA {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })); $script:falhas++ }
    $script:total++
}

# Login do admin (o admin tem o painel tambem).
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 30
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 30 -MaximumRedirection 5

Write-Host '-- Telas do Divulga Vagas (usuario) --' -ForegroundColor Yellow

foreach ($rota in @('/painel/divulgavagas', '/painel/divulgavagas/historico')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 30
        Checar "GET $rota" ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota" ($codigo -eq 200) "HTTP $codigo"
    }
}

Write-Host ''
Write-Host '-- Conteudo da tela de execucao manual --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/painel/divulgavagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
$html = $r.Content

Checar 'tem o titulo da secao de busca' ($html -match 'Buscar vagas agora')
Checar 'tem o seletor de palavra-chave' ($html -match 'name="palavra_chave_id"')
Checar 'tem o campo de cidade' ($html -match 'name="cidade"')
Checar 'tem o botao de executar a busca' ($html -match 'Executar busca agora')
Checar 'tem o botao de candidatar em todas as vagas' ($html -match 'Candidatar em todas as vagas pendentes')
Checar 'tem a barra do limite de buscas' ($html -match 'barra-limite-manual')
Checar 'mostra o contador de buscas' ($html -match 'Buscas manuais hoje')
Checar 'mostra o contador de candidaturas' ($html -match 'Candidaturas manuais hoje')
Checar 'tem a secao de situacao das candidaturas' ($html -match 'Aguardando o bot')
Checar 'tem o historico' ($html -match 'Historico')
Checar 'os formularios tem data-execucao-divulgavagas' (([regex]::Matches($html, 'data-execucao-divulgavagas')).Count -eq 2)

Write-Host ''
Write-Host '-- Aba e badges na listagem de vagas --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/painel/vagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
$html = $r.Content

Checar 'a aba "Divulga Vagas" existe' ($html -match 'Divulga Vagas')

Write-Host ''
Write-Host '-- Card no dashboard --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $session -UseBasicParsing -TimeoutSec 30
$html = $r.Content

Checar 'o menu tem o item Divulga Vagas' ($html -match 'painel/divulgavagas')
Checar 'a lista de candidaturas realizadas aparece' ($html -match 'Candidaturas realizadas')

Write-Host ''
Write-Host '-- Escrita: so POST --' -ForegroundColor Yellow

# As rotas de escrita nao podem responder a GET.
foreach ($rota in @('/painel/divulgavagas/buscar-manual', '/painel/divulgavagas/candidatar-manual')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 30
        Checar "GET $rota nao executa nada" ($r.StatusCode -eq 404 -or $r.StatusCode -eq 405) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota nao executa nada" ($codigo -eq 404 -or $codigo -eq 405) "HTTP $codigo"
    }
}

# Um POST sem os dados obrigatorios tem de recusar (sem executar nada).
$r = Invoke-WebRequest -Uri "$base/painel/divulgavagas/buscar-manual" -Method Post -Body @{} -WebSession $session -UseBasicParsing -TimeoutSec 30
Checar 'POST sem palavra-chave e recusado' ($r.Content -match 'Escolha a palavra-chave') 'mensagem de erro'

Write-Host ''
Write-Host '-- Resposta JSON para o fetch --' -ForegroundColor Yellow

$corpo = ''

try {
    $r = Invoke-WebRequest -Uri "$base/painel/divulgavagas/buscar-manual" -Method Post -Body @{} -WebSession $session -UseBasicParsing -TimeoutSec 30 -Headers @{ 'X-Requested-With' = 'fetch' }
    $corpo = $r.Content
    $codigo = $r.StatusCode
} catch {
    $codigo = 0
    $corpo = ''

    if ($_.Exception.Response) {
        $codigo = [int] $_.Exception.Response.StatusCode

        try {
            $leitor = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $corpo = $leitor.ReadToEnd()
        } catch { }
    }
}

Checar 'a resposta do fetch e JSON' ($corpo -ne '') "HTTP $codigo"

$json = $null

if ($corpo -ne '') {
    $json = $corpo | ConvertFrom-Json -ErrorAction SilentlyContinue
}

Checar 'o JSON tem sucesso e mensagem' ($json -ne $null -and $json.mensagem -ne '') "sucesso: $($json.sucesso) | mensagem: $($json.mensagem)"
Checar 'o JSON tem o limite' ($json -ne $null -and $json.limite -ne $null) "limite: $($json.limite) | usadas: $($json.usadas)"

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
