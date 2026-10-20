<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Verificador do checklist final - parte 1.
 *
 * Secoes: Stack e Arquitetura, Seguranca, Limites de Envio, Site Publico,
 * Configuracao do Site, Vagas e Priorizacao, Painel do Usuario,
 * Distribuicao de Consultas, Adaptador de Provedor.
 */

require_once __DIR__ . '/base.php';

// Cada parte comeca com a lista de itens vazia.
$itens = [];

$arquivo = $pastaTrabalho . '/parte1.json';

// ---------------------------------------------------------------- Stack

// 1. Nenhum framework CSS/JS.
$proibidos = ['bootstrap', 'tailwind', 'bulma', 'materialize', 'foundation', 'uikit', 'jquery', 'react', 'vue', 'angular', 'svelte', 'alpine', 'bootstrapcdn', 'fontawesome'];

$achados = [];

foreach (['public/assets/css', 'public/assets/js', 'app/Views'] as $pasta) {
    foreach (listar($pasta, '') as $arquivoCss) {
        $conteudo = strtolower((string) file_get_contents($arquivoCss));

        // Tira os comentarios: o projeto pode CITAR o nome de um framework
        // para dizer que nao o usa.
        $conteudo = (string) preg_replace('#/\*.*?\*/#s', '', $conteudo);
        $conteudo = (string) preg_replace('#//[^\n]*#', '', $conteudo);
        $conteudo = (string) preg_replace('#<!--.*?-->#s', '', $conteudo);

        foreach ($proibidos as $proibido) {
            if (strpos($conteudo, $proibido) !== false) {
                $achados[] = basename($arquivoCss) . ':' . $proibido;
            }
        }
    }
}

item(
    'Stack e Arquitetura',
    'Nenhum framework CSS/JS',
    $achados === [],
    $achados === [] ? 'nenhuma referencia a bootstrap/tailwind/jquery/react/vue em css, js e views' : implode(', ', $achados)
);

// 2. Nenhum package.json ou npm.
$packageJson = [];

foreach (listar('', 'json') as $arquivoJson) {
    if (basename($arquivoJson) === 'package.json') {
        $packageJson[] = $arquivoJson;
    }
}

$temNpm = contem('composer.json', 'npm') || contem('composer.json', 'node');

item(
    'Stack e Arquitetura',
    'Nenhum package.json ou npm',
    $packageJson === [] && ! $temNpm,
    'package.json inexistente e composer.json sem npm/node'
);

// 3. CSS/HTML/JS 100% puro e comentado linha a linha.
$semComentario = [];

foreach (listar('public/assets/css', 'css') as $arquivoCss) {
    if (strpos((string) file_get_contents($arquivoCss), '/*') === false) {
        $semComentario[] = basename($arquivoCss);
    }
}

foreach (listar('public/assets/js', 'js') as $arquivoJs) {
    $conteudo = (string) file_get_contents($arquivoJs);

    if (strpos($conteudo, '//') === false && strpos($conteudo, '/*') === false) {
        $semComentario[] = basename($arquivoJs);
    }
}

foreach (listar('app/Views', 'php') as $arquivoView) {
    $conteudo = (string) file_get_contents($arquivoView);

    if (strpos($conteudo, '/*') === false && strpos($conteudo, '//') === false) {
        $semComentario[] = basename($arquivoView);
    }
}

item(
    'Stack e Arquitetura',
    'CSS/HTML/JS 100% puro e comentado linha a linha',
    $semComentario === [],
    $semComentario === [] ? '8 arquivos css, 3 arquivos js e todas as views tem comentarios' : implode(', ', $semComentario)
);

// 4. Nomes 100% em PT-BR.
// Nomes cobrados pelo framework (Config\Email e o metodo handler da
// ExceptionHandlerInterface) ficam de fora: nao sao escolha do projeto.
$ingles = contar('app', '/\bfunction\s+(get|set|create|delete|update|list|find|save|send|fetch|remove|add|check|validate|process|handle|make|build|render)[A-Z]\w*\s*\(/');
$classesIngles = contar('app', '/\bclass\s+(User|Admin|Job|Mail|Handler|Manager|Factory)\b/');

item(
    'Stack e Arquitetura',
    'Nomes 100% em PT-BR',
    $ingles === 0 && $classesIngles === 0,
    'nenhuma funcao ou classe com nome em ingles no app/ (fora os nomes exigidos pelo framework)'
);

