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
# DISPARO DA FILA DO DIVULGA VAGAS (LINUX)
# ---------------------------------------------------------------------------
#
# Roda o comando do CI4 do segundo provedor pela linha de comando. O
# script NAO decide o que roda: ele apenas chama o comando Spark, que ja
# segue as regras do sistema (limites do bot, limite de e-mail, janela de
# envio e as situacoes da candidatura).
#
# Uso:
#   ./automacoes-rpa/divulgavagas/processar_fila.sh buscar-vagas
#   ./automacoes-rpa/divulgavagas/processar_fila.sh candidatar
#   ./automacoes-rpa/divulgavagas/processar_fila.sh buscar-vagas 8
#   ./automacoes-rpa/divulgavagas/processar_fila.sh candidatar --simular
#
# Instalacao no crontab (crontab -e):
#   # CRON 1 do Divulga Vagas (busca) - a cada 30 min
#   */30 * * * * /caminho/do/projeto/automacoes-rpa/divulgavagas/processar_fila.sh buscar-vagas >> /caminho/do/projeto/writable/automacoes-rpa/divulgavagas/logs/cron-buscar.log 2>&1
#
#   # CRON 2 do Divulga Vagas (candidatura) - a cada 5 min
#   */5 * * * * /caminho/do/projeto/automacoes-rpa/divulgavagas/processar_fila.sh candidatar >> /caminho/do/projeto/writable/automacoes-rpa/divulgavagas/logs/cron-candidatar.log 2>&1
#
# Requisito de permissao (uma vez, no Linux):
#   chmod +x automacoes-rpa/divulgavagas/processar_fila.sh
#
# ---------------------------------------------------------------------------

set -euo pipefail

# ---------------------------------------------------------------------------
# ARGUMENTOS
# ---------------------------------------------------------------------------

MODO=""
USUARIO=""
SIMULAR="nao"

for ARG in "$@"; do
    if [ "$ARG" = "--simular" ]; then
        SIMULAR="sim"
        continue
    fi

    if [ -z "$MODO" ]; then
        MODO="$ARG"
        continue
    fi

    if [ -z "$USUARIO" ]; then
        USUARIO="$ARG"
        continue
    fi

    echo "Argumento inesperado: $ARG" >&2
    exit 2
done

case "$MODO" in
    buscar-vagas)
        COMANDO="divulgavagas:buscar-vagas"
        ;;
    candidatar)
        COMANDO="divulgavagas:candidatar"
        ;;
    *)
        echo "Uso: $0 {buscar-vagas|candidatar} [usuarioId] [--simular]" >&2
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

# Pasta do provedor (a mesma deste arquivo).
PASTA_PROVEDOR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Pasta das automacoes RPA (um nivel acima).
PASTA_RPA="$(cd "$PASTA_PROVEDOR/.." && pwd)"

# Raiz do projeto (um nivel acima das automacoes).
RAIZ="$(cd "$PASTA_RPA/.." && pwd)"

PASTA_TRABALHO="$RAIZ/writable/automacoes-rpa/divulgavagas"
DIRETORIO_LOGS="$PASTA_TRABALHO/logs"
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

registrar() {
    if [ -n "$ARQUIVO_LOG" ]; then
        printf '%s\n' "$1" >> "$ARQUIVO_LOG" 2>/dev/null || true
    fi
}

INICIO="$(date '+%Y-%m-%d %H:%M:%S')"
SEGUNDOS_INICIO="$(date +%s)"

registrar "$INICIO | inicio | comando=$COMANDO | usuario=${USUARIO:-todos}"

# A execucao pode terminar com codigo diferente de zero (ex.: limite do
# bot). O codigo e preservado para o agendador.
set +e
SAIDA="$("$PHP_CLI" "${ARGUMENTOS[@]}" 2>&1)"
CODIGO=$?
set -e

if [ -n "$SAIDA" ]; then
    registrar "$SAIDA"
fi

FIM="$(date '+%Y-%m-%d %H:%M:%S')"
DURACAO=$(( $(date +%s) - SEGUNDOS_INICIO ))

registrar "$FIM | fim | comando=$COMANDO | codigo=$CODIGO | duracao=${DURACAO}s"

# Repete a saida no console (o CRON envia para o e-mail do dono da tarefa).
if [ -n "$SAIDA" ]; then
    printf '%s\n' "$SAIDA"
fi

exit "$CODIGO"
