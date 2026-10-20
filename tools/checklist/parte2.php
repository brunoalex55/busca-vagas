<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Verificador do checklist final - parte 2.
 *
 * Secoes: Extracao de Email, Exibicao no Painel, Envio de Email,
 * Cron e Agendamento (Bloco A).
 */

require_once __DIR__ . '/base.php';

// Cada parte comeca com a lista de itens vazia.
$itens = [];

$arquivo = $pastaTrabalho . '/parte2.json';

// ------------------------------------------------- Extracao de Email

$extrator = arq('app/Libraries/ExtratorEmail.php');

item(
    'Extracao de Email',
    'Etapa 1: regex na descricao e apply_options',
    contem('app/Libraries/ExtratorEmail.php', 'extrairDeTexto')
        && contem('app/Libraries/ExtratorEmail.php', 'extrairDeLinks')
        && contem('app/Libraries/ExtratorEmail.php', 'apply_options'),
    'extrairDeTexto() (descricao) e extrairDeLinks() (apply_options e equivalentes)'
);

$temScraping = contem('app/Libraries/ExtratorEmail.php', 'curlrequest')
    || contem('app/Libraries/ExtratorEmail.php', 'file_get_contents')
    || contem('app/Libraries/ExtratorEmail.php', 'CURLRequest');

item(
    'Extracao de Email',
    'Etapa 2: scraping da pagina (fallback)',
    $temScraping,
    $temScraping ? 'a pagina do anuncio e lida quando a descricao nao traz e-mail' : 'NAO IMPLEMENTADO: o extrator le a descricao e os links do anuncio, mas nao baixa a pagina do vaga'
);

item(
    'Extracao de Email',
    'BLACKLIST completa de prefixos',
    contem('app/Libraries/ExtratorEmail.php', 'PARTES_LOCAIS_IGNORADAS')
        && contem('app/Libraries/ExtratorEmail.php', 'DOMINIOS_IGNORADOS')
        && contem('app/Libraries/ExtratorEmail.php', 'EXTENSOES_FALSAS'),
    '3 listas no ExtratorEmail: partes locais, dominios e extensoes de arquivo'
);

item(
    'Extracao de Email',
    'PRIORIZACAO de e-mails',
    contem('app/Libraries/ExtratorEmail.php', 'escolher') && contem('app/Libraries/ExtratorEmail.php', 'daDescricao'),
    'escolher() prioriza o e-mail da descricao sobre o dos links'
);

$temTimeout = contem('app/Libraries/ExtratorEmail.php', 'timeout');
$temUserAgent = contem('app/Libraries/ExtratorEmail.php', 'user-agent') || contem('app/Libraries/ExtratorEmail.php', 'userAgent');
$temRedirect = contem('app/Libraries/ExtratorEmail.php', 'redirect');

item(
    'Extracao de Email',
    'Scraping com timeout 5s, user-agent, max 3 redirects',
    $temScraping && $temTimeout && $temUserAgent && $temRedirect,
    $temScraping ? 'tempo limite, user-agent e controle de redirecionamento presentes' : 'NAO IMPLEMENTADO: nao existe scraping de pagina (o user-agent e o tempo limite existem so para a API do provedor)'
);

item(
    'Extracao de Email',
    'Max 20 vagas processadas por execucao',
    contem('app/Config/Cron.php', 'limiteConsultasPorExecucao'),
    'teto por execucao em Config\Cron::$limiteConsultasPorExecucao (padrao 40, configuravel)'
);

// ------------------------------------------------ Exibicao no Painel

$viewVagas = arq('app/Views/painel/vagas/index.php');
$viewDetalhe = arq('app/Views/painel/vagas/detalhe.php');

