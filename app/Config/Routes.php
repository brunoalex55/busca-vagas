<?php

use CodeIgniter\Router\RouteCollection;

/**
 * --------------------------------------------------------------------------
 * ROTAS DA APLICACAO
 * --------------------------------------------------------------------------
 *
 * Arquitetura MONOLITICA: site publico e sistema vivem neste mesmo
 * projeto, com as mesmas rotas base e os mesmos assets.
 *
 * REGRA: toda acao de escrita e POST. Nunca GET.
 * O CodeIgniter responde 404 automaticamente quando o metodo nao
 * corresponde ao declarado aqui (ex.: GET em rota POST).
 *
 * @var RouteCollection $routes
 */

// =========================================================================
// SITE PUBLICO
// =========================================================================

// Pagina inicial (landing page).
$routes->get('/', 'Site::inicio', ['as' => 'inicio']);

// Pagina de recursos do sistema.
$routes->get('recursos', 'Site::recursos', ['as' => 'recursos']);

// Pagina explicando o passo a passo.
$routes->get('como-funciona', 'Site::comoFunciona', ['as' => 'como_funciona']);

// Pagina de planos e precos.
$routes->get('precos', 'Site::precos', ['as' => 'precos']);

// Formulario de contato (leitura).
$routes->get('contato', 'Site::contato', ['as' => 'contato']);

// Envio do formulario de contato (escrita, obrigatoriamente POST).
$routes->post('contato', 'Site::enviarContato');

// Pagina institucional "Sobre".
$routes->get('sobre', 'Site::sobre', ['as' => 'sobre']);

// =========================================================================
// AUTENTICACAO
// =========================================================================

// Exibe o formulario de login.
$routes->get('login', 'Autenticacao::login', ['as' => 'login']);

// Processa o login (escrita via POST).
$routes->post('login', 'Autenticacao::autenticar');

// Exibe o formulario de cadastro.
$routes->get('registro', 'Autenticacao::registro', ['as' => 'registro']);

// Processa o cadastro (escrita via POST).
$routes->post('registro', 'Autenticacao::cadastrar');

// Encerra a sessao (escrita via POST - nunca por link GET).
$routes->post('logout', 'Autenticacao::sair', ['as' => 'logout']);

// =========================================================================
// AGUARDANDO APROVACAO
// =========================================================================
// Area acessivel somente a quem esta autenticado, mas ainda nao foi
// liberado pelo administrador.
$routes->group('', ['filter' => 'autenticacao'], static function (RouteCollection $routes): void {
    // Tela de espera com a chave PIX e as instrucoes de pagamento.
    $routes->get('aguardando', 'AguardandoAprovacao::index', ['as' => 'aguardando']);

    // Registra o aviso de pagamento (escrita via POST).
    $routes->post('aguardando/informar-pagamento', 'AguardandoAprovacao::informarPagamento');
});

