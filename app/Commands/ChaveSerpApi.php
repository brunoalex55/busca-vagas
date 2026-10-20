<?php

namespace App\Commands;

use App\Libraries\OpcoesCli;
use App\Models\ChaveSerpApiModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * --------------------------------------------------------------------------
 * COMANDO: CHAVES DO PROVEDOR DE VAGAS
 * --------------------------------------------------------------------------
 *
 * Gerencia as chaves usadas na busca de vagas.
 *
 * IMPORTANTE: o painel administrativo (com estas mesmas funcoes, em tela)
 * faz parte da Fase 5. Ate la, este comando e a forma oficial de cadastrar
 * as chaves, sem precisar mexer no banco a mao.
 *
 * A chave NUNCA e exibida por inteiro na saida: apenas o comeco.
 *
 * As opcoes valem nas duas formas: `--id 1` e `--id=1`.
 *
 * Uso:
 *   php spark vagas:chave listar
 *   php spark vagas:chave criar --chave SUA_CHAVE --apelido "Conta 1" --quantidade 100
 *   php spark vagas:chave ativar --id 1
 *   php spark vagas:chave desativar --id 1
 *   php spark vagas:chave zerar --id 1      (sem --id, zera todas)
 *   php spark vagas:chave remover --id 1
 */
class ChaveSerpApi extends BaseCommand
{
    // Leitura de opcoes nos dois formatos (--x valor e --x=valor).
    use OpcoesCli;

    // Grupo exibido em "php spark list".
    protected $group = 'Vagas';

    // Nome invocado na linha de comando.
    protected $name = 'vagas:chave';

    // Como usar. As duas formas de passar opcao sao aceitas.
    protected $usage = 'vagas:chave <listar|criar|ativar|desativar|zerar|remover> [--id N] [--chave VALOR] [--apelido TEXTO] [--quantidade N] [--ordem N]';

    // Descricao curta.
    protected $description = 'Gerencia as chaves do provedor de vagas (ponte ate o painel da Fase 5).';

    // Opcoes aceitas.
    protected $options = [
        '--id'         => 'Identificador da chave.',
        '--chave'      => 'Valor da chave da API.',
        '--apelido'    => 'Nome amigavel da conta.',
        '--quantidade' => 'Consultas mensais disponiveis na conta.',
        '--ordem'      => 'Ordem de uso (menor roda primeiro).',
    ];

    /**
     * Executa o comando.
     *
     * @param array<array-key, string|null> $params
     */
    public function run(array $params)
    {
        // Acao desejada (primeiro parametro).
        $acao = 'listar';

        if (isset($params[0])) {
            $acao = strtolower((string) $params[0]);
        }

        // Model das chaves.
        $model = new ChaveSerpApiModel();

        // Despacha a acao.
        match ($acao) {
            'listar'   => $this->listar($model),
            'criar'    => $this->criar($model),
            'ativar'   => $this->mudarAtivacao($model, true),
            'desativar' => $this->mudarAtivacao($model, false),
            'zerar'    => $this->zerar($model),
            'remover'  => $this->remover($model),
            default    => $this->ajuda($acao),
        };
    }

    /**
     * Lista as chaves cadastradas, sem expor o valor.
     */
    private function listar(ChaveSerpApiModel $model): void
    {
        $chaves = $model->listarTodas();

        if ($chaves === []) {
            CLI::write('Nenhuma chave cadastrada.', 'yellow');
            CLI::write('Cadastre com: php spark vagas:chave criar --chave SUA_CHAVE', 'dark_gray');

            return;
        }

        // Cabecalho da tabela.
        $cabecalho = ['ID', 'Apelido', 'Chave', 'Usadas/Total', 'Ativa', 'Motivo', 'Ordem'];
        $linhas    = [];

        foreach ($chaves as $chave) {
            // Situacao da chave ("sim" ou "nao").
            $ativa = 'nao';

            if ((int) $chave['ativa'] === 1) {
                $ativa = 'sim';
            }

            $linhas[] = [
                (string) $chave['id'],
                (string) $chave['apelido'],
                $this->mascarar((string) $chave['chave']),
                (int) $chave['consultas_usadas'] . '/' . (int) $chave['quantidade_consultas'],
                $ativa,
                texto_ou($chave, 'motivo_inativacao', '-'),
                (string) $chave['ordem'],
            ];
        }

        CLI::table($linhas, $cabecalho);
    }

