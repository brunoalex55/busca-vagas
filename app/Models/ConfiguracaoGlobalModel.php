<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: CONFIGURACAO GLOBAL
 * --------------------------------------------------------------------------
 *
 * Configuracao interna do sistema, separada da configuracao publica do
 * site (que esta em `configuracao_site`). A tabela tem UMA linha (id 1).
 *
 * Aqui ficam os limites das execucoes manuais disparadas pelo
 * ADMINISTRADOR:
 *   - admin_max_buscas_manuais_dia: buscas globais por dia;
 *   - admin_max_candidaturas_manuais_dia: candidaturas globais por dia;
 *   - os contadores do dia e a data do ultimo reset.
 *
 * Os limites do usuario comum ficam em `configuracoes`, uma linha por
 * usuario. O administrador nao fura limite nenhum: ele acelera o
 * processo, mas a candidatura por usuario continua respeitando o limite
 * de e-mail e os limites do bot daquele usuario.
 */
class ConfiguracaoGlobalModel extends Model
{
    // Tabela do banco.
    protected $table = 'configuracao_global';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Campos gravaveis.
    protected $allowedFields = [
        'admin_max_buscas_manuais_dia',
        'admin_max_candidaturas_manuais_dia',
        'admin_buscas_manuais_hoje',
        'admin_candidaturas_manuais_hoje',
        'admin_data_reset_manual',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Valores usados quando a linha ainda nao existe.
     */
    public const PADRAO_BUSCAS = 10;
    public const PADRAO_CANDIDATURAS = 20;

    /**
     * Le a configuracao global, criando a linha se preciso.
     *
     * @return array<string, mixed>
     */
    public function obter(): array
    {
        $linha = $this->find(1);

        if ($linha === null) {
            $this->insert([
                'id'                             => 1,
                'admin_max_buscas_manuais_dia'   => self::PADRAO_BUSCAS,
                'admin_max_candidaturas_manuais_dia' => self::PADRAO_CANDIDATURAS,
                'admin_buscas_manuais_hoje'      => 0,
                'admin_candidaturas_manuais_hoje' => 0,
                'admin_data_reset_manual'        => null,
            ]);

            $linha = $this->find(1);
        }

        if ($linha === null) {
            return [];
        }

        return $linha;
    }

    /**
     * Zera os contadores do dia quando o dia virou.
     */
    public function resetarContadoresSeNecessario(): bool
    {
        $configuracao = $this->obter();

        $hoje = date('Y-m-d');
        $ultimoReset = texto_ou($configuracao, 'admin_data_reset_manual', '');

        // Ja foi resetado hoje: nada a fazer.
        if ($ultimoReset === $hoje) {
            return false;
        }

        return $this->update(1, [
            'admin_buscas_manuais_hoje'      => 0,
            'admin_candidaturas_manuais_hoje' => 0,
            'admin_data_reset_manual'        => $hoje,
        ]);
    }

    /**
     * Soma uma busca manual do administrador no contador do dia.
     */
    public function registrarBusca(): bool
    {
        $this->resetarContadoresSeNecessario();

        $configuracao = $this->obter();
        $usadas = inteiro_ou($configuracao, 'admin_buscas_manuais_hoje', 0);

        return $this->update(1, ['admin_buscas_manuais_hoje' => $usadas + 1]);
    }

    /**
     * Soma uma candidatura manual do administrador no contador do dia.
     */
    public function registrarCandidatura(): bool
    {
        $this->resetarContadoresSeNecessario();

        $configuracao = $this->obter();
        $usadas = inteiro_ou($configuracao, 'admin_candidaturas_manuais_hoje', 0);

        return $this->update(1, ['admin_candidaturas_manuais_hoje' => $usadas + 1]);
    }

    /**
     * Grava os limites globais de execucao manual do administrador.
     *
     * O zero desliga a execucao manual daquele tipo.
     *
     * @param int $buscas      Limite de buscas por dia.
     * @param int $candidaturas Limite de candidaturas por dia.
     */
    public function salvarLimites(int $buscas, int $candidaturas): bool
    {
        // A linha precisa existir antes de gravar.
        $this->obter();

        return $this->update(1, [
            'admin_max_buscas_manuais_dia'       => max(0, $buscas),
            'admin_max_candidaturas_manuais_dia' => max(0, $candidaturas),
        ]);
    }
}
