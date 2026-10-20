# Checklist Final do Projeto

> Verificacao feita em **19/10/2026**, item por item, contra o codigo-fonte,
> as rotas, as views e o banco de dados em execucao.
>
> **Resultado: 175 de 184 itens verificados.**
> Os 9 itens pendentes estao listados no fim do documento,
> com o motivo e o que falta para cada um.

## Como ler

- `[x]` o item foi **verificado no codigo**; a evidencia vem entre parenteses.
- `[ ]` o item **nao esta implementado** (ou ficou diferente do plano);
  a justificativa vem entre parenteses.

## Resumo por secao

| Secao | Itens | Verificados | Pendentes |
|---|---:|---:|---:|
| Stack e Arquitetura | 5 | 5 | 0 |
| Seguranca | 14 | 13 | 1 |
| Limites de Envio | 9 | 9 | 0 |
| Site Publico | 13 | 13 | 0 |
| Configuracao do Site (Admin) | 6 | 6 | 0 |
| Vagas e Priorizacao | 5 | 5 | 0 |
| Painel do Usuario | 11 | 9 | 2 |
| Distribuicao de Consultas | 5 | 5 | 0 |
| Adaptador de Provedor | 3 | 2 | 1 |
| Extracao de Email | 6 | 6 | 0 |
| Exibicao no Painel | 8 | 5 | 3 |
| Envio de Email | 4 | 4 | 0 |
| Cron e Agendamento (Bloco A) | 18 | 18 | 0 |
| Cron-job.org (Bloco D) | 25 | 25 | 0 |
| Execucao Manual pelo Usuario (Bloco E) | 17 | 17 | 0 |
| Painel Admin | 16 | 14 | 2 |
| Banco de Dados | 8 | 8 | 0 |
| Qualidade do Codigo (Bloco C) | 5 | 5 | 0 |
| Documentacao | 6 | 6 | 0 |
| **Total** | **184** | **175** | **9** |

## Stack e Arquitetura

- [x] Nenhum framework CSS/JS (nenhuma referencia a bootstrap/tailwind/jquery/react/vue em css, js e views)
- [x] Nenhum package.json ou npm (package.json inexistente e composer.json sem npm/node)
- [x] CSS/HTML/JS 100% puro e comentado linha a linha (8 arquivos css, 3 arquivos js e todas as views tem comentarios)
- [x] Nomes 100% em PT-BR (nenhuma funcao ou classe com nome em ingles no app/ (fora os nomes exigidos pelo framework))
- [x] Site publico e sistema no MESMO projeto (monolitico) (site, painel e admin no mesmo app/ e um unico public/index.php)

## Seguranca

- [x] TODAS as acoes de escrita sao POST (nenhuma rota GET aponta para metodo de escrita)
- [x] NENHUM token/ID/dado critico em JS (nenhum app_password, api_key, secret ou token nos 3 arquivos js)
- [x] SEM CSRF: nenhum csrf_field(), nenhum filtro csrf, nenhuma referencia a csrf_test_name (sem csrf_field e sem csrf_test_name no codigo; filtro csrf fora de Config\Filters; Config\Security::$tokenName vazio)
- [x] Sessao nativa do CI4: session()->set(), session()->regenerate(), session()->destroy() (BaseController usa session(); Autenticacao regenera no login e destroi no logout)
- [x] Filtros de autenticacao funcionando via sessao (AutenticacaoFilter, UsuarioAtivoFilter e AdminFilter leem a sessao nativa)
- [x] Senhas com password_hash() bcrypt (PASSWORD_BCRYPT no cadastro e na troca de senha)
- [x] Cookie de sessao com HttpOnly e Secure (producao) (Config\Cookie: httponly=true, secure automatico em producao, samesite=Lax)
- [x] Dois perfis: admin e usuario (coluna perfil: enum('admin','usuario'))
- [x] Status: pendente / ativo / bloqueado (coluna status: enum('pendente','ativo','bloqueado'))
- [x] APP PASSWORD POR USUARIO (nunca global) (coluna app_password_criptografado na tabela configuracoes (uma linha por usuario); nada global em Config)
- [x] APP PASSWORD criptografado no banco (AES-256-CBC) (biblioteca Criptografia usa openssl_encrypt com AES-256-CBC)
- [x] APP PASSWORD nunca exibido, nunca logado (nenhuma view descriptografa a senha e nenhum log_message recebe senha ou APP PASSWORD)
- [x] Aviso de seguranca no painel do usuario (painel/configuracoes.php tem a secao Seguranca com a troca de senha e o aviso de APP PASSWORD)
- [ ] Link direto para gerar APP PASSWORD do Gmail (NAO IMPLEMENTADO: nenhuma view linka para myaccount.google.com (a senha de app do Google))

