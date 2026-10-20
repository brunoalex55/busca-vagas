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
# Sobe um servidor de teste do projeto em segundo plano.
#
# Os servidores de teste (o site local do Divulga Vagas e o mock da API
# do cron-job.org) sao usados pelas suites de teste para nao depender da
# internet.
#
# Uso:
#   bash tools/subir-servico-teste.sh [caminho-do-python] [caminho-do-script]
#
set -euo pipefail

# A pasta do projeto (a pasta deste script).
PASTA="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# O Python: o informado ou o primeiro que estiver no PATH.
PYTHON="${1:-}"

if [ -z "$PYTHON" ]; then
    for candidato in python3 python; do
        if command -v "$candidato" > /dev/null 2>&1; then
            PYTHON="$candidato"
            break
        fi
    done
fi

if [ -z "$PYTHON" ]; then
    echo "Python nao encontrado (informe o caminho como argumento)"
    exit 1
fi

# O script do servidor: o informado ou o site local.
SCRIPT="${2:-$PASTA/tools/teste-site-local.py}"

# O log do servidor.
LOG="$PASTA/writable/logs/servico-teste.log"
mkdir -p "$(dirname "$LOG")"

# Solta o processo (nao espera e nao prende o console).
nohup "$PYTHON" "$SCRIPT" > "$LOG" 2>&1 &

echo "servidor de teste iniciando em segundo plano"
