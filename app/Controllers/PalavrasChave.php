<?php

namespace App\Controllers;

use App\Libraries\ResolvedorPlano;
use App\Models\PalavraChaveModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: PALAVRAS-CHAVE
 * --------------------------------------------------------------------------
 *
 * CRUD completo das palavras-chave do usuario.
 *
 * Cada palavra-chave combina um termo com uma lista de cidades. O
 * sistema gera uma consulta por combinacao (termo + cidade).
 *
 * Toda acao de escrita usa POST e e sempre escopada pelo usuario
 * autenticado: ninguem edita ou apaga registro de outra conta.
 */
class PalavrasChave extends PainelBase
{
    /** Model usado por todas as acoes. */
    private PalavraChaveModel $palavras;

    /**
     * Carrega o model depois da inicializacao base.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->palavras = model(PalavraChaveModel::class);
    }

    /**
     * Lista as palavras-chave do usuario.
     */
    public function index(): string
    {
        // Registros do dono da sessao.
        $registros = $this->palavras->listarDoUsuario($this->usuarioId);

        // Total de combinacoes ativas (termo x cidade).
        $totalCombinacoes = $this->palavras->totalCombinacoesDoUsuario($this->usuarioId);

        return view('painel/palavras_chave/index', $this->dadosPainel('Palavras-chave', [
            'palavras'         => $registros,
            'totalPalavras'    => count($registros),
            'totalCombinacoes' => $totalCombinacoes,
        ]));
    }

    /**
     * Formulario de criacao.
     */
    public function nova(): string
    {
        return view('painel/palavras_chave/formulario', $this->dadosPainel('Nova palavra-chave', [
            'palavra' => [
                'id'               => 0,
                'termo'            => old('termo', ''),
                'cidades'          => old('cidades', ''),
                'remoto'           => old('remoto', '0'),
                'frequencia_horas' => old('frequencia_horas', 24),
                'ativa'            => 1,
                'total_consultas'  => 0,
                'ultima_execucao'  => null,
            ],
            'modo' => 'criar',
        ]));
    }

    /**
     * Grava uma nova palavra-chave.
     */
    public function criar(): RedirectResponse
    {
        // O plano permite mais uma palavra-chave? (a tela tambem
        // esconde o botao, mas quem decide e o servidor).
        $permitido = (new ResolvedorPlano())->podeAdicionarPalavraChave($this->usuarioId);

        if ($permitido['pode'] === false) {
            return $this->voltarComErros(['palavras_chave' => $this->mensagemDoLimiteDePalavras($permitido)]);
        }

        // Dados normalizados vindos do formulario.
        $dados = $this->dadosDoFormulario();

        // O plano permite quantas cidades por termo?
        $errosCidades = $this->errosDoLimiteDeCidades($dados);

        if ($errosCidades !== '') {
            return $this->voltarComErros(['cidades' => $errosCidades]);
        }

        // Validacao de negocio.
        $erros = $this->validar($dados);

        // Volta ao formulario mantendo o que foi digitado.
        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Persiste a nova palavra-chave.
        $this->palavras->insert([
            'usuario_id'       => $this->usuarioId,
            'termo'            => $dados['termo'],
            'cidades'          => json_encode($dados['cidades'], JSON_UNESCAPED_UNICODE),
            'remoto'           => $dados['remoto'],
            'idioma'           => 'pt',
            'pais'             => 'br',
            'dominio_google'   => 'google.com.br',
            'frequencia_horas' => $dados['frequencia_horas'],
            'proxima_execucao' => date('Y-m-d H:i:s'),
            'ultima_execucao'  => null,
            'total_consultas'  => 0,
            'ativa'            => 1,
        ]);

        return redirect()
            ->to(site_url('painel/palavras-chave'))
            ->with('sucesso', 'Palavra-chave criada. O sistema ja pode busca-la.');
    }

