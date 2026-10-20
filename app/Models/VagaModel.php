<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * --------------------------------------------------------------------------
 * MODEL: VAGAS
 * --------------------------------------------------------------------------
 *
 * Acesso a tabela `vagas`.
 *
 * Regras importantes:
 *   - Toda vaga pertence a um usuario (usuario_id obrigatorio).
 *   - A ordenacao padrao coloca primeiro as vagas COM e-mail e depois
 *     as sem e-mail.
 *   - Dentro de cada grupo, a fonte segue a prioridade:
 *     Indeed > LinkedIn > Catho > Gupy > outras.
 */
class VagaModel extends Model
{
    // Tabela gerenciada por este model.
    protected $table = 'vagas';

    // Chave primaria.
    protected $primaryKey = 'id';

    // Retorna arrays.
    protected $returnType = 'array';

    // Campos escreviveis.
    protected $allowedFields = [
        'palavra_chave_id',
        'usuario_id',
        'job_id',
        'hash_unico',
        'titulo',
        'empresa',
        'localizacao',
        'cidade_consultada',
        'fonte',
        'url_original',
        'url_candidatura',
        'descricao',
        'salario',
        'tipo_contrato',
        'publicado_em',
        'email_contato',
        'emails_encontrados',
        'origem_email',
        'ja_candidatado',
        'e_prioritaria',
        // Bloco do segundo provedor (Divulga Vagas).
        'provedor_vaga',
        'url_envio_divulgavagas',
        'vaga_id_divulgavagas',
        'candidatura_divulgavagas_status',
        'candidatura_divulgavagas_tentativas',
        'candidatura_divulgavagas_erro',
        'candidatura_divulgavagas_em',
        'candidatura_divulgavagas_confirmado_em',
        'candidatura_divulgavagas_confirmacao_url',
        'data_ultimo_envio_bot',
        'email_enviado_divulgavagas_em',
        'email_enviado_divulgavagas_erro',
    ];

    // Campos de data automaticos.
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
    protected $dateFormat    = 'datetime';