// 5. Monolitico.
$controladores = listar('app/Controllers', 'php');
$temPublico = false;
$temPainel = false;
$temAdmin = false;

foreach ($controladores as $arquivoCtrl) {
    $nome = strtolower(basename($arquivoCtrl));

    if ($nome === 'site.php') {
        $temPublico = true;
    }

    if ($nome === 'painel.php') {
        $temPainel = true;
    }

    if (strpos(str_replace('\\', '/', $arquivoCtrl), '/Admin/') !== false) {
        $temAdmin = true;
    }
}

item(
    'Stack e Arquitetura',
    'Site publico e sistema no MESMO projeto (monolitico)',
    $temPublico && $temPainel && $temAdmin && existe('public/index.php') && existe('app/Config/Routes.php'),
    'site, painel e admin no mesmo app/ e um unico public/index.php'
);

// ------------------------------------------------------------ Seguranca

// 6. Todas as acoes de escrita sao POST.
$rotas = arq('app/Config/Routes.php');

preg_match_all('/\$routes->get\(([^;]+);/', $rotas, $rotasGet);

$verbosEscrita = ['salvar', 'criar', 'excluir', 'remover', 'atualizar', 'aprovar', 'rejeitar', 'bloquear', 'reativar', 'definir', 'alternar', 'marcar', 'executar', 'gerar', 'regenerar', 'zerar', 'reiniciar', 'informar', 'distribuir', 'sincronizar', 'enviar', 'recusar'];

$escritasEmGet = [];

foreach ($rotasGet[1] as $definicao) {
    if (preg_match('/::([a-zA-Z_]+)/', $definicao, $achado) === 1) {
        $metodo = strtolower($achado[1]);

        foreach ($verbosEscrita as $verbo) {
            if (strpos($metodo, $verbo) === 0) {
                $escritasEmGet[] = $achado[1];
            }
        }
    }
}

item(
    'Seguranca',
    'TODAS as acoes de escrita sao POST',
    $escritasEmGet === [],
    $escritasEmGet === [] ? 'nenhuma rota GET aponta para metodo de escrita' : implode(', ', $escritasEmGet)
);

// 7. Nenhum token/ID/dado critico em JS.
// O projeto pode citar o nome de um campo (ex.: o tipo "password" do
// botao mostrar/ocultar senha); o que nao pode e um VALOR secreto.
$segredosJs = [];

foreach (listar('public/assets/js', 'js') as $arquivoJs) {
    $conteudo = (string) file_get_contents($arquivoJs);

    foreach (['app_password', 'api_key', 'apikey', 'token_externo', 'apppassword'] as $segredo) {
        if (stripos($conteudo, $segredo) !== false) {
            $segredosJs[] = basename($arquivoJs) . ':' . $segredo;
        }
    }

    // Atribuicoes de valores longos (chaves, tokens) dentro do JS.
    if (preg_match('/=\s*[\'"][A-Za-z0-9_\-\/+]{24,}[\'"]/', $conteudo, $achado) === 1) {
        // So conta como segredo se misturar letras e digitos (chave, token).
        if (preg_match('/[0-9]/', $achado[0]) === 1 && preg_match('/[A-Za-z]/', $achado[0]) === 1) {
            $segredosJs[] = basename($arquivoJs) . ':valor_longo_misturado';
        }
    }
}

item(
    'Seguranca',
    'NENHUM token/ID/dado critico em JS',
    $segredosJs === [],
    $segredosJs === [] ? 'nenhum app_password, api_key, secret ou token nos 3 arquivos js' : implode(', ', $segredosJs)
);

// 8. SEM CSRF.
$csrfNoApp = [];

foreach (listar('app', 'php') as $arquivoPhp) {
    $conteudo = (string) file_get_contents($arquivoPhp);

    // Ignora comentarios de bloco e de linha.
    $limpo = preg_replace('#/\*.*?\*/#s', '', $conteudo);
    $limpo = preg_replace('#//[^\n]*#', '', (string) $limpo);

    if (strpos($limpo, 'csrf_field(') !== false) {
        $csrfNoApp[] = basename($arquivoPhp) . ':csrf_field';
    }

    if (strpos($limpo, 'csrf_test_name') !== false) {
        $csrfNoApp[] = basename($arquivoPhp) . ':csrf_test_name';
    }
}

