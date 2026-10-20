<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Teste dos models da Fase 8 (segundo provedor Divulga Vagas).
 */

chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER');
require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php';

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

$usuarioId = 8;
$agora = date('Y-m-d H:i:s');
$hoje = date('Y-m-d');

echo PHP_EOL . '-- ConfiguracaoGlobalModel --' . PHP_EOL;

$globais = model('ConfiguracaoGlobalModel');
$configuracao = $globais->obter();

checar('obter() devolve a linha unica', count($configuracao) > 0, 'limite de buscas: ' . ($configuracao['admin_max_buscas_manuais_dia'] ?? '?'));
checar('limite padrao de buscas = 10', (int) ($configuracao['admin_max_buscas_manuais_dia'] ?? 0) === 10);
checar('limite padrao de candidaturas = 20', (int) ($configuracao['admin_max_candidaturas_manuais_dia'] ?? 0) === 20);

// Zera os contadores e confere o reset.
$globais->db->table('configuracao_global')->where('id', 1)->update([
    'admin_buscas_manuais_hoje'      => 5,
    'admin_candidaturas_manuais_hoje' => 7,
    'admin_data_reset_manual'        => '2020-01-01',
]);

$resetou = $globais->resetarContadoresSeNecessario();
$configuracao = $globais->obter();

checar('resetarContadoresSeNecessario() zera o dia virado', $resetou === true);
checar('contador de buscas zerado', (int) $configuracao['admin_buscas_manuais_hoje'] === 0);
checar('contador de candidaturas zerado', (int) $configuracao['admin_candidaturas_manuais_hoje'] === 0);
checar('data do reset gravada', (string) $configuracao['admin_data_reset_manual'] === $hoje);

$globais->registrarBusca();
$globais->registrarCandidatura();
$configuracao = $globais->obter();

checar('registrarBusca() soma no contador', (int) $configuracao['admin_buscas_manuais_hoje'] === 1);
checar('registrarCandidatura() soma no contador', (int) $configuracao['admin_candidaturas_manuais_hoje'] === 1);

// Deixa como estava.
$globais->db->table('configuracao_global')->where('id', 1)->update([
    'admin_buscas_manuais_hoje'      => 0,
    'admin_candidaturas_manuais_hoje' => 0,
    'admin_data_reset_manual'        => null,
]);

echo PHP_EOL . '-- ExecucaoDivulgaVagasModel --' . PHP_EOL;

$execucoes = model('ExecucaoDivulgaVagasModel');

$idExecucao = $execucoes->abrir('buscar', 'manual_usuario', $usuarioId, $usuarioId);
checar('abrir() devolve o id do registro', $idExecucao > 0, 'id ' . $idExecucao);

$registro = $execucoes->find($idExecucao);
checar('registro começa em_andamento', (string) ($registro['status'] ?? '') === 'em_andamento');
checar('registro guarda o tipo e a origem', (string) $registro['tipo'] === 'buscar' && (string) $registro['origem'] === 'manual_usuario');

$execucoes->finalizar($idExecucao, 'sucesso', [
    'vagas_inseridas'        => 3,
    'vagas_atualizadas'      => 2,
    'candidaturas_enviadas'  => 1,
    'emails_enviados'        => 1,
    'erros'                  => 0,
    'mensagem'               => 'Busca concluida.',
]);

$registro = $execucoes->find($idExecucao);
checar('finalizar() grava o status', (string) $registro['status'] === 'sucesso');
checar('finalizar() grava os contadores', (int) $registro['vagas_inseridas'] === 3 && (int) $registro['emails_enviados'] === 1);
checar('finalizar() grava a mensagem', (string) $registro['mensagem'] === 'Busca concluida.');
checar('finalizar() grava a data de fim', (string) $registro['finalizado_em'] !== '');

$lista = $execucoes->listarDoUsuario($usuarioId, 5);
checar('listarDoUsuario() devolve o historico', count($lista) >= 1, count($lista) . ' registro(s)');

$ultima = $execucoes->ultimaDoUsuario($usuarioId);
checar('ultimaDoUsuario() devolve a ultima execucao', $ultima !== null && (int) $ultima['id'] === $idExecucao);

$globais2 = $execucoes->listarGlobais(10);
checar('listarGlobais() devolve as execucoes de todos', count($globais2) >= 1);

// Limpa o registro de teste.
$execucoes->delete($idExecucao);

echo PHP_EOL . '-- VagaModel: fila do bot --' . PHP_EOL;

$vagas = model('VagaModel');

// O cenario e sobre UMA vaga: as outras vagas pendentes do usuario
// sao tiradas da fila durante o teste (e devolvidas no final).
$filaReal = $vagas->db->table('vagas')
    ->select('id, candidatura_divulgavagas_status')
    ->where('usuario_id', $usuarioId)
    ->where('provedor_vaga', 'divulgavagas')
    ->whereIn('candidatura_divulgavagas_status', ['pendente', 'pendente_email'])
    ->get()
    ->getResultArray();

foreach ($filaReal as $vagaReal) {
    $vagas->db->table('vagas')
        ->where('id', (int) $vagaReal['id'])
        ->update(['candidatura_divulgavagas_status' => 'enviada']);
}

// Sem vagas do Divulga Vagas, a proxima e nulo.
$antes = $vagas->proximaParaBot($usuarioId);
checar('proximaParaBot() devolve nulo sem vagas do provedor', $antes === null);

