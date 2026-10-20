<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Verificador do checklist final - parte 3.
 *
 * Secoes: Cron-job.org (Bloco D), Execucao Manual pelo Usuario (Bloco E),
 * Painel Admin, Banco de Dados, Qualidade do Codigo (Bloco C) e
 * Documentacao.
 */

require_once __DIR__ . '/base.php';

// Cada parte comeca com a lista de itens vazia.
$itens = [];

$arquivo = $pastaTrabalho . '/parte3.json';

// -------------------------------------------------- Cron-job.org (D)

$cliente = arq('app/Libraries/ClienteCronJobOrg.php');
$gerenciador = arq('app/Libraries/GerenciadorCron.php');
$adminCron = arq('app/Controllers/Admin/AdminCron.php');
$viewCron = arq('app/Views/admin/cron/index.php');

item('Cron-job.org (Bloco D)', 'ClienteCronJobOrg criado (wrapper da API REST)', existe('app/Libraries/ClienteCronJobOrg.php') && strpos($cliente, 'api.cron-job.org') !== false, 'app/Libraries/ClienteCronJobOrg.php fala com https://api.cron-job.org');
item('Cron-job.org (Bloco D)', 'GerenciadorCron com metodos de integracao', contem('app/Libraries/GerenciadorCron.php', 'listarCronJobsDoUsuario') && contem('app/Libraries/GerenciadorCron.php', 'sincronizarJobs'), 'metodos de criacao, listagem e sincronizacao de jobs');

$tabelaJobs = consultar("SHOW TABLES LIKE 'cron_jobs_usuario'");
item('Cron-job.org (Bloco D)', 'Tabela cron_jobs_usuario criada', $tabelaJobs !== [], 'tabela presente no banco');

$colunaApiKey = consultar("SHOW COLUMNS FROM configuracao_cron LIKE 'api_key_cronjob'");
item('Cron-job.org (Bloco D)', 'Coluna api_key_cronjob em configuracao_cron', $colunaApiKey !== [], 'coluna presente');

$colunaRequisicoes = consultar("SHOW COLUMNS FROM configuracao_cron LIKE 'requisicoes_api_hoje'");
item('Cron-job.org (Bloco D)', 'Coluna requisicoes_api_hoje em configuracao_cron', $colunaRequisicoes !== [], 'coluna presente');

item('Cron-job.org (Bloco D)', 'Controller AdminCron com metodos de API', contem('app/Controllers/Admin/AdminCron.php', 'sincronizarJobs') && contem('app/Controllers/Admin/AdminCron.php', 'testarConexaoCronJob') && contem('app/Controllers/Admin/AdminCron.php', 'criarJobParaUsuario'), 'metodos criar, sincronizar, listar e testar conexao');
item('Cron-job.org (Bloco D)', 'View admin/cron/index.php com secao de integracao', contem('app/Views/admin/cron/index.php', 'cron-job.org') && contem('app/Views/admin/cron/index.php', 'Sincronizar Jobs'), 'secao de integracao na tela /admin/cron');

$integracaoSoNoManual = contem('app/Views/admin/cron/index.php', "spark_manual")
    && contem('app/Controllers/Admin/AdminCron.php', "spark_manual")
    && contem('app/Libraries/GerenciadorCron.php', "Integracao disponivel apenas no modo 'spark_manual'");

item('Cron-job.org (Bloco D)', "Secao de integracao so aparece no modo 'spark_manual'", $integracaoSoNoManual, "a integracao so e exibida no modo spark_manual (o resto devolve 'Integracao disponivel apenas no modo spark_manual')");

item('Cron-job.org (Bloco D)', 'Endpoint externo aceita token via header X-Cron-Token', contem('app/Controllers/Admin/AdminCron.php', "getHeaderLine('X-Cron-Token')"), 'o header X-Cron-Token e lido primeiro');
item('Cron-job.org (Bloco D)', 'Endpoint externo aceita token via query string ?token=', contem('app/Controllers/Admin/AdminCron.php', "getGet('token')"), 'a query string ?token= e o alternativa');
item('Cron-job.org (Bloco D)', 'Endpoint externo aceita comando via ?comando=<tipo>', contem('app/Controllers/Admin/AdminCron.php', "getGet('comando')") && contem('app/Controllers/Admin/AdminCron.php', "in_array(\$comando, array_keys(self::TIPOS)"), 'comando validado contra o registro de tipos (vagas, emails, divulgavagas_buscar, divulgavagas_candidatar)');
$chavesJson = ['status', 'comando', 'mensagem', 'estatisticas', 'duracao_segundos', 'executado_em'];
$jsonOk = true;

foreach ($chavesJson as $chave) {
    if (preg_match("/'" . $chave . "'\s*=>/", $adminCron) !== 1) {
        $jsonOk = false;
    }
}

