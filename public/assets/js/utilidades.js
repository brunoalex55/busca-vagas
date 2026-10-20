/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/

'use strict';

/**
 * --------------------------------------------------------------------------
 * UTILIDADES GLOBAIS
 * --------------------------------------------------------------------------
 *
 * Funcoes pequenas e reutilizaveis usadas pelo site e pelos paineis.
 * Nada aqui contem regra de negocio, validacao ou dado sensivel: toda a
 * logica real fica no PHP.
 *
 * Recursos:
 *   - fechamento e expiracao automatica dos toasts;
 *   - mostrar/ocultar senha;
 *   - copiar texto para a area de transferencia;
 *   - mascaras simples de input.
 */

(function () {
    /**
     * Executa um callback quando o DOM estiver pronto.
     *
     * @param {Function} callback Funcao a executar.
     * @returns {void}
     */
    function aoPronto(callback) {
        // Se o documento ainda esta carregando, espera o evento.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        // Se ja carregou, executa imediatamente.
        callback();
    }

    /**
     * Remove um toast da pagina com uma pequena animacao.
     *
     * @param {HTMLElement} toast Elemento do toast.
     * @returns {void}
     */
    function removerToast(toast) {
        // Protecao contra chamadas duplicadas.
        if (!toast || toast.dataset.removendo === '1') {
            return;
        }

        // Marca como em remocao para evitar reentrada.
        toast.dataset.removendo = '1';

        // Aplica a transicao de saida.
        toast.style.transition = 'opacity 250ms ease, transform 250ms ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';

        // Remove do DOM depois da animacao.
        window.setTimeout(function () {
            toast.remove();
        }, 260);
    }

    /**
     * Liga o fechamento manual e a expiracao automatica dos toasts.
     *
     * @returns {void}
     */
    function iniciarToasts() {
        // Botoes de fechar dentro de cada toast.
        document.querySelectorAll('.toast__fechar').forEach(function (botao) {
            botao.addEventListener('click', function () {
                // Sobe ate o toast correspondente.
                const toast = botao.closest('.toast');

                // Remove o toast.
                removerToast(toast);
            });
        });

        // Expiracao automatica: 6 segundos para mensagens de sucesso,
        // 9 segundos para mensagens de erro (o usuario precisa de mais tempo).
        document.querySelectorAll('.toast').forEach(function (toast) {
            const espera = toast.classList.contains('toast--erro') ? 9000 : 6000;

            window.setTimeout(function () {
                removerToast(toast);
            }, espera);
        });
    }

    /**
     * Mostra ou oculta o conteudo de um campo de senha.
     *
     * O botao usa data-alvo="id-do-campo" e aria-label descritivo.
     *
     * @returns {void}
     */
    function iniciarVerSenha() {
        document.querySelectorAll('[data-alvo]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                // Localiza o campo indicado pelo atributo data-alvo.
                const campo = document.getElementById(botao.dataset.alvo);

                // Se o campo nao existir, ignora.
                if (!campo) {
                    return;
                }

                // Alterna entre texto e senha.
                const visivel = campo.type === 'text';
                campo.type = visivel ? 'password' : 'text';

                // Atualiza o rotulo do botao e o texto visivel.
                botao.textContent = visivel ? 'Ver' : 'Ocultar';
                botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');

                // Devolve o foco ao campo para o usuario continuar digitando.
                campo.focus();
            });
        });
    }

    /**
     * Copia um texto para a area de transferencia.
     *
     * @param {string} texto Texto a copiar.
     * @returns {Promise<boolean>} Verdadeiro quando a copia funciona.
     */
    function copiarTexto(texto) {
        // Caminho moderno: API de clipboard (exige contexto seguro).
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(texto)
                .then(function () {
                    return true;
                })
                .catch(function () {
                    return copiarComFallback(texto);
                });
        }

        // Fallback para navegadores antigos ou HTTP local.
        return Promise.resolve(copiarComFallback(texto));
    }

    /**
     * Copia texto usando um textarea temporario (fallback).
     *
     * @param {string} texto Texto a copiar.
     * @returns {boolean} Verdadeiro quando a copia funciona.
     */
    function copiarComFallback(texto) {
        // Cria um textarea fora da tela.
        const area = document.createElement('textarea');
        area.value = texto;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        area.style.left = '-1000px';

        // Insere no documento para poder selecionar.
        document.body.appendChild(area);
        area.select();

        // Tenta copiar.
        let copiou = false;

        try {
            copiou = document.execCommand('copy');
        } catch (erro) {
            copiou = false;
        }

        // Limpa o elemento temporario.
        area.remove();

        return copiou;
    }

    /**
     * Liga os botoes com data-copiar="id-do-elemento".
     *
     * @returns {void}
     */
    function iniciarCopiar() {
        document.querySelectorAll('[data-copiar]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                // Elemento cujo texto sera copiado.
                const origem = document.getElementById(botao.dataset.copiar);

                // Sem origem, nada a fazer.
                if (!origem) {
                    return;
                }

                // Guarda o rotulo original para restaurar depois.
                const rotuloOriginal = botao.textContent;

                // Copia o texto e da o retorno visual.
                copiarTexto(origem.textContent.trim()).then(function (copiou) {
                    botao.textContent = copiou
                        ? (botao.dataset.copiado || 'Copiado!')
                        : 'Nao foi possivel copiar';

                    // Restaura o rotulo original apos 2 segundos.
                    window.setTimeout(function () {
                        botao.textContent = rotuloOriginal;
                    }, 2000);
                });
            });
        });
    }

    /**
     * Aplica mascaras simples de input.
     *
     * Atributos suportados: data-mascara="telefone|cpf|cnpj|cep|data".
     *
     * @returns {void}
     */
    function iniciarMascaras() {
        document.querySelectorAll('[data-mascara]').forEach(function (campo) {
            campo.addEventListener('input', function () {
                // Mantem apenas digitos.
                const digitos = campo.value.replace(/\D/g, '');

                // Aplica o formato conforme o tipo.
                switch (campo.dataset.mascara) {
                    case 'telefone':
                        campo.value = formatarTelefone(digitos);
                        break;
                    case 'cpf':
                        campo.value = digitos
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                            .slice(0, 14);
                        break;
                    case 'cnpj':
                        campo.value = digitos
                            .replace(/(\d{2})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1.$2')
                            .replace(/(\d{3})(\d)/, '$1/$2')
                            .replace(/(\d{4})(\d{1,2})$/, '$1-$2')
                            .slice(0, 18);
                        break;
                    case 'cep':
                        campo.value = digitos.replace(/(\d{5})(\d)/, '$1-$2').slice(0, 9);
                        break;
                    case 'data':
                        campo.value = digitos
                            .replace(/(\d{2})(\d)/, '$1/$2')
                            .replace(/(\d{2})(\d)/, '$1/$2')
                            .slice(0, 10);
                        break;
                    default:
                        // Tipo desconhecido: nao altera o valor.
                        break;
                }
            });
        });
    }

    /**
     * Formata um telefone brasileiro com DDD.
     *
     * @param {string} digitos Apenas numeros.
     * @returns {string} Telefone formatado.
     */
    function formatarTelefone(digitos) {
        // Telefone fixo com ate 10 digitos: (11) 1234-5678.
        if (digitos.length <= 10) {
            return digitos
                .replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{4})(\d{1,4})$/, '$1-$2')
                .slice(0, 14);
        }

        // Celular com 11 digitos: (11) 91234-5678.
        return digitos
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d{1,4})$/, '$1-$2')
            .slice(0, 15);
    }

    /**
     * Exibe um toast criado dinamicamente (usado pelo painel).
     *
     * @param {string} mensagem Texto da mensagem.
     * @param {string} tipo     "sucesso" ou "erro".
     * @returns {void}
     */
    function toast(mensagem, tipo) {
        // Container de toasts (criado se ainda nao existir).
        let container = document.getElementById('toasts');

        if (!container) {
            container = document.createElement('div');
            container.id = 'toasts';
            container.className = 'toasts';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        // Monta o toast.
        const elemento = document.createElement('div');
        elemento.className = 'toast toast--' + (tipo === 'erro' ? 'erro' : 'sucesso');
        elemento.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');

        // Texto.
        const texto = document.createElement('p');
        texto.className = 'toast__texto';
        texto.textContent = mensagem;

        // Botao de fechar.
        const fechar = document.createElement('button');
        fechar.className = 'toast__fechar';
        fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar mensagem');
        fechar.textContent = '×';
        fechar.addEventListener('click', function () {
            removerToast(elemento);
        });

        // Junta as partes.
        elemento.appendChild(texto);
        elemento.appendChild(fechar);
        container.appendChild(elemento);

        // Fecha sozinho depois de alguns segundos.
        window.setTimeout(function () {
            removerToast(elemento);
        }, tipo === 'erro' ? 9000 : 6000);
    }


    /**
     * Abre um modal pelo id.
     *
     * @param {string} id O id do modal.
     * @returns {void}
     */
    function abrirModal(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.add('modal--aberto');
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-aberto');

        // O foco vai para o primeiro campo util da janela.
        const campo = modal.querySelector('select, input, textarea, button');

        if (campo && typeof campo.focus === 'function') {
            campo.focus();
        }
    }

    /**
     * Fecha um modal pelo id.
     *
     * @param {string} id O id do modal.
     * @returns {void}
     */
    function fecharModal(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.classList.remove('modal--aberto');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-aberto');
    }

    /**
     * Liga os botoes que abrem e fecham os modais.
     *
     * @returns {void}
     */
    function iniciarModais() {
        // Todos os botoes que abrem um modal.
        const botoes = document.querySelectorAll('[data-modal-abrir]');

        botoes.forEach(function (botao) {
            botao.addEventListener('click', function () {
                abrirModal(botao.getAttribute('data-modal-abrir'));
            });
        });

        // Todos os botoes que fecham um modal.
        const fechadores = document.querySelectorAll('[data-modal-fechar]');

        fechadores.forEach(function (botao) {
            botao.addEventListener('click', function () {
                const alvo = botao.getAttribute('data-modal-fechar');

                // Sem alvo: fecha o modal que contem o botao.
                if (!alvo) {
                    const pai = botao.closest('.modal');

                    if (pai) {
                        fecharModal(pai.id);
                    }

                    return;
                }

                fecharModal(alvo);
            });
        });

        // O clique fora da janela fecha o modal.
        const modais = document.querySelectorAll('.modal');

        modais.forEach(function (modal) {
            modal.addEventListener('click', function (evento) {
                // O clique foi na propria camada (fora da janela).
                if (evento.target === modal) {
                    fecharModal(modal.id);
                }
            });
        });

        // A tecla ESC fecha o modal aberto.
        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }

            const aberto = document.querySelector('.modal--aberto');

            if (aberto) {
                fecharModal(aberto.id);
            }
        });
    }
    // Inicializacao geral.
    aoPronto(function () {
        iniciarToasts();
        iniciarVerSenha();
        iniciarCopiar();
        iniciarMascaras();
        iniciarModais();
    });

    // Disponibiliza helpers para os outros arquivos do projeto.
    window.Utilidades = {
        toast: toast,
        copiarTexto: copiarTexto
    };
})();
