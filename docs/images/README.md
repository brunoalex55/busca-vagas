<!--
Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.
-->

# Imagens do ambiente

Esta pasta guarda as capturas de tela do sistema, usadas na secao
**"Imagens do ambiente"** do README.

**As imagens aqui sao as capturas reais do sistema rodando** (com os
dados de demonstracao). Para usar as do seu ambiente, basta substituir
cada arquivo pela sua captura: o nome do arquivo e o que o README
aponta.

Nomes usados (o README ja aponta para eles):

| Arquivo | Tela |
|---|---|
| `login.png` | Login |
| `cadastro.png` | Cadastro de conta |
| `painel-usuario.png` | Painel do usuario (visao geral) |
| `vagas.png` | Listagem de vagas |
| `palavras-chave.png` | Formulario de palavras-chave e cidades |
| `divulgavagas.png` | Candidatura automatica (segundo provedor) |
| `upgrade-plano.png` | Janela de upgrade de plano |
| `admin-dashboard.png` | Visao geral do administrador |
| `planos.png` | CRUD dos planos |
| `pedidos-plano.png` | Pedidos de plano dos usuarios |
| `faturamento.png` | Relatorio de faturamento |

Para refazer as capturas (o servidor precisa estar no ar com
`php spark serve`):

```bash
python tools/gerar-capturas.py
```
