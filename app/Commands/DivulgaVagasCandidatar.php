<?php

namespace App\Commands;

use App\Libraries\CandidaturaDivulgaVagas;
use App\Libraries\OpcoesCli;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * COMANDO: DIVULGA VAGAS - CANDIDATAR (CRON 2)
 * --------------------------------------------------------------------------
 *
 * Candidata-se a UMA vaga do site Divulga Vagas (CRON 2) pela linha de
 * comando. Quem executa o formulario no site e o bot Python; este comando
 * orquestra, respeita os limites e (quando a vaga tem e-mail de contacto)
 * tambem envia o e-mail de candidatura.
 *
 * REGRA DE OURO: este comando NUNCA busca vagas. Ele apenas candidata a
 * uma vaga por ciclo. Quem busca e o comando divulgavagas:buscar-vagas
 * (CRON 1).
 *
 * O agendamento deste provedor e INDEPENDENTE do SerpApi: o admin escolhe
 * os horarios separado, em /admin/cron.
 *
 * Uso:
 *   php spark divulgavagas:candidatar                (todos os usuarios ativos)
 *   php spark divulgavagas:candidatar --usuario=8    (somente o usuario #8)
 *   php spark divulgavagas:candidatar --vaga=123     (somente a vaga #123)
 *   php spark divulgavagas:candidatar --origem=manual_usuario
 */
class DivulgaVagasCandidatar extends BaseCommand
{
    // Leitura de opcoes nos dois formatos (--x valor e --x=valor).
    use OpcoesCli;

    // Grupo exibido em "php spark list".
    protected $group = 'Cron';

    // Nome invocado na linha de comando.
    protected $name = 'divulgavagas:candidatar';

    // Como usar.
    protected $usage = 'divulgavagas:candidatar [usuarioId] [--usuario ID] [--vaga ID] [--origem cron|manual|manual_usuario]';

    // Descricao curta.
    protected $description = 'Candidata a uma vaga do Divulga Vagas pelo bot e envia e-mail se houver contacto (CRON 2). NUNCA busca.';

    // Opcoes aceitas.
    protected $options = [
        '--usuario' => 'ID do usuario. Sem valor, roda para todos os ativos.',
        '--vaga'    => 'ID de UMA vaga especifica (botao "Candidatar agora" do painel).',
        '--vaga-id' => 'Mesma coisa que --vaga.',
        '--limite'  => 'Numero maximo de vagas nesta execucao. Sem valor, candidata TODAS as vagas da fila.',
        '--origem'  => 'Rotulo gravado na auditoria: cron, manual, manual_usuario ou manual_admin (padrao: cron).',
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

        // Vaga especifica (quando o painel pediu uma vaga).
        $vagaId = $this->idDaVaga();

        // Rotulo da origem.
        $origem = $this->origemDaExecucao();

        // Orquestrador da candidatura.
        $candidatura = new CandidaturaDivulgaVagas();

        // Cabecalho.
        CLI::write('CANDIDATURA NO DIVULGA VAGAS (CRON 2)', 'green');
        CLI::write('Provedor: divulgavagas (bot Python)', 'yellow');

        if ($usuarioId !== null) {
            CLI::write('Usuario: #' . $usuarioId, 'yellow');
        } else {
            CLI::write('Usuarios: todos os ativos', 'yellow');
        }

        if ($vagaId !== null) {
            CLI::write('Vaga especifica: #' . $vagaId, 'yellow');
        }

        CLI::write('Origem: ' . $origem, 'yellow');
        CLI::newLine();

        // Executa.
        if ($usuarioId !== null) {
            $resumo = $candidatura->candidatarDoUsuario($usuarioId, $origem, $this->limiteDeVagas());
        } else {
            $resumo = $candidatura->candidatarGlobal($origem);
        }

        // Resumo geral.
        CLI::write('Candidaturas pelo site: ' . $resumo['candidaturas_enviadas'], 'white');
        CLI::write('E-mails enviados: ' . $resumo['emails_enviados'], 'white');

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

        // Mensagem do resultado (ex.: o e-mail ficou para o proximo ciclo).
        if ($resumo['mensagem'] !== null) {
            CLI::write('Aviso: ' . $resumo['mensagem'], 'yellow');
        }

        CLI::newLine();
        CLI::write('Concluido em ' . $resumo['terminado_em'], 'green');
        CLI::write('Este comando NUNCA busca vagas.', 'dark_gray');

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
    private function idDoUsuario(array $params): ?int    {
        // Forma com opcao: --usuario=8 ou --usuario 8.
        $opcao = $this->opcao('usuario');

        if ($opcao !== null && is_numeric($opcao)) {
            return (int) $opcao;
        }

        // Forma posicional: php spark divulgavagas:candidatar 8.
        if (isset($params[0]) && is_numeric($params[0])) {
            return (int) $params[0];
        }

        // Sem informacao: roda para todos os usuarios ativos.
        return null;
    }

    /**
     * Descobre o ID da vaga especifica (--vaga ou --vaga-id).
     */
    private function idDaVaga(): ?int
    {
        $opcao = $this->opcao('vaga');

        if ($opcao === null || ! is_numeric($opcao)) {
            $opcao = $this->opcao('vaga-id');
        }

        if ($opcao === null || ! is_numeric($opcao)) {
            return null;
        }

        if ((int) $opcao <= 0) {
            return null;
        }

        return (int) $opcao;
    }

    /**
     * Rotulo da origem gravado na auditoria.
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

    /**
     * Quantas vagas o usuario pode candidar nesta execucao.
     *
     * Zero (o padrao) significa TODAS as vagas da fila: o comando roda
     * em loop, uma a uma, ate a fila acabar ou o limite do bot acabar.
     */
    private function limiteDeVagas(): int
    {
        $opcao = $this->opcao('limite');

        if ($opcao !== null && is_numeric($opcao) && (int) $opcao > 0) {
            return (int) $opcao;
        }

        return 0;
    }
}
