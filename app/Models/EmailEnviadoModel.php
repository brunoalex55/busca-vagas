<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: E-MAILS ENVIADOS
 * --------------------------------------------------------------------------
 *
 * Historico de todas as candidaturas enviadas (manual, em massa ou
 * automatica). Serve de auditoria e de trava contra reenvio.
 */
class EmailEnviadoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'emails_enviados';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'vaga_id',
        'destinatario',
        'assunto',
        'origem',
        'provedor',
        'status',
        'mensagem_erro',
        'enviado_em',
    ];

    // Esta tabela usa apenas enviado_em.
    protected $useTimestamps = false;

    /**
     * Registra uma tentativa de envio no historico.
     *
     * A origem e normalizada para um dos valores aceitos pela coluna
     * ENUM (`manual`, `massa`, `automatico`). O momento do envio e
     * preenchido quando nao informado.
     *
     * @param array<string, mixed> $dados
     *
     * @return int Identificador do registro criado.
     */
    public function registrar(array $dados): int
    {
        // Nunca deixar a origem fora do ENUM da coluna.
        $origens = ['manual', 'massa', 'automatico'];

        if (! in_array(texto_ou($dados, 'origem'), $origens, true)) {
            $dados['origem'] = 'automatico';
        }

        // Momento do envio.
        if (empty($dados['enviado_em'])) {
            $dados['enviado_em'] = date('Y-m-d H:i:s');
        }

        // Provedor de origem da vaga (a coluna tem padrao 'serpapi').
        $provedores = ['serpapi', 'divulgavagas'];

        if (! isset($dados['provedor']) || ! in_array(texto_ou($dados, 'provedor'), $provedores, true)) {
            $dados['provedor'] = 'serpapi';
        }

        return (int) $this->insert($dados, true);
    }

    /**
     * Retorna o historico recente de um usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function recentesDoUsuario(int $usuarioId, int $limite = 50): array
    {
        return $this->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->findAll($limite);
    }

    /**
     * Conta envios por origem (manual, massa, automatico).
     *
     * @return array<string, int>
     */
    public function contarPorOrigem(int $usuarioId): array
    {
        $linhas = $this->select('origem, COUNT(*) AS total', false)
            ->where('usuario_id', $usuarioId)
            ->groupBy('origem')
            ->findAll();

        $resultado = [];

        foreach ($linhas as $linha) {
            $resultado[$linha['origem']] = (int) $linha['total'];
        }

        return $resultado;
    }

    /**
     * Lista o historico de envios com o titulo da vaga, de forma paginada.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPaginado(int $usuarioId, int $limite, int $offset): array
    {
        // Junta a vaga para exibir titulo/empresa no historico.
        return $this->db->table('emails_enviados e')
            ->select('e.*, v.titulo AS vaga_titulo, v.empresa AS vaga_empresa')
            ->join('vagas v', 'v.id = e.vaga_id', 'left')
            ->where('e.usuario_id', $usuarioId)
            ->orderBy('e.id', 'DESC')
            ->get($limite, $offset)
            ->getResultArray();
    }

    /**
     * Total de registros de envio do usuario (inclui erros).
     */
    public function contarDoUsuario(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->countAllResults();
    }

    /**
     * Total de candidaturas enviadas com sucesso.
     */
    public function contarEnviados(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('status', 'enviado')
            ->countAllResults();
    }

    /**
     * Candidaturas enviadas com sucesso hoje.
     */
    public function contarHoje(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('status', 'enviado')
            ->where('enviado_em >=', date('Y-m-d 00:00:00'))
            ->countAllResults();
    }

    // =========================================================================
    // CONSULTAS DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista os envios de todos os usuarios, com filtros e paginacao.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarGlobal(array $filtros, int $limite, int $offset): array
    {
        $builder = $this->db->table('emails_enviados e')
            ->select('e.*, u.nome AS usuario_nome, u.email AS usuario_email, v.titulo AS vaga_titulo')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->join('vagas v', 'v.id = e.vaga_id', 'left')
            ->orderBy('e.id', 'DESC');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de envios que atendem aos filtros.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     */
    public function contarGlobal(array $filtros): int
    {
        $builder = $this->db->table('emails_enviados e')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de envios.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     */
    private function aplicarFiltrosGlobal(\CodeIgniter\Database\BaseBuilder $builder, array $filtros): void
    {
        // Busca por usuario (nome/e-mail) ou destinatario.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('u.nome', $busca)
                ->orLike('u.email', $busca)
                ->orLike('e.destinatario', $busca)
                ->groupEnd();
        }

        // Status do envio (enviado, erro_destinatario, erro_smtp, ...).
        $status = trim(texto_ou($filtros, 'status'));

        if ($status !== '' && preg_match('/^[a-z_]+$/', $status) === 1) {
            $builder->where('e.status', $status);
        }

        // Origem (manual/massa/automatico).
        $origem = texto_ou($filtros, 'origem');

        if (in_array($origem, ['manual', 'massa', 'automatico'], true)) {
            $builder->where('e.origem', $origem);
        }

        // Provedor da vaga (serpapi/divulgavagas).
        $provedor = texto_ou($filtros, 'provedor');

        if (in_array($provedor, ['serpapi', 'divulgavagas'], true)) {
            $builder->where('e.provedor', $provedor);
        }
    }

    /**
     * Resumo geral dos envios para os cartoes do painel.
     *
     * @return array{total: int, enviados: int, falhas: int, hoje: int, ultimo: ?string}
     */
    public function resumoGlobal(): array
    {
        $total    = (int) $this->db->table($this->table)->countAllResults();
        $enviados = (int) $this->db->table($this->table)->where('status', 'enviado')->countAllResults();

        $hoje = (int) $this->db->table($this->table)
            ->where('status', 'enviado')
            ->where('enviado_em >=', date('Y-m-d 00:00:00'))
            ->countAllResults();

        $linha = $this->db->table($this->table)
            ->selectMax('enviado_em')
            ->get()
            ->getRowArray();

        return [
            'total'    => $total,
            'enviados' => $enviados,
            'falhas'   => max(0, $total - $enviados),
            'hoje'     => $hoje,
            'ultimo'   => valor_ou($linha, 'enviado_em', null),
        ];
    }
}
