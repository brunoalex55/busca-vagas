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
 * MODEL: PLANOS
 * --------------------------------------------------------------------------
 *
 * Os planos que o site publico promete (Basico, Profissional, Empresa).
 *
 * O plano BASICO e o fallback: quem nao tem plano definido usa ele.
 * Por isso ele nunca e excluido (apenas desativado) e nunca some da
 * lista de opcoes.
 */
class PlanoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'planos';

    // Chave primaria.
    protected $primaryKey = 'id';

    // O model cuida dos timestamps.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // As colunas que o model pode gravar.
    protected $allowedFields = [
        'slug',
        'nome',
        'descricao',
        'preco_mensal',
        'max_palavras_chave',
        'max_cidades_por_termo',
        'limite_envios_dia',
        'envio_automatico',
        'envio_em_massa',
        'max_usuarios',
        'suporte',
        'destaque',
        'ordem',
        'ativo',
        'criado_em',
        'atualizado_em',
    ];

    /**
     * Todos os planos ativos, na ordem de exibicao.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAtivos(): array
    {
        return $this->where('ativo', 1)
            ->orderBy('ordem', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Todos os planos (inclusive os desativados).
     *
     * @return list<array<string, mixed>>
     */
    public function listarTodos(): array
    {
        return $this->orderBy('ordem', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Um plano pelo slug (ex.: 'profissional').
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorSlug(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $plano = $this->where('slug', $slug)->first();

        if ($plano === null) {
            return null;
        }

        return $plano;
    }

    /**
     * Quantos usuarios tem no plano.
     */
    public function contarUsuarios(int $planoId): int
    {
        if ($planoId <= 0) {
            return 0;
        }

        return (int) db_connect()->table('usuarios')
            ->where('plano_id', $planoId)
            ->countAllResults();
    }
}