// Insere uma vaga de teste do Divulga Vagas.
$vagas->insert([
    'palavra_chave_id'                   => null,
    'usuario_id'                         => $usuarioId,
    'job_id'                             => 'dv-teste-999',
    'hash_unico'                         => hash('sha256', 'vaga de teste do divulgavagas'),
    'titulo'                             => 'Vaga de Teste do Divulga Vagas',
    'empresa'                            => 'Empresa de Teste',
    'localizacao'                        => 'Curitiba - PR',
    'fonte'                              => 'Divulga Vagas',
    'url_original'                       => 'https://divulgavagas.com.br/vaga-de-emprego/vaga-de-teste-999',
    'provedor_vaga'                      => 'divulgavagas',
    'url_envio_divulgavagas'             => 'https://divulgavagas.com.br/envioCurriculo/vaga-de-teste-999',
    'vaga_id_divulgavagas'               => '999',
    'candidatura_divulgavagas_status'    => 'pendente',
    'candidatura_divulgavagas_tentativas' => 0,
]);

$idVaga = (int) $vagas->db->insertID();

$proxima = $vagas->proximaParaBot($usuarioId);
checar('proximaParaBot() devolve a vaga do Divulga Vagas', $proxima !== null && (int) $proxima['id'] === $idVaga);
checar('a vaga traz a url de envio', ($proxima['url_envio_divulgavagas'] ?? '') !== '');

// A mesma vaga nao pode ser escolhida duas vezes.
$vagas->atualizarCandidaturaDivulgaVagas($idVaga, $usuarioId, [
    'candidatura_divulgavagas_status' => 'enviada',
]);

$proxima = $vagas->proximaParaBot($usuarioId);
checar('vaga ja enviada sai da fila', $proxima === null);

// Contagem por situacao.
$vagas->atualizarCandidaturaDivulgaVagas($idVaga, $usuarioId, [
    'candidatura_divulgavagas_status'    => 'enviada_email',
    'candidatura_divulgavagas_em'        => $agora,
    'email_enviado_divulgavagas_em'      => $agora,
]);

$contagem = $vagas->contarPorSituacaoDivulgaVagas($usuarioId);
checar('contarPorSituacaoDivulgaVagas() conta a vaga', ($contagem['enviada_email'] ?? 0) === 1, json_encode($contagem));

// Ultima candidatura do bot.
$ultimaBot = $vagas->ultimaCandidaturaDoBot($usuarioId);
checar('ultimaCandidaturaDoBot() devolve a data', $ultimaBot === null || $ultimaBot !== '');

// Limpa a vaga de teste.
$vagas->delete($idVaga);

// Devolve as vagas reais do usuario para a fila.
foreach ($filaReal as $vagaReal) {
    $vagas->db->table('vagas')
        ->where('id', (int) $vagaReal['id'])
        ->update(['candidatura_divulgavagas_status' => (string) $vagaReal['candidatura_divulgavagas_status']]);
}

echo PHP_EOL . '-- ConfiguracaoModel: contadores do Divulga Vagas --' . PHP_EOL;

$configuracoes = model('ConfiguracaoModel');
$configuracoes->zerarContadoresManuais($usuarioId, $agora);

$configuracoes->registrarCandidaturaDoBot($usuarioId, $agora);
$registro = $configuracoes->obterPorUsuario($usuarioId);

checar('registrarCandidaturaDoBot() soma o contador do bot', (int) ($registro['divulgavagas_candidaturas_bot_hoje'] ?? 0) === 1);
checar('registrarCandidaturaDoBot() grava a data do reset do bot', (string) ($registro['divulgavagas_data_reset_bot'] ?? '') === $hoje);

// A data da ultima candidatura vive na vaga (coluna data_ultimo_envio_bot).
$vagas->insert([
    'usuario_id'                      => $usuarioId,
    'hash_unico'                      => hash('sha256', 'vaga com data do bot'),
    'titulo'                          => 'Vaga com data do bot',
    'provedor_vaga'                   => 'divulgavagas',
    'candidatura_divulgavagas_status' => 'enviada',
    'data_ultimo_envio_bot'           => $agora,
]);
$idVagaData = (int) $vagas->db->insertID();

$ultimaDoBot = $vagas->ultimaCandidaturaDoBot($usuarioId);
checar('ultimaCandidaturaDoBot() le a data da vaga', $ultimaDoBot !== null && $ultimaDoBot !== '', (string) $ultimaDoBot);

$vagas->delete($idVagaData);

$configuracoes->registrarBuscaManual($usuarioId, $agora);
$configuracoes->registrarCandidaturaManual($usuarioId, $agora);
$registro = $configuracoes->obterPorUsuario($usuarioId);

checar('registrarBuscaManual() soma no contador', (int) ($registro['divulgavagas_buscas_manuais_hoje'] ?? 0) === 1);
checar('registrarCandidaturaManual() soma no contador', (int) ($registro['divulgavagas_candidaturas_manuais_hoje'] ?? 0) === 1);

$configuracoes->zerarContadoresManuais($usuarioId, $agora);
$registro = $configuracoes->obterPorUsuario($usuarioId);

checar('zerarContadoresManuais() zera os contadores', (int) ($registro['divulgavagas_buscas_manuais_hoje'] ?? 0) === 0 && (int) ($registro['divulgavagas_candidaturas_manuais_hoje'] ?? 0) === 0);

// Deixa o usuario como estava.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_candidaturas_bot_hoje' => 0,
    'divulgavagas_data_reset_bot'        => null,
]);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