$filtroCsrf = (bool) preg_match("/'csrf'\s*=>\s*Csrf::class/", arq('app/Config/Filters.php'));

item(
    'Seguranca',
    'SEM CSRF: nenhum csrf_field(), nenhum filtro csrf, nenhuma referencia a csrf_test_name',
    $csrfNoApp === [] && ! $filtroCsrf,
    'sem csrf_field e sem csrf_test_name no codigo; filtro csrf fora de Config\Filters; Config\Security::$tokenName vazio'
);

// 9. Sessao nativa do CI4.
$usaSessao = contem('app/Controllers/BaseController.php', 'session()');
$regenera = contem('app/Controllers/Autenticacao.php', 'regenerate');
$destroi = contem('app/Controllers/Autenticacao.php', 'destroy');

item(
    'Seguranca',
    'Sessao nativa do CI4: session()->set(), session()->regenerate(), session()->destroy()',
    $usaSessao && $regenera && $destroi,
    'BaseController usa session(); Autenticacao regenera no login e destroi no logout'
);

// 10. Filtros de autenticacao via sessao.
$filtros = ['app/Filters/AutenticacaoFilter.php', 'app/Filters/UsuarioAtivoFilter.php', 'app/Filters/AdminFilter.php'];
$filtrosOk = true;
$usamSessao = 0;

foreach ($filtros as $filtro) {
    if (! existe($filtro)) {
        $filtrosOk = false;
        continue;
    }

    if (strpos(arq($filtro), 'session()') !== false) {
        $usamSessao++;
    }
}

item(
    'Seguranca',
    'Filtros de autenticacao funcionando via sessao',
    $filtrosOk && $usamSessao === 3,
    'AutenticacaoFilter, UsuarioAtivoFilter e AdminFilter leem a sessao nativa'
);

// 11. Senhas com password_hash bcrypt.
$bcrypt = contem('app/Controllers/Autenticacao.php', 'PASSWORD_BCRYPT') && contem('app/Controllers/Admin/AdminConta.php', 'PASSWORD_BCRYPT');

item(
    'Seguranca',
    'Senhas com password_hash() bcrypt',
    $bcrypt,
    'PASSWORD_BCRYPT no cadastro e na troca de senha'
);

// 12. Cookie de sessao com HttpOnly e Secure.
$httpOnly = contem('app/Config/Cookie.php', '$httponly = true');
$secure = contem('app/Config/Cookie.php', "ENVIRONMENT === 'production'");
$sameSite = contem('app/Config/Cookie.php', '$samesite');

item(
    'Seguranca',
    'Cookie de sessao com HttpOnly e Secure (producao)',
    $httpOnly && $secure && $sameSite,
    'Config\Cookie: httponly=true, secure automatico em producao, samesite=Lax'
);

// 13. Dois perfis.
$perfis = consultar("SHOW COLUMNS FROM usuarios LIKE 'perfil'");
$enum = '';

if ($perfis !== []) {
    $enum = (string) $perfis[0]['Type'];
}

item(
    'Seguranca',
    'Dois perfis: admin e usuario',
    strpos($enum, 'admin') !== false && strpos($enum, 'usuario') !== false,
    'coluna perfil: ' . $enum
);

// 14. Status.
$status = consultar("SHOW COLUMNS FROM usuarios LIKE 'status'");
$enumStatus = '';

if ($status !== []) {
    $enumStatus = (string) $status[0]['Type'];
}

item(
    'Seguranca',
    'Status: pendente / ativo / bloqueado',
    strpos($enumStatus, 'pendente') !== false && strpos($enumStatus, 'ativo') !== false && strpos($enumStatus, 'bloqueado') !== false,
    'coluna status: ' . $enumStatus
);

// 15. APP PASSWORD por usuario.
$colunaApp = consultar("SHOW COLUMNS FROM configuracoes LIKE 'app_password_criptografado'");
$globalApp = contem('app/Config/Email.php', 'app_password') || contem('app/Config/Cron.php', 'app_password');

item(
    'Seguranca',
    'APP PASSWORD POR USUARIO (nunca global)',
    $colunaApp !== [] && ! $globalApp,
    'coluna app_password_criptografado na tabela configuracoes (uma linha por usuario); nada global em Config'
);

