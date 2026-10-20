"""
Servidor local de teste (espelha a estrutura do Divulga Vagas).

Usa threads para atender varias requisicoes ao mesmo tempo: o servidor
embutido do PHP e de thread unica e trava quando o navegador abre
conexoes em paralelo.

Rotas:
  /listagem.html    -> a listagem com os cards
  /vaga.html        -> a pagina de uma vaga
  /aviso.html       -> a pagina de aviso
  /formulario.html  -> o formulario de candidatura
  /sucesso.html     -> a pagina de sucesso
"""

import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

CABECALHO = """<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Divulga Vagas (local)</title></head>
<body>
"""

VAGAS = [
    ("auxiliar-administrativo-34762271", "Auxiliar Administrativo", "Impar Inclusao e Diversidade", "Curitiba - PR",
     "Vaga de administrativo. Escreva para rh&ZeroWidthSpace;@manpower.com.br sobre a vaga."),
    ("auxiliar-de-loja-34765224", "Auxiliar De Loja", "Manpower Staffing", "Belo Horizonte - MG",
     "Auxiliar de loja no setor de provados. Sem e-mail de contacto."),
    ("fiscal-de-caixa-34765123", "Fiscal de Caixa", "Rede Comercional", "Curitiba - PR",
     "Responsavel pelo caixa e pelo fechamento. Contato: rh@rede-comercial.com.br"),
]


def pagina_listagem() -> str:
    partes = [CABECALHO, '<main class="dv-lista" data-total-paginas="1" data-estado="PR">']

    for slug, titulo, empresa, local, resumo in VAGAS:
        partes.append('<article class="dv-vaga">')
        partes.append('  <div class="dv-vaga__topo"><span>Publicada hoje</span></div>')
        # O titulo do card e um <h2> (igual ao site real).
        partes.append(f'  <h2 class="dv-vaga__titulo"><a class="dv-vaga__link" href="/vaga.html?vaga={slug}">{titulo}</a></h2>')
        partes.append(f'  <p class="dv-vaga__empresa">{empresa}</p>')
        partes.append(f'  <ul class="dv-vaga__meta"><li>{local}</li><li class="is-salario"><span>R$ 1.900,00</span></li><li><span>Administracao</span></li></ul>')
        partes.append(f'  <p class="dv-vaga__resumo">{resumo}</p>')
        partes.append('  <div class="dv-vaga__rodape"><span class="dv-vaga__ver">Ver vaga</span></div>')
        partes.append('</article>')

    partes.append('</main>')

    return "".join(partes)


def pagina_vaga() -> str:
    return CABECALHO + (
        '<main class="dv-job job-container" id="dv-job" data-job-id="34762271" '
        'data-job-title="Auxiliar Administrativo" data-job-location="Curitiba - PR">'
        '<section class="dv-card job-card"><h2 class="dv-card__title">Detalhes da Vaga</h2>'
        '<div class="dv-desc job-description">Vaga de administrativo. Escreva para '
        'rh&ZeroWidthSpace;@manpower.com.br sobre a vaga.</div>'
        '</section>'
        '<section class="dv-card job-card"><h2 class="dv-card__title">Requisitos</h2>'
        '<div class="dv-desc job-description">Ensino medio completo.</div>'
        '</section>'
        '<section class="dv-card job-card"><h2 class="dv-card__title">Beneficios</h2>'
        '<div class="dv-desc job-description">Vale transporte e vale alimentacao.</div>'
        '</section>'
        '<div class="dv-hero__acoes job-apply-section">'
        '<a href="/aviso.html" class="dv-cta btn-apply">Enviar meu curriculo</a></div>'
        '</main>'
    )


def pagina_aviso() -> str:
    return CABECALHO + (
        '<main class="dv-aviso" id="dv-aviso"><section class="dv-card dv-aviso__card">'
        '<h1 class="dv-aviso__titulo">Leia com atencao</h1>'
        '<div class="dv-aviso__acoes">'
        '<a class="dv-cta dv-cta--lg btn-apply" id="btn-apply-link" href="/formulario.html">'
        'Entendi, enviar meu curriculo</a></div>'
        '</section></main>'
    )


def pagina_formulario() -> str:
    return CABECALHO + (
        '<main class="dv-aviso dv-cv" id="dv-cv"><section class="dv-card dv-aviso__card dv-cv__card">'
        '<h1 class="dv-aviso__titulo">Envie seu curriculo</h1>'
        '<form method="POST" action="/sucesso.html" enctype="multipart/form-data" id="cvForm" '
        'class="dv-cv__form" data-vaga="34762271" novalidate>'
        '<input type="hidden" name="id_vaga" id="cv_id" value="34762271">'
        '<input type="hidden" name="dv_tk" value="0.0.teste">'
        '<input id="arquivo_1" type="file" class="dv-cv__input" name="curriculo" accept=".pdf,.doc,.docx" required>'
        '<div class="dv-cv__termos"><label class="dv-cv__check">'
        '<input type="checkbox" id="politica" name="politica" required>'
        '<span>Li e aceito os Termos de Uso</span></label></div>'
        '<button id="enviarCV" type="submit" class="dv-cta dv-cta--lg dv-cv__enviar">Enviar curriculo</button>'
        '</form></section></main>'
    )


def pagina_sucesso() -> str:
    return CABECALHO + (
        '<main class="dv-aviso dv-cv" id="dv-cv"><section class="dv-card dv-aviso__card">'
        '<h1 class="dv-aviso__titulo">Curriculo enviado com sucesso</h1>'
        '<p class="dv-aviso__sub">O curriculo foi enviado para a empresa.</p>'
        '</section></main>'
    )


class Manipulador(BaseHTTPRequestHandler):
    def do_GET(self):  # noqa: N802 - nome imposto pela biblioteca
        caminho = self.path.split("?")[0]

        if "listagem" in caminho:
            corpo = pagina_listagem()
        elif "vaga.html" in caminho:
            corpo = pagina_vaga()
        elif "aviso" in caminho:
            corpo = pagina_aviso()
        elif "formulario" in caminho:
            corpo = pagina_formulario()
        elif "sucesso" in caminho:
            corpo = pagina_sucesso()
        else:
            corpo = CABECALHO + "<p>Pagina de teste.</p>"

        dados = corpo.encode("utf-8")

        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(dados)))
        self.end_headers()
        self.wfile.write(dados)

    def do_POST(self):  # noqa: N802 - nome imposto pela biblioteca
        # O formulario de candidatura responde com a pagina de sucesso.
        tamanho = int(self.headers.get("Content-Length", 0) or 0)

        if tamanho:
            self.rfile.read(tamanho)

        dados = pagina_sucesso().encode("utf-8")

        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(dados)))
        self.end_headers()
        self.wfile.write(dados)

    def log_message(self, formato, *args):
        # Silencia o log do servidor (o teste printa o que importa).
        return


if __name__ == "__main__":
    servidor = ThreadingHTTPServer(("127.0.0.1", 8097), Manipulador)

    print("site local em http://127.0.0.1:8097", flush=True)

    servidor.serve_forever()
