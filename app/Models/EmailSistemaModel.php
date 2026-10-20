<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: E-MAILS DO SISTEMA
 * --------------------------------------------------------------------------
 *
 * Auditoria dos e-mails transacionais (boas-vindas, liberacao, resposta
 * de contato etc). Nao confundir com `emails_enviados`, que registra
 * apenas candidaturas e funciona como trava anti-duplicidade.
 */
class EmailSistemaModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'emails_sistema';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'tipo',
        'destinatario',
        'usuario_id',
        'referencia',
        'assunto',
        'status',
        'mensagem_erro',
        'enviado_em',
    ];

    // Esta tabela usa apenas enviado_em.
    protected $useTimestamps = false;

    /**
     * Tipos de e-mail transacional com o rotulo exibido no painel.
     *
     * @var array<string, string>
     */
    public const TIPOS = [
        'boas_vindas'         => 'Boas-vindas',
        'pagamento_informado' => 'Pagamento informado',
        'alerta_pagamento'    => 'Alerta de pagamento',
        'liberacao_aprovada'  => 'Liberacao aprovada',
        'liberacao_rejeitada' => 'Liberacao rejeitada',
        'conta_bloqueada'     => 'Conta bloqueada',
        'resposta_contato'    => 'Resposta de contato',
        'teste'               => 'Envio de teste',
    ];

    /**
     * Registra uma tentativa de envio transacional.
     *
     * O momento do envio e preenchido quando nao informado.
     *
     * @param array<string, mixed> $dados
     *
     * @return int Identificador do registro criado.
     */
    public function registrar(array $dados): int
    {
        // Nunca deixar o tipo vazio (a coluna e obrigatoria).
        if (trim(texto_ou($dados, 'tipo')) === '') {
            $dados['tipo'] = 'outro';
        }

        // Momento da tentativa.
        if (empty($dados['enviado_em'])) {
            $dados['enviado_em'] = date('Y-m-d H:i:s');
        }

        return (int) $this->insert($dados, true);
    }

    /**
     * Historico de envios ligados a uma referencia (ex.: mensagem_contato:12).
     *
     * @return list<array<string, mixed>>
     */
    public function porReferencia(string $referencia, int $limite = 10): array
    {
        return $this->db->table($this->table)
            ->where('referencia', $referencia)
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    // =========================================================================
    // CONSULTAS DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista os envios transacionais com filtros e paginacao.
     *
     * @param array{busca?: string, status?: string, tipo?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarGlobal(array $filtros, int $limite, int $offset): array
    {
        $builder = $this->db->table('emails_sistema e')
            ->select('e.*, u.nome AS usuario_nome, u.email AS usuario_email')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->orderBy('e.id', 'DESC');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de envios que atendem aos filtros.
     *
     * @param array{busca?: string, status?: string, tipo?: string} $filtros
     */
    public function contarGlobal(array $filtros): int
    {
        $builder = $this->db->table('emails_sistema e')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left');

        // Aplica os filtros.
        $this->aplicarFiltrosGlobal($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de envios.
     *
     * @param array{busca?: string, status?: string, tipo?: string} $filtros
     */
    private function aplicarFiltrosGlobal(BaseBuilder $builder, array $filtros): void
    {
        // Busca por destinatario, assunto ou usuario.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('e.destinatario', $busca)
                ->orLike('e.assunto', $busca)
                ->orLike('e.referencia', $busca)
                ->orLike('u.nome', $busca)
                ->orLike('u.email', $busca)
                ->groupEnd();
        }

        // Status do envio (enviado, erro_destinatario, erro_smtp, ...).
        $status = trim(texto_ou($filtros, 'status'));

        if ($status !== '' && preg_match('/^[a-z_]+$/', $status) === 1) {
            $builder->where('e.status', $status);
        }

        // Tipo de e-mail transacional.
        $tipo = trim(texto_ou($filtros, 'tipo'));

        if (isset(self::TIPOS[$tipo])) {
            $builder->where('e.tipo', $tipo);
        }
    }

    /**
     * Resumo geral dos envios transacionais para os cartoes do painel.
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