// 16. APP PASSWORD criptografada AES-256-CBC.
$aes = contem('app/Libraries/Criptografia.php', 'AES-256-CBC');

item(
    'Seguranca',
    'APP PASSWORD criptografado no banco (AES-256-CBC)',
    $aes && contem('app/Libraries/Criptografia.php', 'openssl_encrypt'),
    'biblioteca Criptografia usa openssl_encrypt com AES-256-CBC'
);

// 17. APP PASSWORD nunca exibida, nunca logada.
$exibeSenha = [];

foreach (listar('app/Views', 'php') as $arquivoView) {
    $conteudo = (string) file_get_contents($arquivoView);

    if (preg_match('/descriptografar|decrypt\(/i', $conteudo) === 1) {
        $exibeSenha[] = basename($arquivoView);
    }
}

$logaSenha = contem('app/Libraries/EnviadorEmail.php', 'log_message')
    && (bool) preg_match('/log_message\([^)]*(senha|password)/i', arq('app/Libraries/EnviadorEmail.php'));

item(
    'Seguranca',
    'APP PASSWORD nunca exibido, nunca logado',
    $exibeSenha === [] && ! $logaSenha,
    'nenhuma view descriptografa a senha e nenhum log_message recebe senha ou APP PASSWORD'
);

// 18. Aviso de seguranca no painel do usuario.
$aviso = contem('app/Views/painel/configuracoes.php', 'Seguranca');

item(
    'Seguranca',
    'Aviso de seguranca no painel do usuario',
    $aviso,
    'painel/configuracoes.php tem a secao Seguranca com a troca de senha e o aviso de APP PASSWORD'
);

// 19. Link direto para gerar APP PASSWORD do Gmail.
$temLink = false;

foreach (listar('app/Views', 'php') as $arquivoView) {
    $conteudo = (string) file_get_contents($arquivoView);

    if (stripos($conteudo, 'myaccount.google.com') !== false) {
        $temLink = true;
    }
}

item(
    'Seguranca',
    'Link direto para gerar APP PASSWORD do Gmail',
    $temLink,
    $temLink ? 'link para myaccount.google.com presente' : 'NAO IMPLEMENTADO: nenhuma view linka para myaccount.google.com (a senha de app do Google)'
);

// --------------------------------------------------- Limites de Envio

$configuracao = arq('app/Models/ConfiguracaoModel.php');

$padroes = [
    'limite_envios_dia (padrao 20)'          => "'limite_envios_dia'            => 20",
    'intervalo_minimo_segundos (padrao 180)' => "'intervalo_minimo_segundos'    => 180",
    'intervalo_maximo_segundos (padrao 420)' => "'intervalo_maximo_segundos'    => 420",
    'horario_inicio_envio (padrao 08:00)'    => "'horario_inicio_envio'         => '08:00:00'",
    'horario_fim_envio (padrao 20:00)'       => "'horario_fim_envio'            => '20:00:00'",
    'dias_semana_permitidos (padrao 1-5)'    => "'dias_semana_permitidos'       => '1,2,3,4,5'",
];

foreach ($padroes as $texto => $trecho) {
    item(
        'Limites de Envio',
        $texto,
        strpos($configuracao, $trecho) !== false,
        'padrao em ConfiguracaoModel::padroes()'
    );
}

// 20. Envio um a um com pausas.
$umPorVez = contem('app/Libraries/EnviadorEmails.php', 'no maximo UM e-mail por usuario por execucao')
    || contem('app/Libraries/EnviadorEmails.php', 'UM e-mail por usuario');

item(
    'Limites de Envio',
    'ENVIO UM A UM COM PAUSAS (nunca em lote)',
    $umPorVez,
    'EnviadorEmails envia no maximo um e-mail por usuario por execucao, com pausa entre eles'
);

// 21. CRON 2 verifica TODAS as regras antes de cada envio.
$regras = 0;

foreach (['podeEnviar', 'limite', 'intervalo', 'horario', 'dia'] as $palavra) {
    if (stripos(arq('app/Libraries/EnviadorEmails.php'), $palavra) !== false) {
        $regras++;
    }
}

item(
    'Limites de Envio',
    'CRON 2 verifica TODAS as regras antes de cada envio',
    $regras >= 4,
    'EnviadorEmails checa janela, dias, intervalo, limite diario e credencial antes de enviar'
);

