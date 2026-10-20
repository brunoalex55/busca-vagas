# Automacoes RPA (automacao de navegador)

Esta pasta tem as automacoes que usam o navegador para trabalhar nos
portais de vaga. Cada portal e um **provedor** e tem a sua propria pasta.

O Chrome headless e um **servico unico e compartilhado**: todos os bots
se conectam nele pela porta CDP (`http://127.0.0.1:9222`). Nenhum bot
abre nem fecha o navegador; cada chamada abre **uma aba nova** e fecha
**so** essa aba.

```
automacoes-rpa/
  README.md ................ este arquivo
  config.ini ............... configuracao do Chrome (compartilhada)
  chrome_service.py ........ o servico do Chrome (abre, fecha, PID)
  chrome_service.sh ........ start/stop/restart no Linux
  chrome_service.ps1 ....... start/stop/restart no Windows
  check_chrome.sh .......... confere e reinicia (cron do Linux)
  check_chrome.ps1 ......... confere e reinicia (Agendador do Windows)
  requirements.txt ......... dependencias do Python
  divulgavagas/ ............ o bot do Divulga Vagas
    bot.py ................. os dois modos (buscar e candidatar)
    config.ini ............. tempos limites e seletores deste bot
    processar_fila.sh ...... dispara o comando do CI4 (Linux)
    processar_fila.ps1 ..... dispara o comando do CI4 (Windows)
```

O que cada bot faz fica no nome dos comandos do CI4:

| Comando | O que faz |
|---|---|
| `php spark divulgavagas:buscar-vagas` | **CRON 1**: busca vagas no site e salva na tabela `vagas` (com a mesma deduplicacao do SerpApi). Nunca candidata. |
| `php spark divulgavagas:candidatar` | **CRON 2**: candidata a UMA vaga pelo formulario do site e, se a vaga tiver e-mail de contacto, tambem envia o e-mail. Nunca busca vagas. |

## O Chrome do servico NAO e o seu Chrome

O servico lanca o Chrome com um **perfil proprio**, em
`writable/automacoes-rpa/chrome/sessions`. O Chrome que voce esta
usando na mao nao e tocado em momento algum: o servico nunca mata
"processos do Chrome" de forma geral, e sim **apenas o processo cujo
PID esta no arquivo de controle** (`writable/automacoes-rpa/chrome/chrome_service.pid`).

Se voce quiser o Chrome do servico com janela (util na primeira vez,
quando o site pede um desafio do Cloudflare), mude no `config.ini`:

```ini
headless = 0
```

## Por que o servico lanca o Chrome como processo

O Playwright e dono de um navegador: enquanto uma conexao dele esta
aberta, outro cliente que se conecta pelo mesmo CDP disputa o
navegador, e um dos dois trava. Como aqui varios bots entram e saem o
todo tempo (cada chamada abre e fecha uma aba), o servico NAO usa o
Playwright: ele lanca o Chrome e cuida do processo. Assim:

- o Chrome e um servico unico e persistente (perfil fixo, CDP fixo);
- cada bot abre UMA aba nova e fecha SO essa aba;
- nenhum bot fecha o Chrome;
- o servico nunca encerra o Chrome que voce esta usando.

## Onde o trabalho fica (fora do versionamento)

```
writable/automacoes-rpa/
  chrome/ .................... perfil do Chrome, logs e o arquivo de PID
  divulgavagas/
    logs/ ................... log de cada execucao do bot
    curriculos/ ............. os curriculos usados nos envios
    usuarios/<id>/ .......... listas de vagas de interesse e processadas
```

## Como subir o Chrome

Linux (crontab):

```bash
# sobe junto com o boot
@reboot /caminho/do/projeto/automacoes-rpa/chrome_service.sh start

# confere a cada minuto (recupera o servico se ele morrer)
* * * * * /caminho/do/projeto/automacoes-rpa/check_chrome.sh
```

Windows (Agendador de Tarefas):

- **Tarefa 1 - Servico do Chrome** (ao logon):
  `powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\chrome_service.ps1" start`
- **Tarefa 2 - Verificacao do Chrome** (a cada 1 minuto):
  `powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\check_chrome.ps1"`

## Como agendar as tarefas do bot

Linux (crontab):

```bash
# CRON 1 do Divulga Vagas (busca) - a cada 30 min
*/30 * * * * /caminho/do/projeto/automacoes-rpa/divulgavagas/processar_fila.sh buscar-vagas

# CRON 2 do Divulga Vagas (candidatura) - a cada 5 min
*/5 * * * * /caminho/do/projeto/automacoes-rpa/divulgavagas/processar_fila.sh candidatar
```

Windows (Agendador de Tarefas):

- **CRON 1 do Divulga Vagas** (a cada 30 min):
  `powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\divulgavagas\processar_fila.ps1" -modo buscar-vagas`
- **CRON 2 do Divulga Vagas** (a cada 5 min):
  `powershell.exe -ExecutionPolicy Bypass -File "C:\sistema\automacoes-rpa\divulgavagas\processar_fila.ps1" -modo candidatar`

## Execucao manual (pelo painel)

O bot tambem pode ser disparado na mao, sem esperar o agendador:

- **pelo usuario**: `/painel/divulgavagas` (menu "Divulga Vagas") — o
  usuario busca vagas ou se candidata nas vagas pendentes dele;
- **pelo administrador**: `/admin/divulgavagas` (menu "Divulga Vagas") —
  o alvo pode ser "todos os usuarios" ou um usuario so.

Nos dois casos a execucao manual passa pelos mesmos limites do CRON
(limite do bot, limite de e-mail e limite de execucoes do dia) e fica
registrada na tabela `execucoes_divulgavagas`.

Duas coisas importantes:

1. **A execucao roda em segundo plano** (o bot leva minutos): o painel
   responde na hora e o resultado aparece no historico.
2. **A candidatura esvazia a fila inteira**: o bot se candidata a uma
   vaga de cada vez, ate a fila do usuario acabar (com uma pausa
   curta entre elas). Nao e preciso clicar de novo para a proxima.

## A prova de que candidatou

O bot so confirma a candidatura quando chega na pagina de sucesso do
site. A confirmacao (a data e a URL da pagina) fica gravada na vaga e
aparece no detalhe da vaga e na lista "Candidaturas realizadas" dos
paineis (do usuario e do admin).

## Como adicionar um novo portal (Gupy, LinkedIn, ...)

1. Crie a pasta do provedor: `automacoes-rpa/gupy/`.
2. Copie o `bot.py` do Divulga Vagas e ajuste os seletores e o fluxo.
3. Copie o `config.ini` do provedor e ajuste tempos e seletores.
4. Copie o `processar_fila.sh` e o `processar_fila.ps1`, trocando o nome do
   comando Spark.
5. Crie o comando do CI4 (em `app/Commands/`) e o `Provedor` que chama o
   bot.

O Chrome continua sendo um so: nenhum portal precisa abrir o seu.

## O Cloudflare

Alguns portais (como o Divulga Vagas) desafiam o navegador headless. O
perfil persistente guarda o cookie que libera o acesso, entao a primeira
navegacao pode ser mais lenta. Se o bot responder
`cloudflare_block`, a saida e o que o site mostrou: **o bot nao tenta
contornar o bloqueio**. Para resolver, abra uma vez o Chrome com janela
visivel (`headless = 0` no `config.ini`) e acesse o site: o perfil fica
pronto para as chamadas seguintes.
