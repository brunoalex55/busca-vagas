<!--
Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.
-->

# CI_EMPLOYER — Regras do projeto

> Este arquivo é a fonte da verdade para qualquer pessoa (ou agente) que
> trabalhe neste repositório. Leia antes de escrever código.

## Stack obrigatória

| Camada | Tecnologia |
|---|---|
| Backend | CodeIgniter 4 — arquitetura **monolítica** |
| Banco | MySQL |
| Frontend | HTML5 semântico + CSS3 puro |
| JavaScript | Vanilla ES6+ (mínimo) |
| Bibliotecas PHP | Puras, sempre que possível |
| API externa | Adaptador de Provedor de Vagas (padrão: SerpApi Google Jobs) |
| E-mail | SMTP Gmail com App Password — **por usuário, nunca global** |

### Monolito

Site público e sistema administrativo vivem no **mesmo projeto**,
nas **mesmas rotas**, compartilhando os **mesmos assets**.

- Site público: `/`, `/vagas`, `/empresas`, ...
- Sistema: `/painel`, `/painel/vagas`, ...
- Não criar subdomínios, não criar segundo projeto, não duplicar assets.

## Regras absolutas (não negociáveis)

1. **ZERO Node.js** — sem `npm`, sem `package.json`, sem bundlers.
2. **ZERO framework CSS** — proibido Bulma, Bootstrap, Tailwind, Materialize, Foundation, UIkit, ou qualquer equivalente.
3. **ZERO framework JS** — proibido React, Vue, Angular, Svelte, Alpine, jQuery.
4. Apenas **HTML5 semântico + CSS3 puro** (Flexbox, Grid, CSS Variables).
5. JavaScript vanilla **apenas** para:
   - menus responsivos
   - mostrar/ocultar senha
   - preview de upload
   - toasts / notificações visuais
   - máscaras simples de input
   - copiar para clipboard
   - animações de scroll (`IntersectionObserver`)
   - contadores animados na landing page
6. CSS moderno: gradientes suaves, sombras elegantes, transições, hover.
7. Design **mobile-first** e responsivo.

> Regra de bolso: se a solução exige uma dependência de front-end,
> a resposta é CSS ou JS vanilla.

## Ambiente local

Nada foi instalado no sistema operacional. Tudo é portátil.

| Componente | Versão | Caminho |
|---|---|---|
| PHP | 8.4.26 (NTS x64) | `C:\Users\Rafael Albanese\AppData\Local\php84\php.exe` |
| Composer | 2.10.3 | `...\php84\composer.phar` |
| MariaDB | 11.8.9 LTS | `C:\Users\Rafael Albanese\AppData\Local\MariaDB` |
| Projeto | CI 4.7.5 | `C:\Users\Rafael Albanese\Desktop\CI_EMPLOYER` |

> **MySQL vs MariaDB:** o banco em uso é o **MariaDB 11.8 LTS**, que é
> drop-in compatible com MySQL e usa o driver `MySQLi` do PHP. Se em
> algum momento for exigido MySQL "puro" (Oracle), a migração é apenas
> trocar o servidor — nenhum código muda. Requisitos específicos de
> MySQL 8 (ex.: `CHECK` constraints, `JSON_TABLE`) devem ser evitados.

### Comandos

```powershell
.\php.cmd spark serve          # http://localhost:8080
.\php.cmd spark migrate
.\php.cmd spark make:controller Nome
.\composer.cmd require pacote
.\db-start.cmd                 # sobe o MariaDB (não é serviço)
.\db-stop.cmd                  # derruba o MariaDB
```

O MariaDB **não roda como serviço do Windows**. Após reiniciar a máquina,
é preciso executar `.\db-start.cmd` (ou `serve.cmd`, que sobe os dois).

### Banco

- Host: `127.0.0.1:3306` (bind apenas em loopback, sem exposição na rede)
- Database: `ci_employer`
- Usuário: `ci_employer` (privilégios restritos ao schema)
- Credenciais: em `.env`, que **não** é versionado

## Convenções

- **SQL**: sempre Query Builder ou bindings. Nunca concatenar input em SQL.
- **E-mail SMTP**: as credenciais (e-mail + App Password) são por usuário,
  armazenadas cifradas e **nunca** em config global, `.env` ou código.
- **Provedor de vagas**: sempre atrás de uma interface
  (`App\Contracts\JobsProviderInterface`), para trocar o SerpApi sem
  tocar no restante da aplicação.
- **Views**: um layout base em `app/Views/layouts/`, partials reutilizáveis.
- **CSS**: arquivos em `public/assets/css/`, sem CDN, sem framework.
- **JS**: arquivos em `public/assets/js/`, ES6+, sem CDN, sem framework.
