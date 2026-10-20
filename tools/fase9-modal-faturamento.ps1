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

# ---------------------------------------------------------------------
# O ADMIN
# ---------------------------------------------------------------------
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $session -UseBasicParsing -TimeoutSec 60
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'admin@admin.com'; senha = 'admin123' } -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

Write-Host '-- As telas novas do admin --' -ForegroundColor Yellow

foreach ($rota in @('/admin/planos/solicitacoes', '/admin/planos/solicitacoes?status=todas', '/admin/faturamento', '/admin/usuarios/8')) {
    try {
        $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 60
        Checar "GET $rota" ($r.StatusCode -eq 200) "HTTP $($r.StatusCode)"
    } catch {
        $codigo = 0
        if ($_.Exception.Response) { $codigo = [int] $_.Exception.Response.StatusCode }
        Checar "GET $rota" ($codigo -eq 200) "HTTP $codigo"
    }
}

Write-Host ''
Write-Host '-- A tela de faturamento --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/faturamento" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'mostra o titulo do relatorio' ($r.Content.Contains('Historico por mes'))
Checar 'mostra a receita estimada' ($r.Content.Contains('Receita estimada'))
Checar 'mostra as contas bloqueadas' ($r.Content.Contains('Bloqueadas por pagamento'))
Checar 'mostra o formulario de bloqueio' ($r.Content.Contains('bloquear-pagamento'))
Checar 'mostra os dados do PIX' ($r.Content.Contains('Chave PIX'))
Checar 'mostra a tabela dos meses' ($r.Content.Contains('Usuarios ativos'))

Write-Host ''
Write-Host '-- O layout (a view fecha a secao e o menu aparece) --' -ForegroundColor Yellow

foreach ($rota in @('/admin/faturamento', '/admin/planos/solicitacoes')) {
    $r = Invoke-WebRequest -Uri ($base + $rota) -WebSession $session -UseBasicParsing -TimeoutSec 60
    Checar "$rota usa o layout do painel" ($r.Content.Contains('painel__menu'))
    Checar "$rota mostra o menu lateral" ($r.Content.Contains('menu-lateral'))
    Checar "$rota tem o item Faturamento no menu" ($r.Content.Contains('>Faturamento<'))
    Checar "$rota tem o item Pedidos de plano no menu" ($r.Content.Contains('>Pedidos de plano<'))
}

Write-Host ''
Write-Host '-- O painel do usuario --' -ForegroundColor Yellow

# Um usuario de teste (criado pelo helper, para nao usar "php -r" aqui).
$idTeste = (& $php "$raiz/tools/fase9-ajuda.php" criar-usuario-modal 2>&1) -join ''
Checar 'o usuario de teste foi criado' ($idTeste -match '^\d+$') "id $idTeste"

$sessionUser = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
$null = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ email = 'fase9-modal@teste.local'; senha = 'teste123' } -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
Checar 'o painel abre' ($r.StatusCode -eq 200)
Checar 'tem o botao que abre o modal' ($r.Content.Contains('data-modal-abrir="modal-plano"'))
Checar 'o modal existe na pagina' ($r.Content.Contains('id="modal-plano"'))
Checar 'o modal tem o select de plano' ($r.Content.Contains('name="plano_id"'))
Checar 'o modal tem o botao de fechar' ($r.Content.Contains('data-modal-fechar="modal-plano"'))
Checar 'o CSS do modal esta no arquivo' ((Get-Content "$raiz/public/assets/css/componentes.css" -Raw).Contains('.modal__janela'))
Checar 'o JS do modal esta no arquivo' ((Get-Content "$raiz/public/assets/js/utilidades.js" -Raw).Contains('iniciarModais'))

Write-Host ''
Write-Host '-- O pedido de plano pelo painel --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/painel/plano/solicitar" -Method Post -Body @{ plano_id = 2; mensagem = 'Teste automatizado do pedido.' } -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5
Checar 'o POST do pedido devolve 200' ($r.StatusCode -eq 200)

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
Checar 'o painel mostra o pedido em analise' ($r.Content.Contains('Pedido em analise'))
Checar 'o painel mostra o botao de cancelar' ($r.Content.Contains('painel/plano/cancelar'))

Write-Host ''
Write-Host '-- A tela dos pedidos de plano (com o pedido na fila) --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/planos/solicitacoes" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'mostra o titulo' ($r.Content.Contains('Pedidos de plano'))
Checar 'mostra as abas de filtro' ($r.Content.Contains('Pendentes:'))
Checar 'mostra o link de aprovacao' ($r.Content.Contains('/aprovar'))
Checar 'mostra o link de recusa' ($r.Content.Contains('/recusar'))
Checar 'o admin ve o pedido do usuario' ($r.Content.Contains('Teste Modal'))
Checar 'o admin ve a observacao do usuario' ($r.Content.Contains('Teste automatizado do pedido.'))

