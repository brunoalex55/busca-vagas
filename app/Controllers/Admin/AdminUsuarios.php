<?php

namespace App\Controllers\Admin;

use App\Libraries\DistribuicaoConsultas;
use App\Libraries\GerenciadorCron;
use App\Models\ConfiguracaoModel;
use App\Models\EmailEnviadoModel;
use App\Models\LogConsultaModel;
use App\Models\PalavraChaveModel;
use App\Models\SolicitacaoLiberacaoModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - USUARIOS
 * --------------------------------------------------------------------------
 *
 * Gestao de contas:
 *   - listagem com filtros (busca, status, perfil, envio automatico);
 *   - ficha completa do usuario (configuracao, cota, movimentacao);
 *   - aprovacao, bloqueio e reativacao;
 *   - limite manual de consultas, perfil e envio automatico.
 *
 * Regras de protecao:
 *   - o admin nunca bloqueia, rebaixa ou exclui a propria conta;
 *   - o sistema nunca deixa o ultimo administrador ativo ser removido.
 */
class AdminUsuarios extends AdminBase
{
    /** Model de usuarios. */
    private UsuarioModel $usuarios;

    /** Model de solicitacoes de liberacao. */
    private SolicitacaoLiberacaoModel $solicitacoes;

    /** Model de configuracoes de envio. */
    private ConfiguracaoModel $configuracoes;

    /** Model de palavras-chave. */
    private PalavraChaveModel $palavras;

    /** Gerenciador de agendamento (limite de execucoes manuais). */
    private GerenciadorCron $gerenciador;

    /**
     * Carrega os models usados em todas as acoes.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->usuarios      = model(UsuarioModel::class);
        $this->solicitacoes  = model(SolicitacaoLiberacaoModel::class);
        $this->configuracoes = model(ConfiguracaoModel::class);
        $this->palavras      = model(PalavraChaveModel::class);
        $this->gerenciador   = new GerenciadorCron();
    }

    /**
     * Lista os usuarios com filtros e paginacao.
     */
    public function index(): string
    {
        // Filtros lidos da query string.
        $filtros = $this->filtrosDaConsulta();

        // Paginacao manual.
        $porPagina = $this->porPagina(20);
        $pagina    = $this->paginaAtual();
        $offset    = ($pagina - 1) * $porPagina;

        // Total e registros da pagina.
        $total        = $this->usuarios->contarAdmin($filtros);
        $registros    = $this->usuarios->listarAdmin($filtros, $porPagina, $offset);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        // Cota calculada automaticamente para exibir em cada linha.
        $distribuicao = (new DistribuicaoConsultas())->calcularTodas();

        return view('admin/usuarios/index', $this->dadosAdmin('Usuarios', [
            'usuarios'     => $registros,
            'filtros'      => $filtros,
            'estatisticas' => $this->usuarios->estatisticas(),
            'distribuicao' => $distribuicao,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'total'        => $total,
            'rotaBase'     => 'admin/usuarios',
        ]));
    }

    /**
     * Ficha completa de um usuario.
     *
     * @param int|string $id Identificador do usuario.
     */
    public function detalhe($id): string
    {
        // Usuario alvo.
        $usuario = $this->usuarios->find($this->idDaRota($id));

        if ($usuario === null) {
            throw PageNotFoundException::forPageNotFound('Usuario nao encontrado.');
        }

        $usuarioId = (int) $usuario['id'];

        // Configuracao de envio (sem o segredo da APP PASSWORD).
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);
        $configuracao['tem_app_password'] = $this->configuracoes->temAppPassword($configuracao);
        $configuracao['tem_curriculo']    = $this->configuracoes->temCurriculo($configuracao);
        unset($configuracao['app_password_criptografado']);

        // Resumo da cota de consultas.
        $cota = (new DistribuicaoConsultas())->resumo($usuarioId);

        // Historico resumido.
        $logs   = model(LogConsultaModel::class);
        $emails = model(EmailEnviadoModel::class);
        $vagas  = model(VagaModel::class);

        // Execucoes manuais do usuario (limite global definido pelo admin).
        $execucoesManuais = $this->gerenciador->usuarioPodeExecutarManualmente($usuarioId);

