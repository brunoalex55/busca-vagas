<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Teste do CRON 2 do Divulga Vagas (candidatura + duplo envio), com o bot
 * e o envio de e-mail simulados.
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

/**
 * Bot simulado: devolve sucesso (ou o erro pedido).
 */
class BotSimulado extends App\Libraries\ProvedorDivulgaVagas
{
    public bool $deveFalhar = false;
    public string $erro = 'erro simulado';
    public int $chamadas = 0;
    public array $vagas = [];

    public function __construct()
    {
        // Nao chama o construtor do pai (que procura o Python).
    }

    public function candidatar(string $urlEnvio, string $caminhoCurriculo, int $usuarioId, ?int $timeoutMs = null): array
    {
        $this->chamadas++;

        if ($this->deveFalhar) {
            return ['sucesso' => false, 'dados' => [], 'erro' => $this->erro, 'codigo' => 1];
        }

        return ['sucesso' => true, 'dados' => ['sucesso' => true], 'erro' => null, 'codigo' => 0];
    }

    public function buscar(string $urlBusca, int $usuarioId, ?int $timeoutMs = null): array
    {
        $this->chamadas++;

        if ($this->deveFalhar) {
            return ['sucesso' => false, 'dados' => [], 'erro' => $this->erro, 'codigo' => 1];
        }

        return ['sucesso' => true, 'dados' => ['sucesso' => true, 'vagas' => $this->vagas], 'erro' => null, 'codigo' => 0];
    }
}

/**
 * Enviador simulado: devolve sucesso (ou o erro pedido).
 */
class EnviadorSimulado extends App\Libraries\EnviadorEmail
{
    public bool $deveFalhar = false;
    public int $chamadas = 0;

    public function enviar(array $configuracao, array $vaga): array
    {
        $this->chamadas++;

        if ($this->deveFalhar) {
            return ['status' => 'erro_smtp', 'mensagem' => 'SMTP simulado', 'assunto' => 'Assunto'];
        }

        return ['status' => 'enviado', 'mensagem' => 'Enviado', 'assunto' => 'Candidatura para a vaga'];
    }
}

$usuarioId = 8;

echo PHP_EOL . '-- Preparo --' . PHP_EOL;

// Limpa sobras de execucoes anteriores (o teste e repetivel).
$vagas = model('VagaModel');
$vagas->db->table('vagas')->where('job_id', 'dv-teste-cron2')->delete();

// O teste e sobre UMA vaga por cenario: as outras vagas pendentes do
// usuario sao tiradas da fila (o loop esvaziaria a fila inteira e as
// contagens deste teste nao fechariam).
//
// As vagas reais do usuario NAO sao apagadas: o teste guarda o estado
// delas e devolve no final.
$filaReal = $vagas->db->table('vagas')
    ->where('usuario_id', $usuarioId)
    ->where('provedor_vaga', 'divulgavagas')
    ->whereIn('candidatura_divulgavagas_status', ['pendente', 'pendente_email'])
    ->get()
    ->getResultArray();

$vagas->db->table('vagas')
    ->where('usuario_id', $usuarioId)
    ->where('provedor_vaga', 'divulgavagas')
    ->whereIn('candidatura_divulgavagas_status', ['pendente', 'pendente_email'])
    ->update(['candidatura_divulgavagas_status' => 'enviada']);

$emails = model('EmailEnviadoModel');
$emails->db->table('emails_enviados')->where('destinatario', 'ocupado@empresa-teste.com.br')->delete();
$emails->db->table('emails_enviados')->where('destinatario', 'rh@empresa-teste.com.br')->delete();
model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')->where('usuario_id', $usuarioId)->delete();

$configuracoes = model('ConfiguracaoModel');
$limites = new App\Libraries\LimiteExecucaoManual();

// Configuracao com e-mail liberado e janela aberta.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio'                    => '00:00:00',
    'horario_fim_envio'                       => '23:59:59',
    'dias_semana_permitidos'                  => '1,2,3,4,5,6,7',
    'data_ultimo_envio'                       => null,
    'limite_envios_dia'                       => 20,
    'divulgavagas_max_candidaturas_bot_dia'   => 30,
    'divulgavagas_candidaturas_bot_hoje'      => 0,
    'divulgavagas_data_reset_bot'             => null,
    'divulgavagas_intervalo_bot_min_segundos' => 1,
    'divulgavagas_intervalo_bot_max_segundos' => 1,
]);

