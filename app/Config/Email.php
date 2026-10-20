<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO DE E-MAIL
 * --------------------------------------------------------------------------
 *
 * Parametros TECNICOS do servidor Gmail usados pelo sistema ao enviar as
 * candidaturas em nome de cada usuario.
 *
 * IMPORTANTE: NAO existem credenciais globais aqui. O e-mail remetente e a
 * APP PASSWORD sao individuais: ficam na tabela `configuracoes`, com a APP
 * PASSWORD cifrada (AES-256-CBC), e sao aplicadas apenas no instante do
 * envio, pela biblioteca App\Libraries\EnviadorEmail.
 *
 * Os campos de credencial abaixo (`fromEmail`, `SMTPUser`, `SMTPPass`)
 * permanecem vazios de proposito: sao preenchidos em memoria, por usuario,
 * no momento do envio.
 *
 * Qualquer valor pode ser sobrescrito pelo .env (prefixo `email.`), sem
 * nunca colocar credenciais no arquivo.
 */
class Email extends BaseConfig
{
    /**
     * E-mail e nome do remetente. Preenchidos por usuario no envio.
     */
    public string $fromEmail  = '';
    public string $fromName   = '';

    /**
     * Destinatarios padrao (nao usados: cada envio define o seu).
     */
    public string $recipients = '';

    /**
     * The "user agent"
     */
    public string $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     * O Gmail exige SMTP autenticado.
     */
    public string $protocol = 'smtp';

    /**
     * The server path to Sendmail.
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Hostname
     */
    public string $SMTPHost = 'smtp.gmail.com';

    /**
     * Which SMTP authentication method to use: login, plain
     */
    public string $SMTPAuthMethod = 'login';

    /**
     * SMTP Username. Preenchido por usuario no envio.
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password (APP PASSWORD). Preenchido por usuario no envio.
     */
    public string $SMTPPass = '';

    /**
     * SMTP Port
     */
    public int $SMTPPort = 587;

    /**
     * SMTP Timeout (in seconds)
     */
    public int $SMTPTimeout = 30;

    /**
     * Enable persistent SMTP connections
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption.
     *
     * @var string '', 'tls' or 'ssl'. 'tls' will issue a STARTTLS command
     *             to the server. 'ssl' means implicit SSL. Connection on port
     *             465 should set this to ''.
     */
    public string $SMTPCrypto = 'tls';

    /**
     * Enable word-wrap
     */
    public bool $wordWrap = true;

    /**
     * Character count to wrap at
     */
    public int $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     */
    public string $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     */
    public bool $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     */
    public int $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     */
    public bool $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     */
    public int $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     */
    public bool $DSN = false;
}
