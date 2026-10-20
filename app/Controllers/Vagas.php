<?php

namespace App\Controllers;

use App\Models\VagaModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: VAGAS DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Listagem com abas por provedor, filtros, paginacao e tela de detalhe.
 *
 * Regras:
 *   - toda consulta e escopada pelo usuario da sessao;
 *   - abrir o link da vaga e uma acao de leitura (GET);
 *   - marcar como candidatada e acao de escrita (POST).
 */
class Vagas extends PainelBase
{
    /** Abas fixas do painel. */
    private const ABAS = ['Todas', 'Indeed', 'LinkedIn', 'Catho', 'Gupy', 'Divulga Vagas', 'Outras'];

    /** Itens por pagina. */
    private const POR_PAGINA = 10;

    /** Model usado por todas as acoes. */
    private VagaModel $vagas;

    /**
     * Carrega o model depois da inicializacao base.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->vagas = model(VagaModel::class);
    }

    /**
     * Lista as vagas do usuario.
     */
    public function index(): string
    {
        // Filtros vindos da URL (sempre normalizados).
        $filtros = $this->filtrosDaUrl();

        // Paginacao manual.
        $porPagina = self::POR_PAGINA;
        $pagina    = $this->paginaAtual();

        // Total de registros que atendem aos filtros.
        $total = $this->vagas->contarComFiltros($this->usuarioId, $filtros);

        // Total de paginas (nunca menos que 1).
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        // Nunca pede uma pagina inexistente.
        $pagina = min($pagina, $totalPaginas);

        // Deslocamento da pagina.
        $offset = ($pagina - 1) * $porPagina;

        // Lista da pagina atual.
        $lista = $this->vagas->listarComFiltros($this->usuarioId, $filtros, $porPagina, $offset);

        // Contagem por fonte para as abas.
        $abas = $this->montarAbas($this->vagas->contarPorFonte($this->usuarioId));

        return view('painel/vagas/index', $this->dadosPainel('Vagas', [
            'vagas'        => $lista,
            'abas'         => $abas,
            'filtros'      => $filtros,
            'total'        => $total,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'rotaBase'     => 'painel/vagas',
            'totalFila'    => $this->vagas->contarComEmailNaoCandidatadas($this->usuarioId),
        ]));
    }

    /**
     * Exibe o detalhe de uma vaga do usuario.
     *
     * @param int|string $id Identificador da vaga.
     */
    public function detalhe($id): string
    {
        // A vaga, mas somente se ela for do usuario da sessao (o helper
        // tambem registra a tentativa de acesso a vaga de outra pessoa).
        $vaga = obterVagaDoUsuarioOu404((int) $id, $this->usuarioId);

        // Decodifica a lista de e-mails encontrados.
        $emails = json_decode(valor_ou($vaga, 'emails_encontrados', '[]'), true);

        // Lista de e-mails (vazia quando o JSON nao traz lista).
        $listaEmails = [];

        if (is_array($emails)) {
            $listaEmails = array_values(array_filter($emails));
        }

        $vaga['emails_encontrados'] = $listaEmails;

        return view('painel/vagas/detalhe', $this->dadosPainel('Detalhe da vaga', [
            'vaga' => $vaga,
        ]));
    }

    /**
     * Marca manualmente uma vaga como candidatada.
     *
     * @param int|string $id Identificador da vaga.
     */
    public function marcarCandidatado($id): RedirectResponse
    {
        // A vaga, mas somente se ela for do usuario da sessao.
        obterVagaDoUsuarioOu404((int) $id, $this->usuarioId);

        // Marca a vaga do proprio usuario.
        $this->vagas->marcarCandidatado((int) $id, $this->usuarioId);

        // Volta para a pagina de origem.
        return redirect()
            ->back()
            ->with('sucesso', 'Vaga marcada como candidatada. Ela nao sera reenviada.');
    }

    /**
     * Le e valida os filtros da URL.
     *
     * @return array{fonte: string, busca: string, com_email: bool, ocultar_candidatadas: bool}
     */
    private function filtrosDaUrl(): array
    {
        // Fonte (aba selecionada).
        $fonte = trim((string) $this->request->getGet('fonte'));

        // Somente abas conhecidas.
        if (! in_array($fonte, self::ABAS, true)) {
            $fonte = 'Todas';
        }

        // Busca textual.
        $busca = trim((string) $this->request->getGet('busca'));

        // Limita o tamanho da busca.
        if (mb_strlen($busca) > 100) {
            $busca = mb_substr($busca, 0, 100);
        }

        return [
            'fonte'                => $fonte,
            'busca'                => $busca,
            'com_email'            => $this->request->getGet('com_email') === '1',
            'ocultar_candidatadas' => $this->request->getGet('ocultar_candidatadas') === '1',
        ];
    }

    /**
     * Agrupa a contagem de vagas nas abas fixas.
     *
     * @param array<string, int> $porFonte
     *
     * @return array<string, int>
     */
    private function montarAbas(array $porFonte): array
    {
        // Abas conhecidas (as demais entram em "Outras").
        $conhecidas = ['Indeed', 'LinkedIn', 'Catho', 'Gupy'];

        // Estrutura inicial.
        $contagens = [
            'Todas'    => 0,
            'Indeed'   => 0,
            'LinkedIn' => 0,
            'Catho'    => 0,
            'Gupy'     => 0,
            'Divulga Vagas' => 0,
            'Outras'   => 0,
        ];

        foreach ($porFonte as $fonte => $quantidade) {
            // Toda vaga soma no total geral.
            $contagens['Todas'] += $quantidade;

            // As vagas do segundo provedor tem a propria aba.
            if ($fonte === 'Divulga Vagas') {
                $contagens['Divulga Vagas'] += $quantidade;
                continue;
            }

            // Procura a fonte conhecida correspondente.
            $encontrada = false;

            foreach ($conhecidas as $conhecida) {
                if (str_starts_with((string) $fonte, $conhecida)) {
                    $contagens[$conhecida] += $quantidade;
                    $encontrada = true;

                    break;
                }
            }

            // Sem correspondencia: conta em "Outras".
            if (! $encontrada) {
                $contagens['Outras'] += $quantidade;
            }
        }

        // A aba do segundo provedor conta pelo provedor (e nao pela fonte).
        $contagens['Divulga Vagas'] = $this->vagas->contarPorProvedor($this->usuarioId, 'divulgavagas');

        return $contagens;
    }
}