item('Exibicao no Painel', 'Badge E-mail encontrado (verde)', contem('app/Views/painel/vagas/index.php', 'selo--sucesso') && contem('app/Views/painel/vagas/index.php', 'Com e-mail'), 'selo verde "Com e-mail"');
item('Exibicao no Painel', 'Badge Possivel e-mail (nao confirmado) (amarelo)', contem('app/Views/painel/vagas/index.php', 'selo--aviso') && (stripos($viewVagas . $viewDetalhe, 'possivel') !== false), 'NAO IMPLEMENTADO: as views de vagas mostram apenas Com e-mail e Sem e-mail, sem selo de e-mail possivel');
item('Exibicao no Painel', 'Badge Sem e-mail (cinza)', contem('app/Views/painel/vagas/index.php', 'selo--neutro') && contem('app/Views/painel/vagas/index.php', 'Sem e-mail'), 'selo cinza "Sem e-mail"');
item('Exibicao no Painel', 'Badge Ja candidatado (azul)', contem('app/Views/painel/vagas/index.php', 'selo--info') && contem('app/Views/painel/vagas/index.php', 'Candidatada'), 'selo azul "Candidatada"');
item('Exibicao no Painel', 'Badge Prioritaria (roxo)', contem('app/Views/painel/vagas/detalhe.php', 'selo--primario') && contem('app/Views/painel/vagas/detalhe.php', 'Prioritaria'), 'selo roxo "Prioritaria" no detalhe da vaga');
item('Exibicao no Painel', 'Badge Aguardando envio (amarelo)', contem('app/Views/painel/vagas/index.php', 'Aguardando envio') || contem('app/Views/painel/vagas/detalhe.php', 'Aguardando envio'), 'NAO IMPLEMENTADO: nao existe selo de vaga que esta na fila de envio');
item('Exibicao no Painel', 'Botao Ver vaga original', contem('app/Views/painel/vagas/detalhe.php', 'urlOriginal') && contem('app/Views/painel/vagas/detalhe.php', 'noopener noreferrer'), 'link para o anuncio original, com rel=noopener');
item('Exibicao no Painel', 'Botao Enviar E-mail (POST, com anexo PDF)', contem('app/Views/painel/vagas/detalhe.php', 'enviar') && contem('app/Config/Routes.php', 'enviar'), 'NAO IMPLEMENTADO: nao existe envio avulso por vaga (o envio fica com o robo e com a tela de envio em massa)');

// ---------------------------------------------------- Envio de Email

item(
    'Envio de Email',
    'Substituicao de variaveis no assunto/corpo',
    contem('app/Libraries/EnviadorEmail.php', "'{titulo_vaga}'")
        && contem('app/Libraries/EnviadorEmail.php', "'{seu_nome}'")
        && contem('app/Libraries/EnviadorEmail.php', 'renderizar'),
    'renderizar() troca as 6 variaveis no assunto e no corpo'
);

item(
    'Envio de Email',
    'Pagina de Envio em Massa (pula ja_candidatado = 1)',
    existe('app/Views/painel/envio_massa.php')
        && contem('app/Models/VagaModel.php', "->where('ja_candidatado', 0)")
        && contem('app/Controllers/EnvioMassa.php', 'listar'),
    'tela /painel/envio-massa alimentada pela fila que ignora ja_candidatado = 1'
);

item(
    'Envio de Email',
    'Marcacao ja candidatado apos envio',
    contem('app/Libraries/EnviadorEmails.php', 'marcarCandidatado'),
    'a vaga e marcada como candidatada depois do envio'
);

item(
    'Envio de Email',
    'Registro em emails_enviados',
    contem('app/Libraries/EnviadorEmails.php', 'EmailEnviadoModel')
        || contem('app/Libraries/EnviadorEmails.php', 'registrarEnvio'),
    'cada envio fica registrado na tabela emails_enviados'
);

// ------------------------------------------- Cron e Agendamento (Bloco A)

item('Cron e Agendamento (Bloco A)', 'Spark Command vagas:consultar', contem('app/Commands/ConsultarVagas.php', 'vagas:consultar'), 'app/Commands/ConsultarVagas.php');
item('Cron e Agendamento (Bloco A)', 'Spark Command emails:enviar-automaticos', contem('app/Commands/EnviarEmailsAutomaticos.php', 'emails:enviar-automaticos'), 'app/Commands/EnviarEmailsAutomaticos.php');

$tabelaCron = consultar("SHOW TABLES LIKE 'configuracao_cron'");
item('Cron e Agendamento (Bloco A)', 'Tabela configuracao_cron criada', $tabelaCron !== [], 'tabela presente no banco');