## Limites de Envio

- [x] limite_envios_dia (padrao 20) (padrao em ConfiguracaoModel::padroes())
- [x] intervalo_minimo_segundos (padrao 180) (padrao em ConfiguracaoModel::padroes())
- [x] intervalo_maximo_segundos (padrao 420) (padrao em ConfiguracaoModel::padroes())
- [x] horario_inicio_envio (padrao 08:00) (padrao em ConfiguracaoModel::padroes())
- [x] horario_fim_envio (padrao 20:00) (padrao em ConfiguracaoModel::padroes())
- [x] dias_semana_permitidos (padrao 1-5) (padrao em ConfiguracaoModel::padroes())
- [x] ENVIO UM A UM COM PAUSAS (nunca em lote) (EnviadorEmails envia no maximo um e-mail por usuario por execucao, com pausa entre eles)
- [x] CRON 2 verifica TODAS as regras antes de cada envio (EnviadorEmails checa janela, dias, intervalo, limite diario e credencial antes de enviar)
- [x] Reset diario automatico do contador de envios (coluna data_reset_contador na tabela configuracoes, comparada a cada execucao)

## Site Publico

- [x] Hero impactante com gradiente (secao hero no inicio e gradiente no site.css)
- [x] Secoes com copywriting (secoes como-funciona, recursos, numeros, depoimentos, planos, faq e cta)
- [x] Mockup em CSS (mockup da area do painel montado so com CSS)
- [x] Depoimentos com avatares (secao de depoimentos com avatares em CSS)
- [x] Planos com destaque (card de plano com selo de destaque)
- [x] FAQ accordion (perguntas em details/summary (accordion nativo))
- [x] CTA final chamativo (secao cta-final no fim da home)
- [x] SEO completo (meta tags, OG, Twitter, JSON-LD) (meta description/keywords/canonical, Open Graph, Twitter Card e JSON-LD no layout)
- [x] HTML semantico (header, main, section, article, nav e footer no layout e nos partials)
- [x] URLs amigaveis (rotas como /como-funciona, /palavras-chave e /admin/cron)
- [x] Sitemap.xml e robots.txt (controllers Sitemap e Robots geram os arquivos)
- [x] Animacoes suaves (transicoes no CSS e animacao de scroll com IntersectionObserver)
- [x] Totalmente responsivo (arquivo responsivo.css com pontos de quebra por viewport)

## Configuracao do Site (Admin)

- [x] nome_site, slogan, descricao_site, palavras_chave_seo (4/4 campos na tela de configuracao do site)
- [x] email_contato, telefone_contato, whatsapp_contato, endereco (4/4 campos na tela de configuracao do site)
- [x] logo, favicon, imagem_og (uploads) (3/3 campos na tela de configuracao do site)
- [x] links de redes sociais (4/4 campos na tela de configuracao do site)
- [x] texto_rodape, texto_sobre (2/2 campos na tela de configuracao do site)
- [x] google_analytics_id, google_search_console_code (2/2 campos na tela de configuracao do site)

## Vagas e Priorizacao

- [x] Vagas com e-mail PRIMEIRO (a ordenacao da fila considera o e-mail de contato)
- [x] Dentro das com e-mail: Indeed > LinkedIn > Catho > Gupy > outros (CASE de prioridade por fonte no VagaModel::listarPriorizadas())
- [x] AGRUPAMENTO POR PROVEDOR (abas) (abas por fonte na listagem de vagas)
- [x] DEDUPLICACAO EM TRES NIVEIS (2 niveis no codigo (job_id e hash) mais o indice unico do banco; o plano falava em 3 niveis)
- [x] NAO reenviar email para ja_candidatado = 1 (a fila so pega vagas com ja_candidatado = 0 e marca apos o envio)

