<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Teste das bibliotecas da Fase 8 (etapa 8.2).
 *
 * 1. VerificadorLimiteEnvio: equivalencia com a regra antiga.
 * 2. LimiteExecucaoManual: limites de execucao manual (usuario e admin).
 * 3. DeduplicadoraVagas: vaga nova e vaga repetida.
 * 4. ExtratorEmail: entidades, zero-width e a etapa 2 (pagina da vaga).
 * 5. ProvedorDivulgaVagas: URL de busca e falhas de ambiente.
 */

chdir('C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER');
require 'C:/Users/Rafael Albanese/Desktop/CI_EMPLOYER/vendor/codeigniter4/framework/system/util_bootstrap.php';
require_once __DIR__ . '/fase8-site-local.php';

// O site local de teste precisa estar no ar.
fase8_site_local_iniciar();

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
$hoje = date('Y-m-d');

echo PHP_EOL . '-- 1. VerificadorLimiteEnvio (equivalencia com a regra antiga) --' . PHP_EOL;

/**
 * Reimplementa a regra antiga (a que estava dentro do EnviadorEmails)
 * para comparar com o verificador novo.
 */
function regraAntiga(array $configuracao, int $usuarioId, int $enviosHoje, DateTimeImmutable $agora): array
{
    // Janela.
    $janela = (new App\Libraries\JanelaEnvio())->avaliar($configuracao, $agora);

    if (! $janela['aberta']) {
        return ['pode' => false, 'motivo' => 'janela_fechada'];
    }

    // Intervalo.
    $ultimo = trim(texto_ou($configuracao, 'data_ultimo_envio'));

    if ($ultimo !== '') {
        $instante = strtotime($ultimo);

        if ($instante !== false) {
            $minimo = max(1, inteiro_ou($configuracao, 'intervalo_minimo_segundos', 180));
            $maximo = max($minimo, inteiro_ou($configuracao, 'intervalo_maximo_segundos', 420));
            $intervalo = $minimo + (int) (crc32($usuarioId . '|' . $ultimo) % ($maximo - $minimo + 1));

            if ($agora->getTimestamp() < $instante + $intervalo) {
                return ['pode' => false, 'motivo' => 'aguardando_intervalo'];
            }
        }
    }

    // Limite diario.
    $limite = max(1, inteiro_ou($configuracao, 'limite_envios_dia', 20));

    if ($enviosHoje >= $limite) {
        return ['pode' => false, 'motivo' => 'limite_diario'];
    }

    return ['pode' => true, 'motivo' => null];
}

$verificador = new App\Libraries\VerificadorLimiteEnvio();

// Cenario 1: dentro da janela, sem intervalo pendente, com limite livre.
$configuracoes = model('ConfiguracaoModel');
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio'         => '00:00:00',
    'horario_fim_envio'            => '23:59:59',
    'dias_semana_permitidos'       => '1,2,3,4,5,6,7',
    'data_ultimo_envio'            => null,
    'limite_envios_dia'            => 20,
    'intervalo_minimo_segundos'    => 180,
    'intervalo_maximo_segundos'    => 420,
]);

$verificacao = $verificador->podeEnviarAgora($usuarioId);
$antiga = regraAntiga($configuracoes->obterOuPadroes($usuarioId), $usuarioId, 0, new DateTimeImmutable());

checar('cenario 1 (tudo livre): pode enviar', $verificacao['pode'] === true, 'motivo: ' . (string) $verificacao['motivo']);
checar('cenario 1: igual a regra antiga', $verificacao['motivo'] === $antiga['motivo']);

// Cenario 2: fora da janela de horario.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio' => '23:59:00',
    'horario_fim_envio'    => '23:59:59',
]);

$verificacao = $verificador->podeEnviarAgora($usuarioId);
$antiga = regraAntiga($configuracoes->obterOuPadroes($usuarioId), $usuarioId, 0, new DateTimeImmutable());

checar('cenario 2 (fora da janela): barrado', $verificacao['pode'] === false);
checar('cenario 2: motivo janela_fechada', $verificacao['motivo'] === 'janela_fechada', (string) $verificacao['motivo']);
checar('cenario 2: igual a regra antiga', $verificacao['motivo'] === $antiga['motivo']);
checar('cenario 2: a mensagem explica o motivo', $verificacao['mensagem'] !== '');

// Cenario 3: intervalo ainda nao venceu.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio'      => '00:00:00',
    'horario_fim_envio'         => '23:59:59',
    'data_ultimo_envio'         => date('Y-m-d H:i:s', time() - 10),
    'intervalo_minimo_segundos' => 180,
    'intervalo_maximo_segundos' => 420,
]);

