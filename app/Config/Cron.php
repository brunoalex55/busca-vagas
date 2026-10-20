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
 * CONFIGURACAO: TAREFAS AUTOMATICAS (CRON)
 * --------------------------------------------------------------------------
 *
 * Limites de seguranca das execucoes automaticas, para que uma unica
 * chamada do agendador nunca estoure o tempo do servidor nem a cota
 * das APIs externas.
 *
 * Os valores podem ser sobrescritos pelo .env, por exemplo:
 *   cron.limiteConsultasPorExecucao = 40
 *   cron.envioAutomaticoLimitePorExecucao = 50
 */
class Cron extends BaseConfig
{
    /**
     * Quantidade maxima de consultas ao provedor de vagas em UMA
     * execucao do CRON 1 (busca de vagas), somando todos os usuarios.
     *
     * Evita que a rotina consuma a cota inteira de uma vez e permite
     * que o agendador rode varias vezes ao dia sem sobrecarga.
     */
    public int $limiteConsultasPorExecucao = 40;

    /**
     * Quantidade maxima de consultas por USUARIO em uma execucao.
     * Zero significa "sem limite proprio" (vale apenas o limite global).
     */
    public int $limiteConsultasPorUsuario = 0;

    /**
     * Pausa, em segundos, entre duas consultas ao provedor. Serve para
     * ser gentil com o servico externo e evitar bloqueio por rajada.
     */
    public int $pausaEntreConsultas = 1;

    /**
     * Quantidade maxima de usuarios processados em uma execucao.
     * Zero significa "todos os usuarios ativos".
     */
    public int $limiteUsuariosPorExecucao = 0;

    /**
     * Quantidade maxima de e-mails enviados em UMA execucao do CRON 2,
     * somando todos os usuarios.
     *
     * Cada usuario envia no maximo um e-mail por execucao e respeita o
     * intervalo configurado no painel (3 a 7 minutos, por padrao), o que
     * protege a conta Gmail contra bloqueio por spam.
     */
    public int $envioAutomaticoLimitePorExecucao = 50;

    /**
     * Tempo maximo, em segundos, que o endpoint externo espera pelo
     * comando antes de responder 202 (Accepted).
     *
     * O servico externo (cron-job.org) abandona a requisicao depois de 30
     * segundos. Por isso o sistema responde antes disso: se o comando
     * terminar antes do limite, a resposta traz o resultado completo
     * (HTTP 200); se nao terminar, a resposta e 202 e o comando segue
     * rodando em segundo plano, com a saida no arquivo de log.
     *
     * Zero desliga o limite (a resposta espera o comando terminar).
     */
    public float $limiteRespostaEndpointExterno = 25.0;

    /**
     * URL base da API do servico externo (cron-job.org).
     *
     * A API oficial fica em https://api.cron-job.org. O valor pode ser
     * trocado no .env (chave "cron.urlBaseCronJobOrg") para apontar para
     * um servidor de teste, sem mexer em nenhuma linha de codigo.
     */
    public string $urlBaseCronJobOrg = 'https://api.cron-job.org';

    /**
     * Caminho absoluto do interpretador PHP de linha de comando (CLI).
     *
     * Usado pelo painel quando o admin (ou o usuario) dispara um comando
     * manualmente: o sistema executa `php spark ...` em um processo filho.
     *
     * Vazio significa "descobrir automaticamente":
     *   1. PHP_BINARY, quando o proprio processo ja e CLI;
     *   2. PHP_BINDIR/php (ou php.exe no Windows).
     *
     * Em servidores com PHP-FPM ou Apache, o binario em uso NAO e o CLI,
     * entao convem definir explicitamente no .env:
     *   cron.caminhoPhpCli = "/usr/bin/php"
     *   cron.caminhoPhpCli = "C:\\php\\php.exe"
     */
    public string $caminhoPhpCli = '';
}