# O dashboard do admin mostra o alerta.
$r = Invoke-WebRequest -Uri "$base/admin" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'o dashboard avisa sobre o pedido' ($r.Content.Contains('pedido(s) de plano'))
Checar 'o dashboard tem o link do faturamento' ($r.Content.Contains('admin/faturamento'))

Write-Host ''
Write-Host '-- O bloqueio por falta de pagamento --' -ForegroundColor Yellow

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/$idTeste" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'o detalhe mostra o botao de bloquear por pagamento' ($r.Content.Contains('bloquear-pagamento'))

$null = Invoke-WebRequest -Uri "$base/admin/usuarios/$idTeste/bloquear-pagamento" -Method Post -Body @{ motivo = 'Mensalidade de teste nao paga.' } -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

$r = Invoke-WebRequest -Uri "$base/admin/usuarios/$idTeste" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'o detalhe mostra o aviso do bloqueio' ($r.Content.Contains('bloqueada por falta de pagamento'))
Checar 'o detalhe mostra o botao de liberar' ($r.Content.Contains('cobrar-pagamento'))
Checar 'o detalhe mostra os dados do PIX' ($r.Content.Contains('Chave PIX'))

# A conta bloqueada nao entra mais no painel (o filtro manda para
# /aguardando: o PowerShell segue o redirect e devolve a tela final).
$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5
Checar 'a conta bloqueada sai do painel' ($r.BaseResponse.ResponseUri.AbsolutePath -like '*aguardando*') $r.BaseResponse.ResponseUri.AbsolutePath

# A tela de espera mostra os dados do pagamento de novo.
$r = Invoke-WebRequest -Uri "$base/aguardando" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
Checar 'a tela de bloqueio mostra a renovacao' ($r.Content.Contains('Renove seu plano'))
Checar 'a tela de bloqueio mostra o PIX' ($r.Content.Contains('Chave PIX'))
Checar 'a tela de bloqueio mostra o motivo' ($r.Content.Contains('Mensalidade de teste nao paga.'))

# O relatorio de faturamento lista a conta bloqueada.
$r = Invoke-WebRequest -Uri "$base/admin/faturamento" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'o relatorio lista a conta bloqueada' ($r.Content.Contains('Teste Modal'))
Checar 'o relatorio mostra o motivo' ($r.Content.Contains('Mensalidade de teste nao paga.'))

# Libera o acesso.
$null = Invoke-WebRequest -Uri "$base/admin/usuarios/$idTeste/cobrar-pagamento" -Method Post -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
Checar 'a conta liberada volta ao painel' ($r.StatusCode -eq 200)

Write-Host ''
Write-Host '-- A aprovacao pelo admin --' -ForegroundColor Yellow

$idPedido = (& $php "$raiz/tools/fase9-ajuda.php" pedido 2>&1) -join ''
Checar 'o pedido tem id' ($idPedido -match '^\d+$') "$idPedido"

if ($idPedido -match '^\d+$') {
    $null = Invoke-WebRequest -Uri "$base/admin/planos/solicitacoes/$idPedido/aprovar" -Method Post -WebSession $session -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

    $r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
    Checar 'o painel nao mostra mais o pedido pendente' (-not $r.Content.Contains('Pedido em analise'))
    Checar 'o painel mostra o plano novo' ($r.Content.Contains('Profissional'))
}

Write-Host ''
Write-Host '-- O cancelamento pelo usuario --' -ForegroundColor Yellow

$null = Invoke-WebRequest -Uri "$base/painel/plano/solicitar" -Method Post -Body @{ plano_id = 3 } -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5
$null = Invoke-WebRequest -Uri "$base/painel/plano/cancelar" -Method Post -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5

$r = Invoke-WebRequest -Uri "$base/painel" -WebSession $sessionUser -UseBasicParsing -TimeoutSec 60
Checar 'o pedido foi cancelado' (-not $r.Content.Contains('Pedido em analise'))

Write-Host ''
Write-Host '-- A limpeza --' -ForegroundColor Yellow

& $php "$raiz/tools/fase9-ajuda.php" apagar-usuario-modal 2>&1 | Out-Null

$r = Invoke-WebRequest -Uri "$base/admin/planos/solicitacoes" -WebSession $session -UseBasicParsing -TimeoutSec 60
Checar 'a fila ficou vazia' (-not $r.Content.Contains('Teste Modal'))

Write-Host ''
Write-Host '===================================================='
Write-Host ("TOTAL: {0} verificacoes | {1} falha(s)" -f $total, $falhas)
Write-Host '===================================================='

exit ($(if ($falhas -gt 0) { 1 } else { 0 }))