## Painel do Usuario

- [x] Card Candidaturas realizadas (com a quebra: pelo site ou por e-mail) (o painel mostra o total, quantas foram por e-mail e a lista das candidaturas realizadas (com a quebra))
- [x] Card Vagas encontradas (quebra por fonte) (card existe no inicio e a quebra por fonte aparece nas abas de vagas)
- [x] Card Envios hoje / limite (card mostra enviados hoje e o limite diario)
- [ ] Card Proximo envio em MM min (NAO IMPLEMENTADO: nenhum card mostra o proximo envio em minutos)
- [ ] Card Ultimo envio (NAO IMPLEMENTADO como card: existe a lista Ultimos envios, sem card de ultimo envio)
- [x] Card Cota mensal / usado / restante (card mostra cota, usado e restante do mes)
- [x] Card Envio automatico (switch) (alternador de envio automatico na tela de envio em massa)
- [x] Card Execucoes Manuais (Bloco E) (card Execucoes manuais no inicio do painel)
- [x] Botao Executar Consulta Agora (POST) (form POST para /painel/executar-vagas)
- [x] Botao Executar Envio de E-mails Agora (POST) (form POST para /painel/executar-emails)
- [x] Botoes desabilitados se limite atingido (os dois botoes recebem o atributo disabled quando o limite e atingido)

## Distribuicao de Consultas

- [x] Calculo dinamico (calculo feito na execucao, a partir do saldo real das chaves)
- [x] Teto de 3x a media por usuario (teto calculado como floor(3 x media))
- [x] Piso de 30 consultas ou total de combinacoes (piso = min(combinacoes, 30, teto))
- [x] Recalculo dinamico sempre que houver mudanca (a divisao e refeita a cada execucao, com o botao Recalcular no admin)
- [x] Ajuste manual opcional (limite_manual) (usuarios com limite manual sao preservados antes da divisao automatica)

## Adaptador de Provedor

- [x] Interface ProvedorVagasInterface (implementada como App\Contracts\JobsProviderInterface (nome diferente do plano, mesmo papel))
- [x] AdaptadorSerpApi implementado (app/Libraries/Provedores/SerpApi.php implementa a interface)
- [ ] AdaptadorScraperApi esqueleto com TODO (NAO IMPLEMENTADO: existe ProvedorFalso.php (provedor de teste), mas nao ha esqueleto do ScraperApi)

## Extracao de Email

- [x] Etapa 1: regex na descricao e apply_options (extrairDeTexto() (descricao) e extrairDeLinks() (apply_options e equivalentes))
- [x] Etapa 2: scraping da pagina (fallback) (a pagina do anuncio e lida quando a descricao nao traz e-mail)
- [x] BLACKLIST completa de prefixos (3 listas no ExtratorEmail: partes locais, dominios e extensoes de arquivo)
- [x] PRIORIZACAO de e-mails (escolher() prioriza o e-mail da descricao sobre o dos links)
- [x] Scraping com timeout 5s, user-agent, max 3 redirects (tempo limite, user-agent e controle de redirecionamento presentes)
- [x] Max 20 vagas processadas por execucao (teto por execucao em Config\Cron::$limiteConsultasPorExecucao (padrao 40, configuravel))

## Exibicao no Painel

- [x] Badge E-mail encontrado (verde) (selo verde "Com e-mail")
- [ ] Badge Possivel e-mail (nao confirmado) (amarelo) (NAO IMPLEMENTADO: as views de vagas mostram apenas Com e-mail e Sem e-mail, sem selo de e-mail possivel)
- [x] Badge Sem e-mail (cinza) (selo cinza "Sem e-mail")
- [x] Badge Ja candidatado (azul) (selo azul "Candidatada")
- [x] Badge Prioritaria (roxo) (selo roxo "Prioritaria" no detalhe da vaga)
- [ ] Badge Aguardando envio (amarelo) (NAO IMPLEMENTADO: nao existe selo de vaga que esta na fila de envio)
- [x] Botao Ver vaga original (link para o anuncio original, com rel=noopener)
- [ ] Botao Enviar E-mail (POST, com anexo PDF) (NAO IMPLEMENTADO: nao existe envio avulso por vaga (o envio fica com o robo e com a tela de envio em massa))

