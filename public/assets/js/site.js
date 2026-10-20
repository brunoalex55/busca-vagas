'use strict';

/**
 * --------------------------------------------------------------------------
 * JAVASCRIPT DO SITE PUBLICO
 * --------------------------------------------------------------------------
 *
 * Responsabilidades (apenas UX visual):
 *   - abrir e fechar o menu mobile;
 *   - adicionar sombra ao header ao rolar a pagina;
 *   - animar elementos ao entrar na viewport (IntersectionObserver);
 *   - animar os contadores da landing page.
 *
 * Nenhuma regra de negocio, validacao ou dado sensivel fica aqui.
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
     * Controla o menu hamburguer no mobile.
     *
     * @returns {void}
     */
    function iniciarMenuMobile() {
        // Elementos do menu.
        const botao = document.getElementById('menu-toggle');
        const navegacao = document.getElementById('navegacao');

        // Se a pagina nao tem menu (ex.: login), nao faz nada.
        if (!botao || !navegacao) {
            return;
        }

        /**
         * Abre ou fecha o menu conforme o estado atual.
         *
         * @param {boolean} abrir Forca o estado quando informado.
         * @returns {void}
         */
        function alternar(abrir) {
            // Descobre o estado desejado.
            const deveAbrir = typeof abrir === 'boolean'
                ? abrir
                : !navegacao.classList.contains('navegacao--aberta');

            // Aplica as classes visuais.
            navegacao.classList.toggle('navegacao--aberta', deveAbrir);
            botao.classList.toggle('menu-toggle--aberto', deveAbrir);

            // Atualiza a acessibilidade.
            botao.setAttribute('aria-expanded', deveAbrir ? 'true' : 'false');
            botao.setAttribute('aria-label', deveAbrir ? 'Fechar menu' : 'Abrir menu');
        }

        // Clique no botao hamburguer.
        botao.addEventListener('click', function () {
            alternar();
        });

        // Fecha ao clicar em qualquer link do menu.
        navegacao.querySelectorAll('a').forEach(function (link) {
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

        // Fecha ao clicar fora do menu (mantendo o botao funcional).
        document.addEventListener('click', function (evento) {
            const clicouDentro = navegacao.contains(evento.target);
            const clicouBotao = botao.contains(evento.target);

            if (!clicouDentro && !clicouBotao) {
                alternar(false);
            }
        });

        // Se a tela aumentar para desktop, garante o menu fechado.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                alternar(false);
            }
        });
    }

    /**
     * Adiciona a classe de sombra ao header quando a pagina e rolada.
     *
     * @returns {void}
     */
    function iniciarHeaderRolagem() {
        // Elemento do header.
        const cabecalho = document.getElementById('cabecalho');

        // Sem header, nao faz nada.
        if (!cabecalho) {
            return;
        }

        /**
         * Atualiza a classe conforme a posicao da rolagem.
         *
         * @returns {void}
         */
        function atualizar() {
            cabecalho.classList.toggle('cabecalho--rolado', window.scrollY > 8);
        }

        // Escuta a rolagem (passive melhora a performance).
        window.addEventListener('scroll', atualizar, { passive: true });

        // Aplica o estado inicial ao carregar.
        atualizar();
    }

    /**
     * Anima os elementos com a classe .ao-rolar quando entram na tela.
     *
     * @returns {void}
     */
    function iniciarAnimacoesScroll() {
        // Todos os elementos animaveis.
        const elementos = document.querySelectorAll('.ao-rolar');

        // Navegador sem suporte: mostra tudo imediatamente.
        if (!('IntersectionObserver' in window)) {
            elementos.forEach(function (elemento) {
                elemento.classList.add('visivel');
            });
            return;
        }

        // Observador com margem para iniciar um pouco antes de aparecer.
        const observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                // Ignora elementos que sairam da tela.
                if (!entrada.isIntersecting) {
                    return;
                }

                // Torna visivel.
                entrada.target.classList.add('visivel');

                // Para de observar depois da primeira animacao.
                observador.unobserve(entrada.target);
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        // Registra cada elemento.
        elementos.forEach(function (elemento) {
            observador.observe(elemento);
        });
    }

    /**
     * Anima os contadores (data-contador) quando ficam visiveis.
     *
     * @returns {void}
     */
    function iniciarContadores() {
        // Todos os contadores da pagina.
        const contadores = document.querySelectorAll('[data-contador]');

        // Sem contadores, encerra.
        if (contadores.length === 0) {
            return;
        }

        // Formatador de numeros no padrao brasileiro (1.200).
        const formatador = new Intl.NumberFormat('pt-BR');

        /**
         * Anima um contador de zero ate o valor final.
         *
         * @param {HTMLElement} elemento Elemento do contador.
         * @returns {void}
         */
        function animar(elemento) {
            // Valor final definido no atributo data-contador.
            const valorFinal = parseInt(elemento.dataset.contador, 10) || 0;

            // Duracao total da animacao em milissegundos.
            const duracao = 1400;

            // Marca o inicio.
            const inicio = performance.now();

            /**
             * Executa um quadro da animacao.
             *
             * @param {number} agora Timestamp atual.
             * @returns {void}
             */
            function quadro(agora) {
                // Progresso de 0 a 1.
                const progresso = Math.min((agora - inicio) / duracao, 1);

                // Suavizacao (easeOutCubic) para desacelerar no final.
                const suavizado = 1 - Math.pow(1 - progresso, 3);

                // Valor atual arredondado.
                const valorAtual = Math.round(valorFinal * suavizado);

                // Atualiza o texto.
                elemento.textContent = formatador.format(valorAtual);

                // Continua ate terminar.
                if (progresso < 1) {
                    requestAnimationFrame(quadro);
                    return;
                }

                // Garante o valor exato no final.
                elemento.textContent = formatador.format(valorFinal);
            }

            // Inicia a animacao.
            requestAnimationFrame(quadro);
        }

        // Navegador sem suporte: mostra os valores finais direto.
        if (!('IntersectionObserver' in window)) {
            contadores.forEach(function (elemento) {
                elemento.textContent = formatador.format(parseInt(elemento.dataset.contador, 10) || 0);
            });
            return;
        }

        // Observa cada contador e anima na primeira aparição.
        const observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (!entrada.isIntersecting) {
                    return;
                }

                // Anima apenas uma vez.
                animar(entrada.target);
                observador.unobserve(entrada.target);
            });
        }, {
            threshold: 0.4
        });

        contadores.forEach(function (elemento) {
            observador.observe(elemento);
        });
    }

    // Inicializacao de todos os comportamentos do site.
    aoPronto(function () {
        iniciarMenuMobile();
        iniciarHeaderRolagem();
        iniciarAnimacoesScroll();
        iniciarContadores();
    });
})();