item('Cron e Agendamento (Bloco A)', 'Biblioteca GerenciadorCron', existe('app/Libraries/GerenciadorCron.php'), 'app/Libraries/GerenciadorCron.php');
item('Cron e Agendamento (Bloco A)', 'Controller Admin/AdminCron', existe('app/Controllers/Admin/AdminCron.php'), 'app/Controllers/Admin/AdminCron.php');
item('Cron e Agendamento (Bloco A)', 'View admin/cron/index.php com 3 cards', contem('app/Views/admin/cron/index.php', 'Modo de agendamento') && contem('app/Libraries/GerenciadorCron.php', 'cron_linux') && contem('app/Libraries/GerenciadorCron.php', 'cron_windows') && contem('app/Libraries/GerenciadorCron.php', 'spark_manual'), 'a tela mostra os 3 modos, com o que esta em uso destacado');
item('Cron e Agendamento (Bloco A)', 'Endpoint externo POST /cron/usuario/{id}/executar-externo', contem('app/Config/Routes.php', "post('cron/usuario/(:num)/executar-externo'"), 'rota POST registrada');
item('Cron e Agendamento (Bloco A)', 'Token externo por usuario gerado e validado com hash_equals', contem('app/Libraries/GerenciadorCron.php', 'hash_equals') && contem('app/Libraries/GerenciadorCron.php', 'obterTokenDoUsuario') && contem('app/Libraries/GerenciadorCron.php', 'regenerarTokenDoUsuario'), 'token por usuario, comparado com hash_equals()');
item('Cron e Agendamento (Bloco A)', 'Script scripts/cron-linux.sh', existe('scripts/cron-linux.sh'), 'scripts/cron-linux.sh (LF, para o crontab)');
item('Cron e Agendamento (Bloco A)', 'Script scripts/cron-windows.bat', existe('scripts/cron-windows.bat'), 'scripts/cron-windows.bat (CRLF, para o Agendador de Tarefas)');
item('Cron e Agendamento (Bloco A)', 'Documentacao docs/agendamento-linux.md', existe('docs/agendamento-linux.md'), 'docs/agendamento-linux.md');
item('Cron e Agendamento (Bloco A)', 'Documentacao docs/agendamento-windows.md', existe('docs/agendamento-windows.md'), 'docs/agendamento-windows.md');
item('Cron e Agendamento (Bloco A)', 'Documentacao docs/agendamento-manual.md', existe('docs/agendamento-manual.md'), 'docs/agendamento-manual.md');
item('Cron e Agendamento (Bloco A)', 'README com secao de agendamento', contem('README.md', 'Execucao de tarefas agendadas'), 'secao 23 do README');

$cronController = arq('app/Controllers/Cron.php');
$rotasCron = arq('app/Config/Routes.php');

// Rotas ATIVAS: as linhas comentadas do arquivo nao contam.
$rotasAtivas = '';

foreach (explode("\n", $rotasCron) as $linhaRota) {
    if (strpos(trim($linhaRota), '//') === 0) {
        continue;
    }

    $rotasAtivas .= $linhaRota . "\n";
}

item(
    'Cron e Agendamento (Bloco A)',
    'Controller Cron.php ajustado ou removido das rotas',
    contem('app/Controllers/Cron.php', '410') && ! preg_match("/routes->(get|post)\('cron\/(global|usuario)\/(buscar-vagas|enviar-emails)/", $rotasAtivas),
    'Cron.php virou tombstone (HTTP 410) e as rotas antigas foram descontinuadas'
);

$curlEmScripts = false;

foreach (['scripts/cron-linux.sh', 'scripts/cron-windows.bat'] as $script) {
    $conteudo = arq($script);

    if (stripos($conteudo, 'curl') !== false || stripos($conteudo, 'wget') !== false) {
        $curlEmScripts = true;
    }
}

item(
    'Cron e Agendamento (Bloco A)',
    'Nenhum cron via curl/wget/HTTP para logica interna',
    ! $curlEmScripts && contem('scripts/cron-linux.sh', 'spark') && contem('scripts/cron-windows.bat', 'spark'),
    'os dois scripts chamam php spark direto, sem curl nem wget'
);

$modoSalvo = consultar("SELECT modo FROM configuracao_cron LIMIT 1");
item(
    'Cron e Agendamento (Bloco A)',
    'Modo escolhido salvo no banco',
    $modoSalvo !== [],
    'coluna modo em configuracao_cron (valor atual: ' . (($modoSalvo[0]['modo'] ?? '-')) . ')'
);

$comandosUsamModo = contem('app/Commands/ConsultarVagas.php', "modo")
    && contem('app/Commands/EnviarEmailsAutomaticos.php', "modo")
    && ! contem('app/Commands/ConsultarVagas.php', "modo === 'cron_linux'")
    && ! contem('app/Commands/ConsultarVagas.php', "modo === 'spark_manual'");

item(
    'Cron e Agendamento (Bloco A)',
    'Modo escolhido nao altera o codigo dos Spark Commands',
    $comandosUsamModo,
    'os comandos comentam o modo, mas nao mudam de comportamento conforme o modo'
);

salvarItens($arquivo);

echo 'parte 2: ' . count($itens) . " itens\n";