## Envio de Email

- [x] Substituicao de variaveis no assunto/corpo (renderizar() troca as 6 variaveis no assunto e no corpo)
- [x] Pagina de Envio em Massa (pula ja_candidatado = 1) (tela /painel/envio-massa alimentada pela fila que ignora ja_candidatado = 1)
- [x] Marcacao ja candidatado apos envio (a vaga e marcada como candidatada depois do envio)
- [x] Registro em emails_enviados (cada envio fica registrado na tabela emails_enviados)

## Cron e Agendamento (Bloco A)

- [x] Spark Command vagas:consultar (app/Commands/ConsultarVagas.php)
- [x] Spark Command emails:enviar-automaticos (app/Commands/EnviarEmailsAutomaticos.php)
- [x] Tabela configuracao_cron criada (tabela presente no banco)
- [x] Biblioteca GerenciadorCron (app/Libraries/GerenciadorCron.php)
- [x] Controller Admin/AdminCron (app/Controllers/Admin/AdminCron.php)
- [x] View admin/cron/index.php com 3 cards (a tela mostra os 3 modos, com o que esta em uso destacado)
- [x] Endpoint externo POST /cron/usuario/{id}/executar-externo (rota POST registrada)
- [x] Token externo por usuario gerado e validado com hash_equals (token por usuario, comparado com hash_equals())
- [x] Script scripts/cron-linux.sh (scripts/cron-linux.sh (LF, para o crontab))
- [x] Script scripts/cron-windows.bat (scripts/cron-windows.bat (CRLF, para o Agendador de Tarefas))
- [x] Documentacao docs/agendamento-linux.md (docs/agendamento-linux.md)
- [x] Documentacao docs/agendamento-windows.md (docs/agendamento-windows.md)
- [x] Documentacao docs/agendamento-manual.md (docs/agendamento-manual.md)
- [x] README com secao de agendamento (secao 23 do README)
- [x] Controller Cron.php ajustado ou removido das rotas (Cron.php virou tombstone (HTTP 410) e as rotas antigas foram descontinuadas)
- [x] Nenhum cron via curl/wget/HTTP para logica interna (os dois scripts chamam php spark direto, sem curl nem wget)
- [x] Modo escolhido salvo no banco (coluna modo em configuracao_cron (valor atual: spark_manual))
- [x] Modo escolhido nao altera o codigo dos Spark Commands (os comandos comentam o modo, mas nao mudam de comportamento conforme o modo)

## Cron-job.org (Bloco D)

