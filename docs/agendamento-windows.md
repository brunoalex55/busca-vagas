# Agendamento no Windows (Agendador de Tarefas)

Este documento explica como agendar as tarefas do sistema em um servidor
Windows usando o Agendador de Tarefas.

## Quando usar este modo

Escolha o modo **"Windows (Agendador de Tarefas)"** no painel
`/admin/cron` quando:

- o servidor e Windows (XAMPP, WAMP, IIS, ou uma maquina Windows dedicada);
- o Agendador de Tarefas esta disponivel;
- o PHP esta instalado em disco local (nao em rede).

Em servidores Linux, prefira o [crontab](agendamento-linux.md). Em
hospedagem sem crontab e sem Agendador, use o
[modo manual](agendamento-manual.md).

> O modo escolhido no painel **nao altera o codigo** dos comandos. Ele
> apenas define quem chama o `php spark`. Nos tres modos a execucao e
> exatamente a mesma.

## O que acontece quando o agendador dispara

```
Agendador de Tarefas  ->  scripts\cron-windows.bat vagas
                              |
                              +-> descobre o PHP (PHP_CLI | .env | php.cmd | PATH)
                              +-> cd para a raiz do projeto
                              +-> php spark vagas:consultar
                              +-> grava o resultado em writable\logs\cron-AAAA-MM-DD.log
```

## Passo 1: conferir o PHP

No Prompt de Comando:

```bat
cd C:\caminho\do\projeto
php -v
php spark list
```

O `php` precisa estar disponivel. O script procura o interpretador nesta
ordem:

1. variavel de ambiente `PHP_CLI`;
2. chave `cron.caminhoPhpCli` no arquivo `.env` do projeto:
   ```
   cron.caminhoPhpCli = "C:\php\php.exe"
   ```
   (no `.env` do CodeIgniter, barras invertidas doubled - `C:\\php\\php.exe`);
3. `php.cmd` na raiz do projeto (o wrapper do PHP portatil, quando existe);
4. `php` no PATH do sistema.

Se nenhuma funcionar, o script avisa "PHP nao encontrado" e sai com o
codigo 3, sem executar nada.

## Passo 2: testar manualmente (faca isso antes da tarefa)

```bat
cd C:\caminho\do\projeto

:: Mostrar o que seria executado, sem rodar nada:
scripts\cron-windows.bat vagas --simular
scripts\cron-windows.bat emails --simular

:: Executar de verdade:
scripts\cron-windows.bat vagas
scripts\cron-windows.bat emails
```

O modo `--simular` mostra a raiz do projeto, o interpretador usado, o
comando final e o arquivo de log.

Argumentos aceitos:

| Argumento | O que faz |
|---|---|
| `vagas` | CRON 1: busca de vagas (comando `vagas:consultar`) |
| `emails` | CRON 2: envio automatico (comando `emails:enviar-automaticos`) |
| `8` (opcional) | roda apenas para o usuario #8 |
| `--simular` | so mostra o que seria executado |

## Passo 3: criar a tarefa "Buscar Vagas"

1. Abra o **Agendador de Tarefas** (menu Iniciar, digite "Agendador de
   Tarefas").
2. Clique em **Criar Tarefa Basica**.
3. **Nome:** `CI4 - Buscar Vagas`.
4. **Geral:** marque "Executar estando o usuario conectado ou nao".
5. **Disparador:** "Diariamente" e depois, em "Editar", repita a cada
   **10 minutos**.
6. **Acao:** "Iniciar um programa".
7. **Programa/script:**
   `C:\caminho\do\projeto\scripts\cron-windows.bat`
8. **Adicionar argumentos:** `vagas`
9. **Iniciar em:** `C:\caminho\do\projeto` **(obrigatorio)**
10. Clique em **Concluir**.

O campo "Iniciar em" e obrigatorio: e ele que garante que o comando rode
com a raiz do projeto como pasta de trabalho.

## Passo 4: criar a tarefa "Enviar E-mails"

Repita o passo anterior com:

- **Nome:** `CI4 - Enviar E-mails`
- **Adicionar argumentos:** `emails`
- **Disparador:** repetir a cada **5 minutos**

## Passo 5: configuracoes adicionais

Na aba **Condicoes** de cada tarefa:

- desmarque "Iniciar a tarefa somente se o computador estiver na energia
  eletrica" (em notebook isso impede a execucao);
- em "Se a tarefa estiver em execucao, a seguinte instancia deve ser",
  escolha "Ignorar a nova instancia" (o sistema ja se protege com os
  limites internos).

Na aba **Geral**:

- marque "Executar mesmo que o usuario nao esteja conectado" (com a senha
  da conta configurada nas opcoes).

## Passo 6: conferir o que rodou

```bat
type C:\caminho\do\projeto\writable\logs\cron-AAAA-MM-DD.log
```

O log tem o formato:

```
2026-10-19 08:00:01 | inicio | tarefa=vagas | comando=vagas:consultar | usuario=todos
... (saida completa do comando) ...
2026-10-19 08:00:12 | fim | tarefa=vagas | codigo=0 | duracao=11s
```

O codigo de saida do comando e repassado ao Agendador de Tarefas, que
mostra o resultado em "Historico de tarefas".

## Problemas comuns

| Sintoma | Causa provável | Solucao |
|---|---|---|
| "0x1" no historico da tarefa | o script nao achou o PHP | defina `cron.caminhoPhpCli` no `.env` ou use `php.cmd` na raiz |
| "O sistema nao pode encontrar o arquivo especificado" | caminho do programa ou "Iniciar em" incorreto | revise os dois campos e use caminho absoluto |
| O log nao e criado | o usuario da tarefa nao pode escrever em `writable\logs` | ajuste a permissao da pasta `writable` |
| A tarefa roda mas nada acontece | nenhuma palavra-chave esta vencida | e o comportamento normal: o comando so consulta o que esta na hora |

## Regra de ouro

**Nunca chame o sistema por HTTP** (`curl`, `wget`, `Invoke-WebRequest`).
O agendamento oficial roda o comando Spark no contexto CLI. A unica rota de
escrita publica do sistema e o endpoint externo com token individual do
usuario, usado exclusivamente pelo modo "Execucao Manual" com servico
externo (cron-job.org) - veja
[agendamento-manual.md](agendamento-manual.md).
