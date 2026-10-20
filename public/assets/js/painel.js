'use strict';

/**
 * --------------------------------------------------------------------------
 * JAVASCRIPT DO PAINEL (USUARIO E ADMIN)
 * --------------------------------------------------------------------------
 *
 * Responsabilidades (apenas UX visual):
 *   - abrir e fechar o menu lateral no mobile;
 *   - animar elementos ao entrar na viewport;
 *   - confirmar acoes destrutivas (data-confirmar em forms);
 *   - mostrar o nome do arquivo escolhido (data-nome-alvo).
 *
 * Observacao: os toasts, a copia para a area de transferencia e as
 * mascaras ficam em utilidades.js, carregado antes deste arquivo.
 *
 * Nada aqui decide permissao ou regra de negocio: toda a validacao
 * continua no PHP. Estes recursos sao apenas conveniencia visual.
 */

(function () {
    /**
     * Executa um callback quando o DOM estiver pronto.
     *
     * @param {Function} callback Funcao a executar.
     * @returns {void}
     */
    function aoPronto(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    /**
     * Controla o menu lateral no mobile.
     *
     * @returns {void}
     */
    function iniciarMenuLateral() {
        // Botao hamburguer do painel.
        const botao = document.getElementById('menu-painel-toggle');

        // Menu lateral.
        const menu = document.getElementById('painel-menu');

        // Se nao existirem, nao faz nada.
        if (!botao || !menu) {
            return;
        }

        /**
         * Abre ou fecha o menu lateral.
         *
         * @param {boolean} abrir Forca o estado quando informado.
         * @returns {void}
         */
        function alternar(abrir) {
            // Descobre o estado desejado.
            const deveAbrir = typeof abrir === 'boolean'
                ? abrir
                : !menu.classList.contains('painel__menu--aberto');

            // Aplica a classe visual.
            menu.classList.toggle('painel__menu--aberto', deveAbrir);

            // Atualiza a acessibilidade do botao.
            botao.setAttribute('aria-expanded', deveAbrir ? 'true' : 'false');
            botao.setAttribute('aria-label', deveAbrir ? 'Fechar menu' : 'Abrir menu');
        }

        // Clique no botao.
        botao.addEventListener('click', function () {
            alternar();
        });

        // Fecha ao clicar em um link do menu.
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                alternar(false);
            });
        });

        // Fecha ao pressionar Esc.
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                alternar(false);
            }
        });

        // Fecha ao clicar fora do menu.
        document.addEventListener('click', function (evento) {
            const clicouNoMenu = menu.contains(evento.target);
            const clicouNoBotao = botao.contains(evento.target);

            if (!clicouNoMenu && !clicouNoBotao) {
                alternar(false);
            }
        });

        // Ao voltar para o desktop, garante o menu fechado.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                alternar(false);
            }
        });
    }

    /**
     * Anima os elementos .ao-rolar dentro do painel.
     *
     * @returns {void}
     */
    function iniciarAnimacoes() {
        // Elementos animaveis.
        const elementos = document.querySelectorAll('.ao-rolar');

        // Navegador sem suporte: mostra tudo.
        if (!('IntersectionObserver' in window)) {
            elementos.forEach(function (elemento) {
                elemento.classList.add('visivel');
            });
            return;
        }

        // Observador de entrada na viewport.
        const observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting) {
                    return;
                }

                // Torna visivel e para de observar.
                entrada.target.classList.add('visivel');
                observador.unobserve(entrada.target);
            });
        }, {
            threshold: 0.1
        });

        elementos.forEach(function (elemento) {
            observador.observe(elemento);
        });
    }

    /**
     * Confirma acoes destrutivas.
     *
     * Formularios marcados com data-confirmar pedem confirmacao antes
     * de efetivar o envio (ex.: excluir palavra-chave, remover PDF).
     *
     * @returns {void}
     */
    function iniciarConfirmacoes() {
        document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
            formulario.addEventListener('submit', function (evento) {
                // Mensagem definida no proprio formulario.
                const mensagem = formulario.dataset.confirmar;

                // Sem mensagem: nao interfere.
                if (!mensagem) {
                    return;
                }

                // Cancela o envio quando o usuario recusa.
                if (!window.confirm(mensagem)) {
                    evento.preventDefault();
                }
            });
        });
    }

    /**
     * Mostra o nome do arquivo escolhido em um campo de upload.
     *
     * O input usa data-nome-alvo="id-do-texto-que-recebe-o-nome".
     *
     * @returns {void}
     */
    function iniciarPreviewArquivo() {
        document.querySelectorAll('input[type="file"][data-nome-alvo]').forEach(function (campo) {
            // Elemento que exibe o nome/ajuda do arquivo.
            const alvo = document.getElementById(campo.dataset.nomeAlvo);

            // Sem alvo, nao ha o que atualizar.
            if (!alvo) {
                return;
            }

            // Guarda o texto original de ajuda.
            const textoOriginal = alvo.textContent;

            campo.addEventListener('change', function () {
                // Nenhum arquivo escolhido: volta a ajuda original.
                if (!campo.files || campo.files.length === 0) {
                    alvo.textContent = textoOriginal;
                    return;
                }

                // Mostra o nome do arquivo selecionado.
                alvo.textContent = 'Arquivo selecionado: ' + campo.files[0].name;
            });
        });
    }

    /**
     * Execucao manual do Divulga Vagas sem recarregar a pagina.
     *
     * Os formularios marcados com data-execucao-divulgavagas sao enviados
     * por fetch: a resposta traz o resumo e a barra de limite do dia e
     * atualizada na hora. Sem JavaScript, o formulario funciona do mesmo
     * jeito (o servidor responde com um redirect e uma mensagem).
     *
     * @returns {void}
     */
    function iniciarExecucaoDivulgaVagas() {
        document.querySelectorAll('form[data-execucao-divulgavagas]').forEach(function (formulario) {
            formulario.addEventListener('submit', function (evento) {
                // Sem suporte a fetch: deixa o envio normal.
                if (!window.fetch || !window.FormData) {
                    return;
                }

                evento.preventDefault();

                // Botao da execucao (fica desabilitado durante o envio).
                const botao = formulario.querySelector('button[type="submit"]');

                // Guarda o conteudo original (com o icone) para restaurar.
                const conteudoOriginal = botao ? botao.innerHTML : '';

                if (botao) {
                    botao.disabled = true;
                    botao.textContent = 'Executando...';
                }

                fetch(formulario.action, {
                    method: 'POST',
                    body: new FormData(formulario),
                    headers: { 'X-Requested-With': 'fetch' },
                    redirect: 'follow'
                })
                    .then(function (resposta) {
                        // O servidor responde JSON quando o pedido vem do fetch.
                        return resposta.json().catch(function () {
                            return null;
                        });
                    })
                    .then(function (dados) {
                        if (!dados) {
                            // Resposta sem JSON: recarrega a pagina.
                            window.location.reload();
                            return;
                        }

                        // Mensagem do servidor (sucesso ou erro).
                        const mensagem = dados.mensagem || 'Execucao concluida.';

                        if (typeof window.toast === 'function') {
                            window.toast(mensagem, dados.sucesso ? 'sucesso' : 'erro');
                        }

                        // Atualiza a barra do limite do dia (a do proprio
                        // cartao do formulario).
                        if (typeof dados.restantes === 'number') {
                            const cartao = formulario.closest('.cartao');
                            const barra = cartao ? cartao.querySelector('.barra-limite-manual') : null;

                            if (barra) {
                                const usados = barra.querySelector('.barra-limite-manual__topo strong');
                                const valor = barra.querySelector('.barra-progresso__valor');
                                const limite = parseInt(dados.limite || '0', 10);
                                const novas = parseInt(dados.usadas || '0', 10);
                                const percentual = limite > 0 ? Math.min(100, Math.round(novas * 100 / limite)) : 0;

                                if (usados) {
                                    usados.textContent = novas + ' / ' + limite;
                                }

                                if (valor) {
                                    valor.style.width = percentual + '%';
                                }
                            }
                        }
                    })
                    .catch(function (erro) {
                        if (typeof window.toast === 'function') {
                            window.toast('Falha na execucao: ' + erro.message, 'erro');
                        }
                    })
                    .finally(function () {
                        if (botao) {
                            botao.disabled = false;
                            botao.innerHTML = conteudoOriginal;
                        }
                    });
            });
        });
    }

    /**
     * Alvo da execucao manual do Divulga Vagas (administrador).
     *
     * A tela do admin tem um seletor de alvo (todos os usuarios ou um
     * usuario so) e dois formularios de execucao. O valor do seletor
     * e copiado para os campos escondidos dos formularios.
     *
     * @returns {void}
     */
    function iniciarAlvoDivulgaVagas() {
        const seletor = document.getElementById('alvo');

        if (!seletor) {
            return;
        }

        // Os dois formularios de execucao (buscar e candidatar).
        const alvos = ['alvo-buscar', 'alvo-candidatar'];
        const usuarios = ['usuario-buscar', 'usuario-candidatar'];

        function copiar() {
            const valor = seletor.value;
            const ehTodos = valor === 'todos';

            alvos.forEach(function (id) {
                const campo = document.getElementById(id);

                if (campo) {
                    campo.value = ehTodos ? 'todos' : 'usuario';
                }
            });

            usuarios.forEach(function (id) {
                const campo = document.getElementById(id);

                if (campo) {
                    campo.value = ehTodos ? '' : valor;
                }
            });
        }

        seletor.addEventListener('change', copiar);

        // Aplica o valor inicial.
        copiar();
    }

    // Inicializacao.
    aoPronto(function () {
        iniciarMenuLateral();
        iniciarAnimacoes();
        iniciarConfirmacoes();
        iniciarPreviewArquivo();
        iniciarExecucaoDivulgaVagas();
        iniciarAlvoDivulgaVagas();
    });
})();