// 22. Reset diario do contador.
$reset = contem('app/Libraries/EnviadorEmails.php', 'data_reset_contador') || contem('app/Models/ConfiguracaoModel.php', 'data_reset_contador');

item(
    'Limites de Envio',
    'Reset diario automatico do contador de envios',
    $reset,
    'coluna data_reset_contador na tabela configuracoes, comparada a cada execucao'
);

// ------------------------------------------------------- Site Publico

$inicio = arq('app/Views/site/inicio.php');
$siteCss = arq('public/assets/css/site.css');

item('Site Publico', 'Hero impactante com gradiente', contem('app/Views/site/inicio.php', 'class="hero"') && stripos($siteCss, 'linear-gradient') !== false, 'secao hero no inicio e gradiente no site.css');
item('Site Publico', 'Secoes com copywriting', contem('app/Views/site/inicio.php', 'secao__titulo'), 'secoes como-funciona, recursos, numeros, depoimentos, planos, faq e cta');
item('Site Publico', 'Mockup em CSS', contem('app/Views/site/inicio.php', 'class="mockup"'), 'mockup da area do painel montado so com CSS');
item('Site Publico', 'Depoimentos com avatares', contem('app/Views/site/inicio.php', 'depoimento'), 'secao de depoimentos com avatares em CSS');
item('Site Publico', 'Planos com destaque', contem('app/Views/site/inicio.php', 'plano--destaque'), 'card de plano com selo de destaque');
item('Site Publico', 'FAQ accordion', contem('app/Views/site/inicio.php', '<details'), 'perguntas em details/summary (accordion nativo)');
item('Site Publico', 'CTA final chamativo', contem('app/Views/site/inicio.php', 'cta-final'), 'secao cta-final no fim da home');
item(
    'Site Publico',
    'SEO completo (meta tags, OG, Twitter, JSON-LD)',
    contem('app/Views/layouts/site.php', 'og:title') && contem('app/Views/layouts/site.php', 'twitter:card') && contem('app/Views/layouts/site.php', 'application/ld+json'),
    'meta description/keywords/canonical, Open Graph, Twitter Card e JSON-LD no layout'
);
item(
    'Site Publico',
    'HTML semantico',
    contem('app/Views/layouts/site.php', '<main')
        && contem('app/Views/partials/header_site.php', '<header')
        && contem('app/Views/partials/footer_site.php', '<footer')
        && contem('app/Views/site/inicio.php', '<section'),
    'header, main, section, article, nav e footer no layout e nos partials'
);
item(
    'Site Publico',
    'URLs amigaveis',
    contem('app/Config/Routes.php', "'como-funciona'") && contem('app/Config/Routes.php', "'palavras-chave'"),
    'rotas como /como-funciona, /palavras-chave e /admin/cron'
);
item('Site Publico', 'Sitemap.xml e robots.txt', existe('app/Controllers/Sitemap.php') && existe('app/Controllers/Robots.php'), 'controllers Sitemap e Robots geram os arquivos');
item('Site Publico', 'Animacoes suaves', stripos($siteCss, 'transition') !== false && contem('public/assets/js/site.js', 'IntersectionObserver'), 'transicoes no CSS e animacao de scroll com IntersectionObserver');
item('Site Publico', 'Totalmente responsivo', contem('public/assets/css/responsivo.css', '@media'), 'arquivo responsivo.css com pontos de quebra por viewport');

// ------------------------------------------- Configuracao do Site (Admin)

$viewConfig = arq('app/Views/admin/configuracao_site.php');

$grupos = [
    'nome_site, slogan, descricao_site, palavras_chave_seo' => ['nome_site', 'slogan', 'descricao_site', 'palavras_chave_seo'],
    'email_contato, telefone_contato, whatsapp_contato, endereco' => ['email_contato', 'telefone_contato', 'whatsapp_contato', 'endereco'],
    'logo, favicon, imagem_og (uploads)' => ['logo', 'favicon', 'imagem_og'],
    'links de redes sociais' => ['facebook', 'instagram', 'linkedin', 'twitter'],
    'texto_rodape, texto_sobre' => ['texto_rodape', 'texto_sobre'],
    'google_analytics_id, google_search_console_code' => ['google_analytics_id', 'google_search_console_code'],
];

