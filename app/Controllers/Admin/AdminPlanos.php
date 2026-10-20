<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers\Admin;

use App\Libraries\ResolvedorPlano;
use App\Models\SolicitacaoPlanoModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - PLANOS
 * --------------------------------------------------------------------------
 *
 * O administrador configura os planos que o site publico promete:
 * quantas palavras-chave, quantas cidades por termo, quantos envios por
 * dia, se o envio automatico e o envio em massa existem, quantos
 * usuarios cabem e qual o tipo de suporte.
 *
 * REGRAS:
 *
 *   - toda escrita e POST (nada muda por GET);
 *   - o plano BASICO nunca pode ser excluido (ele e o fallback de quem
 *     nao tem plano): pode ser editado e desativado;
 *   - desativar um plano com usuarios vinculados e bloqueado (o admin
 *     precisa trocar os usuarios de plano antes);
 *   - NULL em max_palavras_chave e max_cidades_por_termo significa
 *     ILIMITADO (o campo em branco no formulario).
 */
class AdminPlanos extends AdminBase
{
    /**
     * O resolvedor de plano (a mesma regra que o painel do usuario usa).
     *
     * @var ResolvedorPlano
     */
    private ResolvedorPlano $resolvedor;

    /**
     * Carrega o que o controller precisa.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->resolvedor = new ResolvedorPlano();
    }

    /**
     * A lista de planos (com quantos usuarios tem em cada um).
     */
    public function index(): string
    {
        return view('admin/planos/index', $this->dadosAdmin('Planos', [
            'planos' => $this->listarPlanos(),
            'resumo' => $this->resumo(),
        ]));
    }

    /**
     * O formulario de criacao (sem id) ou de edicao (com id).
     *
     * @param int|string|null $id O id do plano (null = novo).
     */
    public function formulario($id = null): string
    {
        // O id vindo da rota (zero = plano novo).
        $planoId = 0;

        if ($id !== null && $id !== '') {
            $planoId = $this->idDaRota($id);
        }

        // Um plano novo com os valores padrao.
        $plano = [
            'id' => 0,
            'slug' => '',
            'nome' => '',
            'descricao' => '',
            'preco_mensal' => '',
            'max_palavras_chave' => '',
            'max_cidades_por_termo' => '',
            'limite_envios_dia' => 10,
            'envio_automatico' => 0,
            'envio_em_massa' => 0,
            'max_usuarios' => 1,
            'suporte' => 'email',
            'destaque' => 0,
            'ordem' => 0,
            'ativo' => 1,
        ];

        // Editando: carrega o plano.
        if ($planoId > 0) {
            $carregado = $this->buscarPlano($planoId);

            if ($carregado === null) {
                return $this->voltar('Plano nao encontrado.', false);
            }

            $plano = $carregado;

            // Os campos em branco viram texto (o formulario mostra "").
            $plano['max_palavras_chave'] = $this->textoOuVazio($plano, 'max_palavras_chave');
            $plano['max_cidades_por_termo'] = $this->textoOuVazio($plano, 'max_cidades_por_termo');
            $plano['preco_mensal'] = $this->textoOuVazio($plano, 'preco_mensal');
        }

        // O titulo da tela e o modo (criar ou editar).
        $titulo = 'Novo plano';
        $modo = 'criar';

        if ($planoId > 0) {
            $titulo = 'Editar plano';
            $modo = 'editar';
        }

        return view('admin/planos/formulario', $this->dadosAdmin(
            $titulo,
            [
                'plano' => $plano,
                'modo' => $modo,
                'suportes' => $this->suportes(),
            ]
        ));
    }