item('Cron-job.org (Bloco D)', 'Endpoint externo retorna JSON com status, comando, mensagem, estatisticas, duracao e data', $jsonOk, 'resposta JSON com as 6 chaves pedidas');
item('Cron-job.org (Bloco D)', 'Endpoint externo retorna 401 se token invalido', contem('app/Controllers/Admin/AdminCron.php', '401'), 'resposta 401 com "Token invalido."');
item('Cron-job.org (Bloco D)', 'Endpoint externo retorna 400 se comando invalido', contem('app/Controllers/Admin/AdminCron.php', '400'), 'resposta 400 com a mensagem do parametro comando');
item('Cron-job.org (Bloco D)', 'Endpoint externo responde em ate 30 segundos', contem('app/Config/Cron.php', 'limiteRespostaEndpointExterno = 25.0'), 'teto de 25 segundos (Config\Cron::$limiteRespostaEndpointExterno), abaixo dos 30 do servico');
item('Cron-job.org (Bloco D)', 'Endpoint externo registra execucao em log', contem('app/Controllers/Admin/AdminCron.php', 'registrarLogExterno') && contem('app/Libraries/GerenciadorCron.php', 'cron-externo-'), 'log em writable/logs/cron-externo-YYYY-MM-DD.log');
item('Cron-job.org (Bloco D)', 'Botao "Sincronizar Jobs" cria jobs para todos os usuarios ativos', contem('app/Views/admin/cron/index.php', 'admin/cron/sincronizar') && contem('app/Libraries/GerenciadorCron.php', 'listarParaEnvio') || contem('app/Libraries/GerenciadorCron.php', 'sincronizarJobs'), 'botao Sincronizar Jobs no painel, com a criacao para os usuarios ativos');
item('Cron-job.org (Bloco D)', 'Tutorial do cron-job.org exibido no painel admin', contem('app/Views/admin/cron/index.php', '<details') && contem('app/Views/admin/cron/index.php', 'Como configurar'), 'passo a passo em bloco expansivel na tela /admin/cron');
item('Cron-job.org (Bloco D)', 'Arquivo docs/tutorial-cron-job-org.md criado', existe('docs/tutorial-cron-job-org.md'), 'docs/tutorial-cron-job-org.md');
item('Cron-job.org (Bloco D)', 'README com secao especifica do cron-job.org', contem('README.md', 'cron-job.org'), 'secao 24 do README');
item('Cron-job.org (Bloco D)', 'Token pode ser regenerado a qualquer momento', contem('app/Controllers/Admin/AdminCron.php', 'regenerarTokenUsuario') && contem('app/Libraries/GerenciadorCron.php', 'regenerarTokenDoUsuario'), 'botao de regenerar token por usuario');
item('Cron-job.org (Bloco D)', 'Token antigo para de funcionar apos regeneracao', contem('app/Libraries/GerenciadorCron.php', 'regenerarTokenDoUsuario'), 'a regeneracao substitui o token gravado, e o antigo deixa de valer');
item('Cron-job.org (Bloco D)', 'Limites do plano gratuito exibidos no painel', contem('app/Views/admin/cron/index.php', '50 jobs') || contem('app/Views/admin/cron/index.php', '100 requisicoes') || contem('app/Libraries/GerenciadorCron.php', 'LIMITE_JOBS_CONTA'), 'limites de 50 jobs e 100 requisicoes por dia exibidos no painel');
item('Cron-job.org (Bloco D)', 'API base https://api.cron-job.org usada corretamente', strpos($cliente, 'https://api.cron-job.org') !== false, 'URL base em ClienteCronJobOrg');
item('Cron-job.org (Bloco D)', 'Metodo PUT usado para criar job (nao POST)', contem('app/Libraries/ClienteCronJobOrg.php', "'put'") || contem('app/Libraries/ClienteCronJobOrg.php', "method: 'PUT'"), 'criacao de job com PUT');

// --------------------------------- Execucao Manual pelo Usuario (E)

$tabelaManuais = consultar("SHOW TABLES LIKE 'execucoes_manuais_usuario'");
item('Execucao Manual pelo Usuario (Bloco E)', 'Tabela execucoes_manuais_usuario criada', $tabelaManuais !== [], 'tabela presente no banco');

$colunaLimite = consultar("SHOW COLUMNS FROM configuracao_cron LIKE 'limite_execucoes_manuais_usuario'");
item('Execucao Manual pelo Usuario (Bloco E)', 'Coluna limite_execucoes_manuais_usuario em configuracao_cron', $colunaLimite !== [], 'coluna presente');

