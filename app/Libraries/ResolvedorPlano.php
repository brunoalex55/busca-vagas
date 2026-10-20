<?php

namespace App\Libraries;

use App\Models\ConfiguracaoModel;
use App\Models\PalavraChaveModel;
use App\Models\UsuarioModel;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: RESOLVEDOR DE PLANO E LIMITES
 * --------------------------------------------------------------------------
 *
 * Descobre qual regra vale para um usuario e de ONDE ela veio.
 *
 * A ORDEM DE PRIORIDADE (do mais forte ao mais fraco):
 *
 *   1. usuarios.limite_manual ....... o admin sobrescreveu a mao (VENCE);
 *   2. planos.{campo} .............. o plano do usuario (NULL = ilimitado);
 *   3. configuracoes.{campo} ....... o valor legado da conta;
 *   4. o padrao do sistema.
 *
 * REGRA CRITICA: NULL em `max_palavras_chave` ou
 * `max_cidades_por_termo` significa ILIMITADO. Todo o codigo que consome
 * esses valores trata o NULL explicitamente (nunca com ternario).
 *
 * O `limite_manual` do usuario e o limite que o admin digitou a mao. Ele
 * vale para os limites DIARIOS (envios por dia e consultas por mes). Os
 * limites de estrutura do plano (quantas palavras-chave e quantas
 * cidades por termo) vem do plano: para mudar isso, o admin ou troca o
 * plano do usuario ou edita o plano.
 */
class ResolvedorPlano
{
    /** @var UsuarioModel */
    private UsuarioModel $usuarios;

    /** @var ConfiguracaoModel */
    private ConfiguracaoModel $configuracoes;

    /** @var PalavraChaveModel */
    private PalavraChaveModel $palavras;

    /**
     * Os valores padrao do sistema (usados quando nada mais existe).
     *
     * @var array<string, int>
     */
    public const PADROES = [
        'max_palavras_chave'    => 2,
        'max_cidades_por_termo' => 5,
        'limite_envios_dia'     => 20,
        'limite_consultas'      => 100,
        'envio_automatico'      => 0,
        'envio_em_massa'        => 0,
    ];

    /**
     * Os campos que o limite manual do usuario sobrescreve.
     *
     * @var list<string>
     */
    private const CAMPOS_DO_LIMITE_MANUAL = [
        'limite_envios_dia',
        'limite_consultas',
    ];

    public function __construct(
        ?UsuarioModel $usuarios = null,
        ?ConfiguracaoModel $configuracoes = null,
        ?PalavraChaveModel $palavras = null
    ) {
        // Os models que chegaram (ou os do sistema).
        if ($usuarios === null) {
            $usuarios = model(UsuarioModel::class);
        }

        if ($configuracoes === null) {
            $configuracoes = model(ConfiguracaoModel::class);
        }

        if ($palavras === null) {
            $palavras = model(PalavraChaveModel::class);
        }

        $this->usuarios = $usuarios;
        $this->configuracoes = $configuracoes;
        $this->palavras = $palavras;
    }

    /**
     * O plano ativo do usuario.
     *
     * Quando o usuario nao tem plano (ou o plano sumiu), devolve o plano
     * basico: o fallback do sistema.
     *
     * @return array<string, mixed>
     */
    public function obterPlanoDoUsuario(int $usuarioId): array
    {
        // O usuario e o plano que ele tem.
        $usuario = $this->usuarios->find($usuarioId);

        if ($usuario === null) {
            return $this->planoBasico();
        }

        $planoId = (int) valor_ou($usuario, 'plano_id', 0);

        // Sem plano definido: o basico.
        if ($planoId <= 0) {
            return $this->planoBasico();
        }

        $plano = $this->db()->table('planos')->where('id', $planoId)->get()->getRowArray();

        // Plano invalido ou desativado: o basico.
        if ($plano === null || $plano === [] || (int) $plano['ativo'] !== 1) {
            return $this->planoBasico();
        }

        return $plano;
    }

