<?php

namespace App\Controllers\Admin;

use App\Libraries\DistribuicaoConsultas;
use App\Models\ChaveSerpApiModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - CHAVES SERPAPI
 * --------------------------------------------------------------------------
 *
 * CRUD das chaves da API de busca de vagas. Exclusivo do administrador:
 * o usuario comum nunca ve esta tabela.
 *
 * A chave em si nunca e exibida por inteiro na interface: a listagem e o
 * formulario de edicao mostram apenas um trecho mascarado. Para trocar o
 * valor, o admin digita uma nova chave.
 */
class AdminChaves extends AdminBase
{
    /** Model das chaves. */
    private ChaveSerpApiModel $chaves;

    /**
     * Carrega o model.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->chaves = model(ChaveSerpApiModel::class);
    }

    /**
     * Lista todas as chaves com o resumo de saldo.
     */
    public function index(): string
    {
        // Todas as chaves, em ordem de uso.
        $chaves = $this->chaves->listarTodas();

        return view('admin/chaves/index', $this->dadosAdmin('Chaves', [
            'chaves'   => array_map([$this, 'prepararChave'], $chaves),
            'saldo'    => $this->chaves->totalRestante(),
            'resumo'   => $this->resumo($chaves),
            'distribuicao' => (new DistribuicaoConsultas())->calcularTodas(),
        ]));
    }

    /**
     * Formulario de cadastro de uma nova chave.
     */
    public function nova(): string
    {
        return view('admin/chaves/formulario', $this->dadosAdmin('Nova chave', [
            'chave' => [
                'id'                   => 0,
                'apelido'              => old('apelido', ''),
                'chave_mascarada'      => '',
                'quantidade_consultas' => old('quantidade_consultas', 250),
                'consultas_usadas'     => 0,
                'ordem'                => old('ordem', $this->proximaOrdem()),
                'ativa'                => 1,
                'motivo_inativacao'    => null,
            ],
            'modo' => 'criar',
        ]));
    }

    /**
     * Grava uma nova chave.
     */
    public function criar(): RedirectResponse
    {
        // Dados normalizados do formulario.
        $dados = $this->dadosDoFormulario();

        // Validacao de negocio.
        $erros = $this->validar($dados, true);

        if ($erros !== []) {
            return $this->voltarComErros($erros, ['chave']);
        }

        // Persiste a chave nova, ativa desde o inicio.
        $this->chaves->insert([
            'apelido'              => $dados['apelido'],
            'chave'                => $dados['chave'],
            'quantidade_consultas' => $dados['quantidade_consultas'],
            'consultas_usadas'     => 0,
            'mes_referencia'       => date('Y-m'),
            'ativa'                => 1,
            'motivo_inativacao'    => null,
            'ordem'                => $dados['ordem'],
        ]);

        return redirect()
            ->to(site_url('admin/chaves'))
            ->with('sucesso', 'Chave cadastrada.');
    }

    /**
     * Formulario de edicao.
     *
     * @param int|string $id Identificador da chave.
     */
    public function editar($id): string
    {
        // Chave alvo.
        $chave = $this->carregarChave($id);

        // Nunca expor a chave completa: a view so mostra o trecho mascarado.
        $chave['chave_mascarada'] = $this->mascarar((string) $chave['chave']);
        unset($chave['chave']);

        return view('admin/chaves/formulario', $this->dadosAdmin('Editar chave', [
            'chave' => $chave,
            'modo'  => 'editar',
        ]));
    }

    /**
     * Atualiza uma chave existente.
     *
     * @param int|string $id Identificador da chave.
     */
    public function atualizar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        // Dados normalizados.
        $dados = $this->dadosDoFormulario();

        // Validacao (a chave so e obrigatoria quando informada).
        $erros = $this->validar($dados, false);

        if ($erros !== []) {
            return $this->voltarComErros($erros, ['chave']);
        }

        // Campos que sempre mudam.
        $atualizar = [
            'apelido'              => $dados['apelido'],
            'quantidade_consultas' => $dados['quantidade_consultas'],
            'ordem'                => $dados['ordem'],
        ];

        // Troca o valor da chave apenas quando uma nova foi digitada.
        if ($dados['chave'] !== '') {
            $atualizar['chave'] = $dados['chave'];
        }

        $this->chaves->skipValidation(true)->update((int) $chave['id'], $atualizar);

