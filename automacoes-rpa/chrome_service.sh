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
# SERVICO DO CHROME HEADLESS (LINUX)
# ---------------------------------------------------------------------------
#
# Sobe, para e reinicia o Chrome headless que as automacoes RPA usam.
#
# O Chrome e um SERVICO UNICO e persistente:
#   - fica no ar com o perfil em writable/automacoes-rpa/chrome/sessions;
#   - escuta a porta CDP (http://127.0.0.1:9222);
#   - os bots (Divulga Vagas, Gupy, LinkedIn...) so abrem e fecham abas.
#
# Uso:
#   ./automacoes-rpa/chrome_service.sh start
#   ./automacoes-rpa/chrome_service.sh stop
#   ./automacoes-rpa/chrome_service.sh restart
#   ./automacoes-rpa/chrome_service.sh status
#
# Instalacao no crontab (crontab -e), para subir junto com o boot:
#   @reboot /caminho/do/projeto/automacoes-rpa/chrome_service.sh start
#
# Se o arquivo nao for executavel, chame pelo interpretador:
#   @reboot bash /caminho/do/projeto/automacoes-rpa/chrome_service.sh start
#
# Requisito de permissao (uma vez, no Linux):
#   chmod +x automacoes-rpa/chrome_service.sh
#
# ---------------------------------------------------------------------------

set -euo pipefail

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

# Pasta das automacoes RPA (a mesma deste arquivo).
PASTA_RPA="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Raiz do projeto (a pasta imediatamente acima).
RAIZ="$(cd "$PASTA_RPA/.." && pwd)"

# Pasta de trabalho (perfil do Chrome, logs e PID): fora do versionamento.
PASTA_TRABALHO="$RAIZ/writable/automacoes-rpa/chrome"

ARQUIVO_PID="$PASTA_TRABALHO/chrome_service.pid"
ARQUIVO_LOCK="$PASTA_TRABALHO/check_chrome.lock"
DIRETORIO_LOGS="$PASTA_TRABALHO/logs"
ARQUIVO_LOG="$DIRETORIO_LOGS/chrome-service-$(date +%Y-%m-%d).log"

# ---------------------------------------------------------------------------
# PYTHON
# ---------------------------------------------------------------------------

# Ordem de descoberta:
#   1. variavel de ambiente PYTHON_BOT;
#   2. python3 e python no PATH do sistema.

PYTHON="${PYTHON_BOT:-}"

if [ -z "$PYTHON" ]; then
    PYTHON="$(command -v python3 || command -v python || true)"
fi

if [ -z "$PYTHON" ]; then
    echo "Python nao encontrado. Instale o Python 3 ou defina PYTHON_BOT." >&2
    exit 3
fi

# ---------------------------------------------------------------------------
# AJUDA
# ---------------------------------------------------------------------------

registrar() {
    if ! mkdir -p "$DIRETORIO_LOGS" 2>/dev/null; then
        return 0
    fi

    printf '%s | %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" >> "$ARQUIVO_LOG" 2>/dev/null || true
}

# O Chrome responde na porta do CDP?
cdp_responde() {
    ENDERECO="$(sed -n 's/^endereco_cdp[[:space:]]*=[[:space:]]*//p' "$PASTA_RPA/config.ini" | tail -n 1 | tr -d '\r')"

    if [ -z "$ENDERECO" ]; then
        ENDERECO="http://127.0.0.1:9222"
    fi

    if command -v curl >/dev/null 2>&1; then
        curl -s -m 5 -o /dev/null "$ENDERECO/json/version" && return 0
        return 1
    fi

    # Sem curl: confere o PID.
    [ -f "$ARQUIVO_PID" ] && kill -0 "$(cat "$ARQUIVO_PID")" 2>/dev/null
}

# O processo do Chrome esta vivo?
processo_vivo() {
    [ -f "$ARQUIVO_PID" ] || return 1

    PID="$(cat "$ARQUIVO_PID" 2>/dev/null || true)"

    if [ -z "$PID" ]; then
        return 1
    fi

    kill -0 "$PID" 2>/dev/null
}

# ---------------------------------------------------------------------------
# ACOES
# ---------------------------------------------------------------------------

acao_start() {
    # Instancia unica: se ja responde, nao abre outro.
    if cdp_responde; then
        registrar "start | o Chrome ja esta no ar"
        echo "O Chrome ja esta no ar."
        return 0
    fi

    mkdir -p "$PASTA_TRABALHO" "$DIRETORIO_LOGS"

    # PID antigo de um processo morto: remove.
    if [ -f "$ARQUIVO_PID" ] && ! processo_vivo; then
        rm -f "$ARQUIVO_PID"
    fi

    registrar "start | subindo o Chrome headless"

    # O servico escreve o PID do Chrome no arquivo de controle.
    cd "$PASTA_RPA"

    nohup "$PYTHON" "$PASTA_RPA/chrome_service.py" run >> "$ARQUIVO_LOG" 2>&1 &

    # Espera a porta CDP responder (ate 60 segundos).
    CONTAGEM=0

    while [ "$CONTAGEM" -lt 60 ]; do
        if cdp_responde; then
            PID_CHROME=""

            if [ -f "$ARQUIVO_PID" ]; then
                PID_CHROME="$(cat "$ARQUIVO_PID" 2>/dev/null || true)"
            fi

            registrar "start | Chrome no ar (pid $PID_CHROME)"
            echo "Chrome no ar (pid $PID_CHROME)."
            return 0
        fi

        sleep 1
        CONTAGEM=$((CONTAGEM + 1))
    done

    registrar "start | o Chrome nao respondeu a tempo"
    echo "O Chrome nao respondeu na porta do CDP. Veja $ARQUIVO_LOG." >&2
    return 1
}

acao_stop() {
    if ! processo_vivo; then
        rm -f "$ARQUIVO_PID"
        registrar "stop | o Chrome nao estava no ar"
        echo "O Chrome nao estava no ar."
        return 0
    fi

    PID="$(cat "$ARQUIVO_PID")"
    registrar "stop | encerrando o Chrome (pid $PID)"

    kill "$PID" 2>/dev/null || true

    # Espera o processo sair.
    CONTAGEM=0

    while [ "$CONTAGEM" -lt 20 ] && kill -0 "$PID" 2>/dev/null; do
        sleep 1
        CONTAGEM=$((CONTAGEM + 1))
    done

    # Ainda vivo: encerra sem piedade.
    if kill -0 "$PID" 2>/dev/null; then
        kill -9 "$PID" 2>/dev/null || true
    fi

    rm -f "$ARQUIVO_PID"
    registrar "stop | Chrome encerrado"
    echo "Chrome encerrado."
    return 0
}

acao_status() {
    if cdp_responde; then
        echo "Chrome no ar (pid $(cat "$ARQUIVO_PID" 2>/dev/null || echo '?'))."
        return 0
    fi

    echo "Chrome fora do ar."
    return 1
}

# ---------------------------------------------------------------------------
# ENTRADA
# ---------------------------------------------------------------------------

ACAO="${1:-start}"

case "$ACAO" in
    start)
        acao_start
        ;;
    stop)
        acao_stop
        ;;
    restart)
        acao_stop
        sleep 2
        acao_start
        ;;
    status)
        acao_status
        ;;
    *)
        echo "Uso: $0 {start|stop|restart|status}" >&2
        exit 2
        ;;
esac