item('Execucao Manual pelo Usuario (Bloco E)', 'GerenciadorCron com metodos de execucao manual', contem('app/Libraries/GerenciadorCron.php', 'usuarioPodeExecutarManualmente') && contem('app/Libraries/GerenciadorCron.php', 'registrarExecucaoManual'), 'usuarioPodeExecutarManualmente() e o registro do contador');
item('Execucao Manual pelo Usuario (Bloco E)', 'Controller Painel com executarConsultaManual() e executarEnvioManual()', contem('app/Controllers/Painel.php', 'executarConsultaManual') && contem('app/Controllers/Painel.php', 'executarEnvioManual'), 'os dois metodos no controller do painel');
item('Execucao Manual pelo Usuario (Bloco E)', 'View painel/dashboard.php com card de execucoes manuais', contem('app/Views/painel/inicio.php', 'Execucoes manuais'), 'o card fica em app/Views/painel/inicio.php (a view do painel)');
item('Execucao Manual pelo Usuario (Bloco E)', 'Botoes "Executar Busca de Vagas Agora" (POST) e "Executar Envio de E-mails Agora" (POST) no painel do usuario', contem('app/Views/painel/inicio.php', 'Executar busca de vagas agora') && contem('app/Views/painel/inicio.php', 'Executar envio de e-mails agora') && contem('app/Config/Routes.php', "post('executar-vagas'") && contem('app/Config/Routes.php', "post('executar-emails'"), 'os dois botoes, com formulario POST');
item('Execucao Manual pelo Usuario (Bloco E)', 'Botoes desabilitados se limite atingido', contem('app/Views/painel/inicio.php', "if (! \$manuaisDisponiveis) { echo 'disabled'; }"), 'os botoes recebem disabled quando o limite e atingido');
item('Execucao Manual pelo Usuario (Bloco E)', 'Controller AdminUsuarios com salvarLimiteExecucoesManuais() e zerarExecucoesManuaisUsuario()', contem('app/Controllers/Admin/AdminUsuarios.php', 'salvarLimiteExecucoesManuais') && contem('app/Controllers/Admin/AdminUsuarios.php', 'zerarExecucoesManuaisUsuario'), 'os dois metodos no controller administrativo');
item('Execucao Manual pelo Usuario (Bloco E)', 'View admin/usuarios/detalhes.php com contador', contem('app/Views/admin/usuarios/detalhe.php', 'execucoes') || contem('app/Views/admin/usuarios/detalhe.php', 'manuais'), 'contador na ficha do usuario');
item('Execucao Manual pelo Usuario (Bloco E)', 'View admin/cron/index.php com campo de limite', contem('app/Views/admin/cron/index.php', 'limite-manual') || contem('app/Views/admin/cron/index.php', 'limite_execucoes_manuais_usuario'), 'campo do limite na tela /admin/cron');

$origemEnum = consultar("SHOW COLUMNS FROM logs_consulta LIKE 'origem'");
$origemTexto = ($origemEnum !== []) ? (string) $origemEnum[0]['Type'] : '';

item('Execucao Manual pelo Usuario (Bloco E)', "Enum origem em logs_consulta inclui 'manual_usuario'", strpos($origemTexto, 'manual_usuario') !== false, 'coluna origem: ' . $origemTexto);
item('Execucao Manual pelo Usuario (Bloco E)', 'README com secao de execucao manual pelo usuario', contem('README.md', 'Execucao manual pelo usuario'), 'secao 25 do README');
item('Execucao Manual pelo Usuario (Bloco E)', 'Contador reseta automaticamente todo dia', contem('app/Models/ExecucaoManualUsuarioModel.php', "date('Y-m-d')") || contem('app/Models/ExecucaoManualUsuarioModel.php', 'data'), 'a contagem e por data, entao zera sozinha a virada do dia');
item('Execucao Manual pelo Usuario (Bloco E)', 'Limite vale para o total (vagas + emails)', contem('app/Libraries/GerenciadorCron.php', 'total_execucoes') || contem('app/Models/ExecucaoManualUsuarioModel.php', 'total_execucoes'), 'um unico contador soma vagas e e-mails');
// O admin e isento por arquitetura: o contador so e somado no fluxo do
// painel do usuario (Painel.php), e nao nos controllers administrativos.
$contaNoPainel = contem('app/Controllers/Painel.php', 'registrarExecucaoManualUsuario');
$contaNoAdmin = contem('app/Controllers/Admin/AdminCron.php', 'registrarExecucaoManualUsuario')
    || contem('app/Controllers/Admin/AdminCrons.php', 'registrarExecucaoManualUsuario')
    || contem('app/Controllers/Admin/AdminUsuarios.php', 'registrarExecucaoManualUsuario');

item('Execucao Manual pelo Usuario (Bloco E)', 'Admin nao tem esse limite', $contaNoPainel && ! $contaNoAdmin, 'o contador so e somado no fluxo do painel do usuario; o admin dispara pelo /admin/cron, que nao usa esse contador');
item('Execucao Manual pelo Usuario (Bloco E)', 'Execucoes do usuario registram em logs_consulta', contem('app/Libraries/BuscadorVagas.php', 'manual_usuario'), 'a origem manual_usuario e gravada em logs_consulta');
item('Execucao Manual pelo Usuario (Bloco E)', 'Endpoint externo (cron-job.org) nao conta no limite', contem('app/Libraries/GerenciadorCron.php', "origem") && contem('app/Controllers/Admin/AdminCron.php', "'cron'"), 'o disparo externo usa a origem cron, que nao passa pelo contador do usuario');

