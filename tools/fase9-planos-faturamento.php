<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * --------------------------------------------------------------------------
 * TESTE: PEDIDO DE PLANO, FATURAMENTO E BLOQUEIO POR PAGAMENTO (FASE 9)
 * --------------------------------------------------------------------------
 *
 * Confere, no nivel do sistema:
 *
 *   1. a tabela de pedidos de plano existe e o modelo grava/lista;
 *   2. o pedido pendente aparece para o admin e a aprovacao troca o
 *      plano da conta (a recusa mantem o plano antigo);
 *   3. o cancelamento pelo usuario tira o pedido da fila;
 *   4. o bloqueio por falta de pagamento marca a conta e a tela volta
 *      a mostrar os dados do pagamento;
 *   5. a lista de contas ativas (a usada pelos crons) ignora quem nao
 *      esta ativo;
 *   6. o relatorio de faturamento monta os meses com os numeros.
 *
 * Uso:
 *   php tools/fase9-planos-faturamento.php
 */

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/util_bootstrap.php';

$falhas = 0;
$verificacoes = 0;

function checar(string $rotulo, bool $ok, string $detalhe = ''): void
{
    global $falhas, $verificacoes;

    $verificacoes++;

    if ($ok) {
        echo '  PASS  ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
        return;
    }

    $falhas++;
    echo '  FALHA ' . $rotulo . ($detalhe === '' ? '' : ' (' . $detalhe . ')') . PHP_EOL;
}

$db = db_connect();

echo PHP_EOL . '-- 1. A tabela de pedidos de plano --' . PHP_EOL;

$tabela = $db->table('solicitacoes_plano')->get()->getResultArray();
checar('a tabela de pedidos existe', is_array($tabela));

$colunas = $db->getFieldNames('solicitacoes_plano');

foreach (['usuario_id', 'plano_id', 'plano_anterior_id', 'status', 'mensagem', 'resposta', 'respondido_em', 'respondido_por'] as $coluna) {
    checar('a coluna ' . $coluna . ' existe', in_array($coluna, $colunas, true));
}

echo PHP_EOL . '-- 2. O usuario de teste --' . PHP_EOL;

// Cria um usuario de teste (apaga no final).
$emailTeste = 'fase9-planos@teste.local';
$db->table('usuarios')->where('email', $emailTeste)->delete();

$db->table('usuarios')->insert([
    'nome' => 'Teste Fase 9 Planos',
    'email' => $emailTeste,
    'senha' => password_hash('teste123', PASSWORD_DEFAULT),
    'perfil' => 'usuario',
    'status' => 'ativo',
    'ativo' => 1,
    'plano_id' => 1,
    'plano_inicio_em' => date('Y-m-d H:i:s'),
    'criado_em' => date('Y-m-d H:i:s'),
    'atualizado_em' => date('Y-m-d H:i:s'),
]);

$usuarioId = (int) $db->insertID();
checar('o usuario de teste foi criado', $usuarioId > 0, 'id ' . $usuarioId);

$solicitacoes = model(App\Models\SolicitacaoPlanoModel::class);

echo PHP_EOL . '-- 3. O pedido de plano --' . PHP_EOL;

// O pedido do usuario (o plano profissional).
$pedidoId = $solicitacoes->criar($usuarioId, 2, 'Quero o plano profissional.');
checar('o pedido foi criado', $pedidoId > 0, 'id ' . $pedidoId);

$pedido = $solicitacoes->buscar($pedidoId);
checar('o pedido esta pendente', (string) valor_ou($pedido, 'status', '') === 'pendente');
checar('o pedido guardou o plano pedido', (int) valor_ou($pedido, 'plano_id', 0) === 2);
checar('o pedido guardou o plano anterior', (int) valor_ou($pedido, 'plano_anterior_id', 0) === 1);
checar('o pedido guardou a mensagem', texto_ou($pedido, 'mensagem', '') === 'Quero o plano profissional.');
checar('o usuario tem pedido pendente', $solicitacoes->temPendente($usuarioId));

// A fila do administrador.
$fila = $solicitacoes->listarPendentes();
checar('o pedido aparece na fila do admin', count($fila) >= 1);
checar('a fila traz o nome do plano', count($fila) > 0 && texto_ou($fila[0], 'plano_nome', '') !== '');
checar('a fila traz o nome do usuario', count($fila) > 0 && texto_ou($fila[0], 'usuario_nome', '') !== '');

// A contagem do alerta.
checar('o admin conta os pedidos pendentes', $solicitacoes->contarPendentes() >= 1);

