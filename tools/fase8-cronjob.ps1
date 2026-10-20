$ErrorActionPreference = 'Continue'

$base = 'http://localhost:8080'
$raiz = 'C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER'
$php = 'C:\Users\Rafael Albanese\AppData\Local\php84\php.exe'
$dados = "$raiz\tools\fase8-cronjob-dados.php"

$falhas = 0
$total = 0

function Checar($rotulo, $ok, $detalhe = '') {
    if ($ok) { Write-Host ("  PASS  {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })) }
    else { Write-Host ("  FALHA {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })); $script:falhas++ }
    $script:total++
}

Write-Host '-- O mock da API do cron-job.org --' -ForegroundColor Yellow

& $php -r "chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER'); require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php'; require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/tools/fase8-site-local.php'; echo '  mock: ' . (fase8_mock_iniciar() ? 'no ar' : 'falhou') . PHP_EOL;" 2>&1 | Out-Null

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 60
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

# O .env aponta para o mock da API (o original volta no fim).
$envArquivo = "$raiz\.env"
$envOriginal = [System.IO.File]::ReadAllText($envArquivo)
$envNovo = $envOriginal

if ($envNovo -match '(?m)^cron\.urlBaseCronJobOrg\s*=.*$') {
    $envNovo = [regex]::Replace($envNovo, '(?m)^cron\.urlBaseCronJobOrg\s*=.*$', 'cron.urlBaseCronJobOrg = http://127.0.0.1:8099')
} else {
    $envNovo = $envNovo + "`r`ncron.urlBaseCronJobOrg = http://127.0.0.1:8099`r`n"
}

[System.IO.File]::WriteAllText($envArquivo, $envNovo, (New-Object System.Text.UTF8Encoding($false)))

Write-Host '-- O painel mostra os 4 tipos de job --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/cron" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'coluna do job vagas' ($r.Content.Contains('Job de vagas'))
Checar 'coluna do job e-mails' ($r.Content.Contains('Job de e-mails'))
Checar 'coluna do job Divulga Vagas (buscar)' ($r.Content.Contains('Job de Divulga Vagas (buscar)'))
Checar 'coluna do job Divulga Vagas (candidatar)' ($r.Content.Contains('Job de Divulga Vagas (candidatar)'))
Checar 'o painel traz o tipo de job novo' ($r.Content.Contains('divulgavagas_buscar'))

Write-Host ''
Write-Host '-- A chave da API e a sincronizacao --' -ForegroundColor Yellow

& $php $dados chave 'chave-de-teste-cronjob-123' | Out-Null
Checar 'a chave de API foi gravada' ((& $php $dados chave) -ne '')

& $php $dados zerar-requisicoes | Out-Null

$antes = [int] (& $php $dados jobs)
Write-Host ("  jobs com id no servico antes: {0}" -f $antes)

$r = Invoke-WebRequest -Uri "$base/admin/cron/sincronizar" -Method Post -Body @{} -WebSession $session -UseBasicParsing -TimeoutSec 180
[regex]::Matches($r.Content, '(?s)<p class="toast__texto">(.*?)</p>') | ForEach-Object {
    Write-Host ("  {0}" -f ($_.Groups[1].Value -replace '\s+', ' '))
}

$depois = [int] (& $php $dados jobs)
Checar 'a sincronizacao criou jobs no servico' ($depois -gt $antes) "antes: $antes | depois: $depois"

$porTipo = (& $php $dados por-tipo) -join ' '
Write-Host ("  jobs por tipo: {0}" -f $porTipo)

Checar 'ha job do tipo divulgavagas_buscar' ($porTipo -match 'divulgavagas_buscar=\d+')
Checar 'ha job do tipo divulgavagas_candidatar' ($porTipo -match 'divulgavagas_candidatar=\d+')

Write-Host ''
Write-Host '-- O endpoint externo aceita os comandos novos --' -ForegroundColor Yellow

$token = (& $php $dados token 1) -join ''

# Um comando invalido tem de dar 400.
try {
    $r = Invoke-WebRequest -Uri "$base/cron/usuario/1/executar-externo?comando=banana" -Method Post -Headers @{ 'X-Cron-Token' = $token } -UseBasicParsing -TimeoutSec 60 -ErrorAction Stop
    Checar 'comando invalido devolve 400' ($r.StatusCode -eq 400) "HTTP $($r.StatusCode)"
} catch {
    $codigo = 0
    if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
    Checar 'comando invalido devolve 400' ($codigo -eq 400) "HTTP $codigo"
}

# Os comandos novos sao reconhecidos (nao dao 400/401).
#
# O teste usa o usuario 1 (o admin, que nao tem palavra-chave): assim o
# comando termina na hora, sem varrer o site de verdade.
foreach ($comando in @('divulgavagas_buscar', 'divulgavagas_candidatar')) {
    try {
        $r = Invoke-WebRequest -Uri "$base/cron/usuario/1/executar-externo?comando=$comando" -Method Post -Headers @{ 'X-Cron-Token' = $token } -UseBasicParsing -TimeoutSec 120 -ErrorAction Stop
        Checar "o comando $comando e aceito" ($r.StatusCode -eq 200 -or $r.StatusCode -eq 202) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "o comando $comando e aceito" ($codigo -eq 200 -or $codigo -eq 202) "HTTP $codigo"
    }
}

Write-Host ''
Write-Host '-- A chave volta ao estado original --' -ForegroundColor Yellow

& $php $dados chave-apagar | Out-Null
& $php $dados zerar-requisicoes | Out-Null
Checar 'a chave de teste foi removida' (((& $php $dados chave) -join '') -eq '')

# O .env volta ao original.
[System.IO.File]::WriteAllText($envArquivo, $envOriginal, (New-Object System.Text.UTF8Encoding($false)))
Checar 'o .env voltou ao original' ([System.IO.File]::ReadAllText($envArquivo) -eq $envOriginal)

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