    /**
     * Formulario de edicao.
     *
     * @param int|string $id Identificador da palavra-chave.
     */
    public function editar($id): string
    {
        // Busca escopada pelo dono.
        $palavra = $this->palavras->buscarDoUsuario((int) $id, $this->usuarioId);

        // Registro inexistente ou de outro usuario.
        if ($palavra === null) {
            throw PageNotFoundException::forPageNotFound('Palavra-chave nao encontrada.');
        }

        // Cidades salvas em JSON voltam como texto (uma por linha).
        $cidades = json_decode(valor_ou($palavra, 'cidades', '[]'), true);

        // Lista de cidades (vazia quando o JSON nao traz lista).
        $listaCidades = [];

        if (is_array($cidades)) {
            $listaCidades = $cidades;
        }

        $palavra['cidades'] = implode("\n", $listaCidades);

        return view('painel/palavras_chave/formulario', $this->dadosPainel('Editar palavra-chave', [
            'palavra' => $palavra,
            'modo'    => 'editar',
        ]));
    }

    /**
     * Atualiza uma palavra-chave existente.
     *
     * @param int|string $id Identificador da palavra-chave.
     */
    public function atualizar($id): RedirectResponse
    {
        // Confere a propriedade antes de qualquer coisa.
        $palavra = $this->palavras->buscarDoUsuario((int) $id, $this->usuarioId);

        if ($palavra === null) {
            throw PageNotFoundException::forPageNotFound('Palavra-chave nao encontrada.');
        }

        // Dados normalizados.
        $dados = $this->dadosDoFormulario();

        // Validacao.
        $erros = $this->validar($dados);

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Atualiza sem revalidar campos que nao vieram no formulario.
        $this->palavras->skipValidation(true)->update((int) $id, [
            'termo'            => $dados['termo'],
            'cidades'          => json_encode($dados['cidades'], JSON_UNESCAPED_UNICODE),
            'remoto'           => $dados['remoto'],
            'frequencia_horas' => $dados['frequencia_horas'],
            'ativa'            => $dados['ativa'],
            'proxima_execucao' => date('Y-m-d H:i:s'),
        ]);

        return redirect()
            ->to(site_url('painel/palavras-chave'))
            ->with('sucesso', 'Palavra-chave atualizada.');
    }

    /**
     * Pausa ou reativa uma palavra-chave.
     *
     * @param int|string $id Identificador da palavra-chave.
     */
    public function alternar($id): RedirectResponse
    {
        // Busca escopada pelo dono.
        $palavra = $this->palavras->buscarDoUsuario((int) $id, $this->usuarioId);

        if ($palavra === null) {
            throw PageNotFoundException::forPageNotFound('Palavra-chave nao encontrada.');
        }

        // Novo estado (inverte o atual).
        $novaAtiva = 1;

        if ((int) $palavra['ativa'] === 1) {
            $novaAtiva = 0;
        }

        // Ao reativar, agenda a proxima execucao para agora.
        $proximaExecucao = $palavra['proxima_execucao'];

        if ($novaAtiva === 1) {
            $proximaExecucao = date('Y-m-d H:i:s');
        }

        $this->palavras->skipValidation(true)->update((int) $id, [
            'ativa'            => $novaAtiva,
            'proxima_execucao' => $proximaExecucao,
        ]);

        // Mensagem conforme o novo estado.
        $mensagem = 'Palavra-chave pausada.';

        if ($novaAtiva === 1) {
            $mensagem = 'Palavra-chave reativada.';
        }

        return redirect()
            ->to(site_url('painel/palavras-chave'))
            ->with('sucesso', $mensagem);
    }

    /**
     * Exclui uma palavra-chave.
     *
     * @param int|string $id Identificador da palavra-chave.
     */
    public function excluir($id): RedirectResponse
    {
        // Busca escopada pelo dono.
        $palavra = $this->palavras->buscarDoUsuario((int) $id, $this->usuarioId);

        if ($palavra === null) {
            throw PageNotFoundException::forPageNotFound('Palavra-chave nao encontrada.');
        }

        // Remove o registro.
        $this->palavras->delete((int) $id);

        return redirect()
            ->to(site_url('painel/palavras-chave'))
            ->with('sucesso', 'Palavra-chave excluida.');
    }

    /**
     * A mensagem do limite de palavras-chave.
     *
     * @param array{pode: bool, motivo: string, restantes: int|null, limite: int|null} $permitido
     */
    private function mensagemDoLimiteDePalavras(array $permitido): string
    {
        $limite = (int) $permitido['limite'];
        $plano = (new ResolvedorPlano())->obterPlanoDoUsuario($this->usuarioId);

        return 'Seu plano ' . (string) $plano['nome'] . ' permite ' . $limite
            . ' palavra-chave(s). Faca upgrade no painel para liberar mais.';
    }