// Uma vaga do Divulga Vagas com e-mail de contacto.
$vagas->insert([
    'usuario_id'                      => $usuarioId,
    'job_id'                          => 'dv-teste-cron2',
    'hash_unico'                      => hash('sha256', 'vaga de teste do cron 2'),
    'titulo'                          => 'Auxiliar Administrativo',
    'empresa'                         => 'Empresa Teste',
    'localizacao'                     => 'Curitiba - PR',
    'fonte'                           => 'Divulga Vagas',
    'email_contato'                   => 'rh@empresa-teste.com.br',
    'provedor_vaga'                   => 'divulgavagas',
    'url_envio_divulgavagas'          => 'https://divulgavagas.com.br/envioCurriculo/vaga-de-teste-1',
    'vaga_id_divulgavagas'            => '1',
    'candidatura_divulgavagas_status' => 'pendente',
]);
$vagaId = (int) $vagas->db->insertID();

// O bot precisa de um curriculo que exista.
// O caminho guardado na configuracao e relativo a pasta de currriculos
// (writable/uploads/curriculos) e inclui a pasta do usuario.
$pastaCurriculos = WRITEPATH . 'uploads/curriculos/' . $usuarioId;

if (! is_dir($pastaCurriculos)) {
    mkdir($pastaCurriculos, 0777, true);
}

$curriculo = $pastaCurriculos . '/teste-cron2.pdf';
file_put_contents($curriculo, '%PDF-1.4 teste');

// O caminho guardado na configuracao e relativo a pasta de uploads.
//
// O curriculo REAL do usuario e guardado antes: o teste devolve tudo
// como estava no final (o curriculo de verdade nunca e perdido).
$configuracaoAntes = $configuracoes->obterPorUsuario($usuarioId);
$curriculoReal = (string) ($configuracaoAntes['curriculo_pdf'] ?? '');

$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'curriculo_pdf' => $usuarioId . '/teste-cron2.pdf',
]);

checar('vaga de teste criada', $vagaId > 0, 'id ' . $vagaId);

echo PHP_EOL . '-- 1. Candidatura com e-mail (duplo envio) --' . PHP_EOL;

$bot = new BotSimulado();
$enviador = new EnviadorSimulado();

$candidatura = new App\Libraries\CandidaturaDivulgaVagas(
    $bot,
    null,
    null,
    null,
    $enviador,
    null,
    null,
    null,
    null
);

$emails->db->table('emails_enviados')->where('vaga_id', $vagaId)->delete();

$resumo = $candidatura->candidatarDoUsuario($usuarioId, 'cron');

checar('o bot foi chamado', $bot->chamadas === 1, $bot->chamadas . ' chamada(s)');
checar('o bot confirmou', $resumo['bot_ok'] === true);
checar('a candidatura foi contada', $resumo['candidaturas_enviadas'] === 1);
checar('o e-mail foi enviado', $resumo['emails_enviados'] === 1);
checar('o enviador foi chamado', $enviador->chamadas === 1);

$linha = $vagas->find($vagaId);
checar('situacao: enviada_email', (string) $linha['candidatura_divulgavagas_status'] === 'enviada_email', (string) $linha['candidatura_divulgavagas_status']);
checar('data da candidatura gravada', (string) $linha['candidatura_divulgavagas_em'] !== '');
checar('data do e-mail gravada', (string) $linha['email_enviado_divulgavagas_em'] !== '');
checar('data_ultimo_envio_bot gravada', (string) $linha['data_ultimo_envio_bot'] !== '');

$registro = $emails->db->table('emails_enviados')->where('vaga_id', $vagaId)->get()->getRowArray();
checar('registro em emails_enviados', $registro !== null);
checar('provedor = divulgavagas', (string) ($registro['provedor'] ?? '') === 'divulgavagas', (string) ($registro['provedor'] ?? 'vazio'));
checar('origem = automatico', (string) ($registro['origem'] ?? '') === 'automatico', (string) ($registro['origem'] ?? 'vazio'));
checar('destinatario = e-mail da vaga', (string) ($registro['destinatario'] ?? '') === 'rh@empresa-teste.com.br');
checar('status do envio = enviado', (string) ($registro['status'] ?? '') === 'enviado', (string) ($registro['status'] ?? 'vazio'));

// O limite de e-mail foi consumido (compartilhado com o SerpApi).
$contador = (int) $configuracoes->obterPorUsuario($usuarioId)['envios_hoje'];
checar('o limite de e-mail foi consumido', $contador >= 1, 'envios_hoje: ' . $contador);