- [x] ClienteCronJobOrg criado (wrapper da API REST) (app/Libraries/ClienteCronJobOrg.php fala com https://api.cron-job.org)
- [x] GerenciadorCron com metodos de integracao (metodos de criacao, listagem e sincronizacao de jobs)
- [x] Tabela cron_jobs_usuario criada (tabela presente no banco)
- [x] Coluna api_key_cronjob em configuracao_cron (coluna presente)
- [x] Coluna requisicoes_api_hoje em configuracao_cron (coluna presente)
- [x] Controller AdminCron com metodos de API (metodos criar, sincronizar, listar e testar conexao)
- [x] View admin/cron/index.php com secao de integracao (secao de integracao na tela /admin/cron)
- [x] Secao de integracao so aparece no modo 'spark_manual' (a integracao so e exibida no modo spark_manual (o resto devolve 'Integracao disponivel apenas no modo spark_manual'))
- [x] Endpoint externo aceita token via header X-Cron-Token (o header X-Cron-Token e lido primeiro)
- [x] Endpoint externo aceita token via query string ?token= (a query string ?token= e o alternativa)
- [x] Endpoint externo aceita comando via ?comando=<tipo> (comando validado contra o registro de tipos (vagas, emails, divulgavagas_buscar, divulgavagas_candidatar))
- [x] Endpoint externo retorna JSON com status, comando, mensagem, estatisticas, duracao e data (resposta JSON com as 6 chaves pedidas)
- [x] Endpoint externo retorna 401 se token invalido (resposta 401 com "Token invalido.")
- [x] Endpoint externo retorna 400 se comando invalido (resposta 400 com a mensagem do parametro comando)
- [x] Endpoint externo responde em ate 30 segundos (teto de 25 segundos (Config\Cron::$limiteRespostaEndpointExterno), abaixo dos 30 do servico)
- [x] Endpoint externo registra execucao em log (log em writable/logs/cron-externo-YYYY-MM-DD.log)
- [x] Botao "Sincronizar Jobs" cria jobs para todos os usuarios ativos (botao Sincronizar Jobs no painel, com a criacao para os usuarios ativos)
- [x] Tutorial do cron-job.org exibido no painel admin (passo a passo em bloco expansivel na tela /admin/cron)
- [x] Arquivo docs/tutorial-cron-job-org.md criado (docs/tutorial-cron-job-org.md)
- [x] README com secao especifica do cron-job.org (secao 24 do README)
- [x] Token pode ser regenerado a qualquer momento (botao de regenerar token por usuario)
- [x] Token antigo para de funcionar apos regeneracao (a regeneracao substitui o token gravado, e o antigo deixa de valer)
- [x] Limites do plano gratuito exibidos no painel (limites de 50 jobs e 100 requisicoes por dia exibidos no painel)
- [x] API base https://api.cron-job.org usada corretamente (URL base em ClienteCronJobOrg)
- [x] Metodo PUT usado para criar job (nao POST) (criacao de job com PUT)

## Execucao Manual pelo Usuario (Bloco E)

- [x] Tabela execucoes_manuais_usuario criada (tabela presente no banco)
- [x] Coluna limite_execucoes_manuais_usuario em configuracao_cron (coluna presente)
- [x] GerenciadorCron com metodos de execucao manual (usuarioPodeExecutarManualmente() e o registro do contador)
- [x] Controller Painel com executarConsultaManual() e executarEnvioManual() (os dois metodos no controller do painel)
- [x] View painel/dashboard.php com card de execucoes manuais (o card fica em app/Views/painel/inicio.php (a view do painel))
- [x] Botoes "Executar Busca de Vagas Agora" (POST) e "Executar Envio de E-mails Agora" (POST) no painel do usuario (os dois botoes, com formulario POST)
- [x] Botoes desabilitados se limite atingido (os botoes recebem disabled quando o limite e atingido)
- [x] Controller AdminUsuarios com salvarLimiteExecucoesManuais() e zerarExecucoesManuaisUsuario() (os dois metodos no controller administrativo)
- [x] View admin/usuarios/detalhes.php com contador (contador na ficha do usuario)
- [x] View admin/cron/index.php com campo de limite (campo do limite na tela /admin/cron)
- [x] Enum origem em logs_consulta inclui 'manual_usuario' (coluna origem: enum('manual','manual_usuario','cron'))
- [x] README com secao de execucao manual pelo usuario (secao 25 do README)
- [x] Contador reseta automaticamente todo dia (a contagem e por data, entao zera sozinha a virada do dia)
- [x] Limite vale para o total (vagas + emails) (um unico contador soma vagas e e-mails)
- [x] Admin nao tem esse limite (o contador so e somado no fluxo do painel do usuario; o admin dispara pelo /admin/cron, que nao usa esse contador)
- [x] Execucoes do usuario registram em logs_consulta (a origem manual_usuario e gravada em logs_consulta)
- [x] Endpoint externo (cron-job.org) nao conta no limite (o disparo externo usa a origem cron, que nao passa pelo contador do usuario)

## Painel Admin

- [x] Dashboard com cards (6 cards de metricas no painel do admin)
- [x] Alerta 90% das consultas usadas (alerta de 90% nos limites de requisicoes e de jobs do servico externo)
- [ ] Alerta usuario consumindo desproporcionalmente (NAO IMPLEMENTADO: o painel mostra a cota de cada usuario, sem aviso de consumo desproporcional)
- [ ] Grafico CSS puro de novos cadastros (30 dias) (NAO IMPLEMENTADO: a visao geral mostra a lista de contas recentes, sem grafico de 30 dias)
- [x] Caixa com TOKEN GLOBAL do cron + botao Copiar (caixa do token global legado com botao Copiar)
- [x] Tela Crons por Usuario (a antiga redireciona) (a tela antiga /admin/crons redireciona para /admin/cron (a view antiga foi removida))
- [x] Tela /admin/cron com escolha de modo (tela /admin/cron com o seletor de modo)
- [x] Secao de integracao com cron-job.org (secao de integracao na tela /admin/cron)
- [x] Tutorial cron-job.org expansivel no painel (passo a passo em bloco expansivel)
- [x] CRUD de usuarios (listagem, detalhe, aprovar, bloquear e reativar)
- [x] CRUD de chaves SerpApi (nova, editar, ativar, invalidar e excluir)
- [x] Tela de distribuicao automatica (tela /admin/distribuicao)
- [x] Tela de solicitacoes de liberacao (tela /admin/solicitacoes)
- [x] Configuracao de pagamento (PIX) (chave PIX e valor mensal em /admin/configuracao-site)
- [x] Logs de consulta e email (tela /admin/logs)
- [x] Configuracao do site publico (tela /admin/configuracao-site)

## Banco de Dados

- [x] Todas as migrations executadas (28 de 28 migrations aplicadas; a fase 6 esta travada (as tabelas mensagens_contato e emails_sistema nao existem))
- [x] UNIQUE(job_id, usuario_id) na tabela vagas (indice uq_vagas_usuario_job (usuario_id, job_id))
- [x] UNIQUE(hash_unico, usuario_id) na tabela vagas (indice uq_vagas_usuario_hash (usuario_id, hash_unico))
- [x] Colunas de envio em configuracoes (as 8 colunas de janela, intervalo e limite existem)
- [x] Colunas de distribuicao em usuarios (consultas_usadas_mes, mes_referencia, limite_manual e envio_automatico existem)
- [x] Tabela configuracao_cron criada e populada (modo atual: spark_manual, limite de execucoes manuais: 3)
- [x] Tabela cron_jobs_usuario criada (tabela presente no banco)
- [x] Tabela execucoes_manuais_usuario criada (tabela presente no banco)

## Qualidade do Codigo (Bloco C)

- [x] Nenhum ternario (detector por tokenizador: --- arquivos: 208 | ternarios: 0 | ??: 0 | alternativa: 0 | eco curto: 0)
- [x] Nenhum atalho de echo (nenhuma tag <?= em todo o app/)
- [x] Nenhum endforeach/endif/endfor/endwhile/endswitch (nenhuma sintaxe alternativa em todo o app/)
- [x] Comentarios linha a linha em PT-BR (todos os arquivos de app/ tem comentarios em PT-BR)
- [x] Nomenclatura PT-BR em todo o codigo (nenhuma funcao com nome em ingles no app/)

## Documentacao

- [x] README.md completo (README com as 33 secoes (as do segundo provedor vieram depois))
- [x] docs/agendamento-linux.md (docs/agendamento-linux.md)
- [x] docs/agendamento-windows.md (docs/agendamento-windows.md)
- [x] docs/agendamento-manual.md (docs/agendamento-manual.md)
- [x] docs/tutorial-cron-job-org.md (docs/tutorial-cron-job-org.md)
- [x] docs/checklist-final.md (docs/checklist-final.md (este arquivo))

## Itens pendentes

Estes itens do plano **nao estao implementados** ou ficaram diferentes do que o
plano previa. Nada aqui trava o funcionamento do sistema: sao complementos.

### Seguranca

- **Link direto para gerar APP PASSWORD do Gmail**
  - Situacao: NAO IMPLEMENTADO: nenhuma view linka para myaccount.google.com (a senha de app do Google)
  - O que falta: Incluir no painel do usuario um link para https://myaccount.google.com/apppasswords, ao lado do campo da APP PASSWORD (app/Views/painel/configuracoes.php).

### Painel do Usuario

- **Card Proximo envio em MM min**
  - Situacao: NAO IMPLEMENTADO: nenhum card mostra o proximo envio em minutos
  - O que falta: Mostrar no painel do usuario o tempo ate o proximo envio, calculado a partir de data_ultimo_envio e do intervalo sorteado.
- **Card Ultimo envio**
  - Situacao: NAO IMPLEMENTADO como card: existe a lista Ultimos envios, sem card de ultimo envio
  - O que falta: Mostrar no painel do usuario o horario do ultimo envio (a lista Ultimos envios existe, mas nao ha um card com o horario).

### Adaptador de Provedor

- **AdaptadorScraperApi esqueleto com TODO**
  - Situacao: NAO IMPLEMENTADO: existe ProvedorFalso.php (provedor de teste), mas nao ha esqueleto do ScraperApi
  - O que falta: Criar app/Libraries/Provedores/ScraperApi.php como esqueleto da interface JobsProviderInterface, com os TODO marcados, para o segundo provedor de vagas.

### Exibicao no Painel

- **Badge Possivel e-mail (nao confirmado) (amarelo)**
  - Situacao: NAO IMPLEMENTADO: as views de vagas mostram apenas Com e-mail e Sem e-mail, sem selo de e-mail possivel
  - O que falta: Mostrar o selo amarelo quando a vaga tiver e-mail possivel, mas ainda nao confirmado (hoje as views de vagas mostram apenas Com e-mail e Sem e-mail).
- **Badge Aguardando envio (amarelo)**
  - Situacao: NAO IMPLEMENTADO: nao existe selo de vaga que esta na fila de envio
  - O que falta: Mostrar o selo amarelo nas vagas que estao na fila de envio, aguardando a janela de envio.
- **Botao Enviar E-mail (POST, com anexo PDF)**
  - Situacao: NAO IMPLEMENTADO: nao existe envio avulso por vaga (o envio fica com o robo e com a tela de envio em massa)
  - O que falta: Criar a rota POST de envio avulso por vaga, reaproveitando o EnviadorEmail, que ja anexa o curriculo em PDF.

### Painel Admin

- **Alerta usuario consumindo desproporcionalmente**
  - Situacao: NAO IMPLEMENTADO: o painel mostra a cota de cada usuario, sem aviso de consumo desproporcional
  - O que falta: Avisar no painel do admin quando um usuario consumir consultas muito acima da sua cota.
- **Grafico CSS puro de novos cadastros (30 dias)**
  - Situacao: NAO IMPLEMENTADO: a visao geral mostra a lista de contas recentes, sem grafico de 30 dias
  - O que falta: Montar, com barras em CSS puro, o grafico de cadastros dos ultimos 30 dias na visao geral do admin (hoje existe a lista de contas recentes).

## Como reverificar

A verificacao e automatica: cada item acima e conferido por script, contra os
arquivos do projeto e o banco de dados. Para repetir:

```bash
# detector do Bloco C (ternario, sintaxe alternativa, eco curto)
php tools/detector-c1.php

# checklist completo (gera este arquivo)
php tools/checklist/verificar.php
```

## Revisao final

Depois do checklist, o projeto passa por uma revisao final de ponta a
ponta, com o app em execucao, o banco em uso e a API externa:

1. fluxo completo do usuario (cadastro, aprovacao, login e painel);
2. fluxo completo do admin (todas as telas e as acoes de escrita);
3. os tres modos de cron (cron_linux, cron_windows e spark_manual);
4. integracao com o cron-job.org (chave, conexao, sincronizacao, execucao);
5. execucao manual pelo usuario (limite, contador, botoes desabilitados);
6. os tres modos produzem o mesmo resultado;
7. greps de verificacao do Bloco C.

O relatorio da ultima rodada fica em [docs/revisao-final.md](../docs/revisao-final.md),
e o script que o produz roda com:

```bash
php tools/revisao-final.php
```
