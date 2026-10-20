# Tutorial: integracao com a API do cron-job.org (por usuario)

## O que e o cron-job.org

O cron-job.org e um servico gratuito que faz **requisicoes HTTP
periodicas** para uma URL. Ele e util quando o servidor nao permite
crontab (hospedagem compartilhada) ou quando voce quer um agendamento
externo, independente do sistema operacional.

O servico nao entra no seu servidor: ele fica la fora e chama a sua URL
de tempos em tempos. Por isso a URL precisa ser **publica** e a chamada
precisa ser **autenticada** - no nosso caso, por um token.

## Como o sistema integra

O sistema integra com a **API REST do cron-job.org** para criar
automaticamente **dois cron jobs para cada usuario ativo**, mas
**somente quando o modo `spark_manual` esta selecionado** no painel
`/admin/cron`:

1. `CI4 - Usuario X - Buscar Vagas` (a cada 10 minutos);
2. `CI4 - Usuario X - Enviar E-mails` (a cada 5 minutos).

Cada job chama o endpoint externo do sistema:

```
POST /cron/usuario/{id}/executar-externo?comando=vagas
POST /cron/usuario/{id}/executar-externo?comando=emails
```

com o **token do usuario** no header:

```
X-Cron-Token: <token individual do usuario>
```

Esse endpoint e a **unica rota de escrita publica** do sistema. Tudo o
mais roda por linha de comando (`php spark ...`).

## Pre-requisitos

- Conta no cron-job.org (a gratuita basta para comecar);
- Chave de API do cron-job.org (obtida em Settings > API);
- URL publica do seu sistema (ex.: `https://seudominio.com`);
- Modo `spark_manual` selecionado no painel `/admin/cron`.

## API base

```
https://api.cron-job.org
```

## Endpoints oficiais usados

| Metodo | Endpoint | Para que serve |
|---|---|---|
| `PUT` | `/jobs` | cria um job |
| `GET` | `/jobs` | lista os jobs da conta |
| `GET` | `/jobs/{jobId}` | obtem um job |
| `PATCH` | `/jobs/{jobId}` | atualiza um job |
| `DELETE` | `/jobs/{jobId}` | exclui um job |
| `GET` | `/jobs/{jobId}/history` | historico de execucoes |

## Autenticacao

Header `Authorization: Bearer SUA_API_KEY`.

A chave pertence a **conta do SaaS** (nao a cada usuario): e a mesma
para todos os jobs criados pelo sistema. Por isso ela nunca aparece
mascarada na tela, nunca vai para o log e nunca deve ser compartilhada.

## Passo a passo

### 1. Criar conta no cron-job.org

1. Acesse <https://cron-job.org>.
2. Clique em **Sign up**.
3. Preencha nome, e-mail e senha e aceite os termos de uso.
4. Clique em **Create account**.
5. Verifique o e-mail e clique no link de ativacao.

### 2. Obter a chave de API

1. Faca login no cron-job.org.
2. Acesse **Settings**.
3. Clique em **API**.
4. Copie a chave exibida.
5. Guarde-a em local seguro (e nao a versione em um repositorio).

### 3. Configurar no sistema

1. Acesse `/admin/cron` (menu **Agendamento**).
2. Selecione o modo **"Execucao Manual (Spark Commands)"** e salve.
3. No campo **"Chave de API do cron-job.org"**, cole a chave.
4. Clique em **Salvar chave**.
5. Clique em **Testar conexao** para confirmar que a chave funciona.

A chave fica gravada no banco, aparece **mascarada** na tela e nunca vai
para os logs. Deixe o campo em branco para remover a chave atual.

### 4. Sincronizar os jobs

1. Clique em **Sincronizar jobs**.
2. O sistema cria (ou atualiza) dois jobs para cada usuario ativo.
3. Confira a tabela **"Tokens e jobs por usuario"**, mais abaixo na
   mesma tela: ela mostra usuario, tipo, id do job no servico, status e
   ultima execucao.

O que o sistema envia ao servico (payload do `PUT /jobs`):

```json
{
  "url": "https://seudominio.com/cron/usuario/8/executar-externo?comando=vagas",
  "enabled": true,
  "title": "CI4 - Usuario 8 - Buscar Vagas",
  "saveResponses": true,
  "schedule": {
    "timezone": "America/Sao_Paulo",
    "expiresAt": 0,
    "hours": [-1],
    "mdays": [-1],
    "minutes": [0, 10, 20, 30, 40, 50],
    "months": [-1],
    "wdays": [-1]
  },
  "requestMethod": 1,
  "extendedData": {
    "headers": { "X-Cron-Token": "<token do usuario>" }
  }
}
```

