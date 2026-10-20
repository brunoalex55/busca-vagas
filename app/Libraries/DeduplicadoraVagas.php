<?php

namespace App\Libraries;

use App\Models\VagaModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: DEDUPLICADORA DE VAGAS
 * --------------------------------------------------------------------------
 *
 * A tabela `vagas` e compartilhada pelos provedores de busca. A regra de
 * deduplicacao tambem:
 *
 *   1. pelo job_id (o identificador da vaga no provedor). Para o Divulga
 *      Vagas, o job_id e o vaga_id_divulgavagas (o numero no fim da URL);
 *   2. pelo hash_unico (SHA-256 de titulo + empresa + localizacao), que
 *      pega a mesma vaga achada por caminhos diferentes.
 *
 * Quando a vaga ja existe, os campos vazios sao completados (uma vaga
 * gravada sem e-mail ganha o e-mail quando ele aparece), sem nunca
 * sobrescrever o que ja estava bom.
 *
 * A deduplicacao e sempre POR USUARIO: dois usuarios podem ter a mesma
 * vaga, cada um com a sua propria candidatura.
 */
class DeduplicadoraVagas
{
    /** @var VagaModel */
    private VagaModel $vagas;

    public function __construct(?VagaModel $vagas = null)
    {
        if ($vagas === null) {
            $vagas = model(VagaModel::class);
        }

        $this->vagas = $vagas;
    }

    /**
     * Salva a vaga, respeitando a deduplicacao.
     *
     * @param array<string, mixed> $dados Dados ja higienizados da vaga.
     *                                      Precisa conter 'usuario_id',
     *                                      'job_id' e 'hash_unico'.
     *
     * @return array{
     *     situacao: string,
     *     id: int,
     *     mensagem: string
     * } Onde 'situacao' e 'nova', 'repetida' ou 'erro'.
     */
    public function salvarOuAtualizarVaga(array $dados): array
    {
        $usuarioId = (int) valor_ou($dados, 'usuario_id', 0);
        $jobId = (string) valor_ou($dados, 'job_id', '');
        $hash = (string) valor_ou($dados, 'hash_unico', '');

        // A vaga ja existe (por job_id ou por hash)?
        $existente = $this->vagas->buscarExistente($usuarioId, $jobId, $hash);

        if ($existente !== null) {
            // Completa apenas os campos vazios.
            $this->vagas->complementarVazios($existente, $dados);

            return [
                'situacao' => 'repetida',
                'id'       => (int) $existente['id'],
                'mensagem' => 'Vaga ja cadastrada (atualizada).',
            ];
        }

        // Tenta gravar. Uma corrida entre execucoes cai no catch.
        try {
            $id = $this->vagas->inserirVaga($dados);

            return [
                'situacao' => 'nova',
                'id'       => $id,
                'mensagem' => 'Vaga nova.',
            ];
        } catch (DatabaseException $e) {
            // A vaga foi gravada por outra execucao no mesmo instante.
            return [
                'situacao' => 'repetida',
                'id'       => 0,
                'mensagem' => 'Vaga gravada por outra execucao no mesmo instante.',
            ];
        } catch (Throwable $e) {
            // Qualquer outra falha nao derruba a busca inteira.
            return [
                'situacao' => 'erro',
                'id'       => 0,
                'mensagem' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monta o hash unico de uma vaga.
     *
     * A regra fica no VagaModel (mesma de sempre); aqui e apenas um
     * atalho para quem usa a deduplicadora.
     */
    public function gerarHash(string $titulo, string $empresa, string $localizacao): string
    {
        return VagaModel::gerarHashUnico($titulo, $empresa, $localizacao);
    }
}