// =========================================================================
// PAINEL DO USUARIO
// =========================================================================
// Todas as telas exigem sessao ativa E conta com status "ativo".
// Toda acao de escrita e POST; nenhuma alteracao acontece por GET.
$routes->group('painel', ['filter' => ['autenticacao', 'usuarioativo']], static function (RouteCollection $routes): void {
    // Dashboard.
    $routes->get('/', 'Painel::index', ['as' => 'painel']);

    // ---------------------------------------------------------------------
    // EXECUCOES MANUAIS DO USUARIO (BLOCO E)
    // ---------------------------------------------------------------------
    // Disparo manual da busca de vagas e do envio de e-mails pelo proprio
    // usuario. Sempre POST e sempre sujeito ao limite diario configurado
    // pelo admin em /admin/cron.
    $routes->post('executar-vagas', 'Painel::executarConsultaManual');
    $routes->post('executar-emails', 'Painel::executarEnvioManual');

    // ---------------------------------------------------------------------
    // PALAVRAS-CHAVE
    // ---------------------------------------------------------------------
    $routes->get('palavras-chave', 'PalavrasChave::index', ['as' => 'painel_palavras_chave']);
    $routes->get('palavras-chave/nova', 'PalavrasChave::nova');
    $routes->post('palavras-chave', 'PalavrasChave::criar');
    $routes->get('palavras-chave/(:num)/editar', 'PalavrasChave::editar/$1');
    $routes->post('palavras-chave/(:num)/atualizar', 'PalavrasChave::atualizar/$1');
    $routes->post('palavras-chave/(:num)/alternar', 'PalavrasChave::alternar/$1');
    $routes->post('palavras-chave/(:num)/excluir', 'PalavrasChave::excluir/$1');

    // ---------------------------------------------------------------------
    // VAGAS
    // ---------------------------------------------------------------------
    $routes->get('vagas', 'Vagas::index', ['as' => 'painel_vagas']);
    $routes->get('vagas/(:num)', 'Vagas::detalhe/$1');
    $routes->post('vagas/(:num)/marcar-candidatado', 'Vagas::marcarCandidatado/$1');

    // ---------------------------------------------------------------------
    // ENVIO EM MASSA
    // ---------------------------------------------------------------------
    $routes->get('envio-massa', 'EnvioMassa::index', ['as' => 'painel_envio_massa']);
    $routes->post('envio-massa/alternar-automatico', 'EnvioMassa::alternarAutomatico');

    // ---------------------------------------------------------------------
    // HISTORICO (consultas e envios)
    // ---------------------------------------------------------------------
    $routes->get('historico', 'Historico::index', ['as' => 'painel_historico']);

    // ---------------------------------------------------------------------
    // DIVULGA VAGAS (SEGUNDO PROVEDOR)
    // ---------------------------------------------------------------------
    // Execucao manual pelo usuario: buscar vagas e candidatar-se pelo site.
    // Toda escrita e POST e sempre sujeita aos limites do dia (as
    // execucoes manuais nao contornam nenhum limite do CRON).
    $routes->get('divulgavagas', 'PainelDivulgaVagas::index', ['as' => 'painel_divulgavagas']);
    $routes->get('divulgavagas/historico', 'PainelDivulgaVagas::historico', ['as' => 'painel_divulgavagas_historico']);
    $routes->post('divulgavagas/buscar-manual', 'PainelDivulgaVagas::buscarManual');
    $routes->post('divulgavagas/candidatar-manual', 'PainelDivulgaVagas::candidatarManual');
    $routes->post('divulgavagas/candidatar-vaga/(:num)', 'PainelDivulgaVagas::candidatarVaga/$1');

    // ---------------------------------------------------------------------
    // CONFIGURACOES
    // ---------------------------------------------------------------------
    $routes->get('configuracoes', 'Configuracoes::index', ['as' => 'painel_configuracoes']);
    $routes->post('configuracoes/perfil', 'Configuracoes::salvarPerfil');
    $routes->post('configuracoes/senha', 'Configuracoes::alterarSenha');
    $routes->post('configuracoes/email', 'Configuracoes::salvarEmail');
    $routes->post('configuracoes/email/remover-app-password', 'Configuracoes::removerAppPassword');
    $routes->post('configuracoes/mensagem', 'Configuracoes::salvarMensagem');
    $routes->post('configuracoes/curriculo', 'Configuracoes::enviarCurriculo');
    $routes->post('configuracoes/curriculo/remover', 'Configuracoes::removerCurriculo');
    $routes->get('configuracoes/curriculo', 'Configuracoes::baixarCurriculo');
    $routes->post('configuracoes/envio', 'Configuracoes::salvarEnvio');
});

