<!--
Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.
-->

# Agendamento no Linux (crontab)

Este documento explica como agendar as tarefas do sistema em um servidor
Linux (VPS, cloud, servidor proprio) usando o crontab.

## Quando usar este modo

Escolha o modo **"Linux (crontab)"** no painel `/admin/cron` quando:

- o servidor e Linux e voce tem acesso a SSH;
- existe crontab disponivel para o usuario do servico;
- o PHP CLI esta instalado no servidor.

Nao use este modo em hospedagem compartilhada sem acesso a crontab: nesse
caso use o modo **"Execucao Manual (Spark Commands)"**
(veja [agendamento-manual.md](agendamento-manual.md)).

> O modo escolhido no painel **nao altera o codigo** dos comandos. Ele
> apenas define quem chama o `php spark`. Nos tres modos a execucao e
> exatamente a mesma.

## O que acontece quando o agendador dispara

```
crontab  ->  scripts/cron-linux.sh vagas
                  |
                  +-> descobre o PHP (PHP_CLI | .env | PATH)
                  +-> cd para a raiz do projeto
                  +-> php spark vagas:consultar
                  +-> grava o resultado em writable/logs/cron-AAAA-MM-DD.log
```

O script **nao** decide o que roda: ele apenas chama os comandos Spark, que
ja respeitam as regras do sistema (cota do usuario, limite de consultas por
execucao, pausa entre consultas, intervalo de envio automatico).

## Passo 1: enviar o projeto e conferir o PHP

```bash
cd /caminho/do/projeto
php -v
php spark list
```

O comando `php spark list` deve mostrar os dois comandos do grupo `Cron`:

```
Cron
  emails:enviar-automaticos
  vagas:consultar
```

Se `php` nao estiver no PATH, o script ainda funciona se voce indicar o
caminho de uma destas formas:

1. variavel de ambiente `PHP_CLI` (util no crontab);
2. chave `cron.caminhoPhpCli` no arquivo `.env` do projeto:
   ```
   cron.caminhoPhpCli = /usr/bin/php
   ```
3. por ultimo, o `php` do PATH do sistema.

O script tenta nessa ordem e avisa com "PHP nao encontrado" se nenhuma
funcionar.

## Passo 2: permissao de execucao

```bash
chmod +x scripts/cron-linux.sh
```

Sem isso, o crontab devolve "Permission denied". Se preferir nao dar
permissao, chame o script pelo interpretador na linha do crontab:

```
*/10 * * * * bash /caminho/do/projeto/scripts/cron-linux.sh vagas
```

## Passo 3: testar manualmente (faca isso antes do crontab)

```bash
cd /caminho/do/projeto

# Mostrar o que seria executado, sem rodar nada:
./scripts/cron-linux.sh vagas --simular
./scripts/cron-linux.sh emails --simular

# Executar de verdade:
./scripts/cron-linux.sh vagas
./scripts/cron-linux.sh emails
```

O modo `--simular` mostra a raiz do projeto, o interpretador usado, o
comando final e o arquivo de log. E o jeito mais seguro de conferir o
caminho do PHP antes de agendar.

Argumentos aceitos:

| Argumento | O que faz |
|---|---|
| `vagas` | CRON 1: busca de vagas (comando `vagas:consultar`) |
| `emails` | CRON 2: envio automatico (comando `emails:enviar-automaticos`) |
| `8` (opcional) | roda apenas para o usuario #8 |
| `--simular` | so mostra o que seria executado |

## Passo 4: configurar o crontab

```bash
crontab -e
```

Cole as linhas abaixo, troque `/caminho/do/projeto` pelo caminho real:

```cron
# Buscar vagas a cada 10 minutos
*/10 * * * * /caminho/do/projeto/scripts/cron-linux.sh vagas >> /dev/null 2>&1

# Enviar e-mails automaticos a cada 5 minutos
*/5 * * * * /caminho/do/projeto/scripts/cron-linux.sh emails >> /dev/null 2>&1
```

Sobre o `>> /dev/null 2>&1`:

- **com** ele, nada chega ao seu e-mail de crontab e o script segue usando o
  proprio arquivo de log;
- **sem** ele, a saida do comando vai para o e-mail do dono do crontab
  (util para acompanhar, ruidoso para producao).

Frequencias sugeridas: a busca de vagas a cada 10 minutos e o envio de
e-mails a cada 5 minutos. O sistema ja tem limites internos por execucao,
entao rodar mais vezes nao acumula consumo alem do configurado.

## Passo 5: conferir o que rodou

```bash
tail -f /caminho/do/projeto/writable/logs/cron-AAAA-MM-DD.log
```

O log tem o formato:

```
2026-10-19 08:00:01 | inicio | tarefa=vagas | comando=vagas:consultar | usuario=todos
... (saida completa do comando) ...
2026-10-19 08:00:12 | fim | tarefa=vagas | codigo=0 | duracao=11s
```

- `codigo=0` significa que o comando terminou bem;
- o codigo de saida do comando e repassado ao agendador;
- a saida do comando tambem vai para a tela (util no teste manual).

## Problemas comuns

| Sintoma | Causa probable | Solucao |
|---|---|---|
| `Permission denied` | falta permissao de execucao | `chmod +x scripts/cron-linux.sh` |
| `PHP nao encontrado` | o crontab tem um PATH reduzido | defina `PHP_CLI` no crontab ou `cron.caminhoPhpCli` no `.env` |
| `Could not open input file: spark` | o `cd` para a raiz do projeto nao aconteceu | o script ja faz isso; confirme que `scripts/cron-linux.sh` esta no lugar certo |
| O log nao e criado | o usuario do crontab nao pode escrever em `writable/logs` | ajuste a permissao da pasta `writable` |
| Nada acontece no horario | crontab do fuso horario diferente | o crontab usa o fuso do servidor; confira com `date` |

## Regra de ouro

**Nunca chame o sistema por HTTP** (`curl`, `wget`). O agendamento oficial
roda o comando Spark no contexto CLI. A unica rota de escrita publica do
sistema e o endpoint externo com token individual do usuario, usado
exclusivamente pelo modo "Execucao Manual" com servico externo
(cron-job.org) - veja [agendamento-manual.md](agendamento-manual.md).
