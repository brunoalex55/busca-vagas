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

# -*- coding: utf-8 -*-
"""
MOCK DA API DO CRON-JOB.ORG (PARA OS TESTES)
==============================================

Este servidor imita a API do cron-job.org (na parte que o projeto usa)
para que os testes rodem sem internet e sem depender de uma conta real.

Ele responde:

    PUT    /jobs            cria um job        -> {"jobId": 1}
    GET    /jobs            lista os jobs      -> {"jobs": [...]}
    GET    /jobs/{id}       devolve um job     -> {"job": {...}}
    PATCH  /jobs/{id}       atualiza um job    -> {"jobId": id}
    DELETE /jobs/{id}       apaga um job        -> {"jobId": id}
    GET    /jobs/{id}/history  historico        -> {"history": [...]}

A autenticacao e pelo cabecalho "Authorization: Bearer <chave>". Sem
chave (ou com chave vazia) a resposta e 401, como no servico real.

Os jobs ficam so na memoria: o servidor pode ser derrubado a qualquer
momento sem perder nada do projeto.

Uso:
    python tools/teste-cronjob-mock.py
"""

import json
import re
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# A porta do mock (a mesma que a revisao final aponta no .env).
PORTA = 8099

# OJobs do mock (id -> job).
JOBS = {}

# O proximo id de job.
PROXIMO_ID = [1]

# Trava do dicionario (o mock atende varias requisicoes ao mesmo tempo).
TRAVA = threading.Lock()


def job_de_exemplo(job_id: int, job: dict) -> dict:
    """Monta o job no formato que o servico devolve."""
    completo = {
        "id": job_id,
        "title": job.get("title", "job de teste"),
        "url": job.get("url", "http://127.0.0.1/"),
        "enabled": bool(job.get("enabled", True)),
        "schedule": job.get("schedule", {}),
        "requestMethod": job.get("requestMethod", 1),
        "createdAt": 1700000000,
    }

    return completo


class Manipulador(BaseHTTPRequestHandler):
    """Responde as chamadas da API do cron-job.org."""

    def responder(self, codigo: int, dados: dict) -> None:
        """Manda a resposta em JSON."""
        corpo = json.dumps(dados, ensure_ascii=False).encode("utf-8")

        self.send_response(codigo)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(corpo)))
        self.end_headers()
        self.wfile.write(corpo)

    def autorizado(self) -> bool:
        """A requisicao tem a chave de API?"""
        cabecalho = self.headers.get("Authorization", "")

        return cabecalho.strip() != "" and cabecalho.strip() != "Bearer"

    def ler_corpo(self) -> dict:
        """Le o corpo JSON da requisicao (vazio quando nao houver)."""
        tamanho = int(self.headers.get("Content-Length", "0") or 0)

        if tamanho <= 0:
            return {}

        bruto = self.rfile.read(tamanho)

        try:
            dados = json.loads(bruto.decode("utf-8"))
        except (ValueError, UnicodeDecodeError):
            return {}

        if not isinstance(dados, dict):
            return {}

        return dados

    def do_PUT(self):  # noqa: N802 - nome imposto pela biblioteca
        """Cria um job (PUT /jobs)."""
        if not self.autorizado():
            self.responder(401, {"error": "Unauthorized"})
            return

        corpo = self.ler_corpo()
        job = corpo.get("job", {})

        with TRAVA:
            job_id = PROXIMO_ID[0]
            PROXIMO_ID[0] += 1
            JOBS[job_id] = job_de_exemplo(job_id, job)

        self.responder(200, {"jobId": job_id})

    def do_GET(self):  # noqa: N802 - nome imposto pela biblioteca
        """Lista os jobs ou devolve um job (GET /jobs)."""
        if not self.autorizado():
            self.responder(401, {"error": "Unauthorized"})
            return

        caminho = self.path.split("?")[0].rstrip("/")

        if caminho == "/jobs":
            with TRAVA:
                lista = list(JOBS.values())

            self.responder(200, {"jobs": lista})
            return

        historico = re.match(r"^/jobs/(\d+)/history$", caminho)

        if historico:
            self.responder(200, {"history": []})
            return

        um_job = re.match(r"^/jobs/(\d+)$", caminho)

        if um_job:
            job_id = int(um_job.group(1))

            with TRAVA:
                job = JOBS.get(job_id)

            if job is None:
                self.responder(404, {"error": "Job not found"})
                return

            self.responder(200, {"job": job})
            return

        self.responder(404, {"error": "Not found"})

    def do_PATCH(self):  # noqa: N802 - nome imposto pela biblioteca
        """Atualiza um job (PATCH /jobs/{id})."""
        if not self.autorizado():
            self.responder(401, {"error": "Unauthorized"})
            return

        corpo = self.ler_corpo()
        job = corpo.get("job", {})

        caminho = self.path.split("?")[0].rstrip("/")
        um_job = re.match(r"^/jobs/(\d+)$", caminho)

        if not um_job:
            self.responder(404, {"error": "Not found"})
            return

        job_id = int(um_job.group(1))

        with TRAVA:
            if job_id not in JOBS:
                self.responder(404, {"error": "Job not found"})
                return

            JOBS[job_id] = job_de_exemplo(job_id, job)

        self.responder(200, {"jobId": job_id})

    def do_DELETE(self):  # noqa: N802 - nome imposto pela biblioteca
        """Apaga um job (DELETE /jobs/{id})."""
        if not self.autorizado():
            self.responder(401, {"error": "Unauthorized"})
            return

        caminho = self.path.split("?")[0].rstrip("/")
        um_job = re.match(r"^/jobs/(\d+)$", caminho)

        if not um_job:
            self.responder(404, {"error": "Not found"})
            return

        job_id = int(um_job.group(1))

        with TRAVA:
            JOBS.pop(job_id, None)

        self.responder(200, {"jobId": job_id})

    def log_message(self, formato, *args):
        """Silencia o log (o mock nao precisa poluir o console)."""
        return


def main() -> None:
    """Sobe o mock."""
    servidor = ThreadingHTTPServer(("127.0.0.1", PORTA), Manipulador)

    print("mock do cron-job.org em http://127.0.0.1:%d" % PORTA, flush=True)

    try:
        servidor.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        servidor.server_close()


if __name__ == "__main__":
    main()
