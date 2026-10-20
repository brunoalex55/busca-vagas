<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: LOGS DE CONSULTA
 * --------------------------------------------------------------------------
 *
 * Historico de cada consulta feita ao provedor de vagas.
 * Registra termo, cidade, chave usada, resultado e erros.
 */
class LogConsultaModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'logs_consulta';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'palavra_chave_id',
        'cidade_consultada',
        'chave_serpapi_id',
        'termo',
        'origem',
        'provedor',
        'total_vagas_retornadas',
        'status',
        'mensagem_erro',
        'consumido_em',
    ];

    // Esta tabela nao tem criado_em/atualizado_em: usamos consumido_em.
    protected $useTimestamps = false;

    /**
     * Retorna os logs recentes de um usuario.
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
     * Lista o historico de consultas de forma paginada.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPaginado(int $usuarioId, int $limite, int $offset): array
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->get($limite, $offset)
            ->getResultArray();
    }

    /**
     * Total de consultas registradas para o usuario.
     */
    public function contarDoUsuario(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->countAllResults();
    }

    /**
     * Consultas que terminaram com erro (qualquer status diferente de sucesso).
     */
    public function contarComErro(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('status !=', 'sucesso')
            ->countAllResults();
    }

    /**
     * Grava uma linha de auditoria de consulta.
     *
     * O model tem validacao desligada aqui para permitir gravar tanto
     * consultas bem-sucedidas quanto falhas (sem termo, sem chave etc.).
     *
     * @param array<string, mixed> $dados
     */
    public function registrar(array $dados): void
    {
        // Id da palavra-chave (null quando o log nao tem palavra).
        $palavraChaveId = null;

        if (isset($dados['palavra_chave_id'])) {
            $palavraChaveId = (int) $dados['palavra_chave_id'];
        }

        // Id da chave do provedor (null quando o log nao tem chave).
        $chaveProvedorId = null;

        if (isset($dados['chave_serpapi_id'])) {
            $chaveProvedorId = (int) $dados['chave_serpapi_id'];
        }

        // Mensagem de erro (null quando nao houve erro).
        $mensagemErro = null;

        if (isset($dados['mensagem_erro']) && $dados['mensagem_erro'] !== '') {
            $mensagemErro = mb_substr((string) $dados['mensagem_erro'], 0, 900);
        }

        // Monta apenas os campos conhecidos da tabela.
        $registro = [
            'usuario_id'              => inteiro_ou($dados, 'usuario_id', 0),
            'palavra_chave_id'        => $palavraChaveId,
            'cidade_consultada'       => texto_ou($dados, 'cidade_consultada'),
            'chave_serpapi_id'        => $chaveProvedorId,
            'termo'                   => texto_ou($dados, 'termo'),
            'origem'                  => texto_ou($dados, 'origem', 'cron'),
            'provedor'                => texto_ou($dados, 'provedor', 'serpapi'),
            'total_vagas_retornadas'  => inteiro_ou($dados, 'total_vagas_retornadas', 0),
            'status'                  => texto_ou($dados, 'status', 'erro'),
            'mensagem_erro'           => $mensagemErro,
            'consumido_em'            => texto_ou($dados, 'consumido_em', date('Y-m-d H:i:s')),
        ];

        // Insercao direta: nao dispara validacao nem timestamps.
        $this->db->table($this->table)->insert($registro);
    }

    // =========================================================================
    // CONSULTAS DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista as consultas de todos os usuarios, com filtros e paginacao.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarGlobal(array $filtros, int $limite, int $offset): array
    {
        $builder = $this->db->table('logs_consulta l')
            ->select('l.*, u.nome AS usuario_nome, u.email AS usuario_email, c.apelido AS chave_apelido')
            ->join('usuarios u', 'u.id = l.usuario_id', 'left')
            ->join('chaves_serp_api c', 'c.id = l.chave_serpapi_id', 'left')
            ->orderBy('l.id', 'DESC');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de consultas que atendem aos filtros.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     */
    public function contarGlobal(array $filtros): int
    {
        $builder = $this->db->table('logs_consulta l')
            ->join('usuarios u', 'u.id = l.usuario_id', 'left');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de consultas.
     *
     * @param array{busca?: string, status?: string, origem?: string} $filtros
     */
    private function aplicarFiltrosGlobal(\CodeIgniter\Database\BaseBuilder $builder, array $filtros): void
    {
        // Busca por usuario (nome/e-mail) ou termo consultado.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('u.nome', $busca)
                ->orLike('u.email', $busca)
                ->orLike('l.termo', $busca)
                ->groupEnd();
        }

        // Status da consulta.
        $status = trim(texto_ou($filtros, 'status'));

        if ($status !== '' && preg_match('/^[a-z_]+$/', $status) === 1) {
            $builder->where('l.status', $status);
        }

        // Origem (manual/manual_usuario/cron).
        $origem = texto_ou($filtros, 'origem');

        if (in_array($origem, ['manual', 'manual_usuario', 'cron'], true)) {
            $builder->where('l.origem', $origem);
        }

        // Provedor da consulta (serpapi/divulgavagas).
        $provedor = texto_ou($filtros, 'provedor');

        if (in_array($provedor, ['serpapi', 'divulgavagas'], true)) {
            $builder->where('l.provedor', $provedor);
        }
    }

    /**
     * Resumo geral das consultas para os cartoes do painel.
     *
     * @return array{total: int, sucesso: int, falhas: int, ultima: ?string}
     */
    public function resumoGlobal(): array
    {
        // Totais por status.
        $total   = (int) $this->db->table($this->table)->countAllResults();
        $sucesso = (int) $this->db->table($this->table)->where('status', 'sucesso')->countAllResults();

        // Ultima consulta registrada.
        $linha = $this->db->table($this->table)
            ->selectMax('consumido_em')
            ->get()
            ->getRowArray();

        return [
            'total'   => $total,
            'sucesso' => $sucesso,
            'falhas'  => max(0, $total - $sucesso),
            'ultima'  => valor_ou($linha, 'consumido_em', null),
        ];
    }
}
