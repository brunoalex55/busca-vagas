<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Teste do CRON 1 do Divulga Vagas (busca), com o bot simulado.
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
 * Bot simulado: devolve uma lista de vagas.
 */
class BotDeBusca extends App\Libraries\ProvedorDivulgaVagas
{
    public array $vagas = [];
    public array $urls = [];
    public bool $deveFalhar = false;

    public function __construct()
    {
        // O construtor do pai resolve a configuracao (o Python so e usado
        // na execucao real, que o mock substitui).
        parent::__construct();
    }

    public function buscar(string $urlBusca, int $usuarioId, ?int $timeoutMs = null): array
    {
        $this->urls[] = $urlBusca;

        if ($this->deveFalhar) {
            return ['sucesso' => false, 'dados' => [], 'erro' => 'bot indisponivel', 'codigo' => 2];
        }

        return ['sucesso' => true, 'dados' => ['sucesso' => true, 'vagas' => $this->vagas], 'erro' => null, 'codigo' => 0];
    }
}

// O teste usa um usuario DE TESTE (sem vagas reais): as contagens do
// resumo da busca somam todas as vagas do usuario, e as vagas reais
// dele atrapalhariam a verificacao.
$usuarioTeste = 'e2e@busca-vagas.teste';

$linhaUsuario = db_connect()->table('usuarios')->where('email', $usuarioTeste)->get()->getRowArray();

if ($linhaUsuario === null) {
    db_connect()->table('usuarios')->insert([
        'nome'     => 'Usuario de Teste',
        'email'    => $usuarioTeste,
        'senha'    => password_hash('teste-123', PASSWORD_BCRYPT),
        'perfil'   => 'usuario',
        'status'   => 'ativo',
        'ativo'    => 1,
    ]);

    $linhaUsuario = db_connect()->table('usuarios')->where('email', $usuarioTeste)->get()->getRowArray();
}

$usuarioId = (int) $linhaUsuario['id'];

$vagas = model('VagaModel');
$palavras = model('PalavraChaveModel');
$logs = model('LogConsultaModel');

echo PHP_EOL . '-- Preparo --' . PHP_EOL;

// Limpa os dados de teste.
$vagas->db->table('vagas')->where('usuario_id', $usuarioId)->delete();
$logs->db->table('logs_consulta')->where('termo', 'Vaga Teste Busca')->delete();

// Uma palavra-chave de teste, devida agora.
$palavras->db->table('palavras_chave')->where('usuario_id', $usuarioId)->where('termo', 'Vaga Teste Busca')->delete();
$palavras->db->table('palavras_chave')->insert([
    'usuario_id'       => $usuarioId,
    'termo'            => 'Vaga Teste Busca',
    'cidades'          => json_encode(['Ponta Grossa - PR']),
    'remoto'           => 0,
    'idioma'           => 'pt',
    'pais'             => 'br',
    'dominio_google'   => 'google.com.br',
    'frequencia_horas' => 6,
    'proxima_execucao' => date('Y-m-d H:i:s', time() - 3600),
    'ultima_execucao'  => null,
    'total_consultas'  => 0,
    'ativa'            => 1,
]);

$bot = new BotDeBusca();
$bot->vagas = [
    [
        'url_vaga'     => 'https://divulgavagas.com.br/vaga-de-emprego/auxiliar-administrativo-34762271',
        'url_envio'    => 'https://divulgavagas.com.br/envioCurriculo/auxiliar-administrativo-34762271',
        'vaga_id'      => '34762271',
        'titulo'       => 'Auxiliar Administrativo',
        'empresa'      => 'Impar Inclusao e Diversidade',
        'localizacao'  => 'Curitiba - PR',
        'descricao'    => 'Vaga de administratif. Escreva para rh&ZeroWidthSpace;@empresa-teste.com.br.',
    ],
    [
        'url_vaga'     => 'https://divulgavagas.com.br/vaga-de-emprego/auxiliar-de-loja-34765224',
        'url_envio'    => 'https://divulgavagas.com.br/envioCurriculo/auxiliar-de-loja-34765224',
        'vaga_id'      => '34765224',
        'titulo'       => 'Auxiliar De Loja',
        'empresa'      => 'Manpower Staffing',
        'localizacao'  => 'Belo Horizonte - MG',
        'descricao'    => 'Sem e-mail de contacto nesta descricao.',
    ],
];

$buscador = new App\Libraries\BuscadorDivulgaVagas($bot);

$resumo = $buscador->buscarDoUsuario($usuarioId, 'cron');

checar('o bot foi chamado', count($bot->urls) === 1, count($bot->urls) . ' chamada(s)');
checar('a URL tem o termo, o estado e a cidade', strpos($bot->urls[0], 'filt_buscar=Vaga+Teste+Busca') !== false && strpos($bot->urls[0], 'estado=PR') !== false && strpos($bot->urls[0], 'cidade=Ponta+Grossa') !== false, $bot->urls[0]);
checar('duas vagas novas', $resumo['vagas_inseridas'] === 2, (string) $resumo['vagas_inseridas']);
checar('uma vaga com e-mail', $resumo['com_email'] === 1, (string) $resumo['com_email']);
checar('uma vaga sem e-mail', $resumo['sem_email'] === 1, (string) $resumo['sem_email']);
checar('sem erros', $resumo['erros'] === 0);

$gravada = $vagas->db->table('vagas')->where('job_id', 'dv-34762271')->get()->getRowArray();

