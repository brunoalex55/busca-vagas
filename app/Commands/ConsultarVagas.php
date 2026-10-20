<?php

namespace App\Commands;

use App\Libraries\BuscadorVagas;
use App\Libraries\OpcoesCli;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * COMANDO: CONSULTAR VAGAS (CRON 1)
 * --------------------------------------------------------------------------
 *
 * Executa a busca de vagas (CRON 1) pela linha de comando, sem depender
 * de URL. Este e o caminho oficial de agendamento do sistema.
 *
 * Modos de agendamento (Fase 7):
 *   - cron_linux   -> o crontab do Linux chama este comando;
 *   - cron_windows -> o Agendador de Tarefas do Windows chama este comando;
 *   - spark_manual -> o admin dispara pelo painel ou um servico externo
 *                     (cron-job.org) chama o endpoint protegido por token.
 *
 * O modo escolhido no painel NAO altera este codigo: ele apenas decide
 * QUEM chama o comando.
 *
 * Para que serve:
 *   - rodar a busca na hora, sem depender do agendador;
 *   - testar a integracao com o provedor;
 *   - servir de alvo dos scripts scripts/cron-linux.sh e
 *     scripts/cron-windows.bat.
 *
 * Uso:
 *   php spark vagas:consultar                    (todos os usuarios ativos)
 *   php spark vagas:consultar --usuario=8        (somente o usuario #8)
 *   php spark vagas:consultar 8                  (idem, em forma posicional)
 *   php spark vagas:consultar --limite=5         (no maximo 5 consultas)
 *   php spark vagas:consultar --origem=manual    (rotulo gravado no log)
 */
class ConsultarVagas extends BaseCommand
{
    // Leitura de opcoes nos dois formatos (--x valor e --x=valor).
    use OpcoesCli;

    // Grupo exibido em "php spark list".
    protected $group = 'Cron';

    // Nome invocado na linha de comando.
    protected $name = 'vagas:consultar';

    // Como usar.
    protected $usage = 'vagas:consultar [usuarioId] [--usuario ID] [--limite N] [--origem cron|manual|manual_usuario]';

    // Descricao curta.
    protected $description = 'Busca vagas para os usuarios ativos via provedor configurado (CRON 1).';

    // Opcoes aceitas.
    protected $options = [
        '--usuario' => 'ID do usuario. Sem valor, roda para todos os ativos.',
        '--limite'  => 'Numero maximo de consultas ao provedor nesta execucao.',
        '--origem'  => 'Rotulo gravado em logs_consulta: cron, manual ou manual_usuario (padrao: cron).',
    ];

    /**
     * Executa o comando.
     *
     * @param array<array-key, string|null> $params
     *
     * @return int Codigo de saida devolvido ao agendador.
     */
    public function run(array $params)
    {
        // ID do usuario: aceito por opcao (--usuario=8) ou posicional (8).
        $usuarioId = $this->idDoUsuario($params);

        // Limite opcional de consultas nesta execucao.
        $limite = $this->limiteDeConsultas();

        // Rotulo da origem gravado em logs_consulta.
        $origem = $this->origemDaExecucao();

        // Orquestrador da busca (mesma biblioteca usada pelo CRON antigo).
        $buscador = new BuscadorVagas();

        // Cabecalho.
        CLI::write('BUSCA DE VAGAS (CRON 1)', 'green');
        CLI::write('Provedor: ' . $buscador->provedor(), 'yellow');

        // Alvo da execucao: um usuario ou todos os ativos.
        if ($usuarioId !== null) {
            CLI::write('Usuario: #' . $usuarioId, 'yellow');
        } else {
            CLI::write('Usuarios: todos os ativos', 'yellow');
        }

        CLI::write('Origem: ' . $origem, 'yellow');
        CLI::newLine();

        // Executa apenas um usuario ou todos os ativos.
        if ($usuarioId !== null) {
            $resumo = $buscador->buscarDoUsuario($usuarioId, $origem, $limite);
        } else {
            $resumo = $buscador->buscarGlobal($origem, $limite);
        }

        // Resumo geral da execucao.
        $this->exibirResumo($resumo);

        // Detalhe por usuario.
        foreach ($resumo['detalhes'] as $detalhe) {
            CLI::write('Usuario #' . $detalhe['usuario_id'] . ' - ' . $detalhe['nome'], 'white');
            CLI::write(
                '   cota ' . $detalhe['usadas_antes'] . '/' . $detalhe['cota']
                . ' | consultas ' . $detalhe['consultas']
                . ' | novas ' . $detalhe['vagas_novas']
                . ' | repetidas ' . $detalhe['vagas_repetidas']
                . ' | palavras ' . $detalhe['palavras_chave']
                . ' | pendentes ' . $detalhe['palavras_pendentes'],
                'dark_gray'
            );

            // Mensagens de erro, quando houver.
            foreach ($detalhe['mensagens'] as $mensagem) {
                CLI::write('   ! ' . $mensagem, 'red');
            }
        }

        CLI::newLine();
        CLI::write('Concluido em ' . $resumo['terminado_em'], 'green');

        // Codigo de saida 1 quando houve erro, para o agendador perceber.
        if ($resumo['erros'] > 0) {
            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }

    /**
     * Descobre o ID do usuario, aceitando --usuario=8 ou o primeiro argumento.
     *
     * @param array<array-key, string|null> $params
     */
    private function idDoUsuario(array $params): ?int
    {
        // Forma com opcao: --usuario=8 ou --usuario 8.
        $opcao = $this->opcao('usuario');

        if ($opcao !== null && is_numeric($opcao)) {
            return (int) $opcao;
        }

        // Forma posicional: php spark vagas:consultar 8.
        if (isset($params[0]) && is_numeric($params[0])) {
            return (int) $params[0];
        }

        // Sem informacao: roda para todos os usuarios ativos.
        return null;
    }

    /**
     * Le o limite de consultas (--limite). Valor invalido vira "sem limite".
     */
    private function limiteDeConsultas(): ?int
    {
        $limite = $this->opcao('limite');

        // Opcao ausente.
        if ($limite === null) {
            return null;
        }

        // Precisa ser numero.
        if (! is_numeric($limite)) {
            return null;
        }

        // Precisa ser positivo.
        if ((int) $limite <= 0) {
            return null;
        }

        return (int) $limite;
    }

    /**
     * Rotulo da origem gravado em logs_consulta.
     *
     * Valores validos do enum: cron, manual e manual_usuario. Qualquer
     * outro valor cai no padrao 'cron' para nunca gravar dado invalido.
     */
    private function origemDaExecucao(): string
    {
        $origem = $this->opcao('origem');

        // Valores aceitos pela coluna origem de logs_consulta.
        $aceitas = ['cron', 'manual', 'manual_usuario'];

        if ($origem !== null && in_array($origem, $aceitas, true)) {
            return $origem;
        }

        return 'cron';
    }

    /**
     * Imprime o resumo geral devolvido pelo BuscadorVagas.
     *
     * @param array<string, mixed> $resumo
     */
    private function exibirResumo(array $resumo): void
    {
        CLI::write('Consultas realizadas: ' . $resumo['consultas'], 'white');
        CLI::write('Vagas novas: ' . $resumo['vagas_novas'], 'white');
        CLI::write('Vagas repetidas: ' . $resumo['vagas_repetidas'], 'white');

        // Erros em vermelho quando houver.
        if ($resumo['erros'] > 0) {
            CLI::write('Erros: ' . $resumo['erros'], 'red');
        } else {
            CLI::write('Erros: 0', 'white');
        }

        // Motivo da parada: pode vir nulo quando nada foi processado.
        if (isset($resumo['motivo_parada']) && $resumo['motivo_parada'] !== null) {
            CLI::write('Motivo da parada: ' . $resumo['motivo_parada'], 'yellow');
        } else {
            CLI::write('Motivo da parada: concluido', 'yellow');
        }

        CLI::newLine();
    }
}
