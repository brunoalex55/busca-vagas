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
BOT DO DIVULGA VAGAS (Playwright + Chrome headless persistente)

Este e o unico programa que fala com o site Divulga Vagas. Ele tem DOIS
modos e nenhum outro:

  --modo=buscar
      Abre a listagem de vagas, coleta os cards e devolve em JSON. Para
      cada vaga, abre uma segunda aba para ler a descricao (que fica em
      <div class="dv-desc job-description">) e descobrir o endereco do
      formulario de candidatura. NAO candidata a nada.

  --modo=candidatar
      Abre o formulario de candidatura, anexa o curriculo em PDF, marca
      o aceite dos termos, clica em "Enviar curriculo" e confere a pagina
      de sucesso. NAO busca vagas.

REGRAS QUE O CODIGO SEMPRE CUMPRE:

  - NUNCA abre um navegador novo: ele se conecta ao Chrome headless
    persistente pela porta CDP (http://127.0.0.1:9222), que fica sempre
    aberto.
  - Cada chamada abre UMA aba nova e fecha SO essa aba. O Chrome nunca
    e fechado por aqui (nem com browser.close()).
  - NUNCA roda em paralelo: as abas sao uma por vez.
  - A saida e UM UNICO JSON no stdout. Os logs vao para o stderr.
  - NUNCA grava no log CPF, e-mail de contacto nem conteudo do
    curriculo.
  - Caminhos sempre com pathlib.Path (nada de barra fixa).
  - O sistema operacional e detectado em tempo de execucao, para o
    mesmo script funcionar no Linux e no Windows.

Codigos de saida:
  0 = sucesso total
  1 = sucesso parcial (algumas vagas falharam, as outras voltaram)
  2 = erro de infraestrutura (Chrome fora, arquivo inexistente, ...)
"""

import argparse
import configparser
import html
import json
import logging
import re
import sys
import time
import unicodedata
from datetime import datetime
from pathlib import Path
from urllib.parse import urljoin

# Pasta do bot (a mesma deste arquivo).
PASTA = Path(__file__).resolve().parent

# Raiz das automacoes RPA (um nivel acima da pasta do provedor).
PASTA_RPA = PASTA.parent

# Raiz do projeto (o CI4 fica duas pastas acima).
RAIZ_PROJETO = PASTA_RPA.parent

# Pasta de trabalho deste provedor (logs, curriculos e listas).
# Fica em writable/ para nunca entrar no versionamento.
PASTA_TRABALHO = RAIZ_PROJETO / "writable" / "automacoes-rpa" / "divulgavagas"

# Configuracao lida do config.ini.
CONFIG = configparser.ConfigParser()

# Caracteres invisiveis que quebram a extracao de e-mail.
# U+200B (espaco de largura zero), U+200C, U+200D, U+2060 e U+FEFF.
# Caracteres de largura zero que o site usa para esconder o "@".
# O codigo U+200B (espaco de largura zero) sumia quando a linha era
# escrita direto no arquivo, entao a lista fica com escapes.
CARACTERES_INVISIVEIS = "\u200b\u200c\u200d\u2060\ufeff"

# Sinais de bloqueio do Cloudflare (titulo e corpo da pagina).
MARCAS_CLOUDFLARE = [
    "just a moment",
    "checking your browser",
    "attention required",
    "access denied",
    "verifying you are human",
]


def configurar_log(nivel: str) -> logging.Logger:
    """Prepara o log (sempre no stderr, nunca no stdout)."""
    log = logging.getLogger("bot-divulgavagas")
    log.setLevel(getattr(logging, nivel.upper(), logging.INFO))
    log.propagate = False

    if not log.handlers:
        # Streamhandler do log: stderr.
        manipulador = logging.StreamHandler(sys.stderr)
        manipulador.setFormatter(logging.Formatter("%(asctime)s | %(levelname)s | %(message)s"))
        log.addHandler(manipulador)

    return log


def carregar_config() -> configparser.ConfigParser:
    """
    Le as configuracoes do bot.

    Sao dois arquivos:
      - automacoes-rpa/config.ini: o que e do servico do Chrome
        (endereco do CDP, porta, perfil, canal...);
      - automacoes-rpa/<provedor>/config.ini: o que e deste bot
        (tempo limite, seletores, numero de vagas).

    O arquivo do provedor vem depois e sobrescreve o compartilhado.
    """
    compartilhado = PASTA_RPA / "config.ini"

    if compartilhado.is_file():
        CONFIG.read(str(compartilhado), encoding="utf-8")

    proprio = PASTA / "config.ini"

    if proprio.is_file():
        CONFIG.read(str(proprio), encoding="utf-8")

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


def sanitizar(texto: str) -> str:
    """
    Prepara o texto da pagina para a extracao de e-mail no PHP.

    Faz tres coisas:
      1. converte as entidades HTML (&amp;, &#64;, &ZeroWidthSpace;...);
      2. remove os caracteres de largura zero, que escondem o "@";
      3. normaliza os espacos.
    """
    if not texto:
        return ""

    # 1) Entidades HTML (inclusive as numericas).
    limpo = html.unescape(texto)

    # 2) Caracteres de largura zero.
    limpo = limpo.translate({ord(c): None for c in CARACTERES_INVISIVEIS})

    # 3) Espacos: qualquer sequencia vira um espaco simples.
    limpo = re.sub(r"\s+", " ", limpo)

    return limpo.strip()


def normalizar_texto(texto: str) -> str:
    """Normaliza acentos e caixa (para comparar titulos)."""
    if not texto:
        return ""

    decomposto = unicodedata.normalize("NFKD", texto)
    sem_acento = "".join(c for c in decomposto if not unicodedata.combining(c))

    return re.sub(r"\s+", " ", sem_acento).strip().lower()


def pagina_bloqueada(titulo: str, corpo: str) -> bool:
    """Diz se a pagina mostra um desafio do Cloudflare."""
    alvo = normalizar_texto((titulo or "") + " " + (corpo or ""))

    for marca in MARCAS_CLOUDFLARE:
        if marca in alvo:
            return True

    return False


def tem_desafio(pagina) -> bool:
    """
    Confere, na pagina aberta, se ha um desafio do Cloudflare.

    A conferencia e LEVE de proposito: pegamos o titulo e o começo do
    texto do corpo, sem serializar o HTML inteiro. Uma pagina ocupada
    (com anuncios carregando) pode travar uma leitura pesada, e o bot
    nao pode ficar preso por causa da conferencia.
    """
    titulo = ""

    try:
        titulo = pagina.title()
    except Exception:  # noqa: BLE001
        pass

    if pagina_bloqueada(titulo, ""):
        return True

    # O começo do texto do corpo.
    try:
        corpo = pagina.inner_text("body")[:1500]
    except Exception:  # noqa: BLE001
        return False

    if pagina_bloqueada("", corpo):
        return True

    return False


def abrir_pagina(pagina, url: str, seletor_esperado: str, tempo_espera: int, log: logging.Logger) -> dict:
    """
    Abre uma pagina e espera o elemento esperado.

    Devolve um dicionario com o desfecho:
      - 'ok' = true quando o elemento apareceu;
      - 'erro' = a mensagem (com 'cloudflare_block' quando o site
        desafiou o navegador).

    A pagina NAO e fechada aqui: quem chama decide (a aba e sempre
    fechada no finally de quem abriu).
    """
    desfecho = {"ok": False, "erro": None, "desafio": False}

    # Todas as esperas da aba ficam curtas: o site e lento, mas nao a
    # ponto de passar de minutos.
    pagina.set_default_timeout(tempo_espera)

    # A navegacao tem prazo curto: depois dela, a espera e pelo elemento.
    try:
        pagina.goto(url, wait_until="commit", timeout=tempo_espera)
    except Exception as erro:  # noqa: BLE001
        # A navegacao estourou o tempo: o elemento nao deve ter
        # apareceu. O motivo pode ser o desafio do Cloudflare.
        if tem_desafio(pagina):
            desfecho["erro"] = "cloudflare_block: o site desafiou o navegador."
            desfecho["desafio"] = True
        else:
            desfecho["erro"] = f"Timeout ao abrir a pagina: {str(erro)[:120]}"

        return desfecho

    # Espera o elemento que diz que a pagina e a que queremos.
    try:
        pagina.wait_for_selector(seletor_esperado, timeout=tempo_espera)
        desfecho["ok"] = True

        return desfecho
    except Exception:  # noqa: BLE001
        if tem_desafio(pagina):
            desfecho["erro"] = "cloudflare_block: o site desafiou o navegador."
            desfecho["desafio"] = True
        else:
            desfecho["erro"] = f"O elemento '{seletor_esperado}' nao apareceu na pagina."

        return desfecho


def url_absoluta(url: str, base: str) -> str:
    """
    Devolve o endereco completo de uma URL.

    A listagem pode usar enderecos relativos (como /vaga-de-emprego/...),
    e a aba nova comeca em about:blank: sem esta conversao, o bot nao
    saberia para onde ir.
    """
    if not url:
        return ""

    if url.startswith("http://") or url.startswith("https://"):
        return url

    return urljoin(base, url)


def id_da_vaga(url: str) -> str:
    """
    Extrai o id numerico da vaga da URL.

    O site termina o endereco da vaga com o id:
    /vaga-de-emprego/auxiliar-administrativo-34762271 -> 34762271

    A busca tambem pode vir por query string
    (/vaga.html?vaga=auxiliar-de-loja-34765224), entao o id e procurado
    la tambem.
    """
    if not url:
        return ""

    sem_query = url.split("?")[0].split("#")[0]
    pedacos = [p for p in sem_query.split("/") if p]

    if pedacos:
        achado = re.search(r"(\d+)$", pedacos[-1])

        if achado:
            return achado.group(1)

    # O id pode estar na query string.
    if "?" in url:
        consulta = url.split("?", 1)[1]

        for item in consulta.split("&"):
            if "=" not in item:
                continue

            chave, valor = item.split("=", 1)
            achado = re.search(r"(\d+)$", valor)

            if achado:
                return achado.group(1)

    return ""


def url_de_envio(url_vaga: str) -> str:
    """
    Descobre o endereco do formulario de candidatura.

    O site tem tres paginas:
      /vaga-de-emprego/<slug>-<id>  (a vaga)
      /aviso/<slug>-<id>             (o aviso de seguranca)
      /envioCurriculo/<slug>-<id>    (o formulario)

    O "<slug>-<id>" e o mesmo nas tres, entao a conversao e direta.
    """
    if not url_vaga:
        return ""

    sem_query = url_vaga.split("?")[0].split("#")[0]
    pedacos = [p for p in sem_query.split("/") if p]

    if not pedacos:
        return ""

    ultimo = pedacos[-1]
    base = sem_query.rsplit("/", 1)[0]

    # A query string da vaga e mantida (alguns sites carregam o slug nela).
    consulta = ""

    if "?" in url_vaga:
        consulta = "?" + url_vaga.split("?", 1)[1]

    return f"{base}/envioCurriculo/{ultimo}{consulta}"


def conectar():
    """
    Conecta no Chrome headless persistente (servico unico).

    Nao abre navegador nenhum: apenas se conecta no CDP.
    """
    from playwright.sync_api import sync_playwright

    endereco = valor_config("chrome", "endereco_cdp", "http://127.0.0.1:9222")

    playwright = sync_playwright().start()

    try:
        navegador = playwright.chromium.connect_over_cdp(endereco)
    except Exception as erro:  # noqa: BLE001 - a mensagem vai para o JSON
        playwright.stop()
        raise RuntimeError(
            "Nao foi possivel conectar no Chrome headless em "
            f"{endereco}. O servico esta rodando? ({erro})"
        ) from erro

    # O primeiro contexto e o do perfil persistente.
    if not navegador.contexts:
        playwright.stop()
        raise RuntimeError("O Chrome conectado nao tem nenhum contexto aberto.")

    return playwright, navegador, navegador.contexts[0]


def abrir_aba(contexto):
    """Abre UMA aba nova no contexto persistente."""
    return contexto.new_page()


def modo_buscar(url_busca: str, usuario_id: int, limite: int, log: logging.Logger) -> dict:
    """
    MODO BUSCAR: coleta as vagas da listagem.

    Devolve um dicionario com as vagas encontradas. Cada vaga tem:
    url_vaga, url_envio, vaga_id, titulo, empresa, localizacao e a
    descricao (o texto interno dos divs de descricao).

    Tudo acontece em UMA aba: a listagem e lida, a aba e reaproveitada
    para as paginas das vagas e so entao e fechada.
    """
    resultado = {
        "sucesso": False,
        "modo": "buscar",
        "usuario_id": usuario_id,
        "url_busca": url_busca,
        "vagas": [],
        "erro": None,
    }

    playwright, navegador, contexto = conectar()

    try:
        cartoes = []
        pagina = abrir_aba(contexto)

        try:
            log.info("Abrindo a listagem: %s", url_busca)

            espera = inteiro_config("bot", "espera_navegacao", 15000)
            abertura = abrir_pagina(pagina, url_busca, "article.dv-vaga", espera, log)

            if abertura["desafio"]:
                # O site desafiou o navegador: nao ha o que fazer aqui.
                log.warning("O site desafiou o navegador (Cloudflare).")
                resultado["erro"] = "cloudflare_block: o site desafiou o navegador na listagem."

                return resultado

            if not abertura["ok"]:
                log.info("A listagem nao devolveu cards: %s", abertura["erro"])

            # Coleta os cards da primeira pagina (os dados sao copiados
            # aqui, porque a aba e reaproveitada em seguida).
            for cartao in pagina.query_selector_all("article.dv-vaga"):
                dados = extrair_dados_do_cartao(cartao)

                if dados is not None:
                    cartoes.append(dados)

            log.info("Cards encontrados na listagem: %d", len(cartoes))
        finally:
            pass

        total = len(cartoes)

        if total > limite:
            cartoes = cartoes[:limite]

        # Prazo total da busca: o bot nao pode passar do tempo todo
        # numa unica chamada (o agendador depende disso).
        prazo_total = inteiro_config("bot", "timeout_busca", 60000) / 1000
        inicio_busca = time.time()

        for dados in cartoes:
            # O prazo acabou: devolve o que ja foi coletado.
            if (time.time() - inicio_busca) >= prazo_total:
                log.info("Prazo da busca esgotado; devolvendo o que ja foi coletado.")
                resultado["parcial"] = True
                break

            try:
                vaga = extrair_vaga(dados, pagina, log, url_busca)
            except Exception as erro:  # noqa: BLE001 - uma vaga ruim nao derruba a busca
                log.warning("Vaga ignorada: %s", erro)
                continue

            if vaga is not None:
                resultado["vagas"].append(vaga)

                # Guarda a vaga na lista de interesse do usuario.
                registrar_vaga_interesse(
                    usuario_id,
                    str(vaga.get("vaga_id", "")),
                    str(vaga.get("url_vaga", "")),
                    str(vaga.get("titulo", "")),
                )

        # Sucesso: pelo menos a listagem respondeu.
        resultado["sucesso"] = True

        if len(resultado["vagas"]) < total:
            # Algum cartao nao deu vaga completa: sucesso parcial.
            resultado["parcial"] = True
    finally:
        # Fecha a aba desta chamada (so ela: o Chrome continua no ar).
        try:
            pagina.close()
        except Exception:  # noqa: BLE001
            pass

        # NUNCA fecha o navegador nem o contexto: o Chrome e o servico.
        # Em um navegador conectado, close() limparia os contextos; o
        # certo e apenas desconectar (stop).
        playwright.stop()

    return resultado


def primeiro_texto(cartao, seletores):
    """
    Devolve o texto do primeiro seletor que existir no elemento.

    O site pode mudar a tag (o titulo do card ja foi <h3> e passou a
    ser <h2>), entao o bot tenta uma lista de seletores em vez de
    depender de um so.
    """
    for seletor in seletores:
        elemento = cartao.query_selector(seletor)

        if elemento is not None:
            texto = (elemento.inner_text() or "").strip()

            if texto:
                return texto

    return ""


def extrair_dados_do_cartao(cartao):
    """
    Copia os dados basicos de um card da listagem.

    O texto e copiado agora, enquanto o card esta na tela: depois a aba
    fecha e o elemento nao existe mais.
    """
    link = cartao.query_selector("a.dv-vaga__link")
    url_vaga = ""

    if link is not None:
        url_vaga = link.get_attribute("href") or ""

    if not url_vaga:
        return None

    # O titulo: o site usa <h2> hoje (ja foi <h3>), entao a lista de
    # seletores cobre as duas markups.
    titulo = primeiro_texto(
        cartao,
        ["h2.dv-vaga__titulo", "h3.dv-vaga__titulo", ".dv-vaga__titulo"],
    )

    empresa = primeiro_texto(cartao, ["p.dv-vaga__empresa", ".dv-vaga__empresa"])

    # A localizacao e o primeiro item da lista de metadados que nao seja
    # o salario.
    localizacao = ""

    for item in cartao.query_selector_all("ul.dv-vaga__meta li"):
        texto = (item.inner_text() or "").strip()

        if not texto:
            continue

        # O item do salario tem a classe "is-salario".
        if item.get_attribute("class") and "is-salario" in (item.get_attribute("class") or ""):
            continue

        if "-" in texto and not texto.startswith("R$"):
            localizacao = texto
            break

    return {
        "url_vaga": url_vaga,
        "titulo": sanitizar(titulo),
        "empresa": sanitizar(empresa),
        "localizacao": sanitizar(localizacao),
    }


def extrair_vaga(dados: dict, pagina, log: logging.Logger, url_base: str = ""):
    """
    Extrai a descricao e o endereco do formulario da pagina da vaga.

    A aba e REUTILIZADA: o bot navega nela em vez de abrir e fechar uma
    aba por vaga. Abrir e fechar abas em ciclo pelo CDP deixa o
    navegador travado depois de algumas voltas.
    """
    url_vaga = url_absoluta(str(dados.get("url_vaga", "")), url_base)

    if not url_vaga:
        return None

    titulo = str(dados.get("titulo", ""))
    empresa = str(dados.get("empresa", ""))
    localizacao = str(dados.get("localizacao", ""))

    descricao = ""
    url_envio = ""
    vaga_id = ""

    # A espera e pelo elemento que diz que a pagina e a da vaga.
    espera = inteiro_config("bot", "timeout_elemento", 20000)
    abertura = abrir_pagina(pagina, url_vaga, "main#dv-job", espera, log)

    if abertura["desafio"]:
        log.warning("O site desafiou o navegador na pagina da vaga.")

        return None

    # O id da vaga esta no proprio <main> da pagina.
    principal = pagina.query_selector("main#dv-job")

    if principal is not None:
        vaga_id = principal.get_attribute("data-job-id") or ""

    # Reserva: se a listagem nao trouxe o titulo (a tag do titulo
    # mudou no site), ele e lido da propria pagina da vaga.
    if not titulo:
        titulo = primeiro_texto(
            pagina,
            ["h1.job-title", "h1.dv-hero__title", "main#dv-job h1", "h1"],
        )

    # O mesmo para a empresa.
    if not empresa:
        empresa = primeiro_texto(
            pagina,
            [".dv-hero__empresa-nome", ".dv-hero__empresa-txt", "main#dv-job"],
        )

    if not empresa or not titulo:
        log.info(
            "Vaga sem titulo/empresa no card: usando a pagina da vaga (%s | %s)",
            titulo or url_vaga,
            empresa or "-",
        )

    # A descricao: os tres blocos (detalhes, requisitos e beneficios).
    blocos = pagina.query_selector_all("div.dv-desc.job-description")
    textos = []

    for bloco in blocos:
        texto = (bloco.inner_text() or "").strip()

        if texto:
            textos.append(texto)

    descricao = sanitizar(" \n".join(textos))

    # O endereco do formulario: o botao da pagina de vaga aponta para
    # o aviso, e o aviso aponta para o formulario. O slug e o mesmo
    # nas tres paginas, entao a conversao e direta.
    url_envio = ""

    botao = pagina.query_selector("#btn-apply-link")

    if botao is not None:
        url_envio = botao.get_attribute("href") or ""

    if not url_envio:
        botao = pagina.query_selector("a.btn-apply")

        if botao is not None:
            url_envio = botao.get_attribute("href") or ""

    if url_envio:
        url_envio = url_envio.replace("/aviso/", "/envioCurriculo/")
        url_envio = url_envio.replace("/aviso.html", "/envioCurriculo.html")

    if not url_envio:
        url_envio = url_de_envio(url_vaga)

    if not vaga_id:
        vaga_id = id_da_vaga(url_vaga)

    log.info("Vaga coletada: %s", titulo if titulo else url_vaga)

    return {
        "url_vaga": url_vaga,
        "url_envio": url_envio,
        "vaga_id": vaga_id,
        "titulo": sanitizar(titulo),
        "empresa": sanitizar(empresa),
        "localizacao": sanitizar(localizacao),
        "descricao": descricao,
    }


def modo_candidatar(url_envio: str, curriculo: str, usuario_id: int, log: logging.Logger) -> dict:
    """
    MODO CANDIDATAR: envia o curriculo no formulario do site.

    Devolve um dicionario com o desfecho. NUNCA busca vagas.
    """
    resultado = {
        "sucesso": False,
        "modo": "candidatar",
        "usuario_id": usuario_id,
        "url_envio": url_envio,
        "erro": None,
    }

    # O curriculo precisa existir.
    caminho = Path(curriculo)

    if not caminho.is_file():
        resultado["erro"] = "Curriculo nao encontrado."
        return resultado

    # O tamanho maximo do site (5 MB por padrao).
    tamanho_maximo = inteiro_config("candidatura", "tamanho_maximo_curriculo", 5)
    tamanho_mb = caminho.stat().st_size / (1024 * 1024)

    if tamanho_mb > tamanho_maximo:
        resultado["erro"] = f"Curriculo maior que o limite de {tamanho_maximo} MB."
        return resultado

    playwright, navegador, contexto = conectar()

    try:
        pagina = abrir_aba(contexto)

        try:
            log.info("Abrindo o formulario: %s", url_envio)

            # O campo de upload e o que diz se a pagina e a do formulario.
            seletor_arquivo = valor_config("candidatura", "seletor_arquivo", "input[type=\"file\"]")
            espera_elemento = inteiro_config("bot", "timeout_elemento", 20000)

            abertura = abrir_pagina(pagina, url_envio, seletor_arquivo, espera_elemento, log)

            if abertura["desafio"]:
                # Sem o campo de upload: o site desafiou o navegador.
                log.warning("O site desafiou o navegador no formulario.")
                resultado["erro"] = "cloudflare_block: a pagina do formulario nao abriu."

                return resultado

            if not abertura["ok"]:
                resultado["erro"] = "O formulario de candidatura nao apareceu na pagina."

                return resultado

            # Anexa o curriculo.
            pagina.set_input_files(seletor_arquivo, str(caminho))
            log.info("Curriculo anexado.")

            # Marca o aceite dos termos (quando existir).
            seletor_termos = valor_config("candidatura", "seletor_termos", "input#politica")

            try:
                caixa = pagina.query_selector(seletor_termos)

                if caixa is not None and not caixa.is_checked():
                    caixa.check()
                    log.info("Termos marcados.")
            except Exception as erro:  # noqa: BLE001
                log.warning("Nao foi possivel marcar os termos: %s", erro)

            # Clica em enviar.
            seletor_enviar = valor_config("candidatura", "seletor_enviar", "button#enviarCV")

            try:
                pagina.click(seletor_enviar, timeout=espera_elemento)
            except Exception as erro:  # noqa: BLE001
                resultado["erro"] = f"Nao foi possivel clicar em enviar: {erro}"
                return resultado

            log.info("Envio clicado; aguardando a confirmacao.")

            # Espera a pagina de sucesso (ou um sinal de sucesso no texto).
            url_sucesso = valor_config("candidatura", "url_sucesso", "/sucesso")
            seletor_sucesso = valor_config("candidatura", "seletor_sucesso", "/sucesso")

            try:
                pagina.wait_for_url(f"**{url_sucesso}**", timeout=espera_elemento)
                resultado["sucesso"] = True
                resultado["url_confirmacao"] = pagina.url
                marcar_vaga_processada(usuario_id, id_da_vaga(url_envio), "enviada")
            except Exception:  # noqa: BLE001
                # Sem mudanca de URL: confere o texto da pagina.
                texto = ""

                try:
                    texto = pagina.inner_text("body")
                except Exception:  # noqa: BLE001
                    pass

                if seletor_sucesso and seletor_sucesso in texto:
                    resultado["sucesso"] = True
                    resultado["url_confirmacao"] = pagina.url
                    marcar_vaga_processada(usuario_id, id_da_vaga(url_envio), "enviada")
                else:
                    titulo = pagina.title()
                    corpo = ""

                    try:
                        corpo = pagina.inner_text("body")[:2000]
                    except Exception:  # noqa: BLE001
                        pass

                    if pagina_bloqueada(titulo, corpo):
                        resultado["erro"] = "cloudflare_block: a pagina nao confirmou o envio."
                    else:
                        resultado["erro"] = "O site nao confirmou o envio do curriculo."

        finally:
            # Fecha SO a aba do formulario.
            pagina.close()

    finally:
        # NUNCA fecha o navegador: o Chrome e o servico unico e persistente.
        # Em um navegador conectado, close() limparia os contextos; o
        # certo e apenas desconectar (stop).
        playwright.stop()

    return resultado


def pasta_do_usuario(usuario_id: int) -> Path:
    """
    Pasta de trabalho de um usuario (as listas de vagas dele).
    """
    pasta = PASTA_TRABALHO / "usuarios" / str(usuario_id)
    pasta.mkdir(parents=True, exist_ok=True)

    return pasta


def ler_lista(caminho: Path) -> list:
    """Le uma lista JSON (vazia quando o arquivo ainda nao existe)."""
    if not caminho.is_file():
        return []

    try:
        dados = json.loads(caminho.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return []

    if not isinstance(dados, list):
        return []

    return dados


def gravar_lista(caminho: Path, itens: list) -> None:
    """Grava uma lista JSON (a pasta e criada se preciso)."""
    caminho.parent.mkdir(parents=True, exist_ok=True)
    caminho.write_text(json.dumps(itens, ensure_ascii=False, indent=2), encoding="utf-8")


def registrar_vaga_interesse(usuario_id: int, vaga_id: str, url_vaga: str, titulo: str) -> None:
    """
    Guarda a vaga na lista de interesse do usuario.

    A lista serve para o painel mostrar o que o bot ja viu, sem precisar
    varrer o site de novo.
    """
    if not vaga_id:
        return

    pasta = pasta_do_usuario(usuario_id)
    caminho = pasta / "vagas_interesse.json"

    itens = ler_lista(caminho)
    conhecidos = {str(item.get("vaga_id", "")) for item in itens if isinstance(item, dict)}

    if vaga_id in conhecidos:
        return

    itens.append({
        "vaga_id": vaga_id,
        "url_vaga": url_vaga,
        "titulo": titulo,
        "encontrada_em": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
    })

    gravar_lista(caminho, itens)


def marcar_vaga_processada(usuario_id: int, vaga_id: str, situacao: str) -> None:
    """
    Marca a vaga como processada (a candidatura ja aconteceu).
    """
    if not vaga_id:
        return

    pasta = pasta_do_usuario(usuario_id)
    caminho = pasta / "vagas_processadas.json"

    itens = ler_lista(caminho)
    conhecidos = {str(item.get("vaga_id", "")) for item in itens if isinstance(item, dict)}

    if vaga_id in conhecidos:
        return

    itens.append({
        "vaga_id": vaga_id,
        "situacao": situacao,
        "processada_em": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
    })

    gravar_lista(caminho, itens)


def main() -> int:
    # A saida e sempre em UTF-8: em um console que nao usa UTF-8,
    # o JSON quebraria em um caractere de largura zero.
    for fluxo in (sys.stdout, sys.stderr):
        try:
            fluxo.reconfigure(encoding="utf-8", errors="replace")
        except (AttributeError, ValueError):
            pass

    """Ponto de entrada: le os argumentos, roda o modo e devolve o JSON."""
    analisador = argparse.ArgumentParser(description="Bot do Divulga Vagas (Playwright).")
    analisador.add_argument("--modo", required=True, choices=["buscar", "candidatar"], help="O que o bot vai fazer.")
    analisador.add_argument("--usuario-id", default="0", help="ID do usuario (para a auditoria).")
    analisador.add_argument("--url-busca", default="", help="URL da listagem (modo buscar).")
    analisador.add_argument("--url-envio", default="", help="URL do formulario (modo candidatar).")
    analisador.add_argument("--curriculo", default="", help="Caminho do curriculo em PDF (modo candidatar).")
    analisador.add_argument("--timeout", type=int, default=0, help="Tempo limite da chamada, em milissegundos.")
    analisador.add_argument("--log", default="INFO", help="Nivel do log: DEBUG, INFO, WARNING ou ERROR.")

    argumentos = analisador.parse_args()

    log = configurar_log(argumentos.log)
    carregar_config()

    usuario_id = 0

    try:
        usuario_id = int(argumentos.usuario_id)
    except (TypeError, ValueError):
        usuario_id = 0

    try:
        if argumentos.modo == "buscar":
            if not argumentos.url_busca:
                raise ValueError("Informe a URL da busca (--url-busca).")

            limite = inteiro_config("bot", "maximo_vagas", 20)
            resultado = modo_buscar(argumentos.url_busca, usuario_id, limite, log)
        else:
            if not argumentos.url_envio:
                raise ValueError("Informe a URL do formulario (--url-envio).")

            if not argumentos.curriculo:
                raise ValueError("Informe o curriculo (--curriculo).")

            resultado = modo_candidatar(argumentos.url_envio, argumentos.curriculo, usuario_id, log)

    except Exception as erro:  # noqa: BLE001 - vira JSON de erro
        log.exception("Falha na execucao do bot")
        resultado = {
            "sucesso": False,
            "modo": argumentos.modo,
            "usuario_id": usuario_id,
            "erro": str(erro),
        }

    # A saida e UM UNICO JSON, sempre no stdout.
    print(json.dumps(resultado, ensure_ascii=False))

    # 0 = sucesso total, 1 = parcial, 2 = erro de infraestrutura.
    if resultado.get("sucesso"):
        if resultado.get("parcial"):
            return 1

        return 0

    erro = str(resultado.get("erro") or "")

    if "cloudflare" in erro.lower():
        return 1

    return 2


if __name__ == "__main__":
    sys.exit(main())
