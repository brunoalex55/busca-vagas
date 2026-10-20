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

# Login do admin.
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 30
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 30 -MaximumRedirection 5

Write-Host '-- As telas do Divulga Vagas (admin) --' -ForegroundColor Yellow

foreach ($rota in @('/admin/divulgavagas', '/admin/divulgavagas/historico')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 30
        Checar "GET $rota" ($r.StatusCode -eq 200) "HTTP $($r.StatusCode) | $($r.RawContentLength) bytes"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota" ($codigo -eq 200) "HTTP $codigo"
    }
}

Write-Host ''
Write-Host '-- O conteudo da tela de execucao manual --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/divulgavagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
$html = $r.Content

Checar 'tem o titulo da secao' ($html.Contains('Divulga Vagas (segundo provedor)'))
Checar 'tem o seletor de alvo' ($html.Contains('name="alvo"'))
Checar 'o alvo comeca em "todos"' ($html.Contains('<option value="todos">Todos os usuarios ativos</option>'))
Checar 'tem o botao de executar a busca' ($html.Contains('Executar busca agora'))
Checar 'tem o botao de candidatar em todas as vagas' ($html.Contains('Candidatar em todas as vagas pendentes'))
Checar 'tem a barra do limite de buscas' ($html.Contains('barra-limite-manual'))
Checar 'mostra o contador de buscas do admin' ($html.Contains('Buscas manuais do admin hoje'))
Checar 'mostra o contador de candidaturas do admin' ($html.Contains('Candidaturas manuais do admin hoje'))
Checar 'tem a situacao global das candidaturas' ($html.Contains('Situacao das candidaturas (todos os usuarios)'))
Checar 'tem o formulario de limites' ($html.Contains('admin_max_buscas_manuais_dia'))
Checar 'tem o formulario de limites (candidaturas)' ($html.Contains('admin_max_candidaturas_manuais_dia'))
Checar 'os formularios de execucao tem data-execucao-divulgavagas' (([regex]::Matches($html, 'data-execucao-divulgavagas')).Count -eq 2)
Checar 'tem os campos escondidos do alvo' ($html.Contains('id="alvo-buscar"') -and $html.Contains('id="usuario-candidatar"'))

Write-Host ''
Write-Host '-- O menu e a tela de agendamento --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin" -WebSession $session -UseBasicParsing -TimeoutSec 30
Checar 'o menu do admin tem o item Divulga Vagas' ($r.Content.Contains('admin/divulgavagas'))

$r = Invoke-WebRequest -Uri "$base/admin/cron" -WebSession $session -UseBasicParsing -TimeoutSec 30
Checar 'a tela de agendamento tem a secao do Divulga Vagas' ($r.Content.Contains('Divulga Vagas (segundo provedor)'))
Checar 'a secao mostra o CRON 1' ($r.Content.Contains('cron/global/divulgavagas/buscar-vagas'))
Checar 'a secao mostra o CRON 2' ($r.Content.Contains('cron/global/divulgavagas/candidatar'))
Checar 'a secao tem o link para o painel do provedor' ($r.Content.Contains('admin/divulgavagas'))

Write-Host ''
Write-Host '-- O filtro por provedor nos logs --' -ForegroundColor Yellow

# Cria um envio de cada provedor (para o filtro ter o que separar).
& $php "$raiz\tools\fase8-logs-dados.php" criar | Out-Null

