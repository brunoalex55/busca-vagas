<?php

namespace App\Commands;

use App\Libraries\EnviadorEmails;
use App\Libraries\OpcoesCli;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * COMANDO: ENVIAR E-MAILS AUTOMATICOS (CRON 2)
 * --------------------------------------------------------------------------
 *
 * Executa o envio automatico de candidaturas (CRON 2) pela linha de comando,
 * sem depender de URL. Este e o caminho oficial de agendamento do sistema.
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
 * O envio respeita todas as regras de cada usuario:
 *   - janela de horario e dias da semana permitidos;
 *   - limite diario de envios (limite_envios_dia);
 *   - intervalo minimo e maximo entre envios;
 *   - chave envio_automatico ligada;
 *   - remetente e APP PASSWORD proprios do usuario.
 *
 * Uso:
 *   php spark emails:enviar-automaticos              (todos os habilitados)
 *   php spark emails:enviar-automaticos --usuario=8  (somente o usuario #8)
 *   php spark emails:enviar-automaticos 8            (idem, posicional)
 *   php spark emails:enviar-automaticos --limite=5   (no maximo 5 e-mails)
 */
class EnviarEmailsAutomaticos extends BaseCommand
{
    // Leitura de opcoes nos dois formatos (--x valor e --x=valor).
    use OpcoesCli;

    // Grupo exibido em "php spark list".
    protected $group = 'Cron';

    // Nome invocado na linha de comando.
    protected $name = 'emails:enviar-automaticos';

    // Como usar.
    protected $usage = 'emails:enviar-automaticos [usuarioId] [--usuario ID] [--limite N]';

    // Descricao curta.
    protected $description = 'Envia e-mails automaticos (CRON 2) para os usuarios habilitados.';

    // Opcoes aceitas.
    protected $options = [
        '--usuario' => 'ID do usuario. Sem valor, roda para todos os habilitados.',
        '--limite'  => 'Numero maximo de e-mails nesta execucao.',
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

        // Limite opcional de e-mails nesta execucao.
        $limite = $this->limiteDeEnvios();

        // Orquestrador do envio (mesma biblioteca usada pelo CRON antigo).
        $enviador = new EnviadorEmails();

        // Cabecalho.
        CLI::write('ENVIO DE E-MAILS AUTOMATICOS (CRON 2)', 'green');

        // Alvo da execucao: um usuario ou todos os habilitados.
        if ($usuarioId !== null) {
            CLI::write('Usuario: #' . $usuarioId, 'yellow');
        } else {
            CLI::write('Usuarios: todos os habilitados', 'yellow');
        }

        CLI::newLine();

        // Executa apenas um usuario ou todos os habilitados.
        if ($usuarioId !== null) {
            $resumo = $enviador->enviarDoUsuario($usuarioId, $limite);
        } else {
            $resumo = $enviador->enviarGlobal($limite);
        }

        // Resumo geral da execucao.
        $this->exibirResumo($resumo);

        // Detalhe por usuario.
        foreach ($resumo['detalhes'] as $detalhe) {
            CLI::write('Usuario #' . $detalhe['usuario_id'] . ' - ' . $detalhe['nome'], 'white');
            CLI::write(
                '   enviados ' . $detalhe['enviados']
                . ' | falhas ' . $detalhe['falhas']
                . ' | motivo ' . $this->motivo($detalhe['motivo_parada']),
                'dark_gray'
            );

            // Mensagens de erro, quando houver.
            foreach ($detalhe['mensagens'] as $mensagem) {
                CLI::write('   ! ' . $mensagem, 'red');
            }
        }

        CLI::newLine();
        CLI::write('Concluido em ' . $resumo['terminado_em'], 'green');

        // Codigo de saida 1 quando houve falha, para o agendador perceber.
        if ($resumo['falhas'] > 0) {
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

        // Forma posicional: php spark emails:enviar-automaticos 8.
        if (isset($params[0]) && is_numeric($params[0])) {
            return (int) $params[0];
        }

        // Sem informacao: roda para todos os usuarios habilitados.
        return null;
    }

    /**
     * Le o limite de e-mails (--limite). Valor invalido vira "sem limite".
     */
    private function limiteDeEnvios(): ?int
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
     * Texto de exibicao do motivo da parada.
     *
     * @param string|null $motivo Motivo devolvido pela biblioteca.
     */
    private function motivo($motivo): string
    {
        // Sem motivo registrado: a execucao terminou normalmente.
        if ($motivo === null) {
            return 'concluido';
        }

        return (string) $motivo;
    }

    /**
     * Imprime o resumo geral devolvido pelo EnviadorEmails.
     *
     * @param array<string, mixed> $resumo
     */
    private function exibirResumo(array $resumo): void
    {
        CLI::write('E-mails enviados: ' . $resumo['enviados'], 'white');

        // Falhas em vermelho quando houver.
        if ($resumo['falhas'] > 0) {
            CLI::write('Falhas: ' . $resumo['falhas'], 'red');
        } else {
            CLI::write('Falhas: 0', 'white');
        }

        CLI::write('Motivo da parada: ' . $this->motivo($resumo['motivo_parada']), 'yellow');
        CLI::newLine();
    }
}
