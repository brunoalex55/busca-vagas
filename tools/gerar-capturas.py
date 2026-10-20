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
Gera as capturas de tela do ambiente (docs/images/).

Usa o Playwright com o Chrome instalado no sistema (o mesmo canal do
bot do Divulga Vagas): nao baixa nenhum navegador e nao mexe no Chrome
que o usuario esta usando.

O servidor precisa estar no ar (php spark serve) e o banco com o
usuario de demonstracao.

Uso:
    python tools/gerar-capturas.py
"""

import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

# A pasta do projeto (a pasta deste arquivo).
RAIZ = Path(__file__).resolve().parent.parent

# Onde as imagens vao parar.
DESTINO = RAIZ / "docs" / "images"

# O endereco do sistema.
BASE = "http://localhost:8080"

# O tamanho da janela (cabe laptop e monitor).
LARGURA = 1440
ALTURA = 900

# O admin e o usuario de demonstracao.
ADMIN = {"email": "admin@admin.com", "senha": "admin123"}
USUARIO = {"email": "fase9-modal@teste.local", "senha": "teste123"}


def entrar(page, credenciais):
    """Loga no sistema."""
    page.goto(f"{BASE}/login", wait_until="networkidle")
    page.fill("#email", credenciais["email"])
    page.fill("#senha", credenciais["senha"])
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")


def capturar(page, nome, url, tela_inteira=True, antes=None):
    """Abre a tela e salva a imagem."""
    page.goto(f"{BASE}{url}", wait_until="networkidle")
    page.wait_for_timeout(900)

    if antes is not None:
        antes(page)
        page.wait_for_timeout(700)

    caminho = DESTINO / f"{nome}.png"
    page.screenshot(path=str(caminho), full_page=tela_inteira)
    print(f"  OK {nome}.png")


def main():
    """Gera todas as capturas."""
    DESTINO.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        navegador = p.chromium.launch(channel="chrome", headless=True)

        # ------------------------------------------------- o site e o painel
        contexto = navegador.new_context(
            viewport={"width": LARGURA, "height": ALTURA},
            device_scale_factor=1,
            locale="pt-BR",
        )
        page = contexto.new_page()

        print("-- o site publico --")
        capturar(page, "login", "/login")
        capturar(page, "cadastro", "/registro")

        print("-- o painel do usuario --")
        entrar(page, USUARIO)
        capturar(page, "painel-usuario", "/painel")
        capturar(page, "vagas", "/painel/vagas")
        capturar(page, "palavras-chave", "/painel/palavras-chave")
        capturar(page, "divulgavagas", "/painel/divulgavagas")

        # O modal do upgrade de plano (so a tela: a sobreposicao cobre
        # tudo).
        capturar(
            page,
            "upgrade-plano",
            "/painel",
            tela_inteira=False,
            antes=lambda p: p.click("[data-modal-abrir='modal-plano']"),
        )

        # ------------------------------------------------ o painel do admin
        print("-- o painel do administrador --")
        # Um contexto novo (o outro ainda esta logado como o usuario).
        contexto_admin = navegador.new_context(
            viewport={"width": LARGURA, "height": ALTURA},
            device_scale_factor=1,
            locale="pt-BR",
        )
        page_admin = contexto_admin.new_page()

        entrar(page_admin, ADMIN)
        capturar(page_admin, "admin-dashboard", "/admin")
        capturar(page_admin, "planos", "/admin/planos")
        capturar(page_admin, "pedidos-plano", "/admin/planos/solicitacoes")
        capturar(page_admin, "faturamento", "/admin/faturamento")

        navegador.close()

    print("fim")


if __name__ == "__main__":
    sys.exit(main())
