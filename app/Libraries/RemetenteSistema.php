<?php

namespace App\Libraries;

use App\Models\ConfiguracaoModel;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: REMETENTE DO SISTEMA
 * --------------------------------------------------------------------------
 *
 * Resolve QUAL conta de administrador assina os e-mails transacionais
 * (boas-vindas, liberacao, resposta de contato etc).
 *
 * REGRA DO PROJETO: nao existe credencial global. O remetente e sempre a
 * configuracao pessoal de um administrador (tabela `configuracoes`), com a
 * APP PASSWORD cifrada. Esta classe apenas DESCOBRE essa conta e devolve a
 * credencial em memoria; quem descriptografa e envia e o EnviadorSistema.
 *
 * Ordem de escolha:
 *   1. o administrador indicado pela tela que pediu o envio;
 *   2. o primeiro administrador ativo que tenha credencial completa.
 *
 * A senha nunca sai daqui em texto claro: o valor devolvido e sempre o
 * campo cifrado.
 */
class RemetenteSistema
{
    /** Fonte das credenciais por usuario. */
    private ConfiguracaoModel $configuracoes;

    /**
     * @param ConfiguracaoModel|null $configuracoes Modelo injetavel.
     */
    public function __construct(?ConfiguracaoModel $configuracoes = null)
    {
        // Sem modelo injetado: usa o padrao do projeto.
        if ($configuracoes === null) {
            $configuracoes = model(ConfiguracaoModel::class);
        }

        $this->configuracoes = $configuracoes;
    }

    /**
     * Devolve a credencial de envio que deve assinar a mensagem.
     *
     * @param int|null $preferido Administrador preferido (quem disparou a acao).
     *
     * @return array{usuario_id: int, remetente: string, nome: string, app_password_criptografado: string}|null
     */
    public function obter(?int $preferido = null): ?array
    {
        // 1. O administrador indicado, quando tem credencial completa.
        if ($preferido !== null && $preferido > 0) {
            $credencial = $this->configuracoes->credencialDoUsuario($preferido);

            if ($credencial !== null) {
                return $this->normalizar($credencial);
            }
        }

        // 2. Qualquer administrador ativo com credencial completa.
        $linha = $this->configuracoes->primeiroAdminComCredencial();

        if ($linha === null) {
            return null;
        }

        return $this->normalizar($linha);
    }

    /**
     * Resumo seguro do remetente, para exibir na interface.
     *
     * Nunca inclui a APP PASSWORD, nem mesmo cifrada.
     *
     * @return array{disponivel: bool, remetente: ?string, nome: ?string, usuario_id: int}
     */
    public function resumo(?int $preferido = null): array
    {
        $credencial = $this->obter($preferido);

        // Sem credencial: os campos do resumo ficam vazios.
        $dados = [];

        if ($credencial !== null) {
            $dados = $credencial;
        }

        return [
            'disponivel' => $credencial !== null,
            'remetente'  => valor_ou($dados, 'remetente', null),
            'nome'       => valor_ou($dados, 'nome', null),
            'usuario_id' => valor_ou($dados, 'usuario_id', 0),
        ];
    }

    /**
     * Informa se existe remetente pronto para enviar.
     */
    public function disponivel(?int $preferido = null): bool
    {
        return $this->obter($preferido) !== null;
    }

    /**
     * Normaliza a linha vinda do banco em uma credencial tipada.
     *
     * @param array<string, mixed> $linha
     *
     * @return array{usuario_id: int, remetente: string, nome: string, app_password_criptografado: string}
     */
    private function normalizar(array $linha): array
    {
        // Nome amigavel: o configurado; sem ele, o nome da conta admin.
        $nome = trim(texto_ou($linha, 'nome_remetente'));

        if ($nome === '') {
            $nome = trim(texto_ou($linha, 'usuario_nome'));
        }

        return [
            'usuario_id'  => inteiro_ou($linha, 'usuario_id', 0),
            'remetente'   => strtolower(trim(texto_ou($linha, 'email_remetente'))),
            'nome'        => $nome,
            'app_password_criptografado' => texto_ou($linha, 'app_password_criptografado'),
        ];
    }
}