        return view('admin/usuarios/detalhe', $this->dadosAdmin('Usuario: ' . $usuario['nome'], [
            'usuario'       => $usuario,
            'configuracao'  => $configuracao,
            'cota'          => $cota,
            'solicitacoesUsuario' => $this->solicitacoesDoUsuario($usuarioId),
            'palavras'      => $this->palavras->listarDoUsuario($usuarioId),
            'totalVagas'    => $vagas->contarDoUsuario($usuarioId),
            'totalEnvios'   => $emails->contarDoUsuario($usuarioId),
            'totalConsultas' => $logs->contarDoUsuario($usuarioId),
            'logsRecentes'  => $logs->recentesDoUsuario($usuarioId, 10),
            'enviosRecentes' => $emails->recentesDoUsuario($usuarioId, 10),
            'execucoesManuais' => $execucoesManuais,
            'limiteGlobalManuais' => $this->gerenciador->obterLimiteExecucoesManuaisUsuario(),
        ]));
    }

    /**
     * Aprova a conta do usuario (status ativo).
     *
     * @param int|string $id Identificador do usuario.
     */
    public function aprovar($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        // Aprova a conta e encerra solicitacoes pendentes.
        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'ativo', 1);
        $this->solicitacoes->responderPendentesDoUsuario((int) $usuario['id'], 'aprovada');

        return $this->voltarParaUsuario((int) $usuario['id'], 'Usuario aprovado e liberado para uso.');
    }

    /**
     * Bloqueia a conta do usuario (status bloqueado).
     *
     * @param int|string $id Identificador do usuario.
     */
    public function bloquear($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        // Protecoes antes de bloquear.
        $erro = $this->validarProtecao((int) $usuario['id'], 'bloquear');

        if ($erro !== null) {
            return $this->voltarParaUsuario((int) $usuario['id'], $erro, true);
        }

        // Bloqueia a conta e mantem a flag ativo desligada.
        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'bloqueado', 0);

        return $this->voltarParaUsuario((int) $usuario['id'], 'Usuario bloqueado.');
    }

    /**
     * Reativa uma conta bloqueada (status ativo).
     *
     * @param int|string $id Identificador do usuario.
     */
    public function reativar($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        $this->usuarios->atualizarSituacao((int) $usuario['id'], 'ativo', 1);

        return $this->voltarParaUsuario((int) $usuario['id'], 'Usuario reativado.');
    }

    /**
     * Define ou limpa o limite manual de consultas.
     *
     * @param int|string $id Identificador do usuario.
     */
    public function definirLimite($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        // Campo vazio significa "voltar ao calculo automatico".
        $campo = trim((string) $this->request->getPost('limite_manual'));

        if ($campo === '') {
            $this->usuarios->definirLimiteManual((int) $usuario['id'], null);

            return $this->voltarParaUsuario((int) $usuario['id'], 'Limite manual removido. A cota volta a ser automatica.');
        }

        // Precisa ser um inteiro nao negativo.
        if (! ctype_digit($campo) || (int) $campo > 100000) {
            return $this->voltarParaUsuario((int) $usuario['id'], 'Informe um limite manual valido (0 a 100000).', true);
        }

        $this->usuarios->definirLimiteManual((int) $usuario['id'], (int) $campo);

        return $this->voltarParaUsuario((int) $usuario['id'], 'Limite manual atualizado.');
    }

    /**
     * Zera o contador de consultas do mes do usuario.
     *
     * @param int|string $id Identificador do usuario.
     */
    public function reiniciarConsultas($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        $this->usuarios->reiniciarConsultas((int) $usuario['id']);

        return $this->voltarParaUsuario((int) $usuario['id'], 'Contador de consultas do mes zerado.');
    }

    /**
     * Liga ou desliga o envio automatico autorizado pelo usuario.
     *
     * @param int|string $id Identificador do usuario.
     */
    public function alternarEnvio($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        // Inverte o estado atual.
        $novo = 1;

        if ((int) $usuario['envio_automatico'] === 1) {
            $novo = 0;
        }

        $this->usuarios->definirEnvioAutomatico((int) $usuario['id'], $novo);

        // Mensagem conforme o novo estado.
        $mensagem = 'Envio automatico desligado para este usuario.';

        if ($novo === 1) {
            $mensagem = 'Envio automatico ligado para este usuario.';
        }

        return $this->voltarParaUsuario((int) $usuario['id'], $mensagem);
    }

    /**
     * Altera o perfil de acesso (admin/usuario).
     *
     * @param int|string $id Identificador do usuario.
     */
    public function definirPerfil($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        // Perfil pedido no formulario.
        $perfil = (string) $this->request->getPost('perfil');

        if (! in_array($perfil, ['admin', 'usuario'], true)) {
            return $this->voltarParaUsuario((int) $usuario['id'], 'Perfil invalido.', true);
        }

        // Rebaixar exige protecoes (nao mexer no proprio admin nem no ultimo).
        if ($perfil === 'usuario') {
            $erro = $this->validarProtecao((int) $usuario['id'], 'rebaixar');

            if ($erro !== null) {
                return $this->voltarParaUsuario((int) $usuario['id'], $erro, true);
            }
        }

        $this->usuarios->definirPerfil((int) $usuario['id'], $perfil);

        // Mensagem conforme o novo perfil.
        $mensagem = 'Perfil alterado para usuario.';

        if ($perfil === 'admin') {
            $mensagem = 'Usuario promovido a administrador.';
        }

        return $this->voltarParaUsuario((int) $usuario['id'], $mensagem);
    }

    // =========================================================================
    // LIMITE DE EXECUCOES MANUAIS DO USUARIO
    // =========================================================================

    /**
     * Salva o limite diario de execucoes manuais (vale para todos os usuarios).
     *
     * O valor 0 desabilita as execucoes manuais. Administradores sao isentos.
     */
    public function salvarLimiteExecucoesManuais(): RedirectResponse
    {
        $limite = (int) $this->request->getPost('limite_execucoes_manuais');

        // Fora da faixa aceita (0 a 100).
        if (! $this->gerenciador->salvarLimiteExecucoesManuaisUsuario($limite)) {
            return $this->voltarParaUsuarioAlvo(
                $this->request->getPost('usuario_id'),
                'Informe um limite entre 0 e 100 (0 desabilita as execucoes manuais).',
                true
            );
        }

        $mensagem = 'Limite de execucoes manuais atualizado para ' . $limite . ' por dia.';

        if ($limite === 0) {
            $mensagem = 'Execucoes manuais desabilitadas para os usuarios.';
        }

        return $this->voltarParaUsuarioAlvo($this->request->getPost('usuario_id'), $mensagem);
    }

    /**
     * Zera o contador de execucoes manuais de hoje de um usuario.
     *
     * @param int|string $id Identificador do usuario.
     */
    public function zerarExecucoesManuaisUsuario($id): RedirectResponse
    {
        $usuario = $this->carregarUsuario($id);

        $this->gerenciador->zerarExecucoesManuaisUsuario((int) $usuario['id']);

        return $this->voltarParaUsuario(
            (int) $usuario['id'],
            'Contador de execucoes manuais de hoje zerado.'
        );
    }

    // =========================================================================
    // APOIO INTERNO
    // =========================================================================

    /**
     * Le os filtros da listagem a partir da query string.
     *
     * @return array{busca: string, status: string, perfil: string, envio: string}
     */
    private function filtrosDaConsulta(): array
    {
        return [
            'busca'  => trim((string) $this->request->getGet('busca')),
            'status' => (string) $this->request->getGet('status'),
            'perfil' => (string) $this->request->getGet('perfil'),
            'envio'  => (string) $this->request->getGet('envio'),
        ];
    }

    /**
     * Carrega um usuario garantindo que ele existe.
     *
     * @param int|string $id Identificador da rota.
     *
     * @return array<string, mixed>
     */
    private function carregarUsuario($id): array
    {
        $usuario = $this->usuarios->find($this->idDaRota($id));

        if ($usuario === null) {
            throw PageNotFoundException::forPageNotFound('Usuario nao encontrado.');
        }

        return $usuario;
    }

    /**
     * Lista as solicitacoes de um usuario especifico (historico da ficha).
     *
     * @return list<array<string, mixed>>
     */
    private function solicitacoesDoUsuario(int $usuarioId): array
    {
        return $this->solicitacoes->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->findAll(10);
    }

    /**
     * Valida as protecoes de acesso antes de bloquear/rebaixar.
     *
     * @param string $acao "bloquear" ou "rebaixar".
     *
     * @return string|null Mensagem de erro ou null quando a acao e segura.
     */
    private function validarProtecao(int $usuarioId, string $acao): ?string
    {
        // Nunca mexer na propria conta de administrador logado.
        if ($usuarioId === $this->adminId) {
            if ($acao === 'bloquear') {
                return 'Voce nao pode bloquear a propria conta.';
            }

            return 'Voce nao pode remover o seu proprio perfil de administrador.';
        }

        // Descobre se o alvo e um admin ativo.
        $alvo = $this->usuarios->find($usuarioId);

        if ($alvo === null || $alvo['perfil'] !== 'admin') {
            return null;
        }

        // Precisa sobrar pelo menos um admin ativo.
        if ($this->usuarios->contarAdminsAtivos() <= 1) {
            return 'O sistema precisa de pelo menos um administrador ativo.';
        }

        return null;
    }

    /**
     * Redireciona para a ficha do usuario com uma mensagem de flash.
     */
    private function voltarParaUsuario(int $usuarioId, string $mensagem, bool $erro = false): RedirectResponse
    {
        // Chave do flash conforme o tipo de mensagem.
        $chave = 'sucesso';

        if ($erro) {
            $chave = 'erro';
        }

        return redirect()
            ->to(site_url('admin/usuarios/' . $usuarioId))
            ->with($chave, $mensagem);
    }

    /**
     * Volta para a ficha do usuario informado no formulario ou, quando nao ha
     * ficha valida, para a listagem de usuarios.
     *
     * @param mixed $usuarioId Identificador vindo do POST.
     */
    private function voltarParaUsuarioAlvo($usuarioId, string $mensagem, bool $erro = false): RedirectResponse
    {
        $id = (int) $usuarioId;

        // Ficha valida: devolve para ela.
        if ($id > 0 && $this->usuarios->find($id) !== null) {
            return $this->voltarParaUsuario($id, $mensagem, $erro);
        }

        // Sem ficha confiavel: devolve para a listagem.
        // Chave do flash conforme o tipo de mensagem.
        $chave = 'sucesso';

        if ($erro) {
            $chave = 'erro';
        }

        return redirect()
            ->to(site_url('admin/usuarios'))
            ->with($chave, $mensagem);
    }
}
