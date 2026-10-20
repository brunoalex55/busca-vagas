#!/usr/bin/env python3
# Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
#
# Autor: Bruno Alexandre
#
# Pix (doacao): bruno@brunoalexandre.com
#
# Este projeto e open source. Doe se quiser manter o projeto vivo.
#
# Mais informacoes no README.

"""
SERVICO DO CHROME HEADLESS (automacoes RPA)

Este e o UNICO processo que abre o navegador. Ele fica rodando como um
servico, com o perfil persistente em
writable/automacoes-rpa/chrome/sessions, e escuta a porta CDP
(http://127.0.0.1:9222) para os bots se conectarem.

POR QUE O CHROME E LANCADO COMO PROCESSO (e nao pelo Playwright)

O Playwright e dono de um navegador: enquanto uma conexao dele esta
aberta, outro cliente que se conecta pelo mesmo CDP disputa o
navegador, e um dos dois trava. Como aqui varios bots entram e saem o
todo tempo (cada chamada abre e fecha uma aba), o servico NAO usa o
Playwright: ele apenas lanca o Chrome e cuida do processo.

Assim:
  - o Chrome e um servico unico e persistente (perfil fixo, CDP fixo);
  - cada bot abre UMA aba nova e fecha SO essa aba;
  - nenhum bot fecha o Chrome.

REGRAS:

  - se o arquivo de PID existe e o processo esta vivo, este script NAO
    abre outro Chrome (instancia unica);
  - se o PID existe mas o processo morreu, o PID e removido e o Chrome e
    aberto de novo;
  - o encerramento e limpo nos sinais SIGINT e SIGTERM (Linux) e no
    Ctrl+C (Windows): o processo do Chrome recebe o sinal e o arquivo de
    PID e apagado;
  - a pasta de sessao e a pasta de perfil: o Chrome guarda ali os
    cookies e o estado do navegador (inclusive o cookie que libera o
    acesso em sites com Cloudflare).

Uso:
  python chrome_service.py start     # sobe o servico (ou ja esta no ar)
  python chrome_service.py stop      # fecha o Chrome
  python chrome_service.py restart   # fecha e sobe de novo
  python chrome_service.py status    # mostra se esta no ar
  python chrome_service.py run       # roda em primeiro plano (debug)
"""

import configparser
import os
import signal
import subprocess
import sys
import time
from pathlib import Path

# Pasta do servico (a mesma deste arquivo).
PASTA = Path(__file__).resolve().parent

# Raiz do projeto (o CI4 fica uma pasta acima).
RAIZ_PROJETO = PASTA.parent

# Pasta de trabalho do servico (perfil do Chrome, logs e PID).
# Fica em writable/ para nunca entrar no versionamento.
PASTA_TRABALHO = RAIZ_PROJETO / "writable" / "automacoes-rpa" / "chrome"

# Arquivos de controle.
ARQUIVO_PID = PASTA_TRABALHO / "chrome_service.pid"
ARQUIVO_LOCK = PASTA_TRABALHO / "check_chrome.lock"

# Configuracao.
CONFIG = configparser.ConfigParser()

# Caminhos onde o Chrome pode estar instalado (por sistema operacional).
CAMINHOS_CHROME = {
    "nt": [
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
        r"C:\Users\%USERNAME%\AppData\Local\Google\Chrome\Application\chrome.exe",
    ],
    "posix": [
        "/usr/bin/google-chrome",
        "/usr/bin/google-chrome-stable",
        "/usr/bin/chromium",
        "/usr/bin/chromium-browser",
        "/snap/bin/chromium",
    ],
}


def configurar_log(nivel: str = "INFO"):
    """Prepara o log do servico (no stderr)."""
    import logging

    log = logging.getLogger("chrome-service")
    log.setLevel(getattr(logging, nivel.upper(), logging.INFO))
    log.propagate = False

    if not log.handlers:
        manipulador = logging.StreamHandler(sys.stderr)
        manipulador.setFormatter(logging.Formatter("%(asctime)s | %(levelname)s | %(message)s"))
        log.addHandler(manipulador)

    return log


def carregar_config() -> configparser.ConfigParser:
    """Le o config.ini compartilhado das automacoes RPA."""
    arquivo = PASTA / "config.ini"

    if arquivo.is_file():
        CONFIG.read(str(arquivo), encoding="utf-8")

    return CONFIG


def valor_config(secao: str, chave: str, padrao: str) -> str:
    """Le uma chave do config.ini (ou devolve o padrao)."""
    if CONFIG.has_option(secao, chave):
        return CONFIG.get(secao, chave)

    return padrao