    /**
     * O plano basico (o fallback do sistema).
     *
     * @return array<string, mixed>
     */
    public function planoBasico(): array
    {
        $plano = $this->db()->table('planos')->where('slug', 'basico')->get()->getRowArray();

        // Sem o plano basico no banco: um plano vazio com os padroes.
        if ($plano === null || $plano === []) {
            return [
                'id' => 0,
                'slug' => 'basico',
                'nome' => 'Básico',
                'max_palavras_chave' => self::PADROES['max_palavras_chave'],
                'max_cidades_por_termo' => self::PADROES['max_cidades_por_termo'],
                'limite_envios_dia' => self::PADROES['limite_envios_dia'],
                'envio_automatico' => 0,
                'envio_em_massa' => 0,
                'max_usuarios' => 1,
                'suporte' => 'email',
                'destaque' => 0,
                'ordem' => 1,
                'ativo' => 1,
            ];
        }

        return $plano;
    }

    /**
     * O valor efetivo de um limite.
     *
     * @param string $campo     O campo do plano (ou do limite).
     * @param int    $usuarioId O dono da regra.
     *
     * @return int|null Null significa ILIMITADO.
     */
    public function resolverLimite(string $campo, int $usuarioId): int|null
    {
        $resolucao = $this->resolverLimiteComOrigem($campo, $usuarioId);

        return $resolucao['valor'];
    }

    /**
     * O valor efetivo de um limite E de onde ele veio.
     *
     * @param string $campo     O campo do plano (ou do limite).
     * @param int    $usuarioId O dono da regra.
     *
     * @return array{valor: int|null, origem: string, rotulo: string}
     */
    public function resolverLimiteComOrigem(string $campo, int $usuarioId): array
    {
        // 1) Limite manual do usuario (o admin sobrescreveu a mao).
        $manual = $this->limiteManualDoUsuario($usuarioId);

        if ($manual !== null && in_array($campo, self::CAMPOS_DO_LIMITE_MANUAL, true)) {
            return [
                'valor' => $manual,
                'origem' => 'manual',
                'rotulo' => 'Limite manual',
            ];
        }

        // 2) O plano do usuario.
        $plano = $this->obterPlanoDoUsuario($usuarioId);

        if (array_key_exists($campo, $plano)) {
            $valor = $plano[$campo];

            // NULL no plano = ilimitado (nao tem fallback legado).
            if ($valor === null) {
                return [
                    'valor' => null,
                    'origem' => 'plano',
                    'rotulo' => 'Ilimitado (' . (string) $plano['nome'] . ')',
                ];
            }

            return [
                'valor' => (int) $valor,
                'origem' => 'plano',
                'rotulo' => 'Plano ' . (string) $plano['nome'],
            ];
        }

        // 3) O valor legado da conta.
        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);
        $legado = valor_ou($configuracao, $campo, null);

        if ($legado !== null) {
            // A configuracao guarda se o valor dela veio do plano ou foi
            // sobrescrito a mao (a coluna limite_envios_dia_origem).
            $rotulo = 'Configuracao da conta';

            if ($campo === 'limite_envios_dia' && texto_ou($configuracao, 'limite_envios_dia_origem', 'plano') === 'manual') {
                $rotulo = 'Configuracao da conta (ajustada a mao)';
            }

            return [
                'valor' => (int) $legado,
                'origem' => 'configuracao',
                'rotulo' => $rotulo,
            ];
        }

        // 4) O padrao do sistema.
        $padrao = null;

        if (array_key_exists($campo, self::PADROES)) {
            $padrao = self::PADROES[$campo];
        }