    /**
     * Ordenacao por prioridade:
     *   1. Vagas com e-mail primeiro.
     *   2. Indeed, LinkedIn, Catho, Gupy, outras.
     *   3. Mais recentes primeiro.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPriorizadas(int $usuarioId, int $limite = 100): array
    {
        // A expressao CASE devolve um numero menor para as fontes
        // consideradas mais relevantes.
        return $this->select('vagas.*', false)
            ->select(
                "CASE
                    WHEN fonte LIKE 'Indeed%'   THEN 1
                    WHEN fonte LIKE 'LinkedIn%' THEN 2
                    WHEN fonte LIKE 'Catho%'    THEN 3
                    WHEN fonte LIKE 'Gupy%'     THEN 4
                    ELSE 5
                END AS prioridade_fonte",
                false
            )
            ->where('usuario_id', $usuarioId)
            // Vagas com e-mail aparecem primeiro.
            ->orderBy('email_contato IS NULL', 'ASC', false)
            // Depois a fonte prioritaria.
            ->orderBy('prioridade_fonte', 'ASC', false)
            // Por fim, as mais recentes.
            ->orderBy('id', 'DESC')
            ->findAll($limite);
    }

    /**
     * Retorna a proxima vaga que ainda nao recebeu candidatura.
     * Usado pelo envio automatico (uma vaga por execucao).
     *
     * @return array<string, mixed>|null
     */
    public function proximaParaEnvio(int $usuarioId): ?array
    {
        return $this->select('vagas.*', false)
            ->select(
                "CASE
                    WHEN fonte LIKE 'Indeed%'   THEN 1
                    WHEN fonte LIKE 'LinkedIn%' THEN 2
                    WHEN fonte LIKE 'Catho%'    THEN 3
                    WHEN fonte LIKE 'Gupy%'     THEN 4
                    ELSE 5
                END AS prioridade_fonte",
                false
            )
            ->where('usuario_id', $usuarioId)
            ->where('ja_candidatado', 0)
            ->where('email_contato IS NOT NULL', null, false)
            ->where('email_contato !=', '')
            ->orderBy('prioridade_fonte', 'ASC', false)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Conta vagas por fonte para os cards do dashboard.
     *
     * @return array<string, int>
     */
    public function contarPorFonte(int $usuarioId): array
    {
        $linhas = $this->select('fonte, COUNT(*) AS total', false)
            ->where('usuario_id', $usuarioId)
            ->groupBy('fonte')
            ->findAll();

        $resultado = [];

        foreach ($linhas as $linha) {
            // Fonte da vaga (vagas sem fonte vao para "Outros").
            $fonte = trim(texto_ou($linha, 'fonte'));

            if ($fonte === '') {
                $fonte = 'Outros';
            }

            $resultado[$fonte] = (int) $linha['total'];
        }

        return $resultado;
    }

    /**
     * Lista vagas do usuario com filtros, ordenacao por prioridade e
     * paginacao manual.
     *
     * @param array<string, mixed> $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function listarComFiltros(int $usuarioId, array $filtros, int $limite, int $offset): array
    {
        // Builder independente para nao interferir no count.
        $builder = $this->db->table($this->table);

        // Aplica os filtros comuns.
        $this->aplicarFiltros($builder, $usuarioId, $filtros);

        // Ordenacao: com e-mail primeiro, fonte prioritaria e mais recentes.
        $this->aplicarOrdenacao($builder);

        // Busca a pagina solicitada.
        return $builder->get($limite, $offset)->getResultArray();
    }

    /**
     * Lista as CANDIDATURAS REALIZADAS de um usuario (todos os provedores).
     *
     * Uma vaga conta como candidatada quando:
     *   - o e-mail de candidatura foi enviado (a marca "ja_candidatado"),
     *     ou
     *   - o bot do segundo provedor confirmou o envio no site.
     *
     * E o que o painel mostra: quantas vagas foram candidatas, quais
     * foram e como cada uma foi feita.
     *
     * @return list<array<string, mixed>>
     */
    public function listarCandidaturasRealizadas(int $usuarioId, int $limite = 10): array
    {
        return $this->db->table($this->table)
            ->select("id, titulo, empresa, localizacao, fonte, provedor_vaga, ja_candidatado, email_contato, candidatura_divulgavagas_status, candidatura_divulgavagas_em, candidatura_divulgavagas_confirmado_em, candidatura_divulgavagas_confirmacao_url, url_original, atualizado_em")
            ->where('usuario_id', $usuarioId)
            ->groupStart()
            ->where('ja_candidatado', 1)
            ->orWhereIn('candidatura_divulgavagas_status', ['enviada', 'enviada_email'])
            ->groupEnd()
            ->orderBy('atualizado_em', 'DESC')
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Lista as CANDIDATURAS REALIZADAS de todo o sistema (admin).
     *
     * @return list<array<string, mixed>>
     */
    public function listarCandidaturasRealizadasGlobal(int $limite = 10): array
    {
        return $this->db->table($this->table . ' v')
            ->select('v.id, v.usuario_id, v.titulo, v.empresa, v.localizacao, v.fonte, v.provedor_vaga, v.ja_candidatado, v.email_contato, v.candidatura_divulgavagas_status, v.candidatura_divulgavagas_em, v.candidatura_divulgavagas_confirmado_em, v.candidatura_divulgavagas_confirmacao_url, v.atualizado_em, u.nome AS usuario_nome')
            ->join('usuarios u', 'u.id = v.usuario_id', 'left')
            ->groupStart()
            ->where('v.ja_candidatado', 1)
            ->orWhereIn('v.candidatura_divulgavagas_status', ['enviada', 'enviada_email'])
            ->groupEnd()
            ->orderBy('v.atualizado_em', 'DESC')
            ->orderBy('v.id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Conta as vagas candidadas de um usuario (todos os provedores).
     */
    public function contarCandidaturasRealizadas(int $usuarioId): int
    {
        return (int) $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->groupStart()
            ->where('ja_candidatado', 1)
            ->orWhereIn('candidatura_divulgavagas_status', ['enviada', 'enviada_email'])
            ->groupEnd()
            ->countAllResults();
    }

    /**
     * Conta as vagas candidadas de todo o sistema (admin).
     */
    public function contarCandidaturasRealizadasGlobal(): int
    {
        return (int) $this->db->table($this->table)
            ->groupStart()
            ->where('ja_candidatado', 1)
            ->orWhereIn('candidatura_divulgavagas_status', ['enviada', 'enviada_email'])
            ->groupEnd()
            ->countAllResults();
    }

    /**
     * Conta as vagas de um provedor (usado na aba do segundo provedor).
     */
    public function contarPorProvedor(int $usuarioId, string $provedor): int
    {
        return (int) $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('provedor_vaga', $provedor)
            ->countAllResults();
    }

    /**
     * Conta as vagas do segundo provedor de TODOS os usuarios.
     *
     * E o retrato que o painel do administrador mostra.
     *
     * @return array<string, int>
     */
    public function contarPorSituacaoDivulgaVagasGlobal(): array
    {
        $linhas = $this->db->table($this->table)
            ->select('candidatura_divulgavagas_status AS situacao, COUNT(*) AS total')
            ->where('provedor_vaga', 'divulgavagas')
            ->groupBy('candidatura_divulgavagas_status')
            ->get()
            ->getResultArray();

        $contagem = [
            'pendente'       => 0,
            'enviada'        => 0,
            'enviada_email'  => 0,
            'pendente_email' => 0,
            'erro'           => 0,
            'cloudflare'     => 0,
        ];

        foreach ($linhas as $linha) {
            $situacao = (string) $linha['situacao'];

            if (isset($contagem[$situacao])) {
                $contagem[$situacao] = (int) $linha['total'];
            }
        }

        return $contagem;
    }

    /**
     * Lista as vagas do segundo provedor que ainda nao foram candidatas.
     *
     * @return list<array<string, mixed>>
     */
    public function listarPendentesDoBot(int $usuarioId, int $limite = 3): array
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('provedor_vaga', 'divulgavagas')
            ->whereIn('candidatura_divulgavagas_status', self::SITUACOES_PENDENTES)
            ->where('candidatura_divulgavagas_tentativas <', 3)
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Conta as vagas que atendem aos filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public function contarComFiltros(int $usuarioId, array $filtros): int
    {
        // Builder novo e sem ordenacao.
        $builder = $this->db->table($this->table);

        // Aplica os mesmos filtros da listagem.
        $this->aplicarFiltros($builder, $usuarioId, $filtros);

        return $builder->countAllResults();
    }

    /**
     * Busca uma vaga garantindo que ela pertence ao usuario.
     *
     * @return array<string, mixed>|null
     */
    public function buscarDoUsuario(int $id, int $usuarioId): ?array
    {
        // Consulta direta e escopada pelo dono.
        $linha = $this->db->table($this->table)
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->get()
            ->getRowArray();

        // Sem linha encontrada: devolve nulo.
        if ($linha === null || $linha === []) {
            return null;
        }

        return $linha;
    }

    /**
     * Marca uma vaga como ja candidatada (sempre do proprio usuario).
     */
    public function marcarCandidatado(int $id, int $usuarioId): bool
    {
        // Atualiza apenas o registro do dono.
        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->update([
                'ja_candidatado' => 1,
                'atualizado_em'  => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Gera o hash que identifica uma vaga sem depender do identificador
     * do provedor.
     *
     * Regra (definida na migration): SHA-256 de titulo + empresa +
     * localizacao, tudo em minusculas. Serve para nao gravar a mesma vaga
     * duas vezes quando ela aparece em fontes diferentes.
     */
    public static function gerarHashUnico(string $titulo, string $empresa, string $localizacao): string
    {
        // Normaliza cada parte: minusculas e espacos colapsados.
        $normalizar = static function (string $valor): string {
            $valor = mb_strtolower(trim($valor));

            return (string) preg_replace('/\s+/u', ' ', $valor);
        };

        return hash('sha256', $normalizar($titulo) . '|' . $normalizar($empresa) . '|' . $normalizar($localizacao));
    }

    /**
     * Procura uma vaga ja existente do usuario pelo job_id OU pelo hash.
     *
     * Devolve os campos que podem ser complementados depois, para que
     * uma vaga antiga sem e-mail ganhe o e-mail quando ele aparecer.
     *
     * @return array<string, mixed>|null
     */
    public function buscarExistente(int $usuarioId, string $jobId, string $hash): ?array
    {
        $linha = $this->db->table($this->table)
            ->select('id, email_contato, emails_encontrados, origem_email, url_candidatura, salario, tipo_contrato, publicado_em, descricao')
            ->where('usuario_id', $usuarioId)
            ->groupStart()
                ->where('job_id', $jobId)
                ->orWhere('hash_unico', $hash)
            ->groupEnd()
            ->get()
            ->getRowArray();

        // Sem linha encontrada: devolve nulo.
        if ($linha === null || $linha === []) {
            return null;
        }

        return $linha;
    }

    /**
     * Grava uma vaga nova.
     *
     * @param array<string, mixed> $dados
     *
     * @return int Identificador da vaga gravada.
     */
    public function inserirVaga(array $dados): int
    {
        // Insercao direta no builder: os campos ja vem higienizados
        // pelo orquestrador da busca.
        $this->db->table($this->table)->insert($dados);

        return (int) $this->db->insertID();
    }

    /**
     * Preenche apenas os campos que estao vazios na vaga ja existente.
     *
     * Isso permite que uma vaga gravada sem e-mail passe a ter e-mail
     * quando uma consulta posterior trouxer essa informacao, sem nunca
     * sobrescrever o que ja estava bom nem tocar em dados do usuario
     * (ja_candidatado, e_prioritaria).
     *
     * @param array<string, mixed> $existente Linha atual da vaga.
     * @param array<string, mixed> $novos     Dados recem-coletados.
     */
    public function complementarVazios(array $existente, array $novos): void
    {
        // Campos que podem ser completados.
        $completaveis = [
            'email_contato',
            'emails_encontrados',
            'origem_email',
            'url_candidatura',
            'salario',
            'tipo_contrato',
            'publicado_em',
            'descricao',
        ];

        $atualizar = [];

        foreach ($completaveis as $campo) {
            // Valor atual na vaga.
            $atual = valor_ou($existente, $campo, null);

            // Vale como vazio: nulo, texto vazio ou lista JSON vazia.
            $vazio = $atual === null || $atual === '' || $atual === '[]';

            if (! $vazio) {
                continue;
            }

            // Novo valor precisa existir de fato.
            $novo = valor_ou($novos, $campo, null);

            if ($novo === null || $novo === '' || $novo === '[]') {
                continue;
            }

            $atualizar[$campo] = $novo;
        }

        // Nada a fazer quando nao ha campo vazio.
        if ($atualizar === []) {
            return;
        }

        $atualizar['atualizado_em'] = date('Y-m-d H:i:s');

        $this->db->table($this->table)
            ->where('id', (int) $existente['id'])
            ->update($atualizar);
    }

    /**
     * Total de vagas do usuario.
     */
    public function contarDoUsuario(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->countAllResults();
    }

    /**
     * Total de vagas armazenadas, somando todos os usuarios.
     * Usado no resumo da visao geral do painel administrativo.
     */
    public function contarGlobal(): int
    {
        return (int) $this->db->table($this->table)->countAllResults();
    }

    /**
     * Total de vagas com e-mail ainda nao candidatadas, somando todos
     * os usuarios (tamanho da fila global de envio).
     */
    public function contarFilaGlobal(): int
    {
        return (int) $this->db->table($this->table)
            ->where('ja_candidatado', 0)
            ->where('email_contato IS NOT NULL', null, false)
            ->where('email_contato !=', '')
            ->countAllResults();
    }

    /**
     * Total de vagas que ainda podem receber candidatura por e-mail.
     */
    public function contarComEmailNaoCandidatadas(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('ja_candidatado', 0)
            ->where('email_contato IS NOT NULL', null, false)
            ->where('email_contato !=', '')
            ->countAllResults();
    }

    /**
     * Total de vagas ja marcadas como candidatadas.
     */
    public function contarCandidatadas(int $usuarioId): int
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('ja_candidatado', 1)
            ->countAllResults();
    }

    /**
     * Ultimas vagas adicionadas, para o dashboard.
     *
     * @return list<array<string, mixed>>
     */
    public function listarRecentes(int $usuarioId, int $limite = 5): array
    {
        return $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->orderBy('id', 'DESC')
            ->get($limite)
            ->getResultArray();
    }

    /**
     * Proximas vagas da fila de envio (com e-mail e nao candidatadas).
     *
     * @return list<array<string, mixed>>
     */
    public function listarFilaEnvio(int $usuarioId, int $limite = 10): array
    {
        // Builder com a fila.
        $builder = $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('ja_candidatado', 0)
            ->where('email_contato IS NOT NULL', null, false)
            ->where('email_contato !=', '');

        // Prioriza as fontes combinadas.
        $this->aplicarOrdenacao($builder);

        return $builder->get($limite)->getResultArray();
    }

    /**
     * Aplica os filtros de tela em um builder.
     *
     * @param array<string, mixed> $filtros
     */
    private function aplicarFiltros(BaseBuilder $builder, int $usuarioId, array $filtros): void
    {
        // Toda consulta e escopada pelo dono.
        $builder->where('usuario_id', $usuarioId);

        // Filtro por fonte (abas do painel).
        $fonte = trim(texto_ou($filtros, 'fonte'));

        if ($fonte !== '' && $fonte !== 'Todas') {
            if ($fonte === 'Divulga Vagas') {
                // Aba do segundo provedor: filtra pelo provedor, e nao
                // pelo texto da fonte.
                $builder->where('provedor_vaga', 'divulgavagas');
            } elseif ($fonte === 'Outras') {
                // Tudo que nao e uma das fontes conhecidas.
                $builder->groupStart()
                    ->where('fonte IS NULL', null, false)
                    ->orWhere('fonte', '')
                    ->orWhere(
                        "fonte NOT LIKE 'Indeed%'"
                        . " AND fonte NOT LIKE 'LinkedIn%'"
                        . " AND fonte NOT LIKE 'Catho%'"
                        . " AND fonte NOT LIKE 'Gupy%'",
                        null,
                        false
                    )
                    ->groupEnd();
            } else {
                // Fonte conhecida: comeca com o nome da fonte.
                $builder->like('fonte', $fonte, 'after');
            }
        }

        // Busca por titulo, empresa ou localizacao.
        $busca = trim(texto_ou($filtros, 'busca'));

        if ($busca !== '') {
            $builder->groupStart()
                ->like('titulo', $busca)
                ->orLike('empresa', $busca)
                ->orLike('localizacao', $busca)
                ->groupEnd();
        }

        // Somente vagas com e-mail de contato.
        if (! empty($filtros['com_email'])) {
            $builder->where('email_contato IS NOT NULL', null, false)
                ->where('email_contato !=', '');
        }

        // Oculta as que ja receberam candidatura.
        if (! empty($filtros['ocultar_candidatadas'])) {
            $builder->where('ja_candidatado', 0);
        }
    }

    /**
     * Ordenacao padrao: e-mail, prioridade da fonte e mais recentes.
     */
    private function aplicarOrdenacao(BaseBuilder $builder): void
    {
        // Vagas com e-mail primeiro.
        $builder->orderBy('email_contato IS NULL', 'ASC', false);

        // Indeed > LinkedIn > Catho > Gupy > outras.
        $builder->orderBy(
            "CASE
                WHEN fonte LIKE 'Indeed%'   THEN 1
                WHEN fonte LIKE 'LinkedIn%' THEN 2
                WHEN fonte LIKE 'Catho%'    THEN 3
                WHEN fonte LIKE 'Gupy%'     THEN 4
                ELSE 5
            END",
            'ASC',
            false
        );

        // Mais recentes primeiro.
        $builder->orderBy('id', 'DESC');
    }

    // =====================================================================
    // BLOCO DO DIVULGA VAGAS (SEGUNDO PROVEDOR)
    // =====================================================================

    /**
     * Situações em que a candidatura pelo site ainda pode acontecer.
     *
     *   - 'pendente'       -> o bot nunca tentou;
     *   - 'pendente_email' -> o bot ja confirmou, mas o e-mail de contacto
     *                         ficou para o proximo ciclo.
     */
    public const SITUACOES_PENDENTES = ['pendente', 'pendente_email'];

    /**
     * Escolhe a proxima vaga do Divulga Vagas para o bot candidatar.
     *
     * Regras:
     *   - a vaga precisa ser do usuario e do provedor 'divulgavagas';
     *   - a situacao precisa ser 'pendente' ou 'pendente_email';
     *   - a vaga nao pode ter esgotado as tres tentativas;
     *   - a ordem e a mesma do projeto: e-mail primeiro, depois a
     *     prioridade da fonte (Indeed, LinkedIn, Catho, Gupy, outras) e
     *     por fim as mais recentes.
     *
     * @return array<string, mixed>|null
     */
    public function proximaParaBot(int $usuarioId, int $maximoTentativas = 3, array $excluirIds = []): ?array
    {
        $builder = $this->db->table($this->table)
            ->where('usuario_id', $usuarioId)
            ->where('provedor_vaga', 'divulgavagas')
            ->whereIn('candidatura_divulgavagas_status', self::SITUACOES_PENDENTES)
            ->where('candidatura_divulgavagas_tentativas <', $maximoTentativas);

        // As vagas ja processadas nesta execucao ficam de fora (o loop
        // nao repete a mesma vaga).
        if ($excluirIds !== []) {
            $builder->whereNotIn('id', $excluirIds);
        }

        $linha = $builder
            ->orderBy('email_contato IS NULL', 'ASC', false)
            ->orderBy(
                "CASE
                    WHEN fonte LIKE 'Indeed%'   THEN 1
                    WHEN fonte LIKE 'LinkedIn%' THEN 2
                    WHEN fonte LIKE 'Catho%'    THEN 3
                    WHEN fonte LIKE 'Gupy%'     THEN 4
                    ELSE 5
                END",
                'ASC',
                false
            )
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if ($linha === null || $linha === []) {
            return null;
        }

        return $linha;
    }

    /**
     * Grava a situacao da candidatura pelo site.
     *
     * @param array<string, mixed> $dados Campos a atualizar.
     */
    public function atualizarCandidaturaDivulgaVagas(int $id, int $usuarioId, array $dados): bool
    {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->update($dados);
    }

    /**
     * Conta as vagas do Divulga Vagas do usuario por situacao.
     *
     * @return array<string, int>
     */
    public function contarPorSituacaoDivulgaVagas(int $usuarioId): array
    {
        $linhas = $this->db->table($this->table)
            ->select('candidatura_divulgavagas_status AS situacao, COUNT(*) AS total')
            ->where('usuario_id', $usuarioId)
            ->where('provedor_vaga', 'divulgavagas')
            ->groupBy('candidatura_divulgavagas_status')
            ->get()
            ->getResultArray();

        $contagem = [
            'pendente'       => 0,
            'enviada'        => 0,
            'enviada_email'  => 0,
            'pendente_email' => 0,
            'erro'           => 0,
            'cloudflare'     => 0,
        ];

        foreach ($linhas as $linha) {
            $situacao = (string) $linha['situacao'];

            if (isset($contagem[$situacao])) {
                $contagem[$situacao] = (int) $linha['total'];
            }
        }

        return $contagem;
    }

    /**
     * Ultima candidatura feita pelo bot (para respeitar o intervalo).
     */
    public function ultimaCandidaturaDoBot(int $usuarioId): ?string
    {
        $valor = $this->db->table($this->table)
            ->selectMax('data_ultimo_envio_bot')
            ->where('usuario_id', $usuarioId)
            ->get()
            ->getRowArray();

        if ($valor === null) {
            return null;
        }

        $data = texto_ou($valor, 'data_ultimo_envio_bot', '');

        if ($data === '') {
            return null;
        }

        return $data;
    }
}