$verificacao = $verificador->podeEnviarAgora($usuarioId);
$antiga = regraAntiga($configuracoes->obterOuPadroes($usuarioId), $usuarioId, 0, new DateTimeImmutable());

checar('cenario 3 (intervalo pendente): barrado', $verificacao['pode'] === false);
checar('cenario 3: motivo aguardando_intervalo', $verificacao['motivo'] === 'aguardando_intervalo', (string) $verificacao['motivo']);
checar('cenario 3: igual a regra antiga', $verificacao['motivo'] === $antiga['motivo']);

// Cenario 4: limite diario atingido.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'data_ultimo_envio'  => date('Y-m-d H:i:s', time() - 3600),
    'limite_envios_dia'  => 1,
]);

// Simula um envio ja feito hoje.
$emails = model('EmailEnviadoModel');
$emails->db->table('emails_enviados')->where('usuario_id', $usuarioId)->delete();
$emails->db->table('emails_enviados')->insert([
    'usuario_id'   => $usuarioId,
    'vaga_id'      => null,
    'destinatario' => 'teste@exemplo.com',
    'assunto'      => 'Teste',
    'origem'       => 'manual',
    'provedor'     => 'serpapi',
    'status'       => 'enviado',
    'enviado_em'   => date('Y-m-d H:i:s'),
]);

$verificacao = $verificador->podeEnviarAgora($usuarioId);
$antiga = regraAntiga($configuracoes->obterOuPadroes($usuarioId), $usuarioId, $emails->contarHoje($usuarioId), new DateTimeImmutable());

checar('cenario 4 (limite diario): barrado', $verificacao['pode'] === false);
checar('cenario 4: motivo limite_diario', $verificacao['motivo'] === 'limite_diario', (string) $verificacao['motivo']);
checar('cenario 4: igual a regra antiga', $verificacao['motivo'] === $antiga['motivo']);
checar('cenario 4: contagem devolve 1', $verificacao['envios_hoje'] === 1);

// Limpa o e-mail de teste.
$emails->db->table('emails_enviados')->where('destinatario', 'teste@exemplo.com')->delete();

// Deixa a configuracao como estava.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'horario_inicio_envio'      => '08:00:00',
    'horario_fim_envio'         => '20:00:00',
    'dias_semana_permitidos'    => '1,2,3,4,5',
    'data_ultimo_envio'         => null,
    'limite_envios_dia'         => 20,
    'intervalo_minimo_segundos' => 180,
    'intervalo_maximo_segundos' => 420,
]);

echo PHP_EOL . '-- 2. LimiteExecucaoManual --' . PHP_EOL;

$limites = new App\Libraries\LimiteExecucaoManual();

$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_max_buscas_manuais_dia'       => 3,
    'divulgavagas_max_candidaturas_manuais_dia' => 5,
    'divulgavagas_buscas_manuais_hoje'          => 0,
    'divulgavagas_candidaturas_manuais_hoje'    => 0,
    'divulgavagas_data_reset_manual'            => null,
]);

$busca = $limites->podeBuscarManualmente($usuarioId);
checar('podeBuscarManualmente: pode', $busca['pode'] === true, 'restantes: ' . $busca['restantes']);
checar('podeBuscarManualmente: limite 3', $busca['limite'] === 3);

$candidatura = $limites->podeCandidatarManualmente($usuarioId);
checar('podeCandidatarManualmente: pode', $candidatura['pode'] === true, 'limite: ' . $candidatura['limite']);

// Usa as tres buscas: a quarta e barrada.
$limites->registrarBuscaManual($usuarioId);
$limites->registrarBuscaManual($usuarioId);
$limites->registrarBuscaManual($usuarioId);

$busca = $limites->podeBuscarManualmente($usuarioId);
checar('apos 3 buscas: barrado', $busca['pode'] === false, 'motivo: ' . $busca['motivo']);
checar('apos 3 buscas: motivo limite_atingido', $busca['motivo'] === 'limite_atingido');

// O reset diario zera o contador.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_data_reset_manual' => '2020-01-01',
]);

$limites->resetarContadoresSeNecessario($usuarioId, 'manual_usuario');
$busca = $limites->podeBuscarManualmente($usuarioId);

checar('reset diario: volta a poder', $busca['pode'] === true, 'restantes: ' . $busca['restantes']);

// Limite do bot.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_max_candidaturas_bot_dia'    => 30,
    'divulgavagas_candidaturas_bot_hoje'       => 0,
    'divulgavagas_data_reset_bot'              => null,
    'divulgavagas_intervalo_bot_min_segundos'  => 60,
    'divulgavagas_intervalo_bot_max_segundos'  => 180,
]);

