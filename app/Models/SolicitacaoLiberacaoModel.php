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
 * MODEL: SOLICITACOES DE LIBERACAO
 * --------------------------------------------------------------------------
 *
 * Controla o fluxo "informar pagamento" -> "admin aprova/rejeita".
 */
class SolicitacaoLiberacaoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'solicitacoes_liberacao';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'observacao',
        'status',
        'respondido_em',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Verifica se o usuario ja tem uma solicitacao pendente.
     * Evita que ele clique varias vezes em "Informar pagamento".
     */
    public function temPendente(int $usuarioId): bool
    {
        return $this->where('usuario_id', $usuarioId)
            ->where('status', 'pendente')
            ->countAllResults() > 0;
    }

    // =========================================================================
    // CONSULTAS E ACOES DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista as solicitacoes com os dados do usuario, de forma paginada.
     *
     * As pendentes aparecem primeiro.
     *
     * @param array{status?: string, busca?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarComUsuario(array $filtros, int $limite, int $offset): array
    {
        // Junta o usuario para exibir nome/e-mail/situacao.
        $builder = $this->db->table('solicitacoes_liberacao s')
            ->select('s.*, u.nome AS usuario_nome, u.email AS usuario_email, u.status AS usuario_status')
            ->join('usuarios u', 'u.id = s.usuario_id', 'left')
            ->orderBy("s.status = 'pendente'", 'DESC', false)
            ->orderBy('s.id', 'DESC');

        // Aplica os filtros.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de solicitacoes que atendem aos filtros.
     *
     * @param array{status?: string, busca?: string} $filtros
     */
    public function contarComUsuario(array $filtros): int
    {
        $builder = $this->db->table('solicitacoes_liberacao s')
            ->join('usuarios u', 'u.id = s.usuario_id', 'left');

        // Aplica os filtros.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de solicitacoes.
     *
     * @param array{status?: string, busca?: string} $filtros
     */
    private function aplicarFiltrosAdmin(\CodeIgniter\Database\BaseBuilder $builder, array $filtros): void
    {
        // Situacao da solicitacao.
        $status = texto_ou($filtros, 'status');

        if (in_array($status, ['pendente', 'aprovada', 'rejeitada'], true)) {
            $builder->where('s.status', $status);
        }

        // Busca pelo nome ou e-mail do usuario.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('u.nome', $busca)
                ->orLike('u.email', $busca)
                ->groupEnd();
        }
    }

    /**
     * Contagem de solicitacoes por status (para as abas do painel).
     *
     * @return array{pendente: int, aprovada: int, rejeitada: int, total: int}
     */
    public function contarPorStatus(): array
    {
        $resultado = ['pendente' => 0, 'aprovada' => 0, 'rejeitada' => 0];

        $linhas = $this->db->table($this->table)
            ->select('status, COUNT(*) AS total', false)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        foreach ($linhas as $linha) {
            $resultado[(string) $linha['status']] = (int) $linha['total'];
        }

        $resultado['total'] = array_sum($resultado);

        return $resultado;
    }

    /**
     * Marca a solicitacao como respondida (aprovada ou rejeitada).
     */
    public function responder(int $id, string $status): bool
    {
        // So aceita os status finais.
        if (! in_array($status, ['aprovada', 'rejeitada'], true)) {
            return false;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'status'        => $status,
                'respondido_em' => date('Y-m-d H:i:s'),
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Responde todas as solicitacoes pendentes de um usuario.
     *
     * Usado quando o admin aprova/bloqueia a conta direto pela tela de
     * usuarios: assim nao fica solicitacao pendente orfa.
     */
    public function responderPendentesDoUsuario(int $usuarioId, string $status): int
    {
        // So aceita os status finais.
        if (! in_array($status, ['aprovada', 'rejeitada'], true)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('status', 'pendente')
            ->update([
                'status'        => $status,
                'respondido_em' => date('Y-m-d H:i:s'),
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows();
    }

    /**
     * Busca uma solicitacao pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public function buscar(int $id): ?array
    {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->get()
            ->getRowArray();
    }
}