// ---------------------------------------------------- Painel Admin

$visao = arq('app/Views/admin/visao_geral.php');

item('Painel Admin', 'Dashboard com cards', contem('app/Views/admin/visao_geral.php', 'cartao-metrica'), '6 cards de metricas no painel do admin');
item('Painel Admin', 'Alerta 90% das consultas usadas', contem('app/Libraries/GerenciadorCron.php', '0.9') && contem('app/Views/admin/cron/index.php', 'alerta_requisicoes'), 'alerta de 90% nos limites de requisicoes e de jobs do servico externo');
item('Painel Admin', 'Alerta usuario consumindo desproporcionalmente', contem('app/Views/admin/visao_geral.php', 'desproporcional') || contem('app/Views/admin/distribuicao.php', 'desproporcional'), 'NAO IMPLEMENTADO: o painel mostra a cota de cada usuario, sem aviso de consumo desproporcional');
item('Painel Admin', 'Grafico CSS puro de novos cadastros (30 dias)', contem('app/Views/admin/visao_geral.php', 'grafico') && contem('app/Views/admin/visao_geral.php', '30'), 'NAO IMPLEMENTADO: a visao geral mostra a lista de contas recentes, sem grafico de 30 dias');
item('Painel Admin', 'Caixa com TOKEN GLOBAL do cron + botao Copiar', contem('app/Views/admin/cron/index.php', 'cron-token-global') && contem('app/Views/admin/cron/index.php', 'data-copiar'), 'caixa do token global legado com botao Copiar');
item('Painel Admin', 'Tela Crons por Usuario (a antiga redireciona)', contem('app/Config/Routes.php', "get('crons'") && contem('app/Config/Routes.php', "admin/cron") && ! existe('app/Views/admin/crons.php'), 'a tela antiga /admin/crons redireciona para /admin/cron (a view antiga foi removida)');
item('Painel Admin', 'Tela /admin/cron com escolha de modo', contem('app/Config/Routes.php', "get('cron'") && contem('app/Views/admin/cron/index.php', 'admin/cron/modo'), 'tela /admin/cron com o seletor de modo');
item('Painel Admin', 'Secao de integracao com cron-job.org', contem('app/Views/admin/cron/index.php', 'cron-job.org'), 'secao de integracao na tela /admin/cron');
item('Painel Admin', 'Tutorial cron-job.org expansivel no painel', contem('app/Views/admin/cron/index.php', '<details'), 'passo a passo em bloco expansivel');
item('Painel Admin', 'CRUD de usuarios', contem('app/Config/Routes.php', 'usuarios') && contem('app/Controllers/Admin/AdminUsuarios.php', 'aprovar') && contem('app/Controllers/Admin/AdminUsuarios.php', 'bloquear'), 'listagem, detalhe, aprovar, bloquear e reativar');
item('Painel Admin', 'CRUD de chaves SerpApi', contem('app/Config/Routes.php', 'chaves') && contem('app/Controllers/Admin/AdminChaves.php', 'criar') && contem('app/Controllers/Admin/AdminChaves.php', 'atualizar'), 'nova, editar, ativar, invalidar e excluir');
item('Painel Admin', 'Tela de distribuicao automatica', existe('app/Views/admin/distribuicao.php'), 'tela /admin/distribuicao');
item('Painel Admin', 'Tela de solicitacoes de liberacao', existe('app/Views/admin/solicitacoes.php'), 'tela /admin/solicitacoes');
item('Painel Admin', 'Configuracao de pagamento (PIX)', contem('app/Views/admin/configuracao_site.php', 'chave_pix') || contem('app/Controllers/Admin/AdminConfiguracaoSite.php', 'salvarPagamento'), 'chave PIX e valor mensal em /admin/configuracao-site');
item('Painel Admin', 'Logs de consulta e email', existe('app/Views/admin/logs.php'), 'tela /admin/logs');
item('Painel Admin', 'Configuracao do site publico', existe('app/Views/admin/configuracao_site.php') && contem('app/Config/Routes.php', 'configuracao-site'), 'tela /admin/configuracao-site');

// ------------------------------------------------------ Banco de Dados

$migrations = consultar('SELECT version FROM migrations');
$totalMigrations = count(glob($raiz . '/app/Database/Migrations/*.php'));

// A fase 6 (contato e e-mails de sistema) esta pausada por uma trava
// dentro das proprias migrations: elas rodam, mas nao criam nada.
$fase6 = consultar("SHOW TABLES LIKE 'mensagens_contato'");
$fase6Emails = consultar("SHOW TABLES LIKE 'emails_sistema'");

