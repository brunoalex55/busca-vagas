<!--
Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.
-->

# Deploy em producao (Apache 2 + cron de sincronizacao)

Este documento conta como o BuscaVagas PRO esta no ar: o servidor
(Apache 2 com HTTPS), o banco, o phpMyAdmin e o **cron que sincroniza
tudo com o GitHub a cada 10 minutos**.

## O resumo

| Parte | Onde |
|---|---|
| Dominio | `buscavagas.brunoalexandre.com` (Cloudflare sem proxy, DNS direto) |
| Servidor | Ubuntu 24.04 (2 vCPU, 8 GB RAM) |
| Web | Apache 2.4 com mod_php **8.3.36** (corrigido) e HTTPS do Let's Encrypt |
| Codigo | `/var/www/buscavagas` (clone do GitHub) |
| Raiz web | `/var/www/buscavagas/public` (o resto nao e servido) |
| Banco | MariaDB 10.11, base `ci_employer` |
| phpMyAdmin | `https://buscavagas.brunoalexandre.com/phpmyadmin/` |
| Sincronizacao | `/etc/cron.d/deploy-buscavagas` (a cada 10 minutos) |
| Bot (2o provedor) | `/etc/cron.d/buscavagas-bot` (a cada 30 e 15 minutos) |
| Chrome do bot | servico `buscavagas-chrome.service` (systemd) |
| Logs do deploy | `/var/log/deploy-buscavagas.log` |

## O que o cron de deploy faz

A cada 10 minutos o servidor roda
`/usr/local/bin/deploy-buscavagas.sh`, que:

1. **verifica se ha mudanca local** (arquivo editado na mao): se houver,
   o deploy **para** e avisa no log — nada do que foi feito no servidor
   e sobrescrito;
2. roda `git pull --ff-only` (o repositorio e publico: o servidor nao
   guarda nenhuma credencial do GitHub);
3. roda `php spark migrate --all` (as migrations pendentes);
4. ajusta as permissoes da pasta `writable/`;
5. limpa o cache do CodeIgniter.

Se o `git pull` falhar, **o site continua no ar** com a versao
anterior (o deploy so troca o codigo quando o `pull` da certo).

Para ver o que aconteceu:

```bash
tail -f /var/log/deploy-buscavagas.log
```

Para forcar um deploy na hora:

```bash
sudo -u ubuntu /usr/local/bin/deploy-buscavagas.sh
```

## O que o cron do bot faz

O segundo provedor (Divulga Vagas) tem os proprios agendamentos:

```bash
# CRON 1: busca as vagas no site (a cada 30 minutos)
*/30 * * * * .../processar_fila.sh buscar-vagas

# CRON 2: candidata pelo formulario do site (a cada 30 minutos, 15 e 45)
15,45 * * * * .../processar_fila.sh candidatar
```

O Chrome que o bot usa e um **servico unico** (systemd), com perfil
proprio em `writable/automacoes-rpa/chrome/sessions` e a porta CDP em
`127.0.0.1:9222`. O Chrome do administrador nunca e tocado.

```bash
systemctl status buscavagas-chrome.service
```

## O PHP e os avisos de RCE de 21/10/2026

O PHP publicou em 21/10/2026 dois avisos de **execucao remota de codigo
sem autenticacao** (CVE-2026-109618 e CVE-2026-109619). O aviso e
explicito: *"only an update is a complete solution"*.

**O que foi feito no servidor:**

| | Antes | Agora |
|---|---|---|
| PHP (CLI e mod_php) | 8.3.6 | **8.3.36** (PPA do ondrej/php) |

**As medidas de contencao** (aplicadas enquanto a atualizacao nao
chegava, e mantidas como camada extra):

| Medida | Onde |
|---|---|
| `session.upload_progress.enabled = 0` | `/etc/php/8.3/{cli,apache2,fpm}/conf.d/99-seguranca.ini` |
| `php_admin_value memory_limit -1` | `/etc/apache2/conf-available/seguranca-php.conf` |
| `post_max_size = 32M` e `upload_max_filesize = 16M` | o mesmo conf do Apache |
| `LimitRequestBody`, `LimitRequestFields` e `LimitRequestLine` | `/etc/apache2/conf-available/limites-requisicao.conf` |
| `MemoryMax=1G` e `MemoryHigh=768M` | `/etc/systemd/system/apache2.service.d/limites.conf` |

**Depois da atualizacao, por ter havido exposicao, foi rotacionado:** a
senha do MySQL (root e do sistema), a `CRIPTOGRAFIA_CHAVE` do `.env`, o
token global do cron, a senha do administrador e as sessoes do painel.

O sistema tambem se protege sozinho: `public/index.php` e `spark` recusam
rodar em um PHP vulneravel (a conference e por serie) e o `composer.json`
exige uma versao corrigida.

---## Os comandos do dia a dia

```bash
# o estado do Apache e do certificado
systemctl status apache2
certbot certificates

# as migrations pendentes
cd /var/www/buscavagas && php spark migrate:status

# o log do projeto (CodeIgniter)
tail -f /var/www/buscavagas/writable/logs/log-*.log

# o log do Apache
tail -f /var/log/apache2/buscavagas-error.log
```

## Onde ficam as credenciais

- O `.env` do projeto: `/var/www/buscavagas/.env` (modo 640, dono
  `ubuntu`, grupo `www-data`).
- A senha do MySQL root e a do usuario do sistema:
  `/root/credenciais-buscavagas.txt` (modo 600, so o root le).
- O token global do cron: `writable/cron_token.txt` (fora do Git).

**Troque a senha do administrador no primeiro acesso**
(`/admin/conta`) e a senha do MySQL root.