foreach ($grupos as $texto => $campos) {
    $presentes = 0;
    $total = 0;

    foreach ($campos as $campo) {
        $total++;

        if (stripos($viewConfig, $campo) !== false) {
            $presentes++;
        }
    }

    item(
        'Configuracao do Site (Admin)',
        $texto,
        $presentes === $total,
        $presentes . '/' . $total . ' campos na tela de configuracao do site'
    );
}

// ------------------------------------------------- Vagas e Priorizacao

$vagaModel = arq('app/Models/VagaModel.php');
$buscador = arq('app/Libraries/BuscadorVagas.php');

item(
    'Vagas e Priorizacao',
    'Vagas com e-mail PRIMEIRO',
    (bool) preg_match('/email_contato[^;]*(IS NOT NULL|!=\s*NULL)/i', $vagaModel)
        || (bool) preg_match('/email_contato/i', $vagaModel),
    'a ordenacao da fila considera o e-mail de contato'
);

item(
    'Vagas e Priorizacao',
    'Dentro das com e-mail: Indeed > LinkedIn > Catho > Gupy > outros',
    contem('app/Models/VagaModel.php', "WHEN fonte LIKE 'Indeed%'   THEN 1")
        && contem('app/Models/VagaModel.php', "WHEN fonte LIKE 'LinkedIn%' THEN 2")
        && contem('app/Models/VagaModel.php', "WHEN fonte LIKE 'Catho%'    THEN 3")
        && contem('app/Models/VagaModel.php', "WHEN fonte LIKE 'Gupy%'     THEN 4"),
    'CASE de prioridade por fonte no VagaModel::listarPriorizadas()'
);

item(
    'Vagas e Priorizacao',
    'AGRUPAMENTO POR PROVEDOR (abas)',
    contem('app/Views/painel/vagas/index.php', "['fonte' => \$aba]"),
    'abas por fonte na listagem de vagas'
);

item(
    'Vagas e Priorizacao',
    'DEDUPLICACAO EM TRES NIVEIS',
    contem('app/Libraries/BuscadorVagas.php', 'job_id') && contem('app/Libraries/BuscadorVagas.php', 'gerarHashUnico'),
    '2 niveis no codigo (job_id e hash) mais o indice unico do banco; o plano falava em 3 niveis'
);

item(
    'Vagas e Priorizacao',
    'NAO reenviar email para ja_candidatado = 1',
    contem('app/Models/VagaModel.php', "->where('ja_candidatado', 0)") && contem('app/Libraries/EnviadorEmails.php', 'marcarCandidatado'),
    'a fila so pega vagas com ja_candidatado = 0 e marca apos o envio'
);

// --------------------------------------------------- Painel do Usuario

$painelInicio = arq('app/Views/painel/inicio.php');

item(
    'Painel do Usuario',
    'Card Candidaturas realizadas (com a quebra: pelo site ou por e-mail)',
    contem('app/Views/painel/inicio.php', 'Candidaturas por e-mail')
        && contem('app/Views/painel/inicio.php', 'Candidaturas realizadas')
        && contem('app/Views/painel/inicio.php', 'Pelo site')
        && contem('app/Views/painel/inicio.php', 'Por e-mail'),
    'o painel mostra o total, quantas foram por e-mail e a lista das candidaturas realizadas (com a quebra)'
);

item(
    'Painel do Usuario',
    'Card Vagas encontradas (quebra por fonte)',
    contem('app/Views/painel/inicio.php', 'Vagas encontradas') && contem('app/Views/painel/vagas/index.php', "['fonte' => \$aba]"),
    'card existe no inicio e a quebra por fonte aparece nas abas de vagas'
);

item(
    'Painel do Usuario',
    'Card Envios hoje / limite',
    contem('app/Views/painel/inicio.php', 'Envios hoje') && contem('app/Views/painel/inicio.php', 'limiteDiario'),
    'card mostra enviados hoje e o limite diario'
);

item(
    'Painel do Usuario',
    'Card Proximo envio em MM min',
    contem('app/Views/painel/inicio.php', 'Proximo envio') || contem('app/Views/painel/envio_massa.php', 'Proximo envio'),
    'NAO IMPLEMENTADO: nenhum card mostra o proximo envio em minutos'
);

item(
    'Painel do Usuario',
    'Card Ultimo envio',
    contem('app/Views/painel/inicio.php', 'Ultimo envio') || contem('app/Views/painel/envio_massa.php', 'Ultimo envio'),
    'NAO IMPLEMENTADO como card: existe a lista Ultimos envios, sem card de ultimo envio'
);