def inteiro_config(secao: str, chave: str, padrao: int) -> int:
    """Le uma chave numerica do config.ini."""
    try:
        return int(valor_config(secao, chave, str(padrao)))
    except (TypeError, ValueError):
        return padrao


def processo_vivo(pid: int) -> bool:
    """Diz se o processo com esse PID ainda existe."""
    if pid <= 0:
        return False

    try:
        import psutil
    except ImportError:
        # Sem o psutil, o teste e feito no sistema operacional.
        if os.name == "nt":
            return os.path.exists(f"\\\\?\\proc\\{pid}")

        try:
            os.kill(pid, 0)
        except OSError:
            return False

        return True

    try:
        return psutil.pid_exists(pid)
    except Exception:  # noqa: BLE001
        return False


def ler_pid() -> int:
    """Le o PID gravado no arquivo (zero quando nao ha)."""
    if not ARQUIVO_PID.is_file():
        return 0

    try:
        return int(ARQUIVO_PID.read_text(encoding="utf-8").strip())
    except (TypeError, ValueError):
        return 0


def gravar_pid(pid: int) -> None:
    """Grava o PID do Chrome no arquivo de controle."""
    PASTA_TRABALHO.mkdir(parents=True, exist_ok=True)
    ARQUIVO_PID.write_text(str(pid), encoding="utf-8")


def remover_pid() -> None:
    """Apaga o arquivo de PID."""
    if ARQUIVO_PID.is_file():
        try:
            ARQUIVO_PID.unlink()
        except OSError:
            pass


def porta_responde(endereco: str) -> bool:
    """Diz se o servico do Chrome responde na porta CDP."""
    import urllib.error
    import urllib.request

    try:
        with urllib.request.urlopen(f"{endereco}/json/version", timeout=5) as resposta:
            return resposta.status == 200
    except (urllib.error.URLError, OSError, ValueError):
        return False


def chrome_rodando() -> bool:
    """Diz se o servico do Chrome ja esta no ar."""
    endereco = valor_config("chrome", "endereco_cdp", "http://127.0.0.1:9222")

    if porta_responde(endereco):
        return True

    # O PID existe e o processo vive: o servico esta no ar.
    pid = ler_pid()

    return processo_vivo(pid)


def caminho_do_chrome() -> str:
    """
    Descobre o executavel do Chrome.

    Ordem de descoberta:
      1. o caminho configurado no config.ini;
      2. o Chrome instalado no sistema;
      3. o Chromium que veio com o Playwright.
    """
    configurado = valor_config("chrome", "caminho_chrome", "").strip()

    if configurado and Path(configurado).is_file():
        return configurado

    # Chrome instalado no sistema.
    for caminho in CAMINHOS_CHROME.get(os.name, []):
        real = os.path.expandvars(caminho)

        if Path(real).is_file():
            return real

    # Chromium que veio com o Playwright.
    try:
        from playwright.sync_api import sync_playwright

        with sync_playwright() as playwright:
            caminho = playwright.chromium.executable_path

        if caminho and Path(caminho).is_file():
            return caminho
    except Exception:  # noqa: BLE001 - sem Playwright, sem Chromium
        pass

    return ""


def argumentos_do_chrome() -> list:
    """Monta os argumentos de linha de comando do Chrome."""
    porta = inteiro_config("chrome", "porta_cdp", 9222)
    headless = valor_config("chrome", "headless", "1").strip() != "0"

    argumentos = [
        f"--remote-debugging-port={porta}",
        "--remote-debugging-address=127.0.0.1",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-background-networking",
        "--disable-backgrounding-occluded-windows",
        "--disable-renderer-backgrounding",
        "--disable-features=Translate,OptimizationHints",
        "--window-size={},{}".format(
            inteiro_config("chrome", "largura", 1440),
            inteiro_config("chrome", "altura", 900),
        ),
    ]

    if headless:
        argumentos.append("--headless=new")

    return argumentos


def abrir_chrome(log):
    """
    Lanca o Chrome headless e devolve o processo.

    O perfil fica em writable/automacoes-rpa/chrome/sessions, para o
    navegador manter o estado entre as execucoes.
    """
    executavel = caminho_do_chrome()

    if not executavel:
        raise RuntimeError(
            "Chrome nao encontrado. Instale o Google Chrome ou rode "
            "'python -m playwright install chromium'."
        )

    pasta_sessoes = PASTA_TRABALHO / valor_config("chrome", "pasta_sessoes", "sessions/default")
    pasta_sessoes.mkdir(parents=True, exist_ok=True)

    comando = [executavel] + argumentos_do_chrome() + [f"--user-data-dir={pasta_sessoes}"]

    log.info("Abrindo o Chrome headless (perfil em %s)...", pasta_sessoes)

    # O processo nasce no mesmo grupo do servico: assim o Ctrl+C do
    # servico leva o Chrome junto.
    if os.name == "nt":
        creation_flags = 0

        if hasattr(subprocess, "CREATE_NEW_PROCESS_GROUP"):
            creation_flags = subprocess.CREATE_NEW_PROCESS_GROUP

        processo = subprocess.Popen(
            comando,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=creation_flags,
        )
    else:
        processo = subprocess.Popen(
            comando,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            start_new_session=True,
        )

    gravar_pid(processo.pid)
    log.info("Chrome aberto (pid %s).", processo.pid)

    return processo


