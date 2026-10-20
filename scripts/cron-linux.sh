#!/usr/bin/env bash
# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

#
# ---------------------------------------------------------------------------
# AGENDAMENTO DE TAREFAS (LINUX)
# ---------------------------------------------------------------------------
#
# Executa uma tarefa do CI_EMPLOYER pela linha de comando, sem depender de
# URL. Este e o caminho oficial de agendamento em servidores Linux (CRON 1 e
# CRON 2).
#
# O script NAO decide o que roda: ele apenas chama os comandos Spark, que ja
# seguem as regras do sistema (cotas, limites por execucao, paus entre
# consultas, modo de agendamento escolhido no painel).
#
# Uso:
#   ./scripts/cron-linux.sh vagas              # CRON 1: busca de vagas
#   ./scripts/cron-linux.sh emails             # CRON 2: envio automatico
#   ./scripts/cron-linux.sh vagas 8            # somente o usuario #8
#   ./scripts/cron-linux.sh vagas --simular    # mostra o que sera executado
#
# Instalacao no crontab (crontab -e):
#   0 8 * * *  /caminho/do/projeto/scripts/cron-linux.sh vagas
#   30 8 * * * /caminho/do/projeto/scripts/cron-linux.sh emails
#
# Se o arquivo nao for executavel, chame pelo interpretador:
#   0 8 * * *  bash /caminho/do/projeto/scripts/cron-linux.sh vagas
#
# Requisito de permissao (uma vez, no Linux):
#   chmod +x scripts/cron-linux.sh
#
# ---------------------------------------------------------------------------

set -euo pipefail

# ---------------------------------------------------------------------------
# ARGUMENTOS
# ---------------------------------------------------------------------------

TAREFA=""
USUARIO=""
SIMULAR="nao"

# Le os argumentos em qualquer ordem: tarefa, usuario opcional e --simular.
for ARG in "$@"; do
    if [ "$ARG" = "--simular" ]; then
        SIMULAR="sim"
        continue
    fi

    if [ -z "$TAREFA" ]; then
        TAREFA="$ARG"
        continue
    fi

    if [ -z "$USUARIO" ]; then
        USUARIO="$ARG"
        continue
    fi

    echo "Argumento inesperado: $ARG" >&2
    exit 2
done

case "$TAREFA" in
    vagas|emails)
        ;;
    *)
        echo "Uso: $0 {vagas|emails} [usuarioId] [--simular]" >&2
        exit 2
        ;;
esac

# Usuario opcional: apenas digitos.
if [ -n "$USUARIO" ] && ! printf '%s' "$USUARIO" | grep -q '^[0-9][0-9]*$'; then
    echo "Usuario invalido: use apenas numeros (ex.: 8)." >&2
    exit 2
fi

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

# Raiz do projeto: a pasta imediatamente acima de "scripts/".
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

DIRETORIO_LOGS="$RAIZ/writable/logs"
ARQUIVO_LOG="$DIRETORIO_LOGS/cron-$(date +%Y-%m-%d).log"

# ---------------------------------------------------------------------------
# INTERPRETADOR PHP
# ---------------------------------------------------------------------------

# Ordem de descoberta:
#   1. variavel de ambiente PHP_CLI;
#   2. cron.caminhoPhpCli no .env do projeto (mesma chave usada pelo painel);
#   3. php no PATH do sistema.

PHP_CLI="${PHP_CLI:-}"

if [ -z "$PHP_CLI" ] && [ -f "$RAIZ/.env" ]; then
    CONFIGURADO="$(sed -n 's/^cron\.caminhoPhpCli[[:space:]]*=[[:space:]]*//p' "$RAIZ/.env" | tail -n 1 | tr -d '\r')"
    CONFIGURADO="$(printf '%s' "$CONFIGURADO" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" -e 's/\\\\/\\/g')"

    if [ -n "$CONFIGURADO" ]; then
        PHP_CLI="$CONFIGURADO"
    fi
fi

if [ -z "$PHP_CLI" ]; then
    PHP_CLI="$(command -v php || true)"
fi

if [ -z "$PHP_CLI" ]; then
    echo "PHP nao encontrado. Defina PHP_CLI ou cron.caminhoPhpCli no .env." >&2
    exit 3
fi

# ---------------------------------------------------------------------------
# COMANDO
# ---------------------------------------------------------------------------

if [ "$TAREFA" = "vagas" ]; then
    COMANDO="vagas:consultar"
else
    COMANDO="emails:enviar-automaticos"
fi

ARGUMENTOS=("spark" "$COMANDO")

if [ -n "$USUARIO" ]; then
    ARGUMENTOS+=("--usuario=$USUARIO")
fi

# ---------------------------------------------------------------------------
# EXECUCAO
# ---------------------------------------------------------------------------

if [ "$SIMULAR" = "sim" ]; then
    echo "Raiz do projeto : $RAIZ"
    echo "Interpretador   : $PHP_CLI"
    echo "Comando         : $PHP_CLI ${ARGUMENTOS[*]}"
    echo "Log             : $ARQUIVO_LOG"
    exit 0
fi

cd "$RAIZ"

# O log e importante para auditoria, mas nao pode impedir a tarefa:
# se a pasta nao for gravavel, o script avisa e continua sem log.
if ! mkdir -p "$DIRETORIO_LOGS" 2>/dev/null; then
    echo "Aviso: sem acesso a $DIRETORIO_LOGS. Esta execucao nao sera registrada em log." >&2
    ARQUIVO_LOG=""
fi

# Grava uma linha no log (nunca interrompe a execucao).
registrar() {
    if [ -n "$ARQUIVO_LOG" ]; then
        printf '%s\n' "$1" >> "$ARQUIVO_LOG" 2>/dev/null || true
    fi
}

INICIO="$(date '+%Y-%m-%d %H:%M:%S')"
SEGUNDOS_INICIO="$(date +%s)"

registrar "$INICIO | inicio | tarefa=$TAREFA | comando=$COMANDO | usuario=${USUARIO:-todos}"

# A execucao pode terminar com codigo diferente de zero (ex.: limite de
# consultas). O codigo e preservado para o agendador.
set +e
SAIDA="$("$PHP_CLI" "${ARGUMENTOS[@]}" 2>&1)"
CODIGO=$?
set -e

if [ -n "$SAIDA" ]; then
    registrar "$SAIDA"
fi

FIM="$(date '+%Y-%m-%d %H:%M:%S')"
DURACAO=$(( $(date +%s) - SEGUNDOS_INICIO ))

registrar "$FIM | fim | tarefa=$TAREFA | codigo=$CODIGO | duracao=${DURACAO}s"

# Repete a saida no console (o CRON envia para o e-mail do dono da tarefa).
if [ -n "$SAIDA" ]; then
    printf '%s\n' "$SAIDA"
fi

exit "$CODIGO"
