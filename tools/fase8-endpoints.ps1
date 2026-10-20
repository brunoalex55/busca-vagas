$ErrorActionPreference = 'Continue'

$base = 'http://localhost:8080'
$raiz = 'C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER'

# Token global do sistema.
$caminhoToken = Join-Path $raiz 'writable\cron_token.txt'
if (-not (Test-Path $caminhoToken)) {
    # Gera o token chamando o admin (o arquivo e criado na primeira leitura).
    $null = Invoke-WebRequest -Uri "$base/admin/cron" -UseBasicParsing -TimeoutSec 20 -ErrorAction SilentlyContinue
}

$token = ''
if (Test-Path $caminhoToken) {
    $token = (Get-Content $caminhoToken -Raw).Trim()
}

Write-Host "token global: $(if ($token -ne '') { 'presente (' + $token.Length + ' caracteres)' } else { 'AUSENTE' })"

function Chamar([string] $rota, [string] $tokenUsado) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -Method Post -UseBasicParsing -TimeoutSec 120
        return @{ Status = $r.StatusCode; Corpo = $r.Content }
    } catch {
        $codigo = 0
        $texto = ''
        if ($_.Exception.Response) {
            $codigo = [int] $_.Exception.Response.StatusCode
            try {
                $leitor = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $texto = $leitor.ReadToEnd()
            } catch { }
        }
        return @{ Status = $codigo; Corpo = $texto }
    }
}

Write-Host ''
Write-Host '-- Token invalido (tem que dar 403) --' -ForegroundColor Yellow
foreach ($rota in @(
    '/cron/global/divulgavagas/buscar-vagas',
    '/cron/global/divulgavagas/candidatar',
    '/cron/usuario/8/divulgavagas/buscar-vagas',
    '/cron/usuario/8/divulgavagas/candidatar'
)) {
    $r = Chamar "$rota`?token=token-errado" ''
    $marca = if ($r.Status -eq 403) { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  {1,-52} HTTP {2}" -f $marca, $rota, $r.Status)
}

Write-Host ''
Write-Host '-- Sem token (tem que dar 403) --' -ForegroundColor Yellow
$r = Chamar '/cron/global/divulgavagas/buscar-vagas' ''
$marca = if ($r.Status -eq 403) { 'PASS' } else { 'FALHA' }
Write-Host ("  {0}  sem token -> HTTP {1}" -f $marca, $r.Status)

Write-Host ''
Write-Host '-- Token valido --' -ForegroundColor Yellow
foreach ($rota in @(
    '/cron/global/divulgavagas/buscar-vagas',
    '/cron/global/divulgavagas/candidatar',
    '/cron/usuario/8/divulgavagas/buscar-vagas',
    '/cron/usuario/8/divulgavagas/candidatar'
)) {
    $r = Chamar "$rota`?token=$token" ''
    $json = $r.Corpo | ConvertFrom-Json -ErrorAction SilentlyContinue
    $status = if ($json) { $json.status } else { '(sem json)' }
    $provedor = if ($json) { $json.provedor } else { '-' }
    $marca = if ($r.Status -eq 200 -and $json -and $json.status -eq 'sucesso') { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  {1,-52} HTTP {2} | status {3} | provedor {4}" -f $marca, $rota, $r.Status, $status, $provedor)
}

Write-Host ''
Write-Host '-- O endpoint de BUSCA nao pode candidatar e o de CANDIDATURA nao pode buscar --' -ForegroundColor Yellow
$r = Chamar "/cron/global/divulgavagas/buscar-vagas?token=$token" ''
$json = $r.Corpo | ConvertFrom-Json -ErrorAction SilentlyContinue
if ($json) {
    $estat = $json.estatisticas
    $temCandidaturas = $estat.PSObject.Properties.Name -contains 'candidaturas_enviadas'
    $marca = if (-not $temCandidaturas) { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  busca nao devolve contadores de candidatura" -f $marca)
    Write-Host ("        chaves: " + ($estat.PSObject.Properties.Name -join ', '))
}

$r = Chamar "/cron/global/divulgavagas/candidatar?token=$token" ''
$json = $r.Corpo | ConvertFrom-Json -ErrorAction SilentlyContinue
if ($json) {
    $estat = $json.estatisticas
    $temBusca = $estat.PSObject.Properties.Name -contains 'vagas_inseridas'
    $marca = if (-not $temBusca) { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  candidatura nao devolve contadores de busca" -f $marca)
    Write-Host ("        chaves: " + ($estat.PSObject.Properties.Name -join ', '))
}

Write-Host ''
Write-Host '-- Metodo GET tem que dar 404/405 (escrita e so POST) --' -ForegroundColor Yellow
try {
    $r = Invoke-WebRequest -Uri "$base/cron/global/divulgavagas/buscar-vagas?token=$token" -UseBasicParsing -TimeoutSec 20
    $marca = if ($r.StatusCode -eq 404 -or $r.StatusCode -eq 405) { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  GET -> HTTP {1}" -f $marca, $r.StatusCode)
} catch {
    $codigo = [int] $_.Exception.Response.StatusCode
    $marca = if ($codigo -eq 404 -or $codigo -eq 405) { 'PASS' } else { 'FALHA' }
    Write-Host ("  {0}  GET -> HTTP {1}" -f $marca, $codigo)
}
