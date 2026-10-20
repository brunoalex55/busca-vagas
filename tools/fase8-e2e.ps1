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

# O site local de teste precisa estar no ar.
& $php "$raiz\tools\fase8-site-local.php" 2>&1 | Out-Null

# Prepara a vaga de teste.
& $php "$raiz\tools\fase8-e2e-dados.php" limpar | Out-Null
& $php "$raiz\tools\fase8-e2e-dados.php" criar | Out-Null

try {
    # Login do admin.
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 30
    $null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 30 -MaximumRedirection 5

    Write-Host '-- A execucao manual pelo painel --' -ForegroundColor Yellow

    # O id da vaga de teste.
    $estado = & $php "$raiz\tools\fase8-e2e-dados.php" estado
    Write-Host ("  {0}" -f $estado)

    $id = ''

    if ($estado -match 'id=(\d+)') {
        $id = $Matches[1]
    }

    Checar 'a vaga de teste existe' ($id -ne '') "id $id"

    if ($id -ne '') {
        # O POST do botao "Candidatar agora" (e a unica forma de executar).
        $r = Invoke-WebRequest -Uri "$base/painel/divulgavagas/candidatar-vaga/$id" -Method Post -Body @{} -WebSession $session -UseBasicParsing -TimeoutSec 180

        Checar 'o POST responde 200' ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"

        $temMensagem = $r.Content -match 'Candidaturas:'

        Checar 'a tela mostra o resumo da candidatura' $temMensagem

        if ($temMensagem) {
            $resumo = [regex]::Match($r.Content, '(?s)Candidaturas:.{0,120}').Value -replace '\s+', ' '
            Write-Host ("      {0}" -f $resumo.Trim())
        }

        # O estado da vaga depois da execucao.
        $estado = & $php "$raiz\tools\fase8-e2e-dados.php" estado
        Write-Host ("  {0}" -f $estado)

        Checar 'a vaga passou para enviada' ($estado -match 'status=enviada') $estado
        # O contador de tentativas so cresce quando o bot falha (a
        # candidatura bem-sucedida nao conta tentativa).
        Checar 'a vaga nao ficou com erro' ($estado -match 'erro=$') $estado
    }

    Write-Host ''
    Write-Host '-- A execucao ficou registrada --' -ForegroundColor Yellow

    $saida = & $php -r "chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER'); require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php'; `$e = model('App\Models\ExecucaoDivulgaVagasModel'); `$u = `$e->listarDoUsuario(1, 3); echo count(`$u) . '|'; foreach (`$u as `$x) { echo `$x['tipo'] . ':' . `$x['status'] . ':' . `$x['origem'] . ' '; }" 2>&1

    Write-Host ("  {0}" -f ($saida -join ' '))

    Checar 'ha uma execucao de candidatura registrada' (($saida -join ' ') -match 'candidatar:sucesso:manual_usuario') ($saida -join ' ')
} finally {
    # Limpa a vaga de teste.
    & $php "$raiz\tools\fase8-e2e-dados.php" limpar | Out-Null
}

Write-Host ''
Write-Host ("RESULTADO: {0} ok | {1} falha(s)" -f ($total - $falhas), $falhas)