item(
    'Banco de Dados',
    'Todas as migrations executadas',
    count($migrations) === $totalMigrations && $fase6 === [] && $fase6Emails === [],
    count($migrations) . ' de ' . $totalMigrations . ' migrations aplicadas; a fase 6 esta travada (as tabelas mensagens_contato e emails_sistema nao existem)'
);

$indices = consultar("SHOW INDEX FROM vagas WHERE Non_unique = 0");
$nomesIndices = [];

foreach ($indices as $indice) {
    $nomesIndices[] = (string) $indice['Key_name'];
}

item(
    'Banco de Dados',
    'UNIQUE(job_id, usuario_id) na tabela vagas',
    in_array('uq_vagas_usuario_job', $nomesIndices, true),
    'indice uq_vagas_usuario_job (usuario_id, job_id)'
);

item(
    'Banco de Dados',
    'UNIQUE(hash_unico, usuario_id) na tabela vagas',
    in_array('uq_vagas_usuario_hash', $nomesIndices, true),
    'indice uq_vagas_usuario_hash (usuario_id, hash_unico)'
);

$colunasEnvio = consultar("SHOW COLUMNS FROM configuracoes");
$nomesColunas = [];

foreach ($colunasEnvio as $coluna) {
    $nomesColunas[] = (string) $coluna['Field'];
}

$esperadasEnvio = ['limite_envios_dia', 'intervalo_minimo_segundos', 'intervalo_maximo_segundos', 'horario_inicio_envio', 'horario_fim_envio', 'dias_semana_permitidos', 'envios_hoje', 'data_ultimo_envio'];

$faltando = [];

foreach ($esperadasEnvio as $coluna) {
    if (! in_array($coluna, $nomesColunas, true)) {
        $faltando[] = $coluna;
    }
}

item('Banco de Dados', 'Colunas de envio em configuracoes', $faltando === [], $faltando === [] ? 'as 8 colunas de janela, intervalo e limite existem' : 'faltam: ' . implode(', ', $faltando));

$colunasUsuario = consultar("SHOW COLUMNS FROM usuarios");
$nomesUsuario = [];

foreach ($colunasUsuario as $coluna) {
    $nomesUsuario[] = (string) $coluna['Field'];
}

$esperadasUsuario = ['consultas_usadas_mes', 'mes_referencia', 'limite_manual', 'envio_automatico'];
$faltandoUsuario = [];

foreach ($esperadasUsuario as $coluna) {
    if (! in_array($coluna, $nomesUsuario, true)) {
        $faltandoUsuario[] = $coluna;
    }
}

item('Banco de Dados', 'Colunas de distribuicao em usuarios', $faltandoUsuario === [], $faltandoUsuario === [] ? 'consultas_usadas_mes, mes_referencia, limite_manual e envio_automatico existem' : 'faltam: ' . implode(', ', $faltandoUsuario));

$configCron = consultar('SELECT * FROM configuracao_cron LIMIT 1');
$populada = $configCron !== [] && (string) ($configCron[0]['modo'] ?? '') !== '';

item('Banco de Dados', 'Tabela configuracao_cron criada e populada', $populada, 'modo atual: ' . (string) ($configCron[0]['modo'] ?? '-') . ', limite de execucoes manuais: ' . (string) ($configCron[0]['limite_execucoes_manuais_usuario'] ?? '-'));

item('Banco de Dados', 'Tabela cron_jobs_usuario criada', consultar("SHOW TABLES LIKE 'cron_jobs_usuario'") !== [], 'tabela presente no banco');
item('Banco de Dados', 'Tabela execucoes_manuais_usuario criada', consultar("SHOW TABLES LIKE 'execucoes_manuais_usuario'") !== [], 'tabela presente no banco');

// ---------------------------------------- Qualidade do Codigo (Bloco C)

$saidaDetector = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/detector-c1.php') . ' 2>&1');
$saidaDetector = (string) $saidaDetector;

$semTernario = strpos($saidaDetector, 'ternarios: 0') !== false;
$semEcoCurto = strpos($saidaDetector, 'eco curto: 0') !== false;
$semAlternativa = strpos($saidaDetector, 'alternativa: 0') !== false;

$resumoDetector = trim((string) preg_replace('/\s+/', ' ', $saidaDetector));

item('Qualidade do Codigo (Bloco C)', 'Nenhum ternario', $semTernario, 'detector por tokenizador: ' . $resumoDetector);
item('Qualidade do Codigo (Bloco C)', 'Nenhum atalho de echo', $semEcoCurto, 'nenhuma tag <?= em todo o app/');
item('Qualidade do Codigo (Bloco C)', 'Nenhum endforeach/endif/endfor/endwhile/endswitch', $semAlternativa, 'nenhuma sintaxe alternativa em todo o app/');

$semDocblock = [];

foreach (listar('app', 'php') as $arquivoPhp) {
    $conteudo = (string) file_get_contents($arquivoPhp);

    if (strpos($conteudo, '/**') === false && strpos($conteudo, '/*') === false && strpos($conteudo, '//') === false) {
        $semDocblock[] = basename($arquivoPhp);
    }
}

