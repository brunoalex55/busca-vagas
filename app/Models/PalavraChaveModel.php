<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: PALAVRAS-CHAVE
 * --------------------------------------------------------------------------
 *
 * Cada palavra-chave pertence a um usuario e combina um termo com
 * varias cidades. O sistema gera uma consulta por (termo + cidade).
 */
class PalavraChaveModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'palavras_chave';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos editaveis pelo usuario.
    protected $allowedFields = [
        'usuario_id',
        'termo',
        'cidades',
        'remoto',
        'idioma',
        'pais',
        'dominio_google',
        'frequencia_horas',
        'proxima_execucao',
        'ultima_execucao',
        'total_consultas',
        'ativa',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // Validacao basica.
    protected $validationRules = [
        'usuario_id' => 'required|integer',
        'termo'      => 'required|min_length[2]|max_length[150]',
        'cidades'    => 'required',
    ];

    /**
     * Retorna as palavras-chave ativas de um usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAtivasDoUsuario(int $usuarioId): array
    {
        return $this->where('usuario_id', $usuarioId)
            ->where('ativa', 1)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Lista todas as palavras-chave do usuario (ativas e pausadas).
     *
     * @return list<array<string, mixed>>
     */
    public function listarDoUsuario(int $usuarioId): array
    {
        return $this->where('usuario_id', $usuarioId)
            ->orderBy('ativa', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Busca uma palavra-chave garantindo que ela pertence ao usuario.
     *
     * @return array<string, mixed>|null
     */
    public function buscarDoUsuario(int $id, int $usuarioId): ?array
    {
        return $this->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->first();
    }

    /**
     * Conta as palavras-chave ativas do usuario.
     */
    public function totalAtivasDoUsuario(int $usuarioId): int
    {
        return $this->where('usuario_id', $usuarioId)
            ->where('ativa', 1)
            ->countAllResults();
    }

    /**
     * Conta quantas combinacoes (termo x cidade) o usuario possui.
     * Usado no calculo dinamico da cota de consultas.
     */
    public function totalCombinacoesDoUsuario(int $usuarioId): int
    {
        // Busca apenas a coluna de cidades das palavras-chave ativas.
        $registros = $this->select('cidades')
            ->where('usuario_id', $usuarioId)
            ->where('ativa', 1)
            ->findAll();

        $total = 0;

        foreach ($registros as $registro) {
            // As cidades ficam salvas em JSON.
            $cidades = json_decode(valor_ou($registro, 'cidades', '[]'), true);

            // Soma a quantidade de cidades validas.
            if (is_array($cidades)) {
                $total += count(array_filter($cidades));
            }
        }

        return $total;
    }

    /**
     * Lista as palavras-chave que ja podem ser executadas.
     *
     * Uma palavra-chave esta "devida" quando esta ativa e nunca rodou
     * (proxima_execucao nula) ou quando o horario agendado ja passou.
     *
     * A ordem tambem importa: quem esta esperando ha mais tempo roda antes,
     * o que faz a cota do usuario girar entre as palavras-chave.
     *
     * @return list<array<string, mixed>>
     */
    public function listarDevidasDoUsuario(int $usuarioId, int $limite = 200, ?string $agora = null): array
    {
        // Momento de referencia da verificacao.
        if ($agora === null) {
            $agora = date('Y-m-d H:i:s');
        }

        return $this->groupStart()
                ->where('proxima_execucao IS NULL', null, false)
                ->orWhere('proxima_execucao <=', $agora)
            ->groupEnd()
            ->where('usuario_id', $usuarioId)
            ->where('ativa', 1)
            // Nunca executadas primeiro.
            ->orderBy('proxima_execucao IS NULL', 'DESC', false)
            // Depois, as mais atrasadas.
            ->orderBy('proxima_execucao', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll($limite);
    }

    /**
     * Atualiza o agendamento de uma palavra-chave depois de executa-la.
     *
     * @param int         $id        Identificador da palavra-chave.
     * @param string      $ultima    Momento da ultima execucao.
     * @param string|null $proxima   Momento da proxima execucao (null = imediata).
     * @param int         $consultas Quantas consultas foram consumidas agora.
     */
    public function registrarExecucao(int $id, string $ultima, ?string $proxima, int $consultas = 0): void
    {
        // Dados basicos de agendamento.
        $dados = [
            'ultima_execucao'  => $ultima,
            'proxima_execucao' => $proxima,
            'atualizado_em'    => date('Y-m-d H:i:s'),
        ];

        // Soma as consultas consumidas, sem sobrescrever o historico.
        if ($consultas > 0) {
            $dados['total_consultas'] = new \CodeIgniter\Database\RawSql('total_consultas + ' . (int) $consultas);
        }

        // Update direto: nao ha necessidade de validacao aqui.
        $this->db->table($this->table)->where('id', $id)->update($dados);
    }

    /**
     * Lista as palavras-chave DEVIDAS para o segundo provedor.
     *
     * O segundo provedor tem o proprio horario (as colunas
     * "proxima_execucao_divulgavagas"): assim ele nao disputa a agenda
     * com o SerpApi. Uma palavra esta devida quando nunca rodou no
     * Divulga Vagas (horario nulo) ou quando o horario ja passou.
     *
     * @return list<array<string, mixed>>
     */
    public function listarDevidasDoUsuarioDivulgaVagas(int $usuarioId, int $limite = 200, ?string $agora = null): array
    {
        // Momento de referencia da verificacao.
        if ($agora === null) {
            $agora = date('Y-m-d H:i:s');
        }

        return $this->groupStart()
            ->where('proxima_execucao_divulgavagas IS NULL', null, false)
            ->orWhere('proxima_execucao_divulgavagas <=', $agora)
            ->groupEnd()
            ->where('usuario_id', $usuarioId)
            ->where('ativa', 1)
            // Nunca executadas primeiro.
            ->orderBy('proxima_execucao_divulgavagas IS NULL', 'DESC', false)
            // Depois, as mais atrasadas.
            ->orderBy('proxima_execucao_divulgavagas', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll($limite);
    }

    /**
     * Atualiza o agendamento do segundo provedor de uma palavra-chave.
     *
     * O horario do SerpApi (coluna "proxima_execucao") nao e tocado:
     * cada provedor tem o seu.
     *
     * @param int         $id        Identificador da palavra-chave.
     * @param string      $ultima    Momento da ultima execucao.
     * @param string|null $proxima   Momento da proxima execucao (null = imediata).
     * @param int         $consultas Quantas consultas foram consumidas agora.
     */
    public function registrarExecucaoDivulgaVagas(int $id, string $ultima, ?string $proxima, int $consultas = 0): void
    {
        // Dados basicos de agendamento.
        $dados = [
            'ultima_execucao_divulgavagas'  => $ultima,
            'proxima_execucao_divulgavagas' => $proxima,
            'atualizado_em'                 => date('Y-m-d H:i:s'),
        ];

        // Soma as consultas consumidas, sem sobrescrever o historico.
        if ($consultas > 0) {
            $dados['total_consultas'] = new \CodeIgniter\Database\RawSql('total_consultas + ' . (int) $consultas);
        }

        // Update direto: nao ha necessidade de validacao aqui.
        $this->db->table($this->table)->where('id', $id)->update($dados);
    }
}