checar('vaga gravada na tabela', $gravada !== null);
checar('provedor_vaga = divulgavagas', (string) ($gravada['provedor_vaga'] ?? '') === 'divulgavagas', (string) ($gravada['provedor_vaga'] ?? 'vazio'));
checar('vaga_id_divulgavagas gravado', (string) ($gravada['vaga_id_divulgavagas'] ?? '') === '34762271');
checar('url_envio_divulgavagas gravada', strpos((string) ($gravada['url_envio_divulgavagas'] ?? ''), '/envioCurriculo/') !== false);
checar('situacao da candidatura: pendente', (string) ($gravada['candidatura_divulgavagas_status'] ?? '') === 'pendente');
checar('e-mail extraido da descricao (zero-width)', (string) ($gravada['email_contato'] ?? '') === 'rh@empresa-teste.com.br', (string) ($gravada['email_contato'] ?? 'vazio'));
checar('origem do e-mail: descricao', (string) ($gravada['origem_email'] ?? '') === 'descricao', (string) ($gravada['origem_email'] ?? 'vazio'));
checar('fonte gravada', (string) ($gravada['fonte'] ?? '') === 'Divulga Vagas');

$semEmail = $vagas->db->table('vagas')->where('job_id', 'dv-34765224')->get()->getRowArray();
checar('a vaga sem e-mail ficou sem contato', (string) ($semEmail['email_contato'] ?? '') === '');

// A deduplicacao: rodar de novo nao duplica.
//
// Cada provedor tem o proprio horario de consulta: a primeira busca
// deixou a palavra agendada para o dia seguinte. O teste zera esse
// horario para simular "a palavra esta devida de novo".
$palavras->db->table('palavras_chave')
    ->where('usuario_id', $usuarioId)
    ->update(['proxima_execucao_divulgavagas' => null]);

$resumo2 = $buscador->buscarDoUsuario($usuarioId, 'cron');
checar('a segunda busca nao duplica', $resumo2['vagas_inseridas'] === 0 && $resumo2['vagas_repetidas'] === 2, 'novas: ' . $resumo2['vagas_inseridas'] . ', repetidas: ' . $resumo2['vagas_repetidas']);

// O log da consulta foi gravado (com a chave do SerpApi vazia).
$log = $logs->db->table('logs_consulta')->where('termo', 'Vaga Teste Busca')->orderBy('id', 'DESC')->get()->getRowArray();
checar('log da consulta gravado', $log !== null);

if ($log !== null) {
    checar('log sem chave do SerpApi', $log['chave_serpapi_id'] === null, var_export($log['chave_serpapi_id'], true));
    checar('log com origem cron', (string) $log['origem'] === 'cron', (string) $log['origem']);
    checar('log com a cidade consultada', (string) $log['cidade_consultada'] === 'Ponta Grossa - PR', (string) $log['cidade_consultada']);
}

// A auditoria em execucoes_divulgavagas.
$auditoria = model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')
    ->where('tipo', 'buscar')
    ->where('usuario_id', $usuarioId)
    ->orderBy('id', 'DESC')
    ->get()
    ->getRowArray();

checar('auditoria gravada', $auditoria !== null);

if ($auditoria !== null) {
    checar('auditoria com tipo buscar', (string) $auditoria['tipo'] === 'buscar');
    checar('auditoria com origem cron', (string) $auditoria['origem'] === 'cron', (string) $auditoria['origem']);

    // A soma das execucoes de auditoria cobre as duas rodadas (a segunda
    // nao insere nada, porque a deduplicacao impede).
    $totalInseridas = (int) model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')
        ->where('tipo', 'buscar')
        ->where('usuario_id', $usuarioId)
        ->selectSum('vagas_inseridas', 'total')
        ->get()
        ->getRowArray()['total'];

    checar('auditoria com as vagas inseridas', $totalInseridas === 2, (string) $totalInseridas);
}

// Falha do bot: o log registra o erro.
//
// A palavra e liberada de novo (o horario do provedor) para que a
// busca de fato rode e o erro do bot apareca.
$palavras->db->table('palavras_chave')
    ->where('usuario_id', $usuarioId)
    ->update(['proxima_execucao_divulgavagas' => null]);

$botFalha = new BotDeBusca();
$botFalha->deveFalhar = true;

$buscadorFalha = new App\Libraries\BuscadorDivulgaVagas($botFalha);
$resumoFalha = $buscadorFalha->buscarDoUsuario($usuarioId, 'cron');

checar('falha do bot vira erro na execucao', $resumoFalha['erros'] === 1, (string) $resumoFalha['erros']);
checar('falha do bot vira erro no log', $logs->db->table('logs_consulta')->where('status', 'erro')->where('termo', 'Vaga Teste Busca')->countAllResults() > 0);

echo PHP_EOL . '-- Limpeza --' . PHP_EOL;

$vagas->db->table('vagas')->where('usuario_id', $usuarioId)->delete();
$logs->db->table('logs_consulta')->where('termo', 'Vaga Teste Busca')->delete();
$palavras->db->table('palavras_chave')->where('usuario_id', $usuarioId)->where('termo', 'Vaga Teste Busca')->delete();
model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')->where('usuario_id', $usuarioId)->where('tipo', 'buscar')->delete();
model('ExecucaoDivulgaVagasModel')->db->table('execucoes_divulgavagas')->where('usuario_id', $usuarioId)->where('tipo', 'candidatar')->delete();

// O usuario de teste some junto (o teste nao deixa rastro).
db_connect()->table('usuarios')->where('id', $usuarioId)->delete();

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