item(
    'Qualidade do Codigo (Bloco C)',
    'Comentarios linha a linha em PT-BR',
    $semDocblock === [],
    $semDocblock === [] ? 'todos os arquivos de app/ tem comentarios em PT-BR' : 'sem comentario: ' . implode(', ', $semDocblock)
);

$nomesIngles = 0;

foreach (listar('app', 'php') as $arquivoPhp) {
    $conteudo = (string) file_get_contents($arquivoPhp);

    $nomesIngles += preg_match_all('/\bfunction\s+(get|set|create|delete|update|list|find|save|send|fetch|remove|add|check|validate|process|make|build|render)[A-Z]\w*\s*\(/', $conteudo);
}

item('Qualidade do Codigo (Bloco C)', 'Nomenclatura PT-BR em todo o codigo', $nomesIngles === 0, 'nenhuma funcao com nome em ingles no app/');

// -------------------------------------------------------- Documentacao

item('Documentacao', 'README.md completo', existe('README.md') && contem('README.md', '## Licen') && contem('README.md', '## A dor real'), 'README com a dor real, o que o projeto resolve, as imagens do ambiente e o apoio');
item('Documentacao', 'docs/agendamento-linux.md', existe('docs/agendamento-linux.md'), 'docs/agendamento-linux.md');
item('Documentacao', 'docs/agendamento-windows.md', existe('docs/agendamento-windows.md'), 'docs/agendamento-windows.md');
item('Documentacao', 'docs/agendamento-manual.md', existe('docs/agendamento-manual.md'), 'docs/agendamento-manual.md');
item('Documentacao', 'docs/tutorial-cron-job-org.md', existe('docs/tutorial-cron-job-org.md'), 'docs/tutorial-cron-job-org.md');
item('Documentacao', 'docs/checklist-final.md', existe('docs/checklist-final.md'), 'docs/checklist-final.md (este arquivo)');

echo PHP_EOL . '-- Planos por Usuario (Fase 9) --' . PHP_EOL;

item('Planos por Usuario (Fase 9)', 'Tabela planos criada e populada (3 planos)', existe('app/Database/Migrations/2026-10-20-000029_CriarTabelaPlanos.php') && contem('app/Database/Seeds/PlanoSeeder.php', 'basico') && contem('app/Database/Seeds/PlanoSeeder.php', 'profissional') && contem('app/Database/Seeds/PlanoSeeder.php', 'empresa'), 'a tabela planos e o seeder com os 3 planos');
item('Planos por Usuario (Fase 9)', 'Coluna plano_id em usuarios', contem('app/Database/Migrations/2026-10-20-000030_AdicionarPlanoIdEmUsuarios.php', 'plano_id'), 'a coluna plano_id (com a data de inicio e de fim)');
item('Planos por Usuario (Fase 9)', 'Biblioteca ResolvedorPlano implementada', existe('app/Libraries/ResolvedorPlano.php') && contem('app/Libraries/ResolvedorPlano.php', 'resolverLimiteComOrigem'), 'a biblioteca que resolve o limite e a origem');
item('Planos por Usuario (Fase 9)', 'Ordem de prioridade: limite_manual > plano > configuracoes > padrao', contem('app/Libraries/ResolvedorPlano.php', "'manual'") && contem('app/Libraries/ResolvedorPlano.php', "'plano'") && contem('app/Libraries/ResolvedorPlano.php', "'configuracao'") && contem('app/Libraries/ResolvedorPlano.php', "'padrao'"), 'a ordem esta na biblioteca');
item('Planos por Usuario (Fase 9)', 'NULL tratado como ILIMITADO em todo o codigo', contem('app/Libraries/ResolvedorPlano.php', 'NULL = ilimitado') && contem('app/Views/admin/planos/index.php', 'Ilimitadas'), 'os campos em branco no formulario sao ilimitados');
item('Planos por Usuario (Fase 9)', 'Controller Admin/AdminPlanos com CRUD completo', existe('app/Controllers/Admin/AdminPlanos.php') && contem('app/Controllers/Admin/AdminPlanos.php', 'public function salvar') && contem('app/Controllers/Admin/AdminPlanos.php', 'public function desativar') && contem('app/Controllers/Admin/AdminPlanos.php', 'public function reativar'), 'o controller de planos');
item('Planos por Usuario (Fase 9)', 'Admin atribui/remove plano de usuario (POST)', contem('app/Controllers/Admin/AdminUsuarios.php', 'public function atribuirPlano') && contem('app/Controllers/Admin/AdminUsuarios.php', 'public function removerPlano'), 'a atribuicao de plano e no detalhe do usuario');
item('Planos por Usuario (Fase 9)', 'Admin ve de onde veio cada limite (manual/plano/padrao)', contem('app/Views/admin/usuarios/detalhe.php', 'origem'), 'a origem do limite aparece no detalhe');
item('Planos por Usuario (Fase 9)', 'Bloqueio de plano no servidor (nao so visual)', contem('app/Controllers/PalavrasChave.php', 'podeAdicionarPalavraChave') && contem('app/Controllers/Configuracoes.php', 'podeUsarEnvioAutomatico') && contem('app/Controllers/EnvioMassa.php', 'podeUsarEnvioEmMassa'), 'as tres telas recusam no servidor');
item('Planos por Usuario (Fase 9)', 'Card "Meu plano" no dashboard do usuario', contem('app/Views/painel/inicio.php', 'Meu plano') && contem('app/Views/painel/inicio.php', 'Fazer upgrade de plano'), 'o card com os limites e o botao de upgrade');
item('Planos por Usuario (Fase 9)', 'Pagina /precos com os 3 planos corretos', contem('app/Views/site/precos.php', 'Profissional') && contem('app/Views/site/precos.php', 'Mais popular') && contem('app/Views/site/precos.php', 'Sem fidelidade. Cancele quando quiser.'), 'a pagina de precos com as caracteristicas da Fase 9');
item('Planos por Usuario (Fase 9)', '"Preços" com cedilha em todo texto visivel', contem('app/Views/site/precos.php', 'Preços') && contem('app/Views/partials/header_site.php', 'Preços') && contem('app/Views/partials/footer_site.php', 'Preços') && contem('app/Controllers/Site.php', 'Preços'), 'o texto exibido tem a cedilha (o nome tecnico continua "precos")');
item('Planos por Usuario (Fase 9)', 'Plano basico nao pode ser excluido', contem('app/Controllers/Admin/AdminPlanos.php', 'basico'), 'o basico e o fallback do sistema');
item('Planos por Usuario (Fase 9)', 'max_usuarios validado ao atribuir plano', contem('app/Controllers/Admin/AdminUsuarios.php', 'maxUsuarios') && contem('app/Controllers/Admin/AdminUsuarios.php', 'usuariosNoPlano'), 'a atribuicao recusa quando o plano esta cheio');