def encerrar(processo, log) -> None:
    """Encerra o Chrome com cuidado (apaga o PID)."""
    if processo is not None and processo.poll() is None:
        log.info("Fechando o Chrome...")

        try:
            processo.terminate()
            processo.wait(timeout=15)
        except Exception as erro:  # noqa: BLE001
            log.warning("Erro ao encerrar o Chrome: %s", erro)

            try:
                processo.kill()
            except Exception:  # noqa: BLE001
                pass

    remover_pid()
    log.info("Servico encerrado.")


def rodar_servico(log) -> int:
    """Roda o servico em primeiro plano (ate um sinal de parada)."""
    if chrome_rodando():
        log.info("O Chrome ja esta no ar. Nada a fazer.")
        return 0

    processo = abrir_chrome(log)

    def ao_parar(sinal, frame):  # noqa: ARG001
        log.info("Sinal de parada recebido.")
        encerrar(processo, log)
        sys.exit(0)

    # Encerramento limpo (SIGINT no Windows, SIGTERM no Linux).
    try:
        signal.signal(signal.SIGINT, ao_parar)
        signal.signal(signal.SIGTERM, ao_parar)
    except (ValueError, AttributeError, OSError):
        # Sinal nao suportado nesta plataforma: o Ctrl+C cai no except.
        pass

    # Espera a porta CDP responder.
    endereco = valor_config("chrome", "endereco_cdp", "http://127.0.0.1:9222")

    for _ in range(60):
        if porta_responde(endereco):
            log.info("Servico no ar. Ctrl+C para encerrar.")
            break

        time.sleep(1)

    try:
        while True:
            time.sleep(1)

            # Se o Chrome morreu, o servico avisa (o check_chrome
            # reinicia quando preciso).
            if processo.poll() is not None:
                log.warning("O Chrome foi encerrado fora do servico.")
                break
    except KeyboardInterrupt:
        encerrar(processo, log)

    return 0


def parar_servico(log) -> int:
    """Fecha o Chrome que estiver rodando."""
    pid = ler_pid()

    if not processo_vivo(pid):
        remover_pid()
        log.info("O Chrome nao esta rodando.")

        return 0

    log.info("Encerrando o Chrome (pid %s)...", pid)

    try:
        import psutil

        processo = psutil.Process(pid)
        processo.terminate()

        try:
            processo.wait(timeout=15)
        except psutil.TimeoutExpired:
            log.warning("O processo nao saiu; forcando.")
            processo.kill()
    except ImportError:
        try:
            os.kill(pid, signal.SIGTERM)
        except OSError as erro:
            log.warning("Nao foi possivel encerrar o processo: %s", erro)
    except Exception as erro:  # noqa: BLE001
        log.warning("Nao foi possivel encerrar o processo: %s", erro)

    remover_pid()
    log.info("Servico parado.")

    return 0


def main() -> int:
    """Ponto de entrada do servico."""
    acao = "start"

    if len(sys.argv) > 1:
        acao = sys.argv[1].strip().lower()

    log = configurar_log()
    carregar_config()

    if acao == "start":
        # Instancia unica: se ja esta no ar, nao abre outro.
        if chrome_rodando():
            log.info("O Chrome ja esta no ar (pid %s).", ler_pid())
            return 0

        # O start em segundo plano e feito pelos scripts .sh/.ps1, que
        # cuidam do nohup e da espera pelo PID.
        log.info("Use o chrome_service.sh/.ps1 para subir em segundo plano.")
        return rodar_servico(log)

    if acao == "run":
        return rodar_servico(log)

    if acao == "stop":
        return parar_servico(log)

    if acao == "restart":
        parar_servico(log)
        time.sleep(1)
        return rodar_servico(log)

    if acao == "status":
        if chrome_rodando():
            log.info("Servico no ar (pid %s).", ler_pid())
            return 0

        log.info("Servico fora do ar.")
        return 1

    log.error("Acao desconhecida: %s", acao)
    return 2


if __name__ == "__main__":
    sys.exit(main())
