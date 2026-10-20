<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * --------------------------------------------------------------------------
 * CONFIG: DIVULGA VAGAS (SEGUNDO PROVEDOR)
 * --------------------------------------------------------------------------
 *
 * Ajuste do segundo provedor de busca: o site Divulga Vagas, consultado
 * pelo bot Python (Playwright) que roda em um Chrome headless persistente.
 *
 * O codigo do bot fica em automacoes-rpa/divulgavagas (versionado) e o
 * trabalho de execucao (logs, curriculos, perfil do Chrome) fica em
 * writable/automacoes-rpa (fora do versionamento).
 */
class DivulgaVagas extends BaseConfig
{
    /**
     * Caminho do interpretador Python.
     *
     * Vazio significa "descobrir automaticamente": o sistema procura
     * python3 (Linux) ou python (Windows) no PATH. Em producao, com o
     * Python instalado fora do PATH, informe o caminho aqui (ou no .env,
     * na chave "divulgavagas.python").
     */
    public string $python = '';

    /**
     * Pasta do bot (onde ficam bot.py e o config.ini do provedor).
     *
     * Vazio significa <raiz do projeto> . '/automacoes-rpa/divulgavagas'.
     */
    public string $pastaBot = '';

    /**
     * Endereco base do site Divulga Vagas.
     */
    public string $urlBase = 'https://divulgavagas.com.br';

    /**
     * Caminho da listagem de vagas.
     */
    public string $caminhoListagem = '/vagas-de-emprego';

    /**
     * Tempo limite da busca, em milissegundos.
     */
    public int $timeoutBusca = 60000;

    /**
     * Tempo limite da candidatura, em milissegundos.
     */
    public int $timeoutCandidatura = 90000;

    /**
     * Endereco do servico do Chrome (CDP), usado pelo bot.
     */
    public string $enderecoCdp = 'http://127.0.0.1:9222';

    /**
     * Numero maximo de vagas que uma busca traz.
     */
    public int $maximoVagasPorBusca = 20;

    /**
     * Pausa entre duas candidaturas do loop, em segundos.
     *
     * O loop esvazia a fila de uma vez, mas o site nao deve receber
     * candidaturas em cima uma da outra.
     */
    public int $pausaEntreCandidaturas = 8;

    /**
     * Quantas vagas o agendador externo candidata por chamada.
     *
     * O agendador externo (cron-job.org) abandona a requisicao depois
     * de 30 segundos: a chamada por HTTP precisa caber nesse tempo.
     * A fila inteira e drenada ao longo das chamadas seguintes (e pelo
     * comando Spark, que nao tem esse limite).
     */
    public int $maxVagasPorChamada = 1;
}