        return [
            'valor' => $padrao,
            'origem' => 'padrao',
            'rotulo' => 'Padrao do sistema',
        ];
    }

    /**
     * O limite manual do usuario (null quando ele nao tem um).
     */
    private function limiteManualDoUsuario(int $usuarioId): ?int
    {
        $usuario = $this->usuarios->find($usuarioId);

        if ($usuario === null) {
            return null;
        }

        $valor = valor_ou($usuario, 'limite_manual', null);

        if ($valor === null) {
            return null;
        }

        return max(0, (int) $valor);
    }

    /**
     * O usuario pode cadastrar mais uma palavra-chave?
     *
     * @return array{pode: bool, motivo: string, restantes: int|null, limite: int|null}
     */
    public function podeAdicionarPalavraChave(int $usuarioId): array
    {
        $limite = $this->resolverLimite('max_palavras_chave', $usuarioId);

        // NULL = ilimitado: sempre pode.
        if ($limite === null) {
            return [
                'pode' => true,
                'motivo' => 'ilimitado',
                'restantes' => null,
                'limite' => null,
            ];
        }

        $usadas = (int) $this->palavras->totalAtivasDoUsuario($usuarioId);
        $restantes = $limite - $usadas;

        if ($restantes <= 0) {
            return [
                'pode' => false,
                'motivo' => 'limite_atingido',
                'restantes' => 0,
                'limite' => $limite,
            ];
        }

        return [
            'pode' => true,
            'motivo' => 'disponivel',
            'restantes' => $restantes,
            'limite' => $limite,
        ];
    }

    /**
     * O usuario pode cadastrar mais uma cidade nesta palavra-chave?
     *
     * @return array{pode: bool, motivo: string, restantes: int|null, limite: int|null}
     */
    public function podeAdicionarCidade(int $usuarioId, int $palavraChaveId): array
    {
        $limite = $this->resolverLimite('max_cidades_por_termo', $usuarioId);

        // NULL = ilimitado: sempre pode.
        if ($limite === null) {
            return [
                'pode' => true,
                'motivo' => 'ilimitado',
                'restantes' => null,
                'limite' => null,
            ];
        }

        // Quantas cidades a palavra-chave ja tem.
        $cidades = $this->palavras->find($palavraChaveId);

        if ($cidades === null) {
            return [
                'pode' => false,
                'motivo' => 'palavra_inexistente',
                'restantes' => 0,
                'limite' => $limite,
            ];
        }

        $lista = json_decode(texto_ou($cidades, 'cidades', '[]'), true);

        if (! is_array($lista)) {
            $lista = [];
        }

        $usadas = count($lista);
        $restantes = $limite - $usadas;

        if ($restantes <= 0) {
            return [
                'pode' => false,
                'motivo' => 'limite_atingido',
                'restantes' => 0,
                'limite' => $limite,
            ];
        }

        return [
            'pode' => true,
            'motivo' => 'disponivel',
            'restantes' => $restantes,
            'limite' => $limite,
        ];
    }

    /**
     * O usuario pode enviar mais um e-mail hoje?
     *
     * @return array{pode: bool, motivo: string, restantes: int, limite: int}
     */
    public function podeEnviarEmailHoje(int $usuarioId): array
    {
        $limite = (int) $this->resolverLimite('limite_envios_dia', $usuarioId);

        if ($limite <= 0) {
            $limite = self::PADROES['limite_envios_dia'];
        }

        $configuracao = $this->configuracoes->obterOuPadroes($usuarioId);
        $usadas = (int) valor_ou($configuracao, 'envios_hoje', 0);
        $restantes = $limite - $usadas;

        if ($restantes <= 0) {
            return [
                'pode' => false,
                'motivo' => 'limite_atingido',
                'restantes' => 0,
                'limite' => $limite,
            ];
        }

        return [
            'pode' => true,
            'motivo' => 'disponivel',
            'restantes' => $restantes,
            'limite' => $limite,
        ];
    }

    /**
     * O plano do usuario permite o envio automatico?
     */
    public function podeUsarEnvioAutomatico(int $usuarioId): bool
    {
        $plano = $this->obterPlanoDoUsuario($usuarioId);

        return (int) valor_ou($plano, 'envio_automatico', 0) === 1;
    }

    /**
     * O plano do usuario permite o envio em massa?
     */
    public function podeUsarEnvioEmMassa(int $usuarioId): bool
    {
        $plano = $this->obterPlanoDoUsuario($usuarioId);

        return (int) valor_ou($plano, 'envio_em_massa', 0) === 1;
    }

    /**
     * A conexao com o banco (usada nas consultas de planos).
     *
     * @return \CodeIgniter\Database\ConnectionInterface
     */
    private function db()
    {
        return db_connect();
    }
}
