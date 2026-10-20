<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: MENSAGENS DE CONTATO
 * --------------------------------------------------------------------------
 *
 * Fila de atendimento das mensagens enviadas pelo formulario publico.
 *
 * Fluxo de status:
 *   nova -> lida -> respondida
 *   (qualquer um) -> arquivada -> nova (reabrir)
 *
 * As transicoes sao gravadas apenas por este model, sempre via POST no
 * painel administrativo.
 */
class MensagemContatoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'mensagens_contato';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'nome',
        'email',
        'mensagem',
        'status',
        'resposta',
        'respondido_em',
        'respondido_por',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // Status aceitos pela coluna ENUM.
    public const STATUS = ['nova', 'lida', 'respondida', 'arquivada'];

    /**
     * Grava uma mensagem nova (formulario publico).
     *
     * @param array<string, mixed> $dados
     *
     * @return int|null Identificador criado ou null quando a gravacao falha.
     */
    public function registrar(array $dados): ?int
    {
        // Toda mensagem entra na fila como "nova".
        $dados['status'] = 'nova';

        $id = $this->insert($dados, true);

        // Falha na insercao: devolve null.
        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Busca uma mensagem pelo identificador.
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

    // =========================================================================
    // CONSULTAS DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista as mensagens com filtros e paginacao.
     *
     * As novas aparecem primeiro; dentro do mesmo status, as mais
     * recentes na frente.
     *
     * @param array{status?: string, busca?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarAdmin(array $filtros, int $limite, int $offset): array
    {
        $builder = $this->db->table('mensagens_contato m')
            ->select('m.*')
            ->orderBy("m.status = 'nova'", 'DESC', false)
            ->orderBy('m.id', 'DESC');

        // Aplica os filtros.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de mensagens que atendem aos filtros.
     *
     * @param array{status?: string, busca?: string} $filtros
     */
    public function contarAdmin(array $filtros): int
    {
        $builder = $this->db->table('mensagens_contato m');

        // Aplica os filtros.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de mensagens.
     *
     * @param array{status?: string, busca?: string} $filtros
     */
    private function aplicarFiltrosAdmin(BaseBuilder $builder, array $filtros): void
    {
        // Situacao da mensagem.
        $status = texto_ou($filtros, 'status');

        if (in_array($status, self::STATUS, true)) {
            $builder->where('m.status', $status);
        }

        // Busca por nome, e-mail ou texto da mensagem.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('m.nome', $busca)
                ->orLike('m.email', $busca)
                ->orLike('m.mensagem', $busca)
                ->groupEnd();
        }
    }

    /**
     * Contagem de mensagens por status (abas do painel).
     *
     * @return array{nova: int, lida: int, respondida: int, arquivada: int, total: int}
     */
    public function contarPorStatus(): array
    {
        $resultado = ['nova' => 0, 'lida' => 0, 'respondida' => 0, 'arquivada' => 0];

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
     * Quantidade de mensagens ainda nao lidas.
     * Usada no selo do menu e no cartao da visao geral.
     */
    public function contarNovas(): int
    {
        return (int) $this->db->table($this->table)
            ->where('status', 'nova')
            ->countAllResults();
    }

    /**
     * Resumo geral das mensagens para os cartoes do painel.
     *
     * @return array{total: int, novas: int, hoje: int, ultima: ?string}
     */
    public function resumo(): array
    {
        $total = (int) $this->db->table($this->table)->countAllResults();

        $hoje = (int) $this->db->table($this->table)
            ->where('criado_em >=', date('Y-m-d 00:00:00'))
            ->countAllResults();

        $linha = $this->db->table($this->table)
            ->selectMax('criado_em')
            ->get()
            ->getRowArray();

        return [
            'total'  => $total,
            'novas'  => $this->contarNovas(),
            'hoje'   => $hoje,
            'ultima' => valor_ou($linha, 'criado_em', null),
        ];
    }

    /**
     * Ultimas mensagens recebidas (resumo da visao geral).
     *
     * @return list<array<string, mixed>>
     */
    public function recentes(int $limite = 5): array
    {
        return $this->db->table($this->table)
            ->select('id, nome, email, status, criado_em')
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    // =========================================================================
    // TRANSICOES DE STATUS (sempre chamadas por acoes POST)
    // =========================================================================

    /**
     * Marca a mensagem como lida.
     */
    public function marcarLida(int $id): bool
    {
        return $this->definirStatus($id, 'lida');
    }

    /**
     * Arquiva a mensagem (sai da fila principal).
     */
    public function arquivar(int $id): bool
    {
        return $this->definirStatus($id, 'arquivada');
    }

    /**
     * Devolve a mensagem arquivada para a fila.
     */
    public function reabrir(int $id): bool
    {
        return $this->definirStatus($id, 'nova');
    }

    /**
     * Registra a resposta enviada e marca a mensagem como respondida.
     */
    public function registrarResposta(int $id, string $resposta, int $respondidoPor): bool
    {
        $agora = date('Y-m-d H:i:s');

        // Quem respondeu (null quando a resposta foi automatica).
        $respondidoPorId = null;

        if ($respondidoPor > 0) {
            $respondidoPorId = $respondidoPor;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'status'        => 'respondida',
                'resposta'      => $resposta,
                'respondido_em' => $agora,
                'respondido_por' => $respondidoPorId,
                'atualizado_em' => $agora,
            ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Remove a mensagem definitivamente (spam ou duplicata).
     */
    public function excluir(int $id): bool
    {
        $this->db->table($this->table)->where('id', $id)->delete();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Grava uma troca simples de status.
     */
    private function definirStatus(int $id, string $status): bool
    {
        // So aceita valores previstos no ENUM.
        if (! in_array($status, self::STATUS, true)) {
            return false;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'status'        => $status,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() > 0;
    }
}