    /**
     * O erro do limite de cidades por termo (vazio quando pode).
     *
     * @param array<string, mixed> $dados Os dados normalizados.
     */
    private function errosDoLimiteDeCidades(array $dados): string
    {
        $resolvedor = new ResolvedorPlano();

        // O limite do plano (NULL = ilimitado).
        $limite = $resolvedor->resolverLimite('max_cidades_por_termo', $this->usuarioId);

        // Ilimitado: nunca bloqueia.
        if ($limite === null) {
            return '';
        }

        $cidades = valor_ou($dados, 'cidades', []);

        if (! is_array($cidades)) {
            $cidades = [];
        }

        // Dentro do limite: tudo certo.
        if (count($cidades) <= $limite) {
            return '';
        }

        $plano = $resolvedor->obterPlanoDoUsuario($this->usuarioId);

        return 'Seu plano ' . (string) $plano['nome'] . ' permite ' . $limite
            . ' cidade(s) por termo. Faca upgrade no painel para liberar mais.';
    }
    /**
     * Le e normaliza os campos enviados pelo formulario.
     *
     * @return array{termo: string, cidades: list<string>, remoto: int, frequencia_horas: int, ativa: int}
     */
    private function dadosDoFormulario(): array
    {
        // Termo buscado.
        $termo = trim((string) $this->request->getPost('termo'));

        // Lista de cidades digitada (uma por linha).
        $cidades = $this->separarCidades((string) $this->request->getPost('cidades'));

        // Trabalho remoto.
        $remoto = 0;

        if ($this->request->getPost('remoto') === '1') {
            $remoto = 1;
        }

        // Frequencia em horas.
        $frequencia = (int) $this->request->getPost('frequencia_horas');

        // Estado (usado na edicao; criacao sempre nasce ativa).
        $ativa = 0;

        if ($this->request->getPost('ativa') === '1') {
            $ativa = 1;
        }

        return [
            'termo'            => $termo,
            'cidades'          => $cidades,
            'remoto'           => $remoto,
            'frequencia_horas' => $frequencia,
            'ativa'            => $ativa,
        ];
    }

    /**
     * Separa a lista de cidades digitada em linhas.
     *
     * @return list<string>
     */
    private function separarCidades(string $texto): array
    {
        // Aceita quebra de linha, virgula e ponto e virgula.
        $partes = preg_split('/[\r\n,;]+/u', $texto);

        // Sem separadores: nenhuma parte.
        if ($partes === false) {
            $partes = [];
        }

        // Mapa auxiliar para evitar repetidas (sem diferenciar maiusculas).
        $cidades = [];

        foreach ($partes as $parte) {
            // Remove espacos extras.
            $cidade = trim((string) preg_replace('/\s+/u', ' ', $parte));

            if ($cidade === '') {
                continue;
            }

            // Limita o tamanho de cada cidade.
            if (mb_strlen($cidade) > 150) {
                $cidade = mb_substr($cidade, 0, 150);
            }

            // A chave em minusculas evita duplicidade.
            $chave = mb_strtolower($cidade);

            if (! isset($cidades[$chave])) {
                $cidades[$chave] = $cidade;
            }
        }

        // Limita a 30 cidades por palavra-chave.
        return array_slice(array_values($cidades), 0, 30);
    }

    /**
     * Valida os dados do formulario.
     *
     * @param array{termo: string, cidades: list<string>, remoto: int, frequencia_horas: int, ativa: int} $dados
     *
     * @return array<string, string>
     */
    private function validar(array $dados): array
    {
        $erros = [];

        // Termo.
        if (mb_strlen($dados['termo']) < 2) {
            $erros['termo'] = 'Informe um termo com pelo menos 2 caracteres.';
        } elseif (mb_strlen($dados['termo']) > 150) {
            $erros['termo'] = 'O termo deve ter no maximo 150 caracteres.';
        }

        // Cidades.
        if ($dados['cidades'] === []) {
            $erros['cidades'] = 'Informe pelo menos uma cidade (uma por linha).';
        }

        // Frequencia.
        if ($dados['frequencia_horas'] < 1 || $dados['frequencia_horas'] > 168) {
            $erros['frequencia_horas'] = 'Escolha uma frequencia entre 1 e 168 horas.';
        }

        return $erros;
    }
}