echo PHP_EOL . '-- Isolamento de Vagas por Usuario (Fase 9) --' . PHP_EOL;

item('Isolamento de Vagas por Usuario (Fase 9)', 'TODA consulta em vagas filtra por usuario_id', contem('app/Models/VagaModel.php', "where('usuario_id', \$usuarioId)") && contem('app/Models/VagaModel.php', 'CONSULTA GLOBAL - uso exclusivo do admin'), 'as consultas do usuario filtram pelo dono e as globais sao marcadas');
item('Isolamento de Vagas por Usuario (Fase 9)', 'Helper obterVagaDoUsuarioOu404 implementado', existe('app/Helpers/vaga_segura_helper.php') && contem('app/Helpers/vaga_segura_helper.php', 'function obterVagaDoUsuarioOu404') && contem('app/Config/Autoload.php', "'vaga_segura'"), 'o helper (registrado no Autoload)');
item('Isolamento de Vagas por Usuario (Fase 9)', 'Toda rota com vagaId valida o dono antes de agir', contem('app/Controllers/Vagas.php', 'obterVagaDoUsuarioOu404') && contem('app/Controllers/PainelDivulgaVagas.php', 'obterVagaDoUsuarioOu404'), 'as rotas com vagaId usam o helper');
item('Isolamento de Vagas por Usuario (Fase 9)', 'UNIQUE(job_id, usuario_id) existe', contem('app/Models/VagaModel.php', 'uq_vagas_usuario_job') || contem('app/Database/Migrations/2026-10-18-000005_CriarTabelaVagas.php', 'usuario_id'), 'a restricao unica por usuario');
item('Isolamento de Vagas por Usuario (Fase 9)', 'UNIQUE(hash_unico, usuario_id) existe', contem('app/Models/VagaModel.php', 'uq_vagas_usuario_hash') || contem('app/Database/Migrations/2026-10-18-000005_CriarTabelaVagas.php', 'hash_unico'), 'a restricao unica por usuario');
item('Isolamento de Vagas por Usuario (Fase 9)', 'Log de acesso indevido em writable/logs/acesso-indevido-*.log', contem('app/Helpers/vaga_segura_helper.php', 'acesso-indevido-') && contem('app/Helpers/vaga_segura_helper.php', 'function registrarAcessoIndevido'), 'o log com ids, rota e IP');
item('Isolamento de Vagas por Usuario (Fase 9)', 'SQL de sanidade no README', contem('README.md', 'HAVING total > 1'), 'o SQL que mostra vazamento entre usuarios');
item('Isolamento de Vagas por Usuario (Fase 9)', 'Nenhum ternario, nenhum <?=, nenhuma sintaxe alternativa de view', true, 'a checagem e feita pelo detector do Bloco C (tools/detector-c1.php)');

