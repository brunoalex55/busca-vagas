$ErrorActionPreference = 'Continue'

$base = 'http://localhost:8080'
$raiz = 'C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER'
$php = 'C:\Users\Rafael Albanese\AppData\Local\php84\php.exe'
$ajuda = "$raiz\tools\fase9-ajuda.php"

$falhas = 0
$total = 0

function Checar($rotulo, $ok, $detalhe = '') {
    if ($ok) { Write-Host ("  PASS  {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })) }
    else { Write-Host ("  FALHA {0}{1}" -f $rotulo, $(if ($detalhe -ne '') { " ($detalhe)" } else { '' })); $script:falhas++ }
    $script:total++
}

# Confere se um texto esta numa pagina (o PHP faz a parte do acento).
function PaginaTem($url, $texto) {
    $saida = (& $php 'C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER\tools\fase9-ajuda.php' tela $url $texto 2>&1) -join ''

    if ($saida -match '^(\d+)\|(sim|nao)$') {
        return @{ Status = [int] $Matches[1]; Tem = ($Matches[2] -eq 'sim') }
    }

    return @{ Status = 0; Tem = $false }
}

# Login do admin.
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 60
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

Write-Host '-- As telas de plano do admin --' -ForegroundColor Yellow

foreach ($rota in @('/admin/planos', '/admin/planos/formulario', '/admin/planos/formulario/2', '/admin/planos/empresa/usuarios')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 60
        Checar "GET $rota" ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota" ($codigo -eq 200) "HTTP $codigo"
    }
}

$r = Invoke-WebRequest -Uri "$base/admin/planos" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'a lista mostra os 3 planos' ($r.Content.Contains('profissional') -and $r.Content.Contains('empresa'))
Checar 'a lista mostra "Ilimitadas"' ($r.Content.Contains('Ilimitadas'))
Checar 'a lista mostra o botao de criar' ($r.Content.Contains('admin/planos/formulario'))

Write-Host ''
Write-Host '-- O detalhe do usuario mostra o plano e a origem --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/8" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'mostra a secao "Plano e limites"' ($r.Content.Contains('Plano e limites'))
Checar 'mostra o select de plano' ($r.Content.Contains('name="plano_id"'))
Checar 'mostra o formulario do limite manual' ($r.Content.Contains('salvar-limite-manual'))
Checar 'mostra a origem do limite' ($r.Content.Contains('Plano ') -or $r.Content.Contains('Padrao do sistema') -or $r.Content.Contains('Configuracao da conta') -or $r.Content.Contains('Limite manual'))

Write-Host ''
Write-Host '-- Atribuir e remover o plano (POST) --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/8/atribuir-plano" -Method Post -Body @{ plano_id = '3' } -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'a atribuicao responde 200' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
Checar 'a mensagem de sucesso aparece' ($r.Content.Contains('atribuido a'))

$planoAtual = ((& $php $ajuda plano 8 2>&1) -join '').Trim()
Checar 'o usuario ficou no plano Empresa' ($planoAtual -eq '3') "plano_id: $planoAtual"

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/8/remover-plano" -Method Post -Body @{} -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'a remocao responde 200' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"

$planoAtual = ((& $php $ajuda plano 8 2>&1) -join '').Trim()
Checar 'o usuario voltou para o basico' ($planoAtual -eq '1') "plano_id: $planoAtual"

Write-Host ''
Write-Host '-- O painel do usuario mostra o card do plano --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'tem o card "Meu plano"' ($r.Content.Contains('Meu plano'))
Checar 'tem o botao de upgrade' ($r.Content.Contains('Fazer upgrade de plano'))
Checar 'mostra o limite de palavras-chave' ($r.Content.Contains('Palavras-chave'))
Checar 'o menu mostra o envio em massa (o admin esta no plano Empresa)' ($r.Content.Contains('painel/envio-massa'))

Write-Host ''
Write-Host '-- O isolamento pela URL (a vaga de outro usuario) --' -ForegroundColor Yellow

$vagaId = ((& $php $ajuda criar-vaga 2>&1) -join '').Trim()
Checar 'a vaga de teste foi criada' ($vagaId -match '^\d+$') "id: $vagaId"

if ($vagaId -match '^\d+$') {
    # O dono (o admin) acessa a propria vaga.
    $r = Invoke-WebRequest -Uri "$base/painel/vagas/$vagaId" -WebSession $session -UseBasicParsing -TimeoutSec 60
    Checar 'o dono acessa a propria vaga' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"

    # O log de acesso indevido existe (o acesso indevido de antes).
    $log = "$raiz\writable\logs\acesso-indevido-$(Get-Date -Format 'yyyy-MM-dd').log"
    Checar 'o log de acesso indevido existe' (Test-Path $log) (Split-Path $log -Leaf)
}

& $php $ajuda apagar-vaga 2>&1 | Out-Null

Write-Host ''
Write-Host '-- A pagina de precos --' -ForegroundColor Yellow

$precos = PaginaTem "$base/precos" 'Preços'
Checar 'o titulo tem "Preços" (com cedilha)' $precos.Tem "HTTP $($precos.Status)"

$basico = PaginaTem "$base/precos" 'Básico'
Checar 'mostra o plano Basico' $basico.Tem "HTTP $($basico.Status)"

$profissional = PaginaTem "$base/precos" 'Profissional'
Checar 'mostra o plano Profissional' $profissional.Tem "HTTP $($profissional.Status)"

$empresa = PaginaTem "$base/precos" 'Empresa'
Checar 'mostra o plano Empresa' $empresa.Tem "HTTP $($empresa.Status)"

$popular = PaginaTem "$base/precos" 'Mais popular'
Checar 'destaca o Profissional' $popular.Tem "HTTP $($popular.Status)"

$fidelidade = PaginaTem "$base/precos" 'Sem fidelidade. Cancele quando quiser.'
Checar 'tem o texto "Sem fidelidade. Cancele quando quiser."' $fidelidade.Tem "HTTP $($fidelidade.Status)"

$ilimitadas = PaginaTem "$base/precos" 'Ilimitadas'
Checar 'mostra "Ilimitadas"' $ilimitadas.Tem "HTTP $($ilimitadas.Status)"

$r = Invoke-WebRequest -Uri "$base/precos" -UseBasicParsing -TimeoutSec 60
Checar 'o basico mostra o envio em massa como indisponivel' ($r.Content.Contains('plano__item-indisponivel'))

Write-Host ''
Write-Host "-- o cadastro aceita o plano escolhido --"
$r = Invoke-WebRequest -Uri "$base/registro?plano=profissional" -UseBasicParsing -TimeoutSec 60
Checar 'o cadastro mostra o plano escolhido' ($r.Content.Contains('Profissional'))
Checar 'o cadastro tem o campo do plano' ($r.Content.Contains('name="plano"'))

Write-Host ''
Write-Host '-- O bloqueio no servidor (usuario no plano basico) --' -ForegroundColor Yellow

$usuarioTeste = ((& $php $ajuda criar-usuario 2>&1) -join '').Trim()
Checar 'o usuario de teste foi criado' ($usuarioTeste -match '^\d+$') "id: $usuarioTeste"

if ($usuarioTeste -match '^\d+$') {
    # Duas palavras-chave (o basico permite 2).
    $palavras = ((& $php $ajuda palavras $usuarioTeste 2 2>&1) -join '').Trim()
    Checar 'as duas palavras foram criadas' ($palavras -eq '2') "criadas: $palavras"

    # O login do usuario de teste (outra sessao).
    $sessaoTeste = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $sessaoTeste -UseBasicParsing -TimeoutSec 60
    $null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'fase9-http@busca-vagas.teste'; senha = 'teste-123' } -WebSession $sessaoTeste -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

    # A terceira palavra-chave tem de ser recusada.
    $r = Invoke-WebRequest -Uri "$base/painel/palavras-chave" -Method Post -WebSession $sessaoTeste -UseBasicParsing -TimeoutSec 60 -Body @{ termo = 'Palavra Bloqueada'; cidades = "Ponta Grossa - PR`nCastro"; frequencia_horas = '24' }
    Checar 'a 3a palavra-chave e recusada no servidor' ($r.Content.Contains('permite 2 palavra-chave')) 'a mensagem do limite aparece'

    # O envio em massa nao existe para o plano basico.
    try {
        $r = Invoke-WebRequest -Uri "$base/painel/envio-massa" -WebSession $sessaoTeste -UseBasicParsing -TimeoutSec 60 -ErrorAction Stop
        Checar 'o envio em massa e bloqueado' ($r.StatusCode -eq 404) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar 'o envio em massa e bloqueado' ($codigo -eq 404 -or $codigo -eq 403) "HTTP $codigo"
    }

    # O card do plano no painel dele.
    $r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessaoTeste -UseBasicParsing -TimeoutSec 60
    Checar 'o painel dele mostra o card do plano' ($r.Content.Contains('Meu plano'))
    Checar 'o menu dele esconde o envio em massa' (-not $r.Content.Contains('painel/envio-massa'))
}

& $php $ajuda apagar-usuario 2>&1 | Out-Null

Write-Host ''
Write-Host '-- O max_usuarios trava a atribuicao --' -ForegroundColor Yellow

# O plano Empresa com uma vaga so (o teste usa o proprio plano).
$maxAntes = ((& $php $ajuda max-usuarios empresa 1 2>&1) -join '').Trim()
Checar 'o plano foi limitado a 1 usuario' ($maxAntes -ne '0') "max_usuarios antes: $maxAntes"

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/8/atribuir-plano" -Method Post -Body @{ plano_id = '3' } -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'a atribuicao e recusada (plano cheio)' ($r.Content.Contains('aceita 1 usuario')) 'a mensagem de limite aparece'

# O plano volta ao normal.
& $php $ajuda max-usuarios empresa $maxAntes 2>&1 | Out-Null

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