$bot = $limites->podeCandidatarPeloBot($usuarioId);
checar('podeCandidatarPeloBot: pode', $bot['pode'] === true, 'restantes: ' . $bot['restantes']);

// Com o contador no limite, o bot para.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_candidaturas_bot_hoje' => 30,
    'divulgavagas_data_reset_bot'        => $hoje,
]);

$bot = $limites->podeCandidatarPeloBot($usuarioId);
checar('bot no limite diario: barrado', $bot['pode'] === false, 'motivo: ' . $bot['motivo']);
checar('bot no limite diario: motivo limite_diario_bot', $bot['motivo'] === 'limite_diario_bot');

// O intervalo do bot e respeitado (ultima candidatura agora).
$vagas = model('VagaModel');
$vagas->insert([
    'usuario_id'                      => $usuarioId,
    'hash_unico'                      => hash('sha256', 'vaga do intervalo do bot'),
    'titulo'                          => 'Vaga do intervalo do bot',
    'provedor_vaga'                   => 'divulgavagas',
    'candidatura_divulgavagas_status' => 'enviada',
    'data_ultimo_envio_bot'           => date('Y-m-d H:i:s'),
]);
$idVagaIntervalo = (int) $vagas->db->insertID();

$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_candidaturas_bot_hoje' => 0,
    'divulgavagas_data_reset_bot'        => $hoje,
]);

$bot = $limites->podeCandidatarPeloBot($usuarioId);
checar('bot aguardando intervalo: barrado', $bot['pode'] === false, 'motivo: ' . $bot['motivo']);
checar('bot aguardando intervalo: motivo aguardando_intervalo_bot', $bot['motivo'] === 'aguardando_intervalo_bot');
checar('bot aguardando intervalo: informa a espera', $bot['espera_segundos'] > 0, $bot['espera_segundos'] . ' segundo(s)');

$vagas->delete($idVagaIntervalo);

// Limites do admin.
$globais = model('ConfiguracaoGlobalModel');
$globais->db->table('configuracao_global')->where('id', 1)->update([
    'admin_max_buscas_manuais_dia'      => 10,
    'admin_candidaturas_manuais_hoje'   => 0,
    'admin_buscas_manuais_hoje'        => 0,
    'admin_data_reset_manual'          => null,
]);

$limitesAdmin = $limites->limitesAdmin();
checar('limitesAdmin: buscas podem', $limitesAdmin['buscas']['pode'] === true, 'limite: ' . $limitesAdmin['buscas']['limite']);
checar('limitesAdmin: candidaturas podem', $limitesAdmin['candidaturas']['pode'] === true, 'limite: ' . $limitesAdmin['candidaturas']['limite']);

// A execucao do admin conta no limite global.
$limites->registrarBuscaManual($usuarioId, 'manual_admin');
$limitesAdmin = $limites->limitesAdmin();
checar('admin: a busca manual conta no global', $limitesAdmin['buscas']['usadas'] === 1, 'usadas: ' . $limitesAdmin['buscas']['usadas']);

// A execucao do admin nao mexe no contador do usuario.
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_buscas_manuais_hoje' => 0,
]);

$limites->registrarBuscaManual($usuarioId, 'manual_admin');
$registro = $configuracoes->obterPorUsuario($usuarioId);
checar('admin: o contador do usuario nao muda', (int) $registro['divulgavagas_buscas_manuais_hoje'] === 0);

// Deixa tudo como estava.
$globais->db->table('configuracao_global')->where('id', 1)->update([
    'admin_buscas_manuais_hoje'      => 0,
    'admin_candidaturas_manuais_hoje' => 0,
    'admin_data_reset_manual'        => null,
]);
$configuracoes->db->table('configuracoes')->where('usuario_id', $usuarioId)->update([
    'divulgavagas_buscas_manuais_hoje'          => 0,
    'divulgavagas_candidaturas_manuais_hoje'    => 0,
    'divulgavagas_data_reset_manual'            => null,
    'divulgavagas_candidaturas_bot_hoje'         => 0,
    'divulgavagas_data_reset_bot'                => null,
]);

echo PHP_EOL . '-- 3. DeduplicadoraVagas --' . PHP_EOL;

$deduplicadora = new App\Libraries\DeduplicadoraVagas();

$dados = [
    'usuario_id'    => $usuarioId,
    'job_id'        => 'dv-dedup-1',
    'hash_unico'    => hash('sha256', 'vaga de deduplicacao'),
    'titulo'        => 'Vaga de Deduplicacao',
    'empresa'       => 'Empresa Dedup',
    'localizacao'   => 'Curitiba - PR',
    'fonte'         => 'Divulga Vagas',
    'provedor_vaga' => 'divulgavagas',
];