- `requestMethod: 1` significa **POST** (o endpoint so aceita POST);
- o fuso e o do sistema (`America/Sao_Paulo`);
- o token vai no header `X-Cron-Token`, nunca na URL.

As frequencias usadas pelo sistema (campo `schedule.minutes`):

| Job | Frequencia | Campo `minutes` |
|---|---|---|
| `CI4 - Usuario X - Buscar Vagas` | a cada 10 minutos | `[0, 10, 20, 30, 40, 50]` |
| `CI4 - Usuario X - Enviar E-mails` | a cada 5 minutos | `[0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]` |

### 5. Verificar

1. No painel do cron-job.org, acesse **Jobs**.
2. Confirme que os jobs foram criados.
3. Acesse **History** para ver as execucoes.

## Resposta do endpoint

O endpoint externo responde sempre em JSON, no mesmo formato:

```json
{
  "status": "sucesso",
  "comando": "vagas",
  "mensagem": "Comando executado com sucesso.",
  "estatisticas": {
    "usuario_id": 8,
    "codigo_saida": 0,
    "saida": "..."
  },
  "duracao_segundos": 0.47,
  "executado_em": "2026-10-19 10:35:34"
}
```

| Situacao | Codigo HTTP |
|---|---|
| Token ausente ou invalido | `401` |
| Comando ausente ou invalido | `400` |
| Usuario inexistente | `404` |
| Comando concluido com sucesso | `200` |
| Comando terminou com erro | `500` |
| Comando ainda em execucao (passou de 25s) | `202` |

O servico externo abandona a requisicao depois de **30 segundos**. Por
isso o sistema responde em no maximo **25**: se o comando nao terminar
nesse prazo, a resposta e `202` e ele **continua rodando em segundo
plano**, com o resultado gravado no log do sistema.

## Seguranca

- A **chave de API do cron-job.org e unica para a conta do SaaS**. Nao
  compartilhe e nao versione.
- O **token do usuario e unico** e pode ser regenerado a qualquer momento
  em `/admin/cron` (botao "Regenerar token" na linha do usuario).
  Regenerar invalida o token antigo: os jobs precisam ser atualizados no
  cron-job.org.
- Se o token vazar, **gere um novo** e atualize o job no servico.
- O endpoint externo devolve `401` quando o token nao bate.
- As chamadas externas ficam registradas em
  `writable/logs/cron-externo-YYYY-MM-DD.log` (metodo, usuario, comando,
  status, resultado, duracao e IP). **O token nunca entra no log.**
- A saida dos comandos fica em
  `writable/logs/cron-externo-saida-YYYY-MM-DD.log`.
- As execucoes do servico externo **nao contam** no limite diario de
  execucoes manuais do usuario.

## Limitacoes do plano gratuito do cron-job.org

- ate **50 cron jobs** por conta;
- ate **100 requisicoes de API por dia**;
- criacao limitada a **1 requisicao por segundo** e **5 por minuto**;
- **timeout de 30 segundos** por requisicao;
- historico de execucoes limitado;
- notificacoes por e-mail em caso de falha.

O sistema tambem se protege: a sincronizacao **para antes de estourar**
o teto de 100 requisicoes/dia da API, e o painel mostra o uso
("Requisicoes de API por dia", "Jobs na conta") com aviso quando passa
de 90%.

## Alternativas ao cron-job.org

- **EasyCron** (<https://www.easycron.com>) - gratuito, com limites;
- **SetCronJob** (<https://www.setcronjob.com>) - gratuito, com limites;
- **UptimeRobot** (<https://uptimerobot.com>) - e monitoramento, nao
  agendamento.

## Quando NAO usar o cron-job.org

Se o numero de usuarios for grande (mais de 20), o plano gratuito pode
nao ser suficiente: sao 2 jobs por usuario, e o limite de 50 jobs por
conta e atingido rapido. Nesse caso:

- use o modo **`cron_linux`** (crontab) ou **`cron_windows`**
  (Agendador de Tarefas) no proprio servidor - veja
  [agendamento-linux.md](agendamento-linux.md) e
  [agendamento-windows.md](agendamento-windows.md); ou
- considere o **plano pago** do cron-job.org.

## Documentos relacionados

- [agendamento-manual.md](agendamento-manual.md) - os tres modos de
  agendamento.
- [agendamento-linux.md](agendamento-linux.md) - crontab.
- [agendamento-windows.md](agendamento-windows.md) - Agendador de
  Tarefas.