    /**
     * Grava o plano (cria ou atualiza). SOMENTE POST.
     */
    public function salvar(): RedirectResponse
    {
        // Os dados normalizados (e as validacoes).
        $dados = $this->dadosDoFormulario();
        $erros = $this->errosDoFormulario($dados);

        // Com erro: volta para o formulario.
        if ($erros !== []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('erros', $erros);
        }

        // O id do plano (zero = novo).
        $planoId = (int) $dados['id'];

        // O id nao entra no gravacao.
        unset($dados['id']);

        if ($planoId > 0) {
            // O plano tem que existir.
            if ($this->buscarPlano($planoId) === null) {
                return $this->voltar('Plano nao encontrado.', false);
            }

            $dados['atualizado_em'] = date('Y-m-d H:i:s');

            db_connect()->table('planos')->where('id', $planoId)->update($dados);

            return $this->voltar('Plano atualizado.');
        }

        // Novo plano.
        $dados['criado_em'] = date('Y-m-d H:i:s');
        $dados['atualizado_em'] = date('Y-m-d H:i:s');

        db_connect()->table('planos')->insert($dados);

        return $this->voltar('Plano criado.');
    }

    /**
     * Desativa o plano. SOMENTE POST.
     *
     * @param int|string $id O id do plano.
     */
    public function desativar($id): RedirectResponse
    {
        $planoId = $this->idDaRota($id);

        $plano = $this->buscarPlano($planoId);

        if ($plano === null) {
            return $this->voltar('Plano nao encontrado.', false);
        }

        // O plano com usuarios nao pode ser desativado (o admin troca
        // os usuarios de plano antes).
        $usuarios = $this->quantidadeDeUsuariosDoPlano($planoId);

        if ($usuarios > 0) {
            return $this->voltar(
                'Este plano tem ' . $usuarios . ' usuario(s). Troque-os de plano antes de desativar.',
                false
            );
        }

        db_connect()->table('planos')->where('id', $planoId)->update([
            'ativo' => 0,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return $this->voltar('Plano desativado.');
    }

    /**
     * Reativa o plano. SOMENTE POST.
     *
     * @param int|string $id O id do plano.
     */
    public function reativar($id): RedirectResponse
    {
        $planoId = $this->idDaRota($id);

        if ($this->buscarPlano($planoId) === null) {
            return $this->voltar('Plano nao encontrado.', false);
        }

        db_connect()->table('planos')->where('id', $planoId)->update([
            'ativo' => 1,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return $this->voltar('Plano reativado.');
    }

    /**
     * Os usuarios de um plano. SOMENTE GET (a listagem nao muda nada).
     *
     * @param string $slug O slug do plano.
     */
    public function usuariosDoPlano($slug): string
    {
        $plano = $this->buscarPlanoPorSlug((string) $slug);

        if ($plano === null) {
            return $this->voltar('Plano nao encontrado.', false);
        }

        return view('admin/planos/usuarios', $this->dadosAdmin('Usuarios do plano', [
            'plano' => $plano,
            'usuarios' => $this->listarUsuariosDoPlano((int) $plano['id']),
        ]));
    }

    /**
     * Os pedidos de plano enviados pelos usuarios do painel.
     *
     * E a fila do administrador: o usuario escolheu um plano la e
     * espera a confirmacao. O filtro de status vem da URL (GET).
     */
    public function solicitacoes(): string
    {
        // O filtro de status (padrao: pendente).
        $status = trim((string) $this->request->getGet('status'));

        if (! in_array($status, ['pendente', 'aprovada', 'recusada', 'todas'], true)) {
            $status = 'pendente';
        }

        $model = model(SolicitacaoPlanoModel::class);

        // Os pedidos do filtro.
        $pedidos = $model->listarPorStatus($status, 100);

        // Quantos tem em cada situacao (as abas).
        $contagens = [
            'pendente' => $model->contarPendentes(),
            'aprovada' => $model->contarPorStatus('aprovada'),
            'recusada' => $model->contarPorStatus('recusada'),
            'todas' => $model->contarPorStatus('todas'),
        ];

        return view('admin/planos/solicitacoes', $this->dadosAdmin('Pedidos de plano', [
            'pedidos' => $pedidos,
            'contagens' => $contagens,
            'status' => $status,
        ]));
    }

    /**
     * Aprova o pedido de plano: o plano passa a valer na conta.
     *
     * @param int|string $id O id do pedido.
     */
    public function aprovarSolicitacao($id): RedirectResponse
    {
        $pedido = model(SolicitacaoPlanoModel::class)->buscar($this->idDaRota($id));

        if ($pedido === null) {
            return $this->voltarParaPedidos('Pedido nao encontrado.', false);
        }

        if ((string) valor_ou($pedido, 'status', '') !== 'pendente') {
            return $this->voltarParaPedidos('Este pedido ja foi respondido.', false);
        }

        // Aplica o plano na conta do usuario.
        $aprovado = model(SolicitacaoPlanoModel::class)->aprovar(
            (int) valor_ou($pedido, 'id', 0),
            (int) $this->adminId
        );

        if (! $aprovado) {
            return $this->voltarParaPedidos('Nao foi possivel aprovar o pedido.', false);
        }

        return $this->voltarParaPedidos('Pedido aprovado. O plano ja vale na conta do usuario.');
    }

    /**
     * Recusa o pedido de plano (com a justificativa que o usuario ve).
     *
     * @param int|string $id O id do pedido.
     */
    public function recusarSolicitacao($id): RedirectResponse
    {
        $pedido = model(SolicitacaoPlanoModel::class)->buscar($this->idDaRota($id));

        if ($pedido === null) {
            return $this->voltarParaPedidos('Pedido nao encontrado.', false);
        }

        if ((string) valor_ou($pedido, 'status', '') !== 'pendente') {
            return $this->voltarParaPedidos('Este pedido ja foi respondido.', false);
        }

        // A justificativa do administrador.
        $resposta = trim((string) $this->request->getPost('resposta'));

        if (mb_strlen($resposta) > 1000) {
            $resposta = mb_substr($resposta, 0, 1000);
        }

        model(SolicitacaoPlanoModel::class)->recusar(
            (int) valor_ou($pedido, 'id', 0),
            (int) $this->adminId,
            $resposta
        );

        return $this->voltarParaPedidos('Pedido recusado. O usuario pode pedir outro plano.');
    }

    // =====================================================================
    // AJUDA
    // =====================================================================

    /**
     * Todos os planos, com a contagem de usuarios de cada um.
     *
     * @return list<array<string, mixed>>
     */
    private function listarPlanos(): array
    {
        $planos = db_connect()->table('planos')->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        foreach ($planos as $indice => $plano) {
            $planos[$indice]['usuarios'] = $this->quantidadeDeUsuariosDoPlano((int) $plano['id']);
        }

        return $planos;
    }

    /**
     * Um plano pelo id.
     *
     * @return array<string, mixed>|null
     */
    private function buscarPlano(int $planoId): ?array
    {
        if ($planoId <= 0) {
            return null;
        }

        $plano = db_connect()->table('planos')->where('id', $planoId)->get()->getRowArray();

        if ($plano === null || $plano === []) {
            return null;
        }

        return $plano;
    }

    /**
     * Um plano pelo slug.
     *
     * @return array<string, mixed>|null
     */
    private function buscarPlanoPorSlug(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $plano = db_connect()->table('planos')->where('slug', $slug)->get()->getRowArray();

        if ($plano === null || $plano === []) {
            return null;
        }

        return $plano;
    }

    /**
     * Quantos usuarios tem no plano.
     */
    private function quantidadeDeUsuariosDoPlano(int $planoId): int
    {
        return (int) db_connect()->table('usuarios')
            ->where('plano_id', $planoId)
            ->countAllResults();
    }

    /**
     * Os usuarios de um plano (para a tela de listagem).
     *
     * @return list<array<string, mixed>>
     */
    private function listarUsuariosDoPlano(int $planoId): array
    {
        return db_connect()->table('usuarios')
            ->select('id, nome, email, status, limite_manual, plano_inicio_em')
            ->where('plano_id', $planoId)
            ->orderBy('nome', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * O resumo dos planos (quantos usuarios em cada um).
     *
     * @return array<string, int>
     */
    private function resumo(): array
    {
        $total = (int) db_connect()->table('usuarios')->countAllResults();

        $porPlano = db_connect()->table('usuarios')
            ->select('plano_id, COUNT(*) AS total')
            ->groupBy('plano_id')
            ->get()
            ->getResultArray();

        $resumo = [
            'total' => $total,
            'sem_plano' => 0,
            'basico' => 0,
            'profissional' => 0,
            'empresa' => 0,
        ];

        foreach ($porPlano as $linha) {
            $planoId = (int) valor_ou($linha, 'plano_id', 0);
            $quantidade = (int) $linha['total'];

            // Sem plano: cai no basico.
            if ($planoId <= 0) {
                $resumo['sem_plano'] += $quantidade;
                continue;
            }

            $plano = db_connect()->table('planos')->where('id', $planoId)->get()->getRowArray();
            $slug = (string) valor_ou($plano, 'slug', '');

            if (isset($resumo[$slug])) {
                $resumo[$slug] += $quantidade;
            }
        }

        return $resumo;
    }

    /**
     * Os tipos de suporte possiveis.
     *
     * @return array<string, string>
     */
    private function suportes(): array
    {
        return [
            'email' => 'E-mail',
            'prioritario' => 'Prioritário',
        ];
    }

    /**
     * O valor de um campo, como texto (vazio quando e null).
     *
     * @param array<string, mixed> $dados
     */
    private function textoOuVazio(array $dados, string $chave): string
    {
        $valor = valor_ou($dados, $chave, null);

        if ($valor === null) {
            return '';
        }

        return (string) $valor;
    }

    /**
     * Os dados do formulario, normalizados.
     *
     * @return array<string, mixed>
     */
    private function dadosDoFormulario(): array
    {
        // O id (zero = novo plano).
        $id = (int) $this->request->getPost('id');

        // O slug: minusculas, sem espaco e sem acento.
        $slug = strtolower(trim((string) $this->request->getPost('slug')));
        $slug = (string) preg_replace('/[^a-z0-9_]/', '', $slug);

        // O nome do plano.
        $nome = trim((string) $this->request->getPost('nome'));

        // A descricao (vazio = null).
        $descricao = trim((string) $this->request->getPost('descricao'));
        $descricaoFinal = null;

        if ($descricao !== '') {
            $descricaoFinal = $descricao;
        }

        // O preco (pode ficar vazio).
        $preco = trim((string) $this->request->getPost('preco_mensal'));
        $precoFinal = null;

        if ($preco !== '') {
            $precoFinal = (float) str_replace(',', '.', $preco);
        }

        // Os limites que podem ser ilimitados (vazio = NULL = ilimitado).
        $palavras = trim((string) $this->request->getPost('max_palavras_chave'));
        $palavrasFinal = null;

        if ($palavras !== '') {
            $palavrasFinal = (int) $palavras;
        }

        $cidades = trim((string) $this->request->getPost('max_cidades_por_termo'));
        $cidadesFinal = null;

        if ($cidades !== '') {
            $cidadesFinal = (int) $cidades;
        }

        // O limite de envios (obrigatorio).
        $envios = (int) $this->request->getPost('limite_envios_dia');

        // As flags (0 ou 1).
        $automatico = 0;
        $massa = 0;
        $destaque = 0;
        $ativo = 0;

        if ((int) $this->request->getPost('envio_automatico') === 1) {
            $automatico = 1;
        }

        if ((int) $this->request->getPost('envio_em_massa') === 1) {
            $massa = 1;
        }

        if ((int) $this->request->getPost('destaque') === 1) {
            $destaque = 1;
        }

        if ((int) $this->request->getPost('ativo') === 1) {
            $ativo = 1;
        }

        // Quantos usuarios cabem no plano.
        $usuarios = (int) $this->request->getPost('max_usuarios');

        // O tipo de suporte.
        $suporte = trim((string) $this->request->getPost('suporte'));

        if (! in_array($suporte, ['email', 'prioritario'], true)) {
            $suporte = 'email';
        }

        // A ordem de exibicao.
        $ordem = (int) $this->request->getPost('ordem');

        return [
            'id' => $id,
            'slug' => $slug,
            'nome' => $nome,
            'descricao' => $descricaoFinal,
            'preco_mensal' => $precoFinal,
            'max_palavras_chave' => $palavrasFinal,
            'max_cidades_por_termo' => $cidadesFinal,
            'limite_envios_dia' => $envios,
            'envio_automatico' => $automatico,
            'envio_em_massa' => $massa,
            'max_usuarios' => $usuarios,
            'suporte' => $suporte,
            'destaque' => $destaque,
            'ordem' => $ordem,
            'ativo' => $ativo,
        ];
    }

    /**
     * As validacoes do formulario.
     *
     * @param array<string, mixed> $dados Os dados normalizados.
     *
     * @return list<string> As mensagens de erro (vazio = tudo certo).
     */
    private function errosDoFormulario(array $dados): array
    {
        $erros = [];

        // O nome e obrigatorio.
        if (texto_ou($dados, 'nome') === '') {
            $erros[] = 'Informe o nome do plano.';
        }

        // O slug: so letras minusculas, numeros e underscore.
        $slug = texto_ou($dados, 'slug');

        if ($slug === '') {
            $erros[] = 'Informe o identificador do plano (slug).';
        } elseif (preg_match('/^[a-z0-9_]+$/', $slug) !== 1) {
            $erros[] = 'O identificador do plano aceita apenas letras minusculas, numeros e underscore.';
        } else {
            // O slug tem que ser unico (fora o proprio plano, na edicao).
            $outro = db_connect()->table('planos')
                ->where('slug', $slug)
                ->where('id !=', (int) $dados['id'])
                ->countAllResults();

            if ($outro > 0) {
                $erros[] = 'Ja existe um plano com esse identificador.';
            }
        }

        // O limite de envios.
        $envios = (int) $dados['limite_envios_dia'];

        if ($envios < 1 || $envios > 1000) {
            $erros[] = 'O limite de envios por dia deve ficar entre 1 e 1000.';
        }

        // Quantos usuarios cabem.
        $usuarios = (int) $dados['max_usuarios'];

        if ($usuarios < 1) {
            $erros[] = 'O plano precisa caber pelo menos 1 usuario.';
        }

        // Os limites que podem ser ilimitados.
        $palavras = valor_ou($dados, 'max_palavras_chave', null);

        if ($palavras !== null && (int) $palavras < 1) {
            $erros[] = 'O limite de palavras-chave deve ser 1 ou maior (ou vazio, para ilimitado).';
        }

        $cidades = valor_ou($dados, 'max_cidades_por_termo', null);

        if ($cidades !== null && (int) $cidades < 1) {
            $erros[] = 'O limite de cidades por termo deve ser 1 ou maior (ou vazio, para ilimitado).';
        }

        return $erros;
    }

    /**
     * Volta para a tela de planos com uma mensagem.
     */
    private function voltar(string $mensagem, bool $sucesso = true): RedirectResponse
    {
        if (! $sucesso) {
            return redirect()
                ->to(site_url('admin/planos'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('admin/planos'))
            ->with('sucesso', $mensagem);
    }

    /**
     * Volta para a fila de pedidos de plano com uma mensagem.
     */
    private function voltarParaPedidos(string $mensagem, bool $sucesso = true): RedirectResponse
    {
        if (! $sucesso) {
            return redirect()
                ->to(site_url('admin/planos/solicitacoes'))
                ->with('erro', $mensagem);
        }

        return redirect()
            ->to(site_url('admin/planos/solicitacoes'))
            ->with('sucesso', $mensagem);
    }
}
