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
 * MODEL: USUARIOS
 * --------------------------------------------------------------------------
 *
 * Centraliza o acesso a tabela `usuarios`.
 *
 * O campo `senha` nunca e exposto em arrays/JSON: ele fica em $hidden.
 * A verificacao de senha e feita com password_verify().
 */
class UsuarioModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'usuarios';

    // Chave primaria da tabela.
    protected $primaryKey = 'id';

    // Retorna arrays em vez de objetos (mais simples nas views).
    protected $returnType = 'array';

    // Permite atualizacao em lote (insertBatch/updateBatch).
    protected $useAutoIncrement = true;

    // Campos que podem ser gravados em massa (protege contra
    // atribuicao maliciosa de campos como `perfil`).
    protected $allowedFields = [
        'nome',
        'email',
        'senha',
        'perfil',
        'status',
        'consultas_usadas_mes',
        'mes_referencia',
        'limite_manual',
        'envio_automatico',
        'emails_automaticos_enviados',
        'ultima_execucao_envio_automatico',
        'ultima_execucao_busca_vagas',
        'ativo',
        // O plano do usuario (Fase 9): escolhido no cadastro ou
        // atribuido pelo administrador.
        'plano_id',
        'plano_inicio_em',
        'plano_fim_em',
    ];

    // Nunca devolver o hash da senha em toArray()/JSON.
    protected $hidden = ['senha'];

    // Campos de data controlados automaticamente pelo model.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    // Regras de validacao basicas.
    protected $validationRules = [
        'nome'  => 'required|min_length[3]|max_length[150]',
        'email' => 'required|valid_email|max_length[180]',
        'senha' => 'required|min_length[6]',
    ];

    // Mensagens de erro em portugues.
    protected $validationMessages = [
        'nome' => [
            'required'   => 'Informe o nome.',
            'min_length' => 'O nome deve ter pelo menos 3 caracteres.',
        ],
        'email' => [
            'required'    => 'Informe o e-mail.',
            'valid_email' => 'Informe um e-mail valido.',
        ],
        'senha' => [
            'required'   => 'Informe a senha.',
            'min_length' => 'A senha deve ter pelo menos 6 caracteres.',
        ],
    ];

    /**
     * Busca um usuario pelo e-mail (usado no login e no cadastro).
     *
     * @return array<string, mixed>|null
     */
    public function buscarPorEmail(string $email): ?array
    {
        // Retorna o registro ou null se nao existir.
        return $this->where('email', $email)->first();
    }

    /**
     * Conta quantos usuarios ativos existem.
     * Usado no calculo dinamico de consultas por usuario.
     */
    public function totalAtivos(): int
    {
        return $this->where('status', 'ativo')->where('ativo', 1)->countAllResults();
    }

    /**
     * Lista as contas que podem participar de uma execucao automatica.
     *
     * Este e o filtro base de TODOS os crons: somente quem tem status
     * ativo e a flag ativa ligada entra na fila. Nada mais e exigido
     * aqui (cada provedor decide o seu proprio criterio adicional).
     *
     * A ordem coloca primeiro quem foi executado ha mais tempo, para o
     * cron rodar todos antes de repetir alguem.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAtivos(int $limite = 0, string $colunaOrdem = 'ultima_execucao_busca_vagas'): array
    {
        // A coluna de ordem precisa existir na tabela.
        $colunas = ['id', 'nome', 'email'];

        if (in_array($colunaOrdem, ['ultima_execucao_busca_vagas', 'ultima_execucao_envio_automatico'], true)) {
            $colunas[] = $colunaOrdem;
        }

        // Builder com apenas as colunas necessarias.
        $builder = $this->db->table($this->table)
            ->select(implode(', ', $colunas))
            ->where('status', 'ativo')
            ->where('ativo', 1);

        // A ordenacao pela coluna de "ultima execucao" so vale quando ela
        // existe na lista.
        if (in_array($colunaOrdem, $colunas, true)) {
            // Nunca executados primeiro.
            $builder->orderBy($colunaOrdem . ' IS NULL', 'DESC', false);
            // Depois, os mais atrasados.
            $builder->orderBy($colunaOrdem, 'ASC');
        }

        $builder->orderBy('id', 'ASC');

        // Limite opcional de usuarios por execucao.
        if ($limite > 0) {
            return $builder->get($limite)->getResultArray();
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Lista os usuarios que podem ter vagas buscadas automaticamente.
     *
     * Criterio: status ativo e flag ativo ligada. A ordem coloca primeiro
     * quem foi buscado ha mais tempo, garantindo que o cron rode todos os
     * usuarios antes de repetir um.
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaBusca(int $limite = 0): array
    {
        // Builder com apenas as colunas necessarias.
        $builder = $this->db->table($this->table)
            ->select('id, nome, email, limite_manual, consultas_usadas_mes, mes_referencia, ultima_execucao_busca_vagas')
            ->where('status', 'ativo')
            ->where('ativo', 1)
            // Nunca buscados primeiro.
            ->orderBy('ultima_execucao_busca_vagas IS NULL', 'DESC', false)
            // Depois, os mais atrasados.
            ->orderBy('ultima_execucao_busca_vagas', 'ASC')
            ->orderBy('id', 'ASC');

        // Limite opcional de usuarios por execucao.
        if ($limite > 0) {
            return $builder->get($limite)->getResultArray();
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Lista os usuarios que autorizaram o envio automatico de candidaturas.
     *
     * Criterio: conta ativa, flag ativa ligada e a chave `envio_automatico`
     * ligada pelo proprio usuario. A ordem coloca primeiro quem foi enviado
     * ha mais tempo, garantindo rodizio entre os usuarios.
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaEnvio(int $limite = 0): array
    {
        // Builder com apenas as colunas necessarias.
        $builder = $this->db->table($this->table)
            ->select('id, nome, email, envio_automatico, emails_automaticos_enviados, ultima_execucao_envio_automatico')
            ->where('status', 'ativo')
            ->where('ativo', 1)
            ->where('envio_automatico', 1)
            // Nunca enviados primeiro.
            ->orderBy('ultima_execucao_envio_automatico IS NULL', 'DESC', false)
            // Depois, os mais atrasados.
            ->orderBy('ultima_execucao_envio_automatico', 'ASC')
            ->orderBy('id', 'ASC');

        // Limite opcional de usuarios por execucao.
        if ($limite > 0) {
            return $builder->get($limite)->getResultArray();
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Registra o momento da ultima execucao do CRON 2 para o usuario e
     * soma os e-mails automaticos enviados com sucesso.
     *
     * O incremento e feito no proprio banco para nao perder contagem em
     * execucoes simultaneas.
     */
    public function registrarExecucaoEnvio(int $usuarioId, int $enviados = 0): void
    {
        $agora = date('Y-m-d H:i:s');

        $this->db->table($this->table)
            ->where('id', $usuarioId)
            ->update([
                'ultima_execucao_envio_automatico' => $agora,
                'emails_automaticos_enviados'      => new \CodeIgniter\Database\RawSql(
                    'emails_automaticos_enviados + ' . max(0, $enviados)
                ),
                'atualizado_em'                    => $agora,
            ]);
    }

    /**
     * Reinicia a contagem de consultas do mes quando o mes virou.
     *
     * Chamado no inicio da busca de cada usuario.
     */
    public function reiniciarMesDeConsultas(int $usuarioId, string $mesReferencia): void
    {
        $this->db->table($this->table)
            ->where('id', $usuarioId)
            ->update([
                'consultas_usadas_mes' => 0,
                'mes_referencia'       => $mesReferencia,
                'atualizado_em'        => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Contabiliza UMA consulta consumida pelo usuario no mes corrente.
     *
     * Se o usuario ainda estiver com o mes anterior registrado, a
     * contagem comeca do zero (protege contra virada de mes no meio da
     * execucao). O calculo e feito no proprio banco, em uma unica
     * instrucao, para nao perder contagem em execucoes simultaneas.
     */
    public function registrarConsultaConsumida(int $usuarioId, string $mesReferencia): void
    {
        // Mes escapado pelo driver: nunca concatenamos valor cru.
        $mes = $this->db->escape($mesReferencia);

        $this->db->table($this->table)
            ->where('id', $usuarioId)
            ->update([
                'consultas_usadas_mes' => new \CodeIgniter\Database\RawSql(
                    'IF(mes_referencia = ' . $mes . ', consultas_usadas_mes + 1, 1)'
                ),
                'mes_referencia'       => $mesReferencia,
                'atualizado_em'        => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Registra o momento da ultima busca de vagas do usuario.
     */
    public function registrarExecucaoBusca(int $usuarioId): void
    {
        $agora = date('Y-m-d H:i:s');

        $this->db->table($this->table)
            ->where('id', $usuarioId)
            ->update([
                'ultima_execucao_busca_vagas' => $agora,
                'atualizado_em'               => $agora,
            ]);
    }

    // =========================================================================
    // CONSULTAS DO PAINEL ADMINISTRATIVO
    // =========================================================================

    /**
     * Lista usuarios para o painel administrativo, com filtros e paginacao.
     *
     * @param array{busca?: string, status?: string, perfil?: string, envio?: string} $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarAdmin(array $filtros, int $limite, int $offset): array
    {
        // Builder com as colunas usadas na listagem.
        $builder = $this->db->table($this->table . ' u')
            ->select(
                'u.id, u.nome, u.email, u.perfil, u.status, u.ativo, u.consultas_usadas_mes, '
                . 'u.mes_referencia, u.limite_manual, u.envio_automatico, '
                . 'u.emails_automaticos_enviados, u.ultima_execucao_busca_vagas, '
                . 'u.ultima_execucao_envio_automatico, u.criado_em, '
                . 'u.plano_id, p.nome AS plano_nome'
            )
            ->join('planos p', 'p.id = u.plano_id', 'left')
            ->orderBy('u.id', 'DESC');

        // Aplica os filtros recebidos.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Total de usuarios que atendem aos filtros do painel administrativo.
     *
     * @param array{busca?: string, status?: string, perfil?: string, envio?: string} $filtros
     */
    public function contarAdmin(array $filtros): int
    {
        $builder = $this->db->table($this->table);

        // Aplica os mesmos filtros da listagem.
        $this->aplicarFiltrosAdmin($builder, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Aplica os filtros administrativos a um builder de usuarios.
     *
     * @param array{busca?: string, status?: string, perfil?: string, envio?: string} $filtros
     */
    private function aplicarFiltrosAdmin(BaseBuilder $builder, array $filtros): void
    {
        // Busca por nome ou e-mail.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('nome', $busca)
                ->orLike('email', $busca)
                ->groupEnd();
        }

        // Situacao da conta.
        $status = texto_ou($filtros, 'status');

        if (in_array($status, ['pendente', 'ativo', 'bloqueado'], true)) {
            $builder->where('u.status', $status);
        }

        // Perfil de acesso.
        $perfil = texto_ou($filtros, 'perfil');

        if (in_array($perfil, ['admin', 'usuario'], true)) {
            $builder->where('u.perfil', $perfil);
        }

        // Apenas quem tem o envio automatico ligado.
        if (valor_ou($filtros, 'envio', '') === '1') {
            $builder->where('u.envio_automatico', 1);
        }
    }

    /**
     * Reune os numeros gerais de usuarios para o painel.
     *
     * @return array<string, int>
     */
    public function estatisticas(): array
    {
        // Contagem por status.
        $porStatus = ['pendente' => 0, 'ativo' => 0, 'bloqueado' => 0];

        $linhas = $this->db->table($this->table)
            ->select('status, COUNT(*) AS total', false)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        foreach ($linhas as $linha) {
            $porStatus[(string) $linha['status']] = (int) $linha['total'];
        }

        return [
            'total'        => array_sum($porStatus),
            'pendente'     => $porStatus['pendente'],
            'ativo'        => $porStatus['ativo'],
            'bloqueado'    => $porStatus['bloqueado'],
            'admins'       => (int) $this->db->table($this->table)->where('perfil', 'admin')->countAllResults(),
            'envio_ligado' => (int) $this->db->table($this->table)->where('envio_automatico', 1)->countAllResults(),
            'inativos'     => (int) $this->db->table($this->table)->where('ativo', 0)->countAllResults(),
        ];
    }

    /**
     * Conta quantos administradores ativos existem (protege contra remover
     * o ultimo admin do sistema).
     */
    public function contarAdminsAtivos(): int
    {
        return (int) $this->db->table($this->table)
            ->where('perfil', 'admin')
            ->where('ativo', 1)
            ->countAllResults();
    }

    /**
     * Lista os usuarios ativos com os dados usados na distribuicao de cotas.
     *
     * @return list<array<string, mixed>>
     */
    public function listarParaDistribuicao(): array
    {
        return $this->db->table($this->table)
            ->select('id, nome, email, limite_manual, consultas_usadas_mes, mes_referencia, envio_automatico')
            ->where('status', 'ativo')
            ->where('ativo', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Contas criadas recentemente, para o resumo da visao geral.
     *
     * @return list<array<string, mixed>>
     */
    public function listarRecentes(int $limite = 5): array
    {
        return $this->db->table($this->table)
            ->select('id, nome, email, perfil, status, criado_em')
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Resumo das ultimas execucoes dos crons, por usuario.
     *
     * @return array{ativos: int, busca_24h: int, envio_24h: int, ultima_busca: ?string, ultimo_envio: ?string}
     */
    public function resumoCrons(): array
    {
        // Limite inferior das ultimas 24 horas.
        $ontem = date('Y-m-d H:i:s', time() - 86400);

        // Usuarios ativos (publico dos crons).
        $ativos = $this->totalAtivos();

        // Quem foi processado nas ultimas 24 horas.
        $busca24h = (int) $this->db->table($this->table)
            ->where('ultima_execucao_busca_vagas >=', $ontem)
            ->countAllResults();

        $envio24h = (int) $this->db->table($this->table)
            ->where('ultima_execucao_envio_automatico >=', $ontem)
            ->countAllResults();

        // Ultimo instante de cada tipo de execucao.
        $ultimaBusca = $this->db->table($this->table)
            ->selectMax('ultima_execucao_busca_vagas')
            ->get()
            ->getRowArray();

        $ultimoEnvio = $this->db->table($this->table)
            ->selectMax('ultima_execucao_envio_automatico')
            ->get()
            ->getRowArray();

        return [
            'ativos'        => $ativos,
            'busca_24h'     => $busca24h,
            'envio_24h'     => $envio24h,
            'ultima_busca'  => valor_ou($ultimaBusca, 'ultima_execucao_busca_vagas', null),
            'ultimo_envio'  => valor_ou($ultimoEnvio, 'ultima_execucao_envio_automatico', null),
        ];
    }

    /**
     * Atualiza a situacao da conta (status e flag ativo).
     */
    public function atualizarSituacao(int $id, string $status, int $ativo): void
    {
        // So aceita valores previstos no ENUM.
        if (! in_array($status, ['pendente', 'ativo', 'bloqueado'], true)) {
            return;
        }

        // Flag de ativo (0 ou 1, como pede a coluna).
        $flagAtivo = 0;

        if ($ativo === 1) {
            $flagAtivo = 1;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'status'       => $status,
                'ativo'        => $flagAtivo,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Define (ou limpa) o limite manual de consultas do usuario.
     */
    public function definirLimiteManual(int $id, ?int $limite): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'limite_manual' => $limite,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Liga ou desliga o envio automatico autorizado pelo usuario.
     */
    public function definirEnvioAutomatico(int $id, int $ligado): void
    {
        // Flag de envio automatico (0 ou 1, como pede a coluna).
        $flagEnvio = 0;

        if ($ligado === 1) {
            $flagEnvio = 1;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'envio_automatico' => $flagEnvio,
                'atualizado_em'    => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Altera o perfil de acesso (admin/usuario).
     */
    public function definirPerfil(int $id, string $perfil): void
    {
        // So aceita os perfis previstos no ENUM.
        if (! in_array($perfil, ['admin', 'usuario'], true)) {
            return;
        }

        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'perfil'        => $perfil,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Zera o contador de consultas do mes do usuario.
     */
    public function reiniciarConsultas(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'consultas_usadas_mes' => 0,
                'mes_referencia'       => date('Y-m'),
                'atualizado_em'        => date('Y-m-d H:i:s'),
            ]);
    }
}