// Um segundo pedido substitui o primeiro (so um pendente por vez).
$pedidoId2 = $solicitacoes->criar($usuarioId, 3, 'Agora o empresa.');
checar('um novo pedido substitui o anterior', $pedidoId2 !== $pedidoId);
checar('continua com um pedido pendente', $solicitacoes->temPendente($usuarioId));

$pendentesDoUsuario = $solicitacoes->listarDoUsuario($usuarioId);
$pendentesContados = 0;

foreach ($pendentesDoUsuario as $item) {
    if ((string) valor_ou($item, 'status', '') === 'pendente') {
        $pendentesContados++;
    }
}

checar('so um pedido fica pendente', $pendentesContados === 1, $pendentesContados . ' pendente(s)');

echo PHP_EOL . '-- 4. A aprovacao do pedido --' . PHP_EOL;

// O plano da conta antes de aprovar.
$planoAntes = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
checar('a conta ainda esta no plano basico', (int) valor_ou($planoAntes, 'plano_id', 0) === 1);

// Aprova (o admin 1 responde).
$aprovado = $solicitacoes->aprovar($pedidoId2, 1);
checar('o pedido foi aprovado', $aprovado);

$planoDepois = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
checar('a conta passou para o plano pedido', (int) valor_ou($planoDepois, 'plano_id', 0) === 3);

$pedidoAprovado = $solicitacoes->buscar($pedidoId2);
checar('o pedido ficou como aprovado', (string) valor_ou($pedidoAprovado, 'status', '') === 'aprovada');
checar('o pedido guardou quem respondeu', (int) valor_ou($pedidoAprovado, 'respondido_por', 0) === 1);
checar('o usuario nao tem mais pedido pendente', ! $solicitacoes->temPendente($usuarioId));

// Aprovar de novo nao pode mudar nada.
$aprovadoDepois = $solicitacoes->aprovar($pedidoId2, 1);
checar('aprovar duas vezes nao muda nada', ! $aprovadoDepois);

echo PHP_EOL . '-- 5. A recusa do pedido --' . PHP_EOL;

// Um novo pedido (para recusar).
$pedidoRecusado = $solicitacoes->criar($usuarioId, 2, 'Quero voltar ao profissional.');
checar('o novo pedido foi criado', $pedidoRecusado > 0);

$recusado = $solicitacoes->recusar($pedidoRecusado, 1, 'Falta o pagamento do mes.');
checar('o pedido foi recusado', $recusado);

$pedidoRecusa = $solicitacoes->buscar($pedidoRecusado);
checar('o pedido ficou como recusado', (string) valor_ou($pedidoRecusa, 'status', '') === 'recusada');
checar('a recusa guardou a justificativa', texto_ou($pedidoRecusa, 'resposta', '') === 'Falta o pagamento do mes.');

// A recusa nao mexe no plano da conta.
$planoFinal = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
checar('a recusa nao troca o plano da conta', (int) valor_ou($planoFinal, 'plano_id', 0) === 3);

echo PHP_EOL . '-- 6. O cancelamento pelo usuario --' . PHP_EOL;

$pedidoCancelado = $solicitacoes->criar($usuarioId, 1, 'Vou voltar ao basico.');
checar('o pedido de cancelamento foi criado', $pedidoCancelado > 0);
checar('o usuario tem pedido pendente', $solicitacoes->temPendente($usuarioId));

$solicitacoes->recusar($pedidoCancelado, $usuarioId, 'Cancelado pelo proprio usuario.');
checar('o cancelamento tira o pedido da fila', ! $solicitacoes->temPendente($usuarioId));

echo PHP_EOL . '-- 7. O bloqueio por falta de pagamento --' . PHP_EOL;

// As colunas novas do usuario.
$colunasUsuario = $db->getFieldNames('usuarios');
checar('a coluna bloqueado_por_pagamento existe', in_array('bloqueado_por_pagamento', $colunasUsuario, true));
checar('a coluna motivo_bloqueio existe', in_array('motivo_bloqueio', $colunasUsuario, true));

// O bloqueio (o que o AdminFaturamento faz).
$db->table('usuarios')->where('id', $usuarioId)->update([
    'status' => 'bloqueado',
    'ativo' => 0,
    'bloqueado_por_pagamento' => 1,
    'motivo_bloqueio' => 'Falta de pagamento da mensalidade.',
    'atualizado_em' => date('Y-m-d H:i:s'),
]);

$bloqueado = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
checar('a conta ficou bloqueada', (string) valor_ou($bloqueado, 'status', '') === 'bloqueado');
checar('a conta parou (ativo = 0)', (int) valor_ou($bloqueado, 'ativo', 1) === 0);
checar('o bloqueio por pagamento foi marcado', (int) valor_ou($bloqueado, 'bloqueado_por_pagamento', 0) === 1);
checar('o motivo do bloqueio foi gravado', texto_ou($bloqueado, 'motivo_bloqueio', '') !== '');

