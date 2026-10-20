<?php

namespace App\Contracts;

/**
 * --------------------------------------------------------------------------
 * CONTRATO: ENVIADOR DE E-MAIL
 * --------------------------------------------------------------------------
 *
 * Todo envio de candidatura passa por esta interface. Ela existe pelo
 * mesmo motivo do JobsProviderInterface: o robo de envio (CRON 2) nao
 * precisa saber COMO o e-mail sai (SMTP do Gmail, servico externo,
 * ambiente de teste) e sim QUE ele saiu.
 *
 * O e-mail remetente e a APP PASSWORD sao individuais: chegam pela
 * configuracao do usuario, com a senha ja cifrada. A implementacao
 * descriptografa apenas no instante do envio e nunca devolve a senha.
 *
 * Status possiveis (um deles e sempre devolvido em `status`):
 *   - enviado            -> a mensagem saiu;
 *   - erro_destinatario  -> a vaga nao tem um e-mail valido (definitivo);
 *   - erro_credencial    -> falta o remetente ou a APP PASSWORD legivel;
 *   - erro_anexo         -> o curriculo em PDF nao foi encontrado;
 *   - erro_smtp          -> o servidor recusou/falhou (pode ser temporario).
 */
interface EnviadorEmailInterface
{
    /** A mensagem foi enviada com sucesso. */
    public const STATUS_ENVIADO = 'enviado';

    /** A vaga nao possui um e-mail de contato valido. */
    public const STATUS_ERRO_DESTINATARIO = 'erro_destinatario';

    /** Faltam credenciais ou a APP PASSWORD nao pode ser lida. */
    public const STATUS_ERRO_CREDENCIAL = 'erro_credencial';

    /** O curriculo em PDF nao existe (ou saiu do disco). */
    public const STATUS_ERRO_ANEXO = 'erro_anexo';

    /** Falha de SMTP (rede, autenticacao ou recusa do servidor). */
    public const STATUS_ERRO_SMTP = 'erro_smtp';

    /**
     * Envia a candidatura de uma vaga para o e-mail de contato dela.
     *
     * @param array<string, mixed> $configuracao Configuracao do usuario
     *                                          (remetente, APP PASSWORD
     *                                          cifrada, curriculo, textos).
     * @param array<string, mixed> $vaga         Vaga alvo da candidatura.
     *
     * @return array{status: string, mensagem: string, assunto: string}
     */
    public function enviar(array $configuracao, array $vaga): array;
}
