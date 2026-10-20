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
 * MODEL: CONFIGURACOES DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Dados de envio de cada usuario:
 *   - e-mail remetente e APP PASSWORD (criptografado)
 *   - curriculo em PDF
 *   - assunto e corpo padrao
 *   - limites e horarios de envio
 *
 * O APP PASSWORD NUNCA aparece em texto puro fora do momento do envio.
 */
class ConfiguracaoModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'configuracoes';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'usuario_id',
        'email_remetente',
        'app_password_criptografado',
        'nome_remetente',
        'telefone',
        'assunto_padrao',
        'corpo_padrao',
        'assinatura',
        'curriculo_pdf',
        'fuso_horario',
        'limite_envios_dia',
        'intervalo_minimo_segundos',
        'intervalo_maximo_segundos',
        'envios_hoje',
        'data_ultimo_envio',
        'data_reset_contador',
        'horario_inicio_envio',
        'horario_fim_envio',
        'dias_semana_permitidos',
        // Limites do segundo provedor (Divulga Vagas).
        'divulgavagas_max_buscas_manuais_dia',
        'divulgavagas_max_candidaturas_manuais_dia',
        'divulgavagas_buscas_manuais_hoje',
        'divulgavagas_candidaturas_manuais_hoje',
        'divulgavagas_data_reset_manual',
        'divulgavagas_max_candidaturas_bot_dia',
        'divulgavagas_candidaturas_bot_hoje',
        'divulgavagas_data_reset_bot',
        'divulgavagas_intervalo_bot_min_segundos',
        'divulgavagas_intervalo_bot_max_segundos',
    ];

    // Campos de data controlados pelo model.
    // ATENCAO: data_ultimo_envio e data_reset_contador sao controlados
    // pela regra de negocio, nao pelo model.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Retorna a configuracao de um usuario.
     *
     * @return array<string, mixed>|null
     */
    public function obterPorUsuario(int $usuarioId): ?array
    {
        return $this->where('usuario_id', $usuarioId)->first();
    }

    /**
     * Valores padrao usados quando o usuario ainda nao salvou nada.
     *
     * @return array<string, mixed>
     */
    public function padroes(int $usuarioId): array
    {
        // Corpo padrao com as variaveis substituidas no momento do envio.
        $corpo = "Ola,\n\n"
            . "Tenho interesse na vaga de {titulo_vaga} na empresa {empresa}, em {localizacao}.\n"
            . "Segue meu curriculo em anexo para sua avaliacao.\n\n"
            . "Fico a disposicao para uma conversa.\n\n"
            . "Atenciosamente,\n"
            . "{seu_nome}";

        return [
            'usuario_id'                   => $usuarioId,
            'email_remetente'              => '',
            'app_password_criptografado'   => null,
            'nome_remetente'               => '',
            'telefone'                     => '',
            'assunto_padrao'               => 'Candidatura para a vaga de {titulo_vaga}',
            'corpo_padrao'                 => $corpo,
            'assinatura'                   => '',
            'curriculo_pdf'                => null,
            'fuso_horario'                 => 'America/Sao_Paulo',
            'limite_envios_dia'            => 20,
            'intervalo_minimo_segundos'    => 180,
            'intervalo_maximo_segundos'    => 420,
            'envios_hoje'                  => 0,
            'data_ultimo_envio'            => null,
            'data_reset_contador'          => null,
            'horario_inicio_envio'         => '08:00:00',
            'horario_fim_envio'            => '20:00:00',
            'dias_semana_permitidos'       => '1,2,3,4,5',
            // Limites do segundo provedor (Divulga Vagas).
            'divulgavagas_max_buscas_manuais_dia'         => 3,
            'divulgavagas_max_candidaturas_manuais_dia'   => 5,
            'divulgavagas_buscas_manuais_hoje'            => 0,
            'divulgavagas_candidaturas_manuais_hoje'      => 0,
            'divulgavagas_data_reset_manual'              => null,
            'divulgavagas_max_candidaturas_bot_dia'        => 30,
            'divulgavagas_candidaturas_bot_hoje'           => 0,
            'divulgavagas_data_reset_bot'                 => null,
            'divulgavagas_intervalo_bot_min_segundos'     => 60,
            'divulgavagas_intervalo_bot_max_segundos'     => 180,
        ];
    }

    /**
     * Devolve a configuracao existente ou os valores padrao.
     *
     * Nao grava nada: leitura nunca cria registro no banco.
     *
     * @return array<string, mixed>
     */
    public function obterOuPadroes(int $usuarioId): array
    {
        // Registro ja salvo.
        $registro = $this->obterPorUsuario($usuarioId);

        // Primeiro acesso: devolve os padroes em memoria.
        if ($registro === null) {
            return $this->padroes($usuarioId);
        }

        // Mescla os padroes com os valores gravados.
        return array_merge($this->padroes($usuarioId), $registro);
    }

    /**
     * Cria ou atualiza a configuracao do usuario.
     *
     * @param array<string, mixed> $dados Campos que serao gravados.
     */
    public function salvarPorUsuario(int $usuarioId, array $dados): void
    {
        // Garante que o dono nunca seja trocado.
        $dados['usuario_id'] = $usuarioId;

        // Procura o registro atual.
        $registro = $this->obterPorUsuario($usuarioId);

        // Primeiro salvamento: cria com os padroes + dados informados.
        if ($registro === null) {
            $this->insert(array_merge($this->padroes($usuarioId), $dados));

            return;
        }

        // Atualiza apenas os campos enviados.
        $this->update((int) $registro['id'], $dados);
    }

    /**
     * Registra UM envio bem-sucedido: contador diario, data do ultimo envio
     * e data de reset do contador.
     *
     * O contador diario e reiniciado quando muda o dia (comparacao com
     * data_reset_contador), sem depender de rotina externa.
     */
    public function registrarEnvio(int $usuarioId, string $agora): void
    {
        // Registro atual (o configuracao existe porque houve envio).
        $registro = $this->obterPorUsuario($usuarioId);

        if ($registro === null) {
            return;
        }

        // Dia corrente e dia do ultimo reset.
        $hoje      = substr($agora, 0, 10);
        $reiniciar = texto_ou($registro, 'data_reset_contador') !== $hoje;

        // Contador diario atualizado.
        $enviosHoje = inteiro_ou($registro, 'envios_hoje', 0) + 1;

        if ($reiniciar) {
            $enviosHoje = 1;
        }

        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'envios_hoje'         => $enviosHoje,
                'data_reset_contador' => $hoje,
                'data_ultimo_envio'   => $agora,
                'atualizado_em'       => $agora,
            ]);
    }

    /**
     * Registra uma TENTATIVA que nao chegou a enviar.
     *
     * Atualiza apenas a data do ultimo envio, para que o intervalo minimo
     * entre tentativas seja respeitado e a vaga nao seja tentada em rajada.
     */
    public function marcarTentativa(int $usuarioId, string $agora): void
    {
        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'data_ultimo_envio' => $agora,
                'atualizado_em'     => $agora,
            ]);
    }

    // =====================================================================
    // BLOCO DO DIVULGA VAGAS (SEGUNDO PROVEDOR)
    // =====================================================================

    /**
     * Registra UMA candidatura feita pelo bot no site do Divulga Vagas.
     *
     * Soma o contador diario do bot e zera o contador quando o dia virou.
     *
     * O limite do bot e SEPARADO do limite de e-mail: uma candidatura
     * pelo site nao consome o limite de e-mails do usuario.
     *
     * A data da ultima candidatura NAO fica aqui: ela vive na propria
     * vaga (vagas.data_ultimo_envio_bot), e a consulta do intervalo usa o
     * maior valor entre as vagas do usuario (VagaModel::ultimaCandidaturaDoBot).
     */
    public function registrarCandidaturaDoBot(int $usuarioId, string $agora): void
    {
        $registro = $this->obterPorUsuario($usuarioId);

        if ($registro === null) {
            return;
        }

        $hoje = substr($agora, 0, 10);
        $reiniciar = texto_ou($registro, 'divulgavagas_data_reset_bot') !== $hoje;

        $candidaturasHoje = inteiro_ou($registro, 'divulgavagas_candidaturas_bot_hoje', 0) + 1;

        if ($reiniciar) {
            $candidaturasHoje = 1;
        }

        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'divulgavagas_candidaturas_bot_hoje' => $candidaturasHoje,
                'divulgavagas_data_reset_bot'        => $hoje,
                'atualizado_em'                      => $agora,
            ]);
    }

    /**
     * Registra uma busca manual disparada pelo usuario no painel dele.
     */
    public function registrarBuscaManual(int $usuarioId, string $agora): void
    {
        $registro = $this->obterPorUsuario($usuarioId);

        if ($registro === null) {
            return;
        }

        $hoje = substr($agora, 0, 10);
        $reiniciar = texto_ou($registro, 'divulgavagas_data_reset_manual') !== $hoje;

        $buscasHoje = inteiro_ou($registro, 'divulgavagas_buscas_manuais_hoje', 0) + 1;

        if ($reiniciar) {
            $buscasHoje = 1;
        }

        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'divulgavagas_buscas_manuais_hoje' => $buscasHoje,
                'divulgavagas_data_reset_manual'    => $hoje,
                'atualizado_em'                     => $agora,
            ]);
    }

    /**
     * Registra uma candidatura manual disparada pelo usuario no painel dele.
     */
    public function registrarCandidaturaManual(int $usuarioId, string $agora): void
    {
        $registro = $this->obterPorUsuario($usuarioId);

        if ($registro === null) {
            return;
        }

        $hoje = substr($agora, 0, 10);
        $reiniciar = texto_ou($registro, 'divulgavagas_data_reset_manual') !== $hoje;

        $candidaturasHoje = inteiro_ou($registro, 'divulgavagas_candidaturas_manuais_hoje', 0) + 1;

        if ($reiniciar) {
            $candidaturasHoje = 1;
        }

        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'divulgavagas_candidaturas_manuais_hoje' => $candidaturasHoje,
                'divulgavagas_data_reset_manual'          => $hoje,
                'atualizado_em'                           => $agora,
            ]);
    }

    /**
     * Zera os contadores manuais do usuario (usado pelo botao do admin).
     */
    public function zerarContadoresManuais(int $usuarioId, string $agora): void
    {
        $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->update([
                'divulgavagas_buscas_manuais_hoje'       => 0,
                'divulgavagas_candidaturas_manuais_hoje' => 0,
                'divulgavagas_data_reset_manual'          => date('Y-m-d'),
                'atualizado_em'                           => $agora,
            ]);
    }

    /**
     * Informa se o usuario ja cadastrou um APP PASSWORD.
     */
    public function temAppPassword(array $configuracao): bool
    {
        return trim(texto_ou($configuracao, 'app_password_criptografado')) !== '';
    }

    /**
     * Informa se o usuario ja enviou um curriculo.
     */
    public function temCurriculo(array $configuracao): bool
    {
        return trim(texto_ou($configuracao, 'curriculo_pdf')) !== '';
    }

    /**
     * Conta quantos usuarios ja cadastraram uma APP PASSWORD.
     * Usado no resumo da visao geral do painel administrativo.
     */
    public function contarComAppPassword(): int
    {
        return (int) $this->db->table($this->table)
            ->where('app_password_criptografado IS NOT NULL', null, false)
            ->where('app_password_criptografado !=', '')
            ->countAllResults();
    }
}
