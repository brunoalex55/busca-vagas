<?php

namespace App\Commands;

use App\Libraries\BuscadorDivulgaVagas;
use App\Libraries\OpcoesCli;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * COMANDO: DIVULGA VAGAS - BUSCAR VAGAS (CRON 1)
 * --------------------------------------------------------------------------
 *
 * Executa a busca de vagas no site Divulga Vagas (CRON 1) pela linha de
 * comando, sem depender de URL. E o caminho oficial de agendamento deste
 * segundo provedor.
 *
 * REGRA DE OURO: este comando NUNCA candidata a nada. Ele apenas busca
 * vagas, extrai o e-mail de contacto e salva na tabela `vagas`. Quem
 * candidate e o comando divulgavagas:candidatar (CRON 2).
 *
 * O agendamento deste provedor e INDEPENDENTE do SerpApi: o admin escolhe
 * os horarios separado, em /admin/cron.
 *
 * Uso:
 *   php spark divulgavagas:buscar-vagas              (todos os usuarios ativos)
 *   php spark divulgavagas:buscar-vagas --usuario=8  (somente o usuario #8)
 *   php spark divulgavagas:buscar-vagas 8            (idem, em forma posicional)
 *   php spark divulgavagas:buscar-vagas --limite=1   (no maximo 1 combinacao)
 *   php spark divulgavagas:buscar-vagas --origem=manual_usuario
 */
class DivulgaVagasBuscar extends BaseCommand
{
    // Leitura de opcoes nos dois formatos (--x valor e --x=valor).
    use OpcoesCli;

    // Grupo exibido em "php spark list".
    protected $group = 'Cron';

    // Nome invocado na linha de comando.
    protected $name = 'divulgavagas:buscar-vagas';

    // Como usar.
    protected $usage = 'divulgavagas:buscar-vagas [usuarioId] [--usuario ID] [--limite N] [--origem cron|manual|manual_usuario]';

    // Descricao curta.
    protected $description = 'Busca vagas no site Divulga Vagas via bot Python (CRON 1). NUNCA candidata.';

    // Opcoes aceitas.
    protected $options = [
        '--usuario' => 'ID do usuario. Sem valor, roda para todos os ativos.',
        '--limite'  => 'Numero maximo de combinacoes (termo + cidade) nesta execucao.',
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
        // Alvo da execucao: um usuario ou todos os ativos.
        $usuarioId = $this->idDoUsuario($params);

        // Teto de combinacoes desta execucao.
        $limite = $this->limiteDeCombinacoes();

        // Rotulo da origem gravado no log.
        $origem = $this->origemDaExecucao();

        // Orquestrador da busca no site.
        $buscador = new BuscadorDivulgaVagas();

        // Cabecalho.
        CLI::write('BUSCA DE VAGAS NO DIVULGA VAGAS (CRON 1)', 'green');
        CLI::write('Provedor: divulgavagas (bot Python)', 'yellow');

        if ($usuarioId !== null) {
            CLI::write('Usuario: #' . $usuarioId, 'yellow');
        } else {
            CLI::write('Usuarios: todos os ativos', 'yellow');
        }

        CLI::write('Origem: ' . $origem, 'yellow');
        CLI::newLine();

        // Executa.
        if ($usuarioId !== null) {
            $resumo = $buscador->buscarDoUsuario($usuarioId, $origem, $limite);
        } else {
            $resumo = $buscador->buscarGlobal($origem, $limite);
        }

        // Resumo geral.
        CLI::write('Combinacoes consultadas: ' . $resumo['consultas'], 'white');
        CLI::write('Vagas novas: ' . $resumo['vagas_inseridas'], 'white');
        CLI::write('Vagas repetidas: ' . $resumo['vagas_repetidas'], 'white');
        CLI::write('Com e-mail de contacto: ' . $resumo['com_email'], 'white');
        CLI::write('Sem e-mail de contacto: ' . $resumo['sem_email'], 'white');

        if ($resumo['erros'] > 0) {
            CLI::write('Erros: ' . $resumo['erros'], 'red');
        } else {
            CLI::write('Erros: 0', 'white');
        }

        // Motivo da parada.
        if (isset($resumo['motivo_parada']) && $resumo['motivo_parada'] !== null) {
            CLI::write('Motivo da parada: ' . $resumo['motivo_parada'], 'yellow');
        } else {
            CLI::write('Motivo da parada: concluido', 'yellow');
        }

        // Detalhe por combinacao.
        foreach ($resumo['detalhes'] as $detalhe) {
            CLI::write('  ' . $detalhe['termo'] . ' - ' . $detalhe['cidade'], 'dark_gray');
            CLI::write(
                '     novas ' . $detalhe['inseridas']
                . ' | repetidas ' . $detalhe['repetidas']
                . ' | com e-mail ' . $detalhe['com_email']
                . ' | sem e-mail ' . $detalhe['sem_email'],
                'dark_gray'
            );

            if ($detalhe['mensagem'] !== null) {
                CLI::write('     ! ' . $detalhe['mensagem'], 'red');
            }
        }

        CLI::newLine();
        CLI::write('Concluido em ' . $resumo['terminado_em'], 'green');
        CLI::write('Este comando NUNCA candidata a vagas.', 'dark_gray');

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

        // Forma posicional: php spark divulgavagas:buscar-vagas 8.
        if (isset($params[0]) && is_numeric($params[0])) {
            return (int) $params[0];
        }

        // Sem informacao: roda para todos os usuarios ativos.
        return null;
    }

    /**
     * Le o limite de combinacoes (--limite). Valor invalido vira "sem limite".
     */
    private function limiteDeCombinacoes(): ?int
    {
        $limite = $this->opcao('limite');

        if ($limite === null || ! is_numeric($limite)) {
            return null;
        }

        if ((int) $limite <= 0) {
            return null;
        }

        return (int) $limite;
    }

    /**
     * Rotulo da origem gravado em logs_consulta.
     */
    private function origemDaExecucao(): string
    {
        $origem = $this->opcao('origem');
        $aceitas = ['cron', 'manual', 'manual_usuario', 'manual_admin'];

        if ($origem !== null && in_array($origem, $aceitas, true)) {
            return $origem;
        }

        return 'cron';
    }
}