// A liberacao (o que o AdminFaturamento faz).
$db->table('usuarios')->where('id', $usuarioId)->update([
    'status' => 'ativo',
    'ativo' => 1,
    'bloqueado_por_pagamento' => 0,
    'motivo_bloqueio' => null,
    'atualizado_em' => date('Y-m-d H:i:s'),
]);

$liberado = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
checar('a conta foi liberada', (string) valor_ou($liberado, 'status', '') === 'ativo');
checar('a marcacao do bloqueio foi limpa', (int) valor_ou($liberado, 'bloqueado_por_pagamento', 0) === 0);

echo PHP_EOL . '-- 8. A lista de contas ativas (a dos crons) --' . PHP_EOL;

$usuarios = model(App\Models\UsuarioModel::class);

// A conta de teste esta ativa: aparece.
$ativos = $usuarios->listarAtivos();
$idsAtivos = [];

foreach ($ativos as $usuario) {
    $idsAtivos[] = (int) $usuario['id'];
}

checar('a conta ativa esta na lista', in_array($usuarioId, $idsAtivos, true));

// Bloqueia: some da lista.
$db->table('usuarios')->where('id', $usuarioId)->update([
    'status' => 'bloqueado',
    'ativo' => 0,
    'bloqueado_por_pagamento' => 1,
]);

$ativos = $usuarios->listarAtivos();
$idsAtivos = [];

foreach ($ativos as $usuario) {
    $idsAtivos[] = (int) $usuario['id'];
}

checar('a conta bloqueada sai da lista dos crons', ! in_array($usuarioId, $idsAtivos, true));

// Volta.
$db->table('usuarios')->where('id', $usuarioId)->update([
    'status' => 'ativo',
    'ativo' => 1,
    'bloqueado_por_pagamento' => 0,
]);

// A lista nao depende da chave de envio automatico (a do SerpApi).
$db->table('usuarios')->where('id', $usuarioId)->update(['envio_automatico' => 0]);

$ativos = $usuarios->listarAtivos();
$idsAtivos = [];

foreach ($ativos as $usuario) {
    $idsAtivos[] = (int) $usuario['id'];
}

checar('a lista ignora a chave de envio automatico', in_array($usuarioId, $idsAtivos, true));

$db->table('usuarios')->where('id', $usuarioId)->update(['envio_automatico' => 1]);

echo PHP_EOL . '-- 9. O relatorio de faturamento --' . PHP_EOL;

// O controller do faturamento monta o relatorio.
$controller = new App\Controllers\Admin\AdminFaturamento();
$reflexao = new ReflectionClass($controller);
$metodo = $reflexao->getMethod('relatorioMensal');
$metodo->setAccessible(true);
$relatorio = $metodo->invoke($controller);

checar('o relatorio tem os meses', count($relatorio) >= 1, count($relatorio) . ' mes(es)');

if ($relatorio !== []) {
    $mesAtual = $relatorio[count($relatorio) - 1];

    checar('o ultimo mes e o mes atual', (string) valor_ou($mesAtual, 'mes', '') === date('Y-m'));
    checar('o mes tem o rotulo', texto_ou($mesAtual, 'rotulo', '') !== '');
    checar('o mes conta os usuarios ativos', (int) valor_ou($mesAtual, 'ativos', 0) >= 1);
    checar('o mes tem o campo de receita', array_key_exists('receita', $mesAtual));
}

// O resumo geral.
$metodoResumo = $reflexao->getMethod('resumoGeral');
$metodoResumo->setAccessible(true);
$resumo = $metodoResumo->invoke($controller);

checar('o resumo conta as contas ativas', (int) valor_ou($resumo, 'ativos', 0) >= 1);
checar('o resumo tem a receita estimada', array_key_exists('receita', $resumo));

// As contas bloqueadas por pagamento.
$metodoBloqueadas = $reflexao->getMethod('contasBloqueadas');
$metodoBloqueadas->setAccessible(true);
$contasBloqueadas = $metodoBloqueadas->invoke($controller);
checar('a lista de bloqueados por pagamento funciona', is_array($contasBloqueadas));

echo PHP_EOL . '-- 10. A limpeza --' . PHP_EOL;

// Apaga o que o teste criou.
$db->table('solicitacoes_plano')->where('usuario_id', $usuarioId)->delete();
$db->table('usuarios')->where('id', $usuarioId)->delete();

checar('o usuario de teste foi apagado', $db->table('usuarios')->where('id', $usuarioId)->countAllResults() === 0);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