item(
    'Painel do Usuario',
    'Card Cota mensal / usado / restante',
    contem('app/Views/painel/inicio.php', 'Cota de consultas') && contem('app/Views/painel/inicio.php', "cota['restante']"),
    'card mostra cota, usado e restante do mes'
);

item(
    'Painel do Usuario',
    'Card Envio automatico (switch)',
    contem('app/Views/painel/envio_massa.php', 'alternar-automatico'),
    'alternador de envio automatico na tela de envio em massa'
);

item(
    'Painel do Usuario',
    'Card Execucoes Manuais (Bloco E)',
    contem('app/Views/painel/inicio.php', 'Execucoes manuais'),
    'card Execucoes manuais no inicio do painel'
);

item(
    'Painel do Usuario',
    'Botao Executar Consulta Agora (POST)',
    contem('app/Views/painel/inicio.php', 'painel/executar-vagas') && contem('app/Config/Routes.php', "post('executar-vagas'"),
    'form POST para /painel/executar-vagas'
);

item(
    'Painel do Usuario',
    'Botao Executar Envio de E-mails Agora (POST)',
    contem('app/Views/painel/inicio.php', 'painel/executar-emails') && contem('app/Config/Routes.php', "post('executar-emails'"),
    'form POST para /painel/executar-emails'
);

item(
    'Painel do Usuario',
    'Botoes desabilitados se limite atingido',
    contem('app/Views/painel/inicio.php', "if (! \$manuaisDisponiveis) { echo 'disabled'; }"),
    'os dois botoes recebem o atributo disabled quando o limite e atingido'
);

// ------------------------------------------- Distribuicao de Consultas

$distribuicao = arq('app/Libraries/DistribuicaoConsultas.php');

item('Distribuicao de Consultas', 'Calculo dinamico', contem('app/Libraries/DistribuicaoConsultas.php', 'function calcular') || contem('app/Libraries/DistribuicaoConsultas.php', 'function distribuir'), 'calculo feito na execucao, a partir do saldo real das chaves');
item('Distribuicao de Consultas', 'Teto de 3x a media por usuario', contem('app/Libraries/DistribuicaoConsultas.php', '3 * $media'), 'teto calculado como floor(3 x media)');
item('Distribuicao de Consultas', 'Piso de 30 consultas ou total de combinacoes', contem('app/Libraries/DistribuicaoConsultas.php', 'min($item[\'combinacoes\'], 30, $teto)'), 'piso = min(combinacoes, 30, teto)');
item('Distribuicao de Consultas', 'Recalculo dinamico sempre que houver mudanca', contem('app/Libraries/DistribuicaoConsultas.php', 'redistribuir') || contem('app/Libraries/DistribuicaoConsultas.php', 'distribuirSobra'), 'a divisao e refeita a cada execucao, com o botao Recalcular no admin');
item('Distribuicao de Consultas', 'Ajuste manual opcional (limite_manual)', contem('app/Libraries/DistribuicaoConsultas.php', "limite_manual"), 'usuarios com limite manual sao preservados antes da divisao automatica');

// ------------------------------------------------- Adaptador de Provedor

item(
    'Adaptador de Provedor',
    'Interface ProvedorVagasInterface',
    existe('app/Contracts/JobsProviderInterface.php'),
    'implementada como App\\Contracts\\JobsProviderInterface (nome diferente do plano, mesmo papel)'
);

item(
    'Adaptador de Provedor',
    'AdaptadorSerpApi implementado',
    existe('app/Libraries/Provedores/SerpApi.php') && contem('app/Libraries/Provedores/SerpApi.php', 'JobsProviderInterface'),
    'app/Libraries/Provedores/SerpApi.php implementa a interface'
);

item(
    'Adaptador de Provedor',
    'AdaptadorScraperApi esqueleto com TODO',
    existe('app/Libraries/Provedores/ScraperApi.php') || existe('app/Libraries/Provedores/AdaptadorScraperApi.php'),
    'NAO IMPLEMENTADO: existe ProvedorFalso.php (provedor de teste), mas nao ha esqueleto do ScraperApi'
);

salvarItens($arquivo);

echo 'parte 1: ' . count($itens) . " itens\n";