    /**
     * Cadastra uma nova chave.
     */
    private function criar(ChaveSerpApiModel $model): void
    {
        // Valor da chave (obrigatorio).
        $valor = trim((string) $this->opcao('chave'));

        if ($valor === '') {
            CLI::error('Informe o valor da chave com --chave VALOR (ou --chave=VALOR).');

            return;
        }

        // Quantidade mensal de consultas (padrao 100).
        $quantidade = $this->opcao('quantidade');

        if (! is_numeric($quantidade) || (int) $quantidade <= 0) {
            $quantidade = 100;
        } else {
            $quantidade = (int) $quantidade;
        }

        // Ordem de uso: menor roda primeiro. Padrao: fim da fila.
        $ordem = $this->opcao('ordem');

        if (! is_numeric($ordem) || (int) $ordem <= 0) {
            $ordem = $this->proximaOrdem($model);
        } else {
            $ordem = (int) $ordem;
        }

        // Apelido amigavel (padrao: "Conta N").
        $apelido = trim((string) $this->opcao('apelido'));

        if ($apelido === '') {
            $apelido = 'Conta ' . $ordem;
        }

        // Insere respeitando a validacao do model.
        $id = $model->insert([
            'apelido'              => $apelido,
            'chave'                => $valor,
            'quantidade_consultas' => $quantidade,
            'consultas_usadas'     => 0,
            'mes_referencia'       => date('Y-m'),
            'ativa'                => 1,
            'motivo_inativacao'    => null,
            'ordem'                => $ordem,
        ]);

        // Falha de validacao.
        if ($id === false) {
            CLI::error('Nao foi possivel cadastrar a chave:');
            CLI::write(implode(PHP_EOL, $model->errors()), 'red');

            return;
        }

        CLI::write('Chave cadastrada com sucesso.', 'green');
        CLI::write('ID: ' . $id . ' | Apelido: ' . $apelido . ' | Consultas: ' . $quantidade . ' | Ordem: ' . $ordem, 'white');
    }

    /**
     * Ativa ou desativa uma chave pelo id.
     */
    private function mudarAtivacao(ChaveSerpApiModel $model, bool $ativar): void
    {
        // Identificador obrigatorio.
        $id = $this->idObrigatorio();

        if ($id === null) {
            return;
        }

        // A chave precisa existir.
        if ($model->find($id) === null) {
            CLI::error('Chave #' . $id . ' nao encontrada.');

            return;
        }

        // Aplica a mudanca.
        if ($ativar) {
            $model->ativar($id);
            CLI::write('Chave #' . $id . ' ativada.', 'green');
        } else {
            $model->desativar($id);
            CLI::write('Chave #' . $id . ' desativada (nao volta sozinha na virada do mes).', 'yellow');
        }
    }

    /**
     * Zera o contador de consultas (de uma chave ou de todas).
     */
    private function zerar(ChaveSerpApiModel $model): void
    {
        // Id opcional: sem ele, zera todas.
        $id = $this->opcao('id');

        if (! is_numeric($id) || (int) $id <= 0) {
            $id = null;
        } else {
            $id = (int) $id;
        }

        $model->zerarConsultas($id);

        // Mensagem conforme o escopo (uma chave ou todas).
        $mensagem = 'Contador de todas as chaves zerado.';

        if ($id !== null) {
            $mensagem = 'Contador da chave #' . $id . ' zerado.';
        }

        CLI::write($mensagem, 'green');
    }

    /**
     * Remove uma chave definitivamente.
     */
    private function remover(ChaveSerpApiModel $model): void
    {
        // Identificador obrigatorio.
        $id = $this->idObrigatorio();

        if ($id === null) {
            return;
        }

        // A chave precisa existir.
        if ($model->find($id) === null) {
            CLI::error('Chave #' . $id . ' nao encontrada.');

            return;
        }

        // Remove (os logs antigos mantem o historico, com o vinculo zerado).
        $model->delete($id);

        CLI::write('Chave #' . $id . ' removida.', 'green');
    }

    /**
     * Explica o uso quando a acao e desconhecida.
     */
    private function ajuda(string $acao): void
    {
        CLI::error('Acao desconhecida: ' . $acao);
        CLI::write('Acoes validas: listar, criar, ativar, desativar, zerar, remover.', 'yellow');
    }

    /**
     * Le e valida a opcao --id.
     */
    private function idObrigatorio(): ?int
    {
        $id = $this->opcao('id');

        if (! is_numeric($id) || (int) $id <= 0) {
            CLI::error('Informe o identificador com --id N (ou --id=N).');

            return null;
        }

        return (int) $id;
    }

    /**
     * Calcula a proxima ordem livre (fim da fila).
     */
    private function proximaOrdem(ChaveSerpApiModel $model): int
    {
        $chaves = $model->listarTodas();

        // Sem chaves cadastradas, comeca em 1.
        if ($chaves === []) {
            return 1;
        }

        // Maior ordem existente + 1.
        return max(array_map(static fn (array $chave): int => (int) $chave['ordem'], $chaves)) + 1;
    }

    /**
     * Mostra apenas o inicio da chave, nunca o valor completo.
     */
    private function mascarar(string $chave): string
    {
        // Chaves curtas sao totalmente mascaradas.
        if (strlen($chave) <= 6) {
            return str_repeat('*', max(1, strlen($chave)));
        }

        return substr($chave, 0, 6) . str_repeat('*', 6);
    }
}
