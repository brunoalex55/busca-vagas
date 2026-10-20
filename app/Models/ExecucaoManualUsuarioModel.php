<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: EXECUCOES MANUAIS DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Controla quantas vezes por dia o usuario comum disparou manualmente a
 * busca de vagas e o envio de e-mails pelo proprio painel.
 *
 * Cada linha representa UM dia. O "reset diario" nao e uma rotina: e
 * simplesmente passar a consultar a data de hoje.
 */
class ExecucaoManualUsuarioModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'execucoes_manuais_usuario';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'data',
        'total_execucoes',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Retorna o total de execucoes manuais do usuario em um dia.
     *
     * @param string $data Data no formato Y-m-d.
     */
    public function obterTotal(int $usuarioId, string $data): int
    {
        $linha = $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('data', $data)
            ->get()
            ->getRowArray();

        // Sem linha: nenhuma execucao manual no dia.
        if ($linha === null) {
            return 0;
        }

        return (int) $linha['total_execucoes'];
    }

    /**
     * Grava o total exato de execucoes manuais do usuario em um dia.
     *
     * Cria a linha do dia quando ainda nao existe.
     */
    public function definirTotal(int $usuarioId, string $data, int $total): void
    {
        $linha = $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('data', $data)
            ->get()
            ->getRowArray();

        // Primeira execucao do dia: cria a linha.
        if ($linha === null) {
            $this->insert([
                'usuario_id'      => $usuarioId,
                'data'            => $data,
                'total_execucoes' => max(0, $total),
            ]);

            return;
        }

        // Linha existente: atualiza o total.
        $this->update((int) $linha['id'], ['total_execucoes' => max(0, $total)]);
    }

    /**
     * Soma uma execucao manual e devolve o novo total do dia.
     */
    public function incrementar(int $usuarioId, string $data): int
    {
        $atual = $this->obterTotal($usuarioId, $data);
        $novo  = $atual + 1;

        $this->definirTotal($usuarioId, $data, $novo);

        return $novo;
    }

    /**
     * Zera o contador do usuario em um dia.
     */
    public function zerar(int $usuarioId, string $data): bool
    {
        $linha = $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('data', $data)
            ->get()
            ->getRowArray();

        // Nada para zerar.
        if ($linha === null) {
            return true;
        }

        return $this->update((int) $linha['id'], ['total_execucoes' => 0]) !== false;
    }

    /**
     * Lista as execucoes manuais mais recentes, com o nome do usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function listarRecentes(int $limite = 20): array
    {
        return $this->db->table($this->table . ' e')
            ->select('e.*, u.nome AS usuario_nome, u.email AS usuario_email')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->orderBy('e.data', 'DESC')
            ->orderBy('e.id', 'DESC')
            ->limit($limite)
            ->get()
            ->getResultArray();
    }
}