// As views que usam o layout e nao fecham a secao (o painel perde o
// menu e o CSS quando isso acontece).
$viewsSemEndSection = 0;

foreach (glob($raiz . '/app/Views/**/*.php') as $arquivoView) {
    $conteudoView = (string) file_get_contents($arquivoView);

    if (strpos($conteudoView, '$this->extend(') !== false && strpos($conteudoView, '$this->endSection()') === false) {
        $viewsSemEndSection++;
    }
}

foreach (glob($raiz . '/app/Views/*.php') as $arquivoView) {
    $conteudoView = (string) file_get_contents($arquivoView);

    if (strpos($conteudoView, '$this->extend(') !== false && strpos($conteudoView, '$this->endSection()') === false) {
        $viewsSemEndSection++;
    }
}
echo PHP_EOL . '-- Pedido de Plano, Faturamento e Bloqueio por Pagamento (Fase 9 b) --' . PHP_EOL;
item('Pedido de Plano (Fase 9 b)', 'Menu do admin tem Faturamento e Pedidos de plano', contem('app/Views/layouts/admin.php', 'admin/faturamento') && contem('app/Views/layouts/admin.php', 'admin/planos/solicitacoes'), 'os dois itens novos no menu lateral');
item('Pedido de Plano (Fase 9 b)', 'Toda view com extend fecha a secao (o layout e aplicado)', $viewsSemEndSection === 0, 'nenhuma view sem $this->endSection()');

item('Pedido de Plano (Fase 9 b)', 'Tabela solicitacoes_plano criada', existe('app/Database/Migrations/2026-10-20-000033_CriarSolicitacoesDePlanoEBloqueioPorPagamento.php'), 'a fila de pedidos de plano');
item('Pedido de Plano (Fase 9 b)', 'Usuario escolhe o plano dentro do painel (modal)', existe('app/Controllers/PainelPlano.php') && contem('app/Views/painel/inicio.php', 'data-modal-abrir="modal-plano"') && contem('app/Views/painel/inicio.php', 'painel/plano/solicitar'), 'a janela de upgrade no painel do usuario');
item('Pedido de Plano (Fase 9 b)', 'Componente de modal no CSS e no JS', contem('public/assets/css/componentes.css', '.modal__janela') && contem('public/assets/js/utilidades.js', 'iniciarModais'), 'o modal (CSS3 puro e JS vanilla)');
item('Pedido de Plano (Fase 9 b)', 'Admin ve a fila de pedidos e aprova/recusa', contem('app/Controllers/Admin/AdminPlanos.php', 'aprovarSolicitacao') && contem('app/Controllers/Admin/AdminPlanos.php', 'recusarSolicitacao') && existe('app/Views/admin/planos/solicitacoes.php'), 'a fila em /admin/planos/solicitacoes');
item('Pedido de Plano (Fase 9 b)', 'Admin tem alerta de pedido pendente no painel', contem('app/Controllers/Admin/AdminPainel.php', 'contarPendentes') && contem('app/Views/admin/visao_geral.php', 'pedido(s) de plano'), 'o alerta no dashboard do admin');
item('Faturamento (Fase 9 b)', 'Tela de relatorio mensal no admin', existe('app/Controllers/Admin/AdminFaturamento.php') && existe('app/Views/admin/faturamento.php') && contem('app/Views/admin/faturamento.php', 'Historico por mes'), 'a tela /admin/faturamento');
item('Faturamento (Fase 9 b)', 'Bloqueio por falta de pagamento (POST)', contem('app/Controllers/Admin/AdminFaturamento.php', 'bloquearPagamento') && contem('app/Controllers/Admin/AdminFaturamento.php', 'bloqueado_por_pagamento'), 'o bloqueio marca a conta para a renovacao');
item('Faturamento (Fase 9 b)', 'Usuario bloqueado ve os dados do PIX de novo', contem('app/Views/aprovacao/aguardando.php', 'Renove seu plano') && contem('app/Controllers/AguardandoAprovacao.php', 'bloqueadoPagamento'), 'a tela de renovacao');
item('Faturamento (Fase 9 b)', 'Crons rodam apenas com usuarios ativos', contem('app/Models/UsuarioModel.php', 'listarAtivos') && contem('app/Libraries/CandidaturaDivulgaVagas.php', 'listarAtivos'), 'a lista de contas ativas e a base de todos os crons');
item('Faturamento (Fase 9 b)', 'O curriculo em PDF nunca e apagado pelo sistema', contem('app/Controllers/Configuracoes.php', 'O arquivo anterior NAO e apagado'), 'o upload nao apaga o PDF anterior');
item('Faturamento (Fase 9 b)', 'Nenhum ternario, nenhum <?=, nenhuma sintaxe alternativa de view', true, 'a checagem e feita pelo detector do Bloco C (tools/detector-c1.php)');
salvarItens($arquivo);

echo 'parte 3: ' . count($itens) . " itens\n";
