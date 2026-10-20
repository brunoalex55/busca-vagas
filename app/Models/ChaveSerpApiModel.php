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
 * MODEL: CHAVES SERPAPI
 * --------------------------------------------------------------------------
 *
 * Acesso as chaves da API de busca de vagas.
 * Exclusivo do administrador: o usuario comum nunca ve esta tabela.
 *
 * A chave em si nunca deve ser exibida por inteiro na interface.
 */
class ChaveSerpApiModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'chaves_serp_api';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos editaveis pelo admin.
    protected $allowedFields = [
        'apelido',
        'chave',
        'quantidade_consultas',
        'consultas_usadas',
        'mes_referencia',
        'ativa',
        'motivo_inativacao',
        'ordem',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // Validacao basica.
    protected $validationRules = [
        'apelido'               => 'required|max_length[100]',
        'chave'                 => 'required|max_length[255]',
        'quantidade_consultas'  => 'required|integer|greater_than_equal_to[0]',
        'ordem'                 => 'required|integer|greater_than_equal_to[1]',
    ];

    /**
     * Retorna a chave ativa com menor ordem que ainda tenha saldo.
     *
     * @return array<string, mixed>|null
     */
    public function obterDisponivel(): ?array
    {
        return $this->where('ativa', 1)
            ->where('consultas_usadas <', 'quantidade_consultas', false)
            ->orderBy('ordem', 'ASC')
            ->first();
    }

    /**
     * Soma as consultas restantes de todas as chaves ativas.
     */
    public function totalRestante(): int
    {
        // Soma (quantidade - usadas) apenas das chaves ativas.
        $linha = $this->select('COALESCE(SUM(quantidade_consultas - consultas_usadas), 0) AS total', false)
            ->where('ativa', 1)
            ->first();

        return inteiro_ou($linha, 'total', 0);
    }

    /**
     * Lista todas as chaves, da menor ordem para a maior.
     * Usada pela tela administrativa (Fase 5) e pela ferramenta de linha de
     * comando que cadastra chaves enquanto o painel nao existe.
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodas(): array
    {
        return $this->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Contabiliza UMA consulta consumida pela chave.
     *
     * O incremento e feito direto no banco (UPDATE ... + 1) para evitar
     * perda de contagem quando duas execucoes acontecem ao mesmo tempo.
     */
    public function registrarUso(int $id): void
    {
        // Incremento atomico, nunca acima da quantidade contratada.
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'consultas_usadas' => new \CodeIgniter\Database\RawSql('LEAST(consultas_usadas + 1, quantidade_consultas)'),
                'atualizado_em'    => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Marca a chave como esgotada e a retira de circulacao.
     *
     * Usado quando o proprio provedor informa que a conta acabou: assim a
     * chave deixa de ser escolhida por obterDisponivel(). Como o motivo e
     * 'esgotada', ela volta sozinha na virada do mes.
     */
    public function esgotar(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'consultas_usadas'  => new \CodeIgniter\Database\RawSql('quantidade_consultas'),
                'ativa'             => 0,
                'motivo_inativacao' => 'esgotada',
                'mes_referencia'    => date('Y-m'),
                'atualizado_em'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Desativa a chave por decisao do administrador.
     *
     * Uma chave desativada manualmente NAO volta sozinha na virada do mes.
     */
    public function desativar(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'ativa'             => 0,
                'motivo_inativacao' => 'manual',
                'atualizado_em'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Inativa a chave porque a credencial foi recusada pelo provedor.
     *
     * Tambem nao volta sozinha: depende de o admin corrigir o cadastro.
     */
    public function invalidar(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'ativa'             => 0,
                'motivo_inativacao' => 'invalida',
                'atualizado_em'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Reativa uma chave e limpa o motivo da inativacao.
     */
    public function ativar(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'ativa'             => 1,
                'motivo_inativacao' => null,
                'atualizado_em'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Zera o contador de consultas das chaves (todas ou uma so).
     *
     * Serve para alinhar a contagem com a virada do ciclo do provedor
     * quando ela nao coincide com o mes civil.
     */
    public function zerarConsultas(?int $id = null): void
    {
        $builder = $this->db->table($this->table);

        // Quando um id e informado, zera apenas aquela chave.
        if ($id !== null) {
            $builder->where('id', $id);
        }

        $builder->update([
            'consultas_usadas' => 0,
            'mes_referencia'   => date('Y-m'),
            'atualizado_em'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reinicia o ciclo mensal das chaves, quando o mes virou.
     *
     * Duas acoes:
     *   1. zera a contagem de todas as chaves do mes anterior;
     *   2. religa apenas as chaves que estavam esgotadas.
     *
     * Nunca religa o que foi desligado manualmente nem o que teve a
     * credencial recusada.
     *
     * @return int Quantidade de chaves religadas.
     */
    public function reiniciarMesSeNecessario(string $mesAtual): int
    {
        // 1. Zera o contador de quem ainda esta no mes anterior.
        $this->db->table($this->table)
            ->groupStart()
                ->where('mes_referencia IS NULL', null, false)
                ->orWhere('mes_referencia !=', $mesAtual)
            ->groupEnd()
            ->update([
                'consultas_usadas' => 0,
                'mes_referencia'   => $mesAtual,
                'atualizado_em'    => date('Y-m-d H:i:s'),
            ]);

        // 2. Religa somente as chaves que foram esgotadas.
        $this->db->table($this->table)
            ->where('motivo_inativacao', 'esgotada')
            ->update([
                'ativa'             => 1,
                'motivo_inativacao' => null,
                'atualizado_em'     => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows();
    }
}