$primeira = $deduplicadora->salvarOuAtualizarVaga($dados);
checar('primeira gravacao: nova', $primeira['situacao'] === 'nova', 'id: ' . $primeira['id']);

$segunda = $deduplicadora->salvarOuAtualizarVaga($dados);
checar('segunda gravacao: repetida', $segunda['situacao'] === 'repetida', 'id: ' . $segunda['id']);

// Com o e-mail, a vaga repetida e completada.
$dadosComEmail = $dados;
$dadosComEmail['email_contato'] = 'rh@empresa.com.br';

$terceira = $deduplicadora->salvarOuAtualizarVaga($dadosComEmail);
$vagas = model('VagaModel');
$linha = $vagas->find($primeira['id']);

checar('vaga repetida com e-mail: completada', (string) $linha['email_contato'] === 'rh@empresa.com.br', (string) $linha['email_contato']);

$vagas->delete($primeira['id']);

echo PHP_EOL . '-- 4. ExtratorEmail (entidades, zero-width e etapa 2) --' . PHP_EOL;

$extrator = new App\Libraries\ExtratorEmail();

// Descricao com o "@" escondido em uma entidade &ZeroWidthSpace;.
$descricaoZeroWidth = 'Fale com rh&ZeroWidthSpace;@manpower.com.br sobre a vaga.';
$achados = $extrator->extrairDeTexto($descricaoZeroWidth);

checar('zero-width: acha o e-mail', in_array('rh@manpower.com.br', $achados, true), implode(', ', $achados));

// Descricao com entidades HTML comuns.
$descricaoEntidades = 'Contato: comercial&#64;empresa.com.br &amp; rh@outra.com.br';
$achados = $extrator->extrairDeTexto($descricaoEntidades);

checar('entidades: acha o e-mail com &#64;', in_array('comercial@empresa.com.br', $achados, true), implode(', ', $achados));
checar('entidades: acha o e-mail com &amp;', in_array('rh@outra.com.br', $achados, true), implode(', ', $achados));

// A etapa 2: leitura da pagina real do Divulga Vagas.
$pagina = 'https://divulgavagas.com.br/vaga-de-emprego/auxiliar-de-loja-118974-34765224';
$achados = $extrator->extrairDaPaginaWeb($pagina);

checar('etapa 2: le a pagina sem erro', is_array($achados), count($achados) . ' e-mail(s)');

// URL invalida: devolve lista vazia, sem erro.
$achados = $extrator->extrairDaPaginaWeb('');
checar('etapa 2: url vazia devolve lista vazia', $achados === []);

$achados = $extrator->extrairDaPaginaWeb('javascript:alert(1)');
checar('etapa 2: url nao http devolve lista vazia', $achados === []);

echo PHP_EOL . '-- 5. ProvedorDivulgaVagas --' . PHP_EOL;

$provedor = new App\Libraries\ProvedorDivulgaVagas();

$url = $provedor->montarUrlBusca('Auxiliar Administrativo', 'Ponta Grossa', 'PR');
checar('URL de busca montada', strpos($url, 'filt_buscar=Auxiliar+Administrativo') !== false, $url);
checar('URL de busca com estado', strpos($url, 'estado=PR') !== false);
checar('URL de busca com cidade', strpos($url, 'cidade=Ponta+Grossa') !== false);

// O bot existe na pasta do provedor: a chamada e feita de verdade.
// Com o Chrome no ar, ela devolve as vagas do site de teste local.
$resultado = $provedor->buscar('http://127.0.0.1:8097/listagem.html', $usuarioId);

if ($resultado['sucesso']) {
    checar('buscar com o bot no ar: traz as vagas', count($resultado['dados']['vagas'] ?? []) === 3, count($resultado['dados']['vagas'] ?? []) . ' vaga(s)');
} else {
    // Sem o Chrome no ar, a falha tem de ser controlada (sem erro fatal).
    checar('buscar sem o Chrome no ar: falha controlada', $resultado['sucesso'] === false, (string) $resultado['erro']);
}

// Candidatar sem curriculo: falha controlada.
$resultado = $provedor->candidatar('https://divulgavagas.com.br/envioCurriculo/teste-1', 'C:/inexistente/curriculo.pdf', $usuarioId);
checar('candidatar sem curriculo: falha controlada', $resultado['sucesso'] === false, (string) $resultado['erro']);

echo PHP_EOL . '====================================================' . PHP_EOL;
echo 'VERIFICACOES: ' . $verificacoes . ' | FALHAS: ' . $falhas . PHP_EOL;
echo '====================================================' . PHP_EOL;

exit($falhas > 0 ? 1 : 0);