// =========================================================================
// PAINEL ADMINISTRATIVO
// =========================================================================
// ORDEM DOS FILTROS:
//   1. autenticacao -> sem sessao vai para /login;
//   2. usuarioativo -> status pendente/bloqueado vai para /aguardando;
//   3. admin        -> perfil diferente de admin vai para /painel.
//
// O status e verificado ANTES do perfil para que uma conta bloqueada
// nunca consiga usar o painel administrativo.
$routes->group('admin', ['filter' => ['autenticacao', 'usuarioativo', 'admin']], static function (RouteCollection $routes): void {
    // Visao geral.
    $routes->get('/', 'Admin\AdminPainel::index', ['as' => 'admin']);

    // ---------------------------------------------------------------------
    // MINHA CONTA
    // ---------------------------------------------------------------------
    $routes->get('conta', 'Admin\AdminConta::index', ['as' => 'admin_conta']);
    $routes->post('conta/perfil', 'Admin\AdminConta::salvarPerfil');
    $routes->post('conta/senha', 'Admin\AdminConta::alterarSenha');

    // ---------------------------------------------------------------------
    // USUARIOS
    // ---------------------------------------------------------------------
    $routes->get('usuarios', 'Admin\AdminUsuarios::index', ['as' => 'admin_usuarios']);
    $routes->get('usuarios/(:num)', 'Admin\AdminUsuarios::detalhe/$1');
    $routes->post('usuarios/limite-manuais', 'Admin\AdminUsuarios::salvarLimiteExecucoesManuais');
    $routes->post('usuarios/(:num)/aprovar', 'Admin\AdminUsuarios::aprovar/$1');
    $routes->post('usuarios/(:num)/bloquear', 'Admin\AdminUsuarios::bloquear/$1');
    $routes->post('usuarios/(:num)/reativar', 'Admin\AdminUsuarios::reativar/$1');
    $routes->post('usuarios/(:num)/limite', 'Admin\AdminUsuarios::definirLimite/$1');
    $routes->post('usuarios/(:num)/zerar-manuais', 'Admin\AdminUsuarios::zerarExecucoesManuaisUsuario/$1');
    $routes->post('usuarios/(:num)/reiniciar-consultas', 'Admin\AdminUsuarios::reiniciarConsultas/$1');
    $routes->post('usuarios/(:num)/envio-automatico', 'Admin\AdminUsuarios::alternarEnvio/$1');
    $routes->post('usuarios/(:num)/perfil', 'Admin\AdminUsuarios::definirPerfil/$1');

    // ---------------------------------------------------------------------
    // CHAVES SERPAPI
    // ---------------------------------------------------------------------
    $routes->get('chaves', 'Admin\AdminChaves::index', ['as' => 'admin_chaves']);
    $routes->get('chaves/nova', 'Admin\AdminChaves::nova');
    $routes->post('chaves', 'Admin\AdminChaves::criar');
    $routes->get('chaves/(:num)/editar', 'Admin\AdminChaves::editar/$1');
    $routes->post('chaves/(:num)/atualizar', 'Admin\AdminChaves::atualizar/$1');
    $routes->post('chaves/(:num)/ativar', 'Admin\AdminChaves::ativar/$1');
    $routes->post('chaves/(:num)/desativar', 'Admin\AdminChaves::desativar/$1');
    $routes->post('chaves/(:num)/esgotar', 'Admin\AdminChaves::esgotar/$1');
    $routes->post('chaves/(:num)/invalidar', 'Admin\AdminChaves::invalidar/$1');
    $routes->post('chaves/(:num)/zerar', 'Admin\AdminChaves::zerar/$1');
    $routes->post('chaves/(:num)/excluir', 'Admin\AdminChaves::excluir/$1');

    // ---------------------------------------------------------------------
    // DISTRIBUICAO DE CONSULTAS
    // ---------------------------------------------------------------------
    $routes->get('distribuicao', 'Admin\AdminDistribuicao::index', ['as' => 'admin_distribuicao']);
    $routes->post('distribuicao/recalcular', 'Admin\AdminDistribuicao::recalcular');

    // ---------------------------------------------------------------------
    // CRONS (TELA ANTIGA - REDIRECIONADA)
    // ---------------------------------------------------------------------
    // A tela "/admin/crons" foi substituida pela tela de agendamento
    // "/admin/cron" (que tem o modo de agendamento, a integracao com o
    // cron-job.org e os limites). Os links antigos nao quebram: eles
    // vao para a tela nova.
    //
    // As acoes de escrita tambem mudaram de casa: a busca e o envio
    // manual agora sao disparados pela tela nova (e pelos comandos
    // Spark, que sao o caminho oficial).
    $routes->get('crons', static fn () => redirect()->to(site_url('admin/cron')));
    $routes->post('crons/executar-busca', 'Admin\AdminCron::executarVagas');
    $routes->post('crons/executar-envio', 'Admin\AdminCron::executarEmails');
    $routes->post('crons/regenerar-token', 'Admin\AdminCron::gerarTokenGlobal');

    // ---------------------------------------------------------------------
    // AGENDAMENTO (FASE 7)
    // ---------------------------------------------------------------------
    $routes->get('cron', 'Admin\AdminCron::index', ['as' => 'admin_cron']);
    $routes->post('cron/modo', 'Admin\AdminCron::salvarModo');
    $routes->post('cron/api-key', 'Admin\AdminCron::salvarApiKeyCronJob');
    $routes->post('cron/testar-conexao', 'Admin\AdminCron::testarConexaoCronJob');
    $routes->post('cron/executar-vagas', 'Admin\AdminCron::executarVagas');
    $routes->post('cron/executar-emails', 'Admin\AdminCron::executarEmails');
    $routes->post('cron/gerar-token-global', 'Admin\AdminCron::gerarTokenGlobal');
    $routes->get('cron/jobs', 'Admin\AdminCron::listarJobsCronJobOrg', ['as' => 'admin_cron_jobs']);
    $routes->post('cron/jobs/criar', 'Admin\AdminCron::criarJobParaUsuario');
    $routes->post('cron/jobs/excluir/(:num)/(:segment)', 'Admin\AdminCron::excluirJobDoUsuario/$1/$2');
    $routes->post('cron/sincronizar', 'Admin\AdminCron::sincronizarJobs');
    $routes->post('cron/limite-manual', 'Admin\AdminCron::salvarLimiteExecucoesManuais');

    // ---------------------------------------------------------------------
    // DIVULGA VAGAS (SEGUNDO PROVEDOR)
    // ---------------------------------------------------------------------
    // Execucao manual do segundo provedor pelo administrador: buscar
    // vagas e candidatar-se pelo site. O alvo pode ser "todos" ou um
    // usuario especifico. Toda escrita e POST e sempre sujeita aos
    // limites do dia.
    $routes->get('divulgavagas', 'Admin\AdminDivulgaVagas::index', ['as' => 'admin_divulgavagas']);
    $routes->get('divulgavagas/historico', 'Admin\AdminDivulgaVagas::historico', ['as' => 'admin_divulgavagas_historico']);
    $routes->post('divulgavagas/buscar-manual', 'Admin\AdminDivulgaVagas::buscarManual');
    $routes->post('divulgavagas/candidatar-manual', 'Admin\AdminDivulgaVagas::candidatarManual');
    $routes->post('divulgavagas/limites', 'Admin\AdminDivulgaVagas::salvarLimites');
    $routes->post('cron/zerar-manual/(:num)', 'Admin\AdminCron::zerarExecucoesManuaisUsuario/$1');
    $routes->post('cron/usuario/(:num)/token', 'Admin\AdminCron::regenerarTokenUsuario/$1');

    // ---------------------------------------------------------------------
    // SOLICITACOES DE LIBERACAO
    // ---------------------------------------------------------------------
    $routes->get('solicitacoes', 'Admin\AdminSolicitacoes::index', ['as' => 'admin_solicitacoes']);
    $routes->post('solicitacoes/(:num)/aprovar', 'Admin\AdminSolicitacoes::aprovar/$1');
    $routes->post('solicitacoes/(:num)/rejeitar', 'Admin\AdminSolicitacoes::rejeitar/$1');

    // ---------------------------------------------------------------------
    // LOGS
    // ---------------------------------------------------------------------
    $routes->get('logs', 'Admin\AdminLogs::index', ['as' => 'admin_logs']);

    // ---------------------------------------------------------------------
    // CONFIGURACAO DO SITE
    // ---------------------------------------------------------------------
    $routes->get('configuracao-site', 'Admin\AdminConfiguracaoSite::index', ['as' => 'admin_configuracao_site']);
    $routes->post('configuracao-site/salvar', 'Admin\AdminConfiguracaoSite::salvarSite');
    $routes->post('configuracao-site/pagamento', 'Admin\AdminConfiguracaoSite::salvarPagamento');
    $routes->post('configuracao-site/remover-imagem', 'Admin\AdminConfiguracaoSite::removerImagem');
});

