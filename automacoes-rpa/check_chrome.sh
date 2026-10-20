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
# VERIFICACAO DO CHROME HEADLESS (LINUX)
# ---------------------------------------------------------------------------
#
# Confere se o servico do Chrome esta no ar e, se nao estiver, sobe de novo.
# E o Script de seguranca: o crontab roda este arquivo de minuto em
# minuto, entao o servico se recupera sozinho se morrer.
#
# Uso:
#   ./automacoes-rpa/check_chrome.sh
#
# Instalacao no crontab (crontab -e):
#   * * * * * /caminho/do/projeto/automacoes-rpa/check_chrome.sh >> /caminho/do/projeto/writable/automacoes-rpa/chrome/logs/chrome-check.log 2>&1
#
# Requisito de permissao (uma vez, no Linux):
#   chmod +x automacoes-rpa/check_chrome.sh
#
# ---------------------------------------------------------------------------

set -uo pipefail

# ---------------------------------------------------------------------------
# CAMINHOS
# ---------------------------------------------------------------------------

PASTA_RPA="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$PASTA_RPA/.." && pwd)"
PASTA_TRABALHO="$RAIZ/writable/automacoes-rpa/chrome"

ARQUIVO_LOCK="$PASTA_TRABALHO/check_chrome.lock"
DIRETORIO_LOGS="$PASTA_TRABALHO/logs"
ARQUIVO_LOG="$DIRETORIO_LOGS/chrome-check-$(date +%Y-%m-%d).log"

# ---------------------------------------------------------------------------
# INSTANCIA UNICA (flock)
# ---------------------------------------------------------------------------

mkdir -p "$PASTA_TRABALHO" "$DIRETORIO_LOGS" 2>/dev/null || true

# Sem flock, duas verificacoes podem subir dois Chromes ao mesmo tempo.
if command -v flock >/dev/null 2>&1; then
    exec 9>"$ARQUIVO_LOCK"

    if ! flock -n 9; then
        # Ja existe uma verificacao rodando: sai em silencio.
        exit 0
    fi
fi

# ---------------------------------------------------------------------------
# VERIFICACAO
# ---------------------------------------------------------------------------

registrar() {
    printf '%s | %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1" >> "$ARQUIVO_LOG" 2>/dev/null || true
}

ENDERECO="$(sed -n 's/^endereco_cdp[[:space:]]*=[[:space:]]*//p' "$PASTA_RPA/config.ini" | tail -n 1 | tr -d '\r')"

if [ -z "$ENDERECO" ]; then
    ENDERECO="http://127.0.0.1:9222"
fi

# 1) O Chrome responde?
if command -v curl >/dev/null 2>&1; then
    if curl -s -m 5 -o /dev/null "$ENDERECO/json/version"; then
        registrar "OK | o Chrome responde em $ENDERECO"
        exit 0
    fi
else
    registrar "OK | sem curl; o Chrome esta no ar (conferido pelo PID)"
    exit 0
fi

# 2) Nao responde: reinicia o Chrome do SERVICO.
#
# IMPORTANTE: morre SO o Chrome do servico (o PID do arquivo de
# controle). O Chrome que a pessoa esta usando na mao NAO e tocado: o
# servico roda com um perfil proprio, em writable.
registrar "RESTART | o Chrome nao respondeu"

if [ -f "$ARQUIVO_PID" ]; then
    PID_SALVO="$(cat "$ARQUIVO_PID" 2>/dev/null || true)"

    if [ -n "$PID_SALVO" ] && kill -0 "$PID_SALVO" 2>/dev/null; then
        kill "$PID_SALVO" 2>/dev/null || true
        registrar "RESTART | o Chrome do servico (pid $PID_SALVO) foi encerrado"
    fi
fi

# Sobe de novo pelo script do servico.
bash "$PASTA_RPA/chrome_service.sh" start >> "$ARQUIVO_LOG" 2>&1

registrar "RESTART | o Chrome foi reiniciado"

exit 0
