# Execucao Manual (Spark Commands)

Este documento explica o terceiro modo de agendamento: quando **nao ha
agendador do sistema** (crontab, Agendador de Tarefas) e a tarefa e
disparada por pessoa ou por um servico externo.

## Quando usar este modo

Escolha o modo **"Execucao Manual (Spark Commands)"** no painel
`/admin/cron` quando:

- o servidor nao permite crontab nem Agendador de Tarefas (hospedagem
  compartilhada, por exemplo);
- voce quer disparar a tarefa na hora, pelo painel;
- voce usa um servico externo (cron-job.org) que chama o sistema por
  HTTP.

> O modo escolhido no painel **nao altera o codigo** dos comandos. Nos
> tres modos a execucao e exatamente a mesma: `php spark vagas:consultar`
> e `php spark emails:enviar-automaticos`.

## Os comandos (iguais nos tres modos)

```
php spark vagas:consultar              # CRON 1: busca de vagas
php spark emails:enviar-automaticos     # CRON 2: envio automatico
```

Opcoes uteis:

| Opcao | Efeito |
|---|---|
| `--usuario=8` | roda apenas para o usuario #8 |
| `--limite=5` | teto de consultas (ou envios) nesta execucao |
| `--origem=manual_usuario` | rotulo gravado em `logs_consulta` |

## Opcao A: disparo pelo painel (administrador)

1. Acesse `/admin/cron`.
2. Selecione o modo **"Execucao Manual (Spark Commands)"** e salve.
3. Use os botoes **"Executar busca de vagas agora"** e
   **"Executar envio de e-mails agora"**.
4. Cada clique dispara o comando imediatamente e mostra o resultado na
   tela.

O administrador **nao tem limite** de execucoes manuais.

O painel tambem mostra, no modo manual, a integracao com o servico
externo (chave de API, criacao de jobs por usuario e sincronizacao).

## Opcao B: servico externo (cron-job.org)

Usado quando o servidor nao tem crontab: o cron-job.org chama o
endpoint publico do sistema em intervalos programados.

- Tutorial completo: [tutorial-cron-job-org.md](tutorial-cron-job-org.md).
- A integracao so aparece no painel quando o modo "Execucao Manual" esta
  selecionado.

Resumo do fluxo:

```
cron-job.org  ->  POST https://seudominio.com/cron/usuario/8/executar-externo
                     header: X-Cron-Token: <token individual do usuario>
                     ?comando=vagas   (ou comando=emails)
```

Pontos importantes:

- o endpoint externo e a **unica** rota de escrita publica do sistema;
- o token e **individual por usuario** e pode ser regenerado a qualquer
  momento pelo admin;
- token invalido devolve **401** e comando invalido devolve **400**;
- as chamadas do servico externo **nao contam** no limite diario de
  execucoes manuais do usuario;
- toda chamada externa e registrada em
  `writable/logs/cron-externo-AAAA-MM-DD.log` (metodo, usuario, comando,
  status, duracao e IP - **nunca** o token).

## Opcao C: disparo pelo usuario (com limite diario)

1. O usuario acessa `/painel`.
2. No card **"Execucoes manuais"** ele ve quantas execucoes ainda tem
   hoje ("Hoje: X / Y", "Restam: Z") e uma barra de progresso.
3. Os botoes **"Executar busca de vagas agora"** e **"Executar envio de
   e-mails agora"** disparam a execucao (sempre por POST).
4. Cada execucao consome **1** do limite diario.
5. Quando o limite acaba, os botoes ficam desabilitados e o card avisa
   que o usuario atingiu o limite.

Regras do limite:

- padrao de **3 execucoes por dia por usuario** (vagas e e-mails somados);
- o admin define o valor em `/admin/usuarios` (ficha do usuario) ou em
  `/admin/cron`; o valor **0 desabilita** as execucoes manuais;
- o limite e **zerado** todo dia (contagem por data);
- o administrador e **isento** do limite;
- a tentativa conta no limite **mesmo quando o comando falha**, para
  impedir disparos repetidos em caso de erro;
- o usuario pode ter o contador zerado pelo admin ("Zerar contador").

O contador vive na tabela `execucoes_manuais_usuario` e cada execucao
tambem fica registrada em `logs_consulta` com a origem `manual_usuario`.

## Seguranca

- O token externo e unico por usuario e pode ser regenerado a qualquer
  momento (`/admin/cron`, lista de usuarios).
- Toda execucao externa e registrada na tabela `cron_jobs_usuario` e no
  log diario `cron-externo-AAAA-MM-DD.log`.
- O endpoint externo devolve 401 se o token for invalido.
- As execucoes manuais do usuario sao registradas em
  `execucoes_manuais_usuario` e em `logs_consulta`.
- Nenhuma chave (SerpApi, cron-job.org, APP PASSWORD) aparece no log nem
  no navegador: as chaves de API sao mascaradas na tela.

## Modo de integracao (Bloco D)

A integracao com o cron-job.org e **opcional** e so vale no modo
"Execucao Manual":

- o admin informa a chave da API do cron-job.org em `/admin/cron`
  (a chave e mascarada na tela e nunca vai para o log);
- o botao "Sincronizar Jobs" cria ou atualiza um job por usuario
  apontando para o endpoint externo com o token individual;
- o sistema respeita o teto de 100 requisicoes por dia da API.

## Como testar antes de deixar no automatico

```bash
cd /caminho/do/projeto

# 1. conferir o que sera executado
./scripts/cron-linux.sh vagas --simular      # Linux
scripts\cron-windows.bat vagas --simular     # Windows

# 2. executar de verdade
./scripts/cron-linux.sh vagas
scripts\cron-windows.bat vagas

# 3. conferir o log
cat writable/logs/cron-AAAA-MM-DD.log
```

## Documentos relacionados

- [agendamento-linux.md](agendamento-linux.md) - crontab.
- [agendamento-windows.md](agendamento-windows.md) - Agendador de Tarefas.
- [tutorial-cron-job-org.md](tutorial-cron-job-org.md) - integracao com o
  servico externo.
