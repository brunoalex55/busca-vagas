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
 * MODEL: PEDIDOS DE TROCA DE PLANO
 * --------------------------------------------------------------------------
 *
 * Quando o usuario escolhe um plano no painel (upgrade), o pedido fica
 * aqui como "pendente" e aparece no painel do administrador.
 *
 * O administrador aprova (o plano passa a valer na conta) ou recusa
 * (com a justificativa, que o usuario ve no painel).
 */
class SolicitacaoPlanoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'solicitacoes_plano';

    // Chave primaria.
    protected $primaryKey = 'id';

    // O model cuida dos timestamps.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // As colunas que o model pode gravar.
    protected $allowedFields = [
        'usuario_id',
        'plano_id',
        'plano_anterior_id',
        'status',
        'mensagem',
        'resposta',
        'respondido_em',
        'respondido_por',
        'criado_em',
        'atualizado_em',
    ];

    /**
     * Cria um pedido de plano (o usuario pediu a troca).
     *
     * @param int    $usuarioId Quem pediu.
     * @param int    $planoId   O plano pedido.
     * @param string $mensagem  O que o usuario escreveu.
     */
    public function criar(int $usuarioId, int $planoId, string $mensagem = ''): int
    {
        // O plano que o usuario tem agora (o anterior).
        $usuario = model(UsuarioModel::class)->find($usuarioId);
        $planoAnterior = null;

        if ($usuario !== null) {
            $planoAnterior = valor_ou($usuario, 'plano_id', null);
        }

        // Ja existe um pedido pendente deste usuario: cancela o antigo
        // (o novo vale).
        $this->where('usuario_id', $usuarioId)->where('status', 'pendente')->delete();

        $mensagemSalva = null;

        if ($mensagem !== '') {
            $mensagemSalva = $mensagem;
        }

        $this->insert([
            'usuario_id' => $usuarioId,
            'plano_id' => $planoId,
            'plano_anterior_id' => $planoAnterior,
            'status' => 'pendente',
            'mensagem' => $mensagemSalva,
            'criado_em' => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Os pedidos pendentes (com o usuario e o plano).
     *
     * @return list<array<string, mixed>>
     */
    public function listarPendentes(int $limite = 50): array
    {
        return $this->db->table($this->table . ' s')
            ->select('s.*, u.nome AS usuario_nome, u.email AS usuario_email, p.nome AS plano_nome, pa.nome AS plano_anterior_nome')
            ->join('usuarios u', 'u.id = s.usuario_id', 'left')
            ->join('planos p', 'p.id = s.plano_id', 'left')
            ->join('planos pa', 'pa.id = s.plano_anterior_id', 'left')
            ->where('s.status', 'pendente')
            ->orderBy('s.id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Os pedidos de um usuario (os mais recentes).
     *
     * @return list<array<string, mixed>>
     */
    public function listarDoUsuario(int $usuarioId, int $limite = 10): array
    {
        return $this->db->table($this->table . ' s')
            ->select('s.*, p.nome AS plano_nome')
            ->join('planos p', 'p.id = s.plano_id', 'left')
            ->where('s.usuario_id', $usuarioId)
            ->orderBy('s.id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Os pedidos com o filtro de status.
     *
     * O status 'todas' devolve tudo (sem filtro).
     *
     * @return list<array<string, mixed>>
     */
    public function listarPorStatus(string $status = 'pendente', int $limite = 50): array
    {
        $builder = $this->db->table($this->table . ' s')
            ->select('s.*, u.nome AS usuario_nome, u.email AS usuario_email, u.status AS usuario_status, p.nome AS plano_nome, p.preco_mensal AS plano_preco, pa.nome AS plano_anterior_nome')
            ->join('usuarios u', 'u.id = s.usuario_id', 'left')
            ->join('planos p', 'p.id = s.plano_id', 'left')
            ->join('planos pa', 'pa.id = s.plano_anterior_id', 'left')
            ->orderBy('s.id', 'DESC');

        // O filtro (o 'todas' nao filtra nada).
        if ($status !== '' && $status !== 'todas') {
            $builder->where('s.status', $status);
        }

        return $builder->get($limite)->getResultArray();
    }

    /**
     * Quantos pedidos tem no status (o 'todas' conta tudo).
     */
    public function contarPorStatus(string $status = 'todas'): int
    {
        if ($status === '' || $status === 'todas') {
            return (int) $this->countAllResults();
        }

        return (int) $this->where('status', $status)->countAllResults();
    }

    /**
     * Um pedido pelo id.
     *
     * @return array<string, mixed>|null
     */
    public function buscar(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $linha = $this->find($id);

        if ($linha === null) {
            return null;
        }

        return $linha;
    }

    /**
     * Aprova o pedido: o plano passa a valer na conta do usuario.
     */
    public function aprovar(int $id, int $adminId): bool
    {
        $pedido = $this->buscar($id);

        if ($pedido === null || (string) $pedido['status'] !== 'pendente') {
            return false;
        }

        // O plano novo entra na conta.
        model(UsuarioModel::class)->db->table('usuarios')->where('id', (int) $pedido['usuario_id'])->update([
            'plano_id' => (int) $pedido['plano_id'],
            'plano_inicio_em' => date('Y-m-d H:i:s'),
        ]);

        // O pedido vira "aprovado".
        $this->update($id, [
            'status' => 'aprovada',
            'respondido_em' => date('Y-m-d H:i:s'),
            'respondido_por' => $adminId,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Recusa o pedido (com a justificativa que o usuario ve).
     */
    public function recusar(int $id, int $adminId, string $resposta = ''): bool
    {
        $pedido = $this->buscar($id);

        if ($pedido === null || (string) $pedido['status'] !== 'pendente') {
            return false;
        }

        $respostaSalva = null;

        if ($resposta !== '') {
            $respostaSalva = $resposta;
        }

        $this->update($id, [
            'status' => 'recusada',
            'resposta' => $respostaSalva,
            'respondido_em' => date('Y-m-d H:i:s'),
            'respondido_por' => $adminId,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Este usuario tem pedido de plano pendente?
     */
    public function temPendente(int $usuarioId): bool
    {
        return $this->where('usuario_id', $usuarioId)->where('status', 'pendente')->countAllResults() > 0;
    }

    /**
     * Quantos pedidos pendentes existem (o alerta do administrador).
     */
    public function contarPendentes(): int
    {
        return (int) $this->where('status', 'pendente')->countAllResults();
    }
}