        return redirect()
            ->to(site_url('admin/chaves'))
            ->with('sucesso', 'Chave atualizada.');
    }

    /**
     * Ativa uma chave e limpa o motivo da inativacao.
     *
     * @param int|string $id Identificador da chave.
     */
    public function ativar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->ativar((int) $chave['id']);

        return $this->voltar('Chave ativada.');
    }

    /**
     * Desativa a chave por decisao do administrador.
     *
     * @param int|string $id Identificador da chave.
     */
    public function desativar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->desativar((int) $chave['id']);

        return $this->voltar('Chave desativada. Ela nao volta sozinha na virada do mes.');
    }

    /**
     * Marca a chave como esgotada (volta sozinha na virada do mes).
     *
     * @param int|string $id Identificador da chave.
     */
    public function esgotar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->esgotar((int) $chave['id']);

        return $this->voltar('Chave marcada como esgotada. Ela volta na virada do mes.');
    }

    /**
     * Inativa a chave porque a credencial foi recusada pelo provedor.
     *
     * @param int|string $id Identificador da chave.
     */
    public function invalidar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->invalidar((int) $chave['id']);

        return $this->voltar('Chave marcada como invalida. Corrija o valor e reative.');
    }

    /**
     * Zera o contador de consultas de uma chave.
     *
     * @param int|string $id Identificador da chave.
     */
    public function zerar($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->zerarConsultas((int) $chave['id']);

        return $this->voltar('Contador de consultas da chave zerado.');
    }

    /**
     * Remove uma chave.
     *
     * Os logs que usavam essa chave permanecem (chave_serpapi_id vira null).
     *
     * @param int|string $id Identificador da chave.
     */
    public function excluir($id): RedirectResponse
    {
        $chave = $this->carregarChave($id);

        $this->chaves->delete((int) $chave['id']);

        return $this->voltar('Chave excluida.');
    }

    // =========================================================================
    // APOIO INTERNO
    // =========================================================================

    /**
     * Le e normaliza os campos do formulario.
     *
     * @return array{apelido: string, chave: string, quantidade_consultas: int, ordem: int}
     */
    private function dadosDoFormulario(): array
    {
        return [
            'apelido'              => trim((string) $this->request->getPost('apelido')),
            'chave'                => trim((string) $this->request->getPost('chave')),
            'quantidade_consultas' => (int) $this->request->getPost('quantidade_consultas'),
            'ordem'                => (int) $this->request->getPost('ordem'),
        ];
    }

    /**
     * Valida os dados do formulario.
     *
     * @param array{apelido: string, chave: string, quantidade_consultas: int, ordem: int} $dados
     * @param bool                                                                        $exigeChave Criacao exige a chave.
     *
     * @return array<string, string>
     */
    private function validar(array $dados, bool $exigeChave): array
    {
        $erros = [];

        // Apelido.
        if ($dados['apelido'] === '' || mb_strlen($dados['apelido']) > 100) {
            $erros['apelido'] = 'Informe um apelido de ate 100 caracteres.';
        }

        // Chave da API.
        if ($exigeChave && $dados['chave'] === '') {
            $erros['chave'] = 'Informe o valor da chave da SerpApi.';
        } elseif (mb_strlen($dados['chave']) > 255) {
            $erros['chave'] = 'A chave deve ter no maximo 255 caracteres.';
        }

        // Quantidade contratada.
        if ($dados['quantidade_consultas'] < 0 || $dados['quantidade_consultas'] > 10000000) {
            $erros['quantidade_consultas'] = 'Informe uma quantidade entre 0 e 10.000.000.';
        }

        // Ordem de uso.
        if ($dados['ordem'] < 1 || $dados['ordem'] > 9999) {
            $erros['ordem'] = 'Informe uma ordem entre 1 e 9999.';
        }

        return $erros;
    }

    /**
     * Carrega uma chave garantindo que ela existe.
     *
     * @param int|string $id Identificador da rota.
     *
     * @return array<string, mixed>
     */
    private function carregarChave($id): array
    {
        $chave = $this->chaves->find($this->idDaRota($id));

        if ($chave === null) {
            throw PageNotFoundException::forPageNotFound('Chave nao encontrada.');
        }

        return $chave;
    }

    /**
     * Acrescenta a versao mascarada da chave e o percentual de uso.
     *
     * @param array<string, mixed> $chave
     *
     * @return array<string, mixed>
     */
    private function prepararChave(array $chave): array
    {
        $chave['chave_mascarada'] = $this->mascarar((string) $chave['chave']);

        // Nunca entregar a chave completa para a view.
        unset($chave['chave']);

        // Percentual de uso da chave.
        $total = max(1, (int) $chave['quantidade_consultas']);
        $chave['percentual'] = min(100, (int) round((int) $chave['consultas_usadas'] * 100 / $total));

        // Saldo restante.
        $chave['saldo'] = max(0, (int) $chave['quantidade_consultas'] - (int) $chave['consultas_usadas']);

        return $chave;
    }

    /**
     * Resumo geral das chaves (ativas, esgotadas, desativadas, invalidas).
     *
     * @param list<array<string, mixed>> $chaves
     *
     * @return array<string, int>
     */
    private function resumo(array $chaves): array
    {
        $resumo = ['total' => count($chaves), 'ativas' => 0, 'esgotadas' => 0, 'desativadas' => 0, 'invalidas' => 0];

        foreach ($chaves as $chave) {
            if ((int) $chave['ativa'] === 1) {
                $resumo['ativas']++;
                continue;
            }

            // Chaves com saldo zerado contam como esgotadas.
            if ((int) $chave['consultas_usadas'] >= (int) $chave['quantidade_consultas']) {
                $resumo['esgotadas']++;
            } elseif (texto_ou($chave, 'motivo_inativacao') === 'invalida') {
                $resumo['invalidas']++;
            } else {
                $resumo['desativadas']++;
            }
        }

        return $resumo;
    }

    /**
     * Proxima ordem livre (maior ordem + 1).
     */
    private function proximaOrdem(): int
    {
        $maior = 0;

        foreach ($this->chaves->listarTodas() as $chave) {
            $maior = max($maior, (int) $chave['ordem']);
        }

        return $maior + 1;
    }

    /**
     * Mascara o valor da chave, exibindo apenas o inicio e o fim.
     */
    private function mascarar(string $chave): string
    {
        $tamanho = strlen($chave);

        // Chave curta: mostra apenas os ultimos caracteres.
        if ($tamanho <= 8) {
            return str_repeat('•', 12) . substr($chave, -2);
        }

        return substr($chave, 0, 4) . str_repeat('•', 12) . substr($chave, -4);
    }

    /**
     * Redireciona para a listagem de chaves com uma mensagem de sucesso.
     */
    private function voltar(string $mensagem): RedirectResponse
    {
        return redirect()
            ->to(site_url('admin/chaves'))
            ->with('sucesso', $mensagem);
    }
}