// O limite do bot foi consumido.
$registroConfig = $configuracoes->obterPorUsuario($usuarioId);
checar('o limite do bot foi consumido', (int) $registroConfig['divulgavagas_candidaturas_bot_hoje'] === 1);

echo PHP_EOL . '-- 2. Candidatura sem e-mail de contacto --' . PHP_EOL;

$vagas->update($vagaId, [
    'candidatura_divulgavagas_status' => 'pendente',
    'email_contato'                   => null,
    'data_ultimo_envio_bot'            => null,
]);

$emails->db->table('emails_enviados')->where('vaga_id', $vagaId)->delete();

$bot2 = new BotSimulado();
$enviador2 = new EnviadorSimulado();

$candidatura2 = new App\Libraries\CandidaturaDivulgaVagas(
    $bot2,
    null,
    null,
    null,
    $enviador2,
    null,
    null,
    null,
    null
);

$resumo = $candidatura2->candidatarDoUsuario($usuarioId, 'cron');

checar('o bot foi chamado', $bot2->chamadas === 1);
checar('a candidatura foi contada', $resumo['candidaturas_enviadas'] === 1);
checar('nenhum e-mail foi enviado', $resumo['emails_enviados'] === 0);
checar('o e-mail foi marcado como nao aplicavel', $resumo['email_nao_aplicavel'] === true);
checar('o enviador nao foi chamado', $enviador2->chamadas === 0);

$linha = $vagas->find($vagaId);
checar('situacao: enviada (so o bot)', (string) $linha['candidatura_divulgavagas_status'] === 'enviada', (string) $linha['candidatura_divulgavagas_status']);

$registro = $emails->db->table('emails_enviados')->where('vaga_id', $vagaId)->get()->getRowArray();
checar('nenhum registro em emails_enviados', $registro === null);

echo PHP_EOL . '-- 3. E-mail barrado pelo limite (fica pendente_email) --' . PHP_EOL;

$vagas->update($vagaId, [
    'candidatura_divulgavagas_status' => 'pendente',
    'email_contato'                   => 'rh@empresa-teste.com.br',
    'data_ultimo_envio_bot'            => null,
]);

// Limite diario de e-mails ja atingido.
$emails->db->table('emails_enviados')->where('usuario_id', $usuarioId)->delete();
$emails->db->table('emails_enviados')->insert([
    'usuario_id'   => $usuarioId,
    'vaga_id'      => null,
    'destinatario' => 'ocupado@empresa-teste.com.br',
    'assunto'      => 'Teste',
    'origem'       => 'manual',
    'provedor'     => 'serpapi',
    'status'       => 'enviado',
    'enviado_em'   => date('Y-m-d H:i:s'),
]);

$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'limite_envios_dia' => 1,
]);

$bot3 = new BotSimulado();
$enviador3 = new EnviadorSimulado();

$candidatura3 = new App\Libraries\CandidaturaDivulgaVagas(
    $bot3,
    null,
    null,
    null,
    $enviador3,
    null,
    null,
    null,
    null
);

$resumo = $candidatura3->candidatarDoUsuario($usuarioId, 'cron');

checar('o bot rodou mesmo assim', $bot3->chamadas === 1);
checar('o e-mail NAO foi enviado', $enviador3->chamadas === 0);
checar('o e-mail ficou pendente', $resumo['email_pendente'] === true, (string) $resumo['mensagem']);

$linha = $vagas->find($vagaId);
checar('situacao: pendente_email', (string) $linha['candidatura_divulgavagas_status'] === 'pendente_email', (string) $linha['candidatura_divulgavagas_status']);

echo PHP_EOL . '-- 4. Proximo ciclo so tenta o e-mail (o bot nao e chamado) --' . PHP_EOL;

// Libera o limite de e-mail de novo.
$emails->db->table('emails_enviados')->where('usuario_id', $usuarioId)->delete();
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'limite_envios_dia' => 20,
    'data_ultimo_envio'  => null,
]);

// A vaga continua 'pendente_email' (o bot ja confirmou antes).
$vagas->update($vagaId, ['candidatura_divulgavagas_status' => 'pendente_email']);

$bot4 = new BotSimulado();
$enviador4 = new EnviadorSimulado();

$candidatura4 = new App\Libraries\CandidaturaDivulgaVagas(
    $bot4,
    null,
    null,
    null,
    $enviador4,
    null,
    null,
    null,
    null
);

$resumo = $candidatura4->candidatarDoUsuario($usuarioId, 'cron');

