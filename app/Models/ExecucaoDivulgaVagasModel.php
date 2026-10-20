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
 * MODEL: EXECUCOES DO DIVULGA VAGAS
 * --------------------------------------------------------------------------
 *
 * Registro de auditoria das execucoes do segundo provedor (Divulga Vagas).
 * Cada linha diz quem mandou, o que foi feito e como terminou.
 *
 * Tipos:
 *   - 'buscar'     -> CRON 1: buscou vagas no site e salvou na tabela de
 *                     vagas (nunca candidata);
 *   - 'candidatar' -> CRON 2: candidatou-se a UMA vaga pelo site (nunca
 *                     busca vagas).
 *
 * Origens:
 *   - 'manual_usuario' -> o proprio usuario disparou pelo painel dele;
 *   - 'manual_admin'   -> o administrador disparou (para um usuario ou
 *                         para todos);
 *   - 'cron'           -> disparado pelo agendador.
 *
 * O campo `usuario_id` guarda de quem foi a execucao: zero significa
 * execucao global do administrador.
 */
class ExecucaoDivulgaVagasModel extends Model
{
    // Tabela do banco.
    protected $table = 'execucoes_divulgavagas';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Campos gravaveis.
    protected $allowedFields = [
        'usuario_id',
        'tipo',
        'origem',
        'executado_por_usuario_id',
        'status',
        'vagas_inseridas',
        'vagas_atualizadas',
        'candidaturas_enviadas',
        'emails_enviados',
        'confirmacoes',
        'erros',
        'mensagem',
        'detalhes',
        'iniciado_em',
        'finalizado_em',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Abre uma execucao (status 'em_andamento') e devolve o id.
     */
    public function abrir(
        string $tipo,
        string $origem,
        int $usuarioId,
        int $executadoPor
    ): int {
        $this->insert([
            'usuario_id'                => $usuarioId,
            'tipo'                      => $tipo,
            'origem'                    => $origem,
            'executado_por_usuario_id'  => $executadoPor,
            'status'                    => 'em_andamento',
            'iniciado_em'               => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * Fecha a execucao com o resultado.
     *
     * @param array<string, mixed> $resultado Contadores do resultado.
     */
    public function finalizar(int $id, string $status, array $resultado = []): bool
    {
        $dados = [
            'status'        => $status,
            'finalizado_em' => date('Y-m-d H:i:s'),
        ];

        // Contadores conhecidos (o que vier no resultado entra na linha).
        $contadores = [
            'vagas_inseridas',
            'vagas_atualizadas',
            'candidaturas_enviadas',
            'emails_enviados',
            'confirmacoes',
            'erros',
        ];

        foreach ($contadores as $contador) {
            if (isset($resultado[$contador])) {
                $dados[$contador] = (int) $resultado[$contador];
            }
        }

        // Mensagem do resultado (motivo da recusa, erro do bot...).
        if (isset($resultado['mensagem'])) {
            $dados['mensagem'] = (string) $resultado['mensagem'];
        }

        // O detalhe vaga por vaga (o loop pode candidatar varias).
        if (isset($resultado['detalhes']) && is_array($resultado['detalhes']) && $resultado['detalhes'] !== []) {
            $json = json_encode($resultado['detalhes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($json)) {
                $dados['detalhes'] = $json;
            }
        }

        return $this->update($id, $dados);
    }

    /**
     * O detalhe de uma execucao (a lista de vagas, ja decodificada).
     *
     * @return list<array<string, mixed>>
     */
    public static function detalhesDe(array $execucao): array
    {
        $bruto = (string) texto_ou($execucao, 'detalhes', '');

        if (trim($bruto) === '') {
            return [];
        }

        $dados = json_decode($bruto, true);

        if (! is_array($dados)) {
            return [];
        }

        return $dados;
    }

    /**
     * Ultimas execucoes de um usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function listarDoUsuario(int $usuarioId, int $limite = 20): array
    {
        return $this->select()
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->findAll($limite);
    }

    /**
     * Ultimas execucoes globais (visao do administrador).
     *
     * @return list<array<string, mixed>>
     */
    public function listarGlobais(int $limite = 50): array
    {
        return $this->db->table($this->table . ' e')
            ->select('e.*, u.nome AS usuario_nome, u.email AS usuario_email')
            ->join('usuarios u', 'u.id = e.usuario_id', 'left')
            ->orderBy('e.id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Ultima execucao de um usuario (para o painel mostrar o resultado).
     *
     * @return array<string, mixed>|null
     */
    public function ultimaDoUsuario(int $usuarioId): ?array
    {
        $linha = $this->select()
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->first();

        if ($linha === null) {
            return null;
        }

        return $linha;
    }
}