// =========================================================================
// CRON (SOMENTE POST)
// =========================================================================
// O agendamento NAO acontece mais por HTTP. O caminho oficial e a linha de
// comando (php spark ...), chamada pelo crontab do Linux, pelo Agendador de
// Tarefas do Windows ou pelo modo 'spark_manual' escolhido no painel.
//
// As rotas abaixo foram DESCONTINUADAS e nao estao mais registradas:
//   POST /cron/global/buscar-vagas
//   POST /cron/global/enviar-emails
//   POST /cron/usuario/{id}/buscar-vagas
//   POST /cron/usuario/{id}/enviar-emails
// Quem ainda usar uma delas recebe 404. O controller Cron.php foi mantido
// apenas como aviso (resposta 410 Gone) caso alguem o chame diretamente.
//
// $routes->post('cron/global/buscar-vagas', 'Cron::buscarVagasGlobal');
// $routes->post('cron/global/enviar-emails', 'Cron::enviarEmailsGlobal');
// $routes->post('cron/usuario/(:num)/buscar-vagas', 'Cron::buscarVagasUsuario/$1');
// $routes->post('cron/usuario/(:num)/enviar-emails', 'Cron::enviarEmailsUsuario/$1');

// Endpoint externo por usuario, usado pelo cron-job.org no modo spark_manual.
// E a UNICA rota de escrita publica: a credencial e o token individual do
// usuario, lido do header X-Cron-Token ou da query string ?token=.
$routes->post('cron/usuario/(:num)/executar-externo', 'Admin\AdminCron::executarExterno/$1');