try {
    $r = Invoke-WebRequest -Uri "$base/admin/logs?aba=envios" -WebSession $session -UseBasicParsing -TimeoutSec 30
    Checar 'a aba de envios tem o filtro de provedor' ($r.Content.Contains('name="provedor"'))
    Checar 'o filtro tem a opcao Divulga Vagas' ($r.Content.Contains('Divulga Vagas'))
    Checar 'a tabela mostra a coluna Provedor' ($r.Content.Contains('<th scope="col">Provedor</th>'))
    Checar 'a tela lista os dois provedores' ($r.Content.Contains('SerpApi') -and $r.Content.Contains('Divulga Vagas'))

    # O filtro por provedor separa os registros.
    $r = Invoke-WebRequest -Uri "$base/admin/logs?aba=envios&provedor=divulgavagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
    Checar 'o filtro traz so o Divulga Vagas' ($r.Content.Contains('teste-divulgavagas@empresa-teste.com.br'))
    Checar 'o filtro esconde o SerpApi' (-not $r.Content.Contains('teste-serpapi@empresa-teste.com.br'))

    $r = Invoke-WebRequest -Uri "$base/admin/logs?aba=envios&provedor=serpapi" -WebSession $session -UseBasicParsing -TimeoutSec 30
    Checar 'o filtro traz so o SerpApi' ($r.Content.Contains('teste-serpapi@empresa-teste.com.br'))
    Checar 'o filtro esconde o Divulga Vagas' (-not $r.Content.Contains('teste-divulgavagas@empresa-teste.com.br'))

    $r = Invoke-WebRequest -Uri "$base/admin/logs?aba=consultas" -WebSession $session -UseBasicParsing -TimeoutSec 30
    Checar 'a aba de consultas tem o filtro de provedor' ($r.Content.Contains('name="provedor"'))
    Checar 'a tabela de consultas mostra a coluna Provedor' ($r.Content.Contains('<th scope="col">Provedor</th>'))

    $r = Invoke-WebRequest -Uri "$base/admin/logs?aba=envios&provedor=divulgavagas" -WebSession $session -UseBasicParsing -TimeoutSec 30
    Checar 'o filtro por provedor e aceito na URL' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
} finally {
    & $php "$raiz\tools\fase8-logs-dados.php" limpar | Out-Null
}

Write-Host ''
Write-Host '-- Escrita: so POST --' -ForegroundColor Yellow

foreach ($rota in @('/admin/divulgavagas/buscar-manual', '/admin/divulgavagas/candidatar-manual', '/admin/divulgavagas/limites')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 30
        Checar "GET $rota nao executa nada" ($r.StatusCode -eq 404 -or $r.StatusCode -eq 405) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota nao executa nada" ($codigo -eq 404 -or $codigo -eq 405) "HTTP $codigo"
    }
}

Write-Host ''
Write-Host '-- Salvar os limites (POST) --' -ForegroundColor Yellow

$antes = & $php -r "chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER'); require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php'; `$c = model('ConfiguracaoGlobalModel')->obter(); echo (int) `$c['admin_max_buscas_manuais_dia'] . '|' . (int) `$c['admin_max_candidaturas_manuais_dia'];"

$r = Invoke-WebRequest -Uri "$base/admin/divulgavagas/limites" -Method Post -Body @{ admin_max_buscas_manuais_dia = 7; admin_max_candidaturas_manuais_dia = 9 } -WebSession $session -UseBasicParsing -TimeoutSec 30
Checar 'o POST dos limites responde 200' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
Checar 'a tela mostra a mensagem de sucesso' ($r.Content.Contains('Limites de execucao manual salvos'))

$depois = & $php -r "chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER'); require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php'; `$c = model('ConfiguracaoGlobalModel')->obter(); echo (int) `$c['admin_max_buscas_manuais_dia'] . '|' . (int) `$c['admin_max_candidaturas_manuais_dia'];"
Checar 'os limites foram gravados' ($depois -eq '7|9') "antes: $antes | depois: $depois"

# Devolve os limites originais.
$r = Invoke-WebRequest -Uri "$base/admin/divulgavagas/limites" -Method Post -Body @{ admin_max_buscas_manuais_dia = 10; admin_max_candidaturas_manuais_dia = 20 } -WebSession $session -UseBasicParsing -TimeoutSec 30

Write-Host ''
Write-Host '-- A resposta JSON para o fetch --' -ForegroundColor Yellow

$corpo = ''

try {
    $r = Invoke-WebRequest -Uri "$base/admin/divulgavagas/buscar-manual" -Method Post -Body @{ alvo = 'todos' } -WebSession $session -UseBasicParsing -TimeoutSec 300 -Headers @{ 'X-Requested-With' = 'fetch' }
    $corpo = $r.Content
} catch {
    $corpo = ''

    if ($_.Exception.Response) {
        try {
            $leitor = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $corpo = $leitor.ReadToEnd()
        } catch { }
    }
}

$json = $null

if ($corpo -ne '') {
    $json = $corpo | ConvertFrom-Json -ErrorAction SilentlyContinue
}

Checar 'a resposta do fetch e JSON' ($json -ne $null) ($corpo -replace '\s+', ' ').Substring(0, [Math]::Min(120, ($corpo -replace '\s+', ' ').Length))
Checar 'o JSON tem a mensagem' ($json -ne $null -and $json.mensagem -ne '') "mensagem: $($json.mensagem)"
Checar 'o JSON tem o limite' ($json -ne $null -and $json.limite -ne $null) "limite: $($json.limite) | usadas: $($json.usadas)"

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