checar('o bot NAO foi chamado de novo', $bot4->chamadas === 0);
checar('o e-mail foi enviado agora', $enviador4->chamadas === 1, (string) $resumo['mensagem']);
checar('a candidatura foi contada', $resumo['candidaturas_enviadas'] === 0);
checar('o e-mail foi contado', $resumo['emails_enviados'] === 1);

$linha = $vagas->find($vagaId);
checar('situacao: enviada_email', (string) $linha['candidatura_divulgavagas_status'] === 'enviada_email', (string) $linha['candidatura_divulgavagas_status']);

echo PHP_EOL . '-- 5. Falha do bot (3 tentativas -> erro) --' . PHP_EOL;

$vagas->update($vagaId, [
    'candidatura_divulgavagas_status'     => 'pendente',
    'candidatura_divulgavagas_tentativas' => 0,
    'email_contato'                       => null,
    'data_ultimo_envio_bot'                => null,
]);

for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
    $botFalha = new BotSimulado();
    $botFalha->deveFalhar = true;
    $botFalha->erro = 'falha simulada do bot';

    $candidaturaFalha = new App\Libraries\CandidaturaDivulgaVagas(
        $botFalha,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null
    );

    $candidaturaFalha->candidatarDoUsuario($usuarioId, 'cron');
}

$linha = $vagas->find($vagaId);
checar('situacao: erro (3 tentativas)', (string) $linha['candidatura_divulgavagas_status'] === 'erro', (string) $linha['candidatura_divulgavagas_status']);
checar('tentativas contadas', (int) $linha['candidatura_divulgavagas_tentativas'] === 3, (string) $linha['candidatura_divulgavagas_tentativas']);
checar('erro gravado', (string) $linha['candidatura_divulgavagas_erro'] !== '');

echo PHP_EOL . '-- 6. Cloudflare (nao retenta) --' . PHP_EOL;

$vagas->update($vagaId, [
    'candidatura_divulgavagas_status'     => 'pendente',
    'candidatura_divulgavagas_tentativas' => 0,
    'data_ultimo_envio_bot'                => null,
]);

$botCloudflare = new BotSimulado();
$botCloudflare->deveFalhar = true;
$botCloudflare->erro = 'cloudflare_block: pagina protegida';

$candidaturaCloudflare = new App\Libraries\CandidaturaDivulgaVagas(
    $botCloudflare,
    null,
    null,
    null,
    null,
    null,
    null,
    null,
    null
);

$candidaturaCloudflare->candidatarDoUsuario($usuarioId, 'cron');

$linha = $vagas->find($vagaId);
checar('situacao: cloudflare', (string) $linha['candidatura_divulgavagas_status'] === 'cloudflare', (string) $linha['candidatura_divulgavagas_status']);
checar('apenas 1 tentativa', (int) $linha['candidatura_divulgavagas_tentativas'] === 1, (string) $linha['candidatura_divulgavagas_tentativas']);

echo PHP_EOL . '-- Limpeza --' . PHP_EOL;

$vagas->delete($vagaId);
$emails->db->table('emails_enviados')->where('vaga_id', $vagaId)->delete();
$emails->db->table('emails_enviados')->where('destinatario', 'ocupado@empresa-teste.com.br')->delete();
$emails->db->table('emails_enviados')->where('destinatario', 'rh@empresa-teste.com.br')->delete();
model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')->where('usuario_id', $usuarioId)->delete();
@unlink($curriculo);

// Devolve as vagas reais do usuario para a fila (o teste so as tirou
// de la para as contagens fecharem).
foreach ($filaReal as $vagaReal) {
    $vagas->db->table('vagas')
        ->where('id', (int) $vagaReal['id'])
        ->update(['candidatura_divulgavagas_status' => (string) $vagaReal['candidatura_divulgavagas_status']]);
}

$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio'                    => '08:00:00',
    'horario_fim_envio'                       => '20:00:00',
    'dias_semana_permitidos'                  => '1,2,3,4,5',
    'limite_envios_dia'                       => 20,
    'divulgavagas_candidaturas_bot_hoje'      => 0,
    'divulgavagas_data_reset_bot'             => null,
    // O curriculo de verdade volta (o teste nao pode perder o PDF do
    // usuario: ele e o que permite o bot se candidatar).
    'curriculo_pdf'                           => $curriculoReal === '' ? null : $curriculoReal,
    'envios_hoje'                             => 0,
    'data_reset_contador'                     => null,
    'data_ultimo_envio'                       => null,
]);

// O arquivo de teste do curriculo e apagado (o do usuario nao).
@unlink($curriculo);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