// ---------------------------------------------------------------------------
// CRON DO DIVULGA VAGAS (SEGUNDO PROVEDOR)
// ---------------------------------------------------------------------------
//
// Endpoints de agendamento do segundo provedor. A credencial e o TOKEN
// GLOBAL do sistema, lido da query string (?token=...) e comparado com
// hash_equals().
//
// A separacao e a mesma do SerpApi:
//   - o endpoint de BUSCA nunca candidata a nada;
//   - o endpoint de CANDIDATURA nunca busca vagas.
//
// O agendamento deste provedor e INDEPENDENTE do SerpApi: o admin escolhe
// os horarios separado, em /admin/cron.
$routes->post('cron/usuario/(:num)/divulgavagas/buscar-vagas', 'CronDivulgaVagas::buscarUsuario/$1');
$routes->post('cron/usuario/(:num)/divulgavagas/candidatar', 'CronDivulgaVagas::candidatarUsuario/$1');
$routes->post('cron/global/divulgavagas/buscar-vagas', 'CronDivulgaVagas::buscarGlobal');
$routes->post('cron/global/divulgavagas/candidatar', 'CronDivulgaVagas::candidatarGlobal');

// =========================================================================
// SEO
// =========================================================================

// Sitemap XML gerado dinamicamente a partir das rotas publicas.
$routes->get('sitemap.xml', 'Sitemap::index');

// robots.txt gerado dinamicamente.
$routes->get('robots.txt', 'Robots::index');
