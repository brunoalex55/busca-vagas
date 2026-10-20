<?php

namespace App\Libraries;

use App\Contracts\EnviadorEmailInterface;
use CodeIgniter\Email\Email as ServicoEmail;
use Config\Email as ConfiguracaoEmail;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: ENVIADOR DE E-MAIL DO SISTEMA
 * --------------------------------------------------------------------------
 *
 * Envia UM e-mail transacional (sem anexo e sem vaga envolvida):
 * boas-vindas, liberacao de conta, resposta de contato e afins.
 *
 * Diferencas em relacao ao EnviadorEmail (candidaturas):
 *   - nao anexa curriculo;
 *   - nao renderiza variaveis de vaga;
 *   - aceita um Reply-To opcional (resposta de contato);
 *   - recebe a credencial pronta, resolvida pelo RemetenteSistema.
 *
 * SEGURANCA:
 *   - a APP PASSWORD e descriptografada somente aqui, no instante do envio;
 *   - ela nunca e gravada, logada ou devolvida em mensagens de erro;
 *   - o remetente e sempre a configuracao pessoal de um administrador.
 *
 * O retorno e sempre um array com `status` e `mensagem`, usando os mesmos
 * codigos do EnviadorEmailInterface para manter o painel de logs coerente.
 */
class EnviadorSistema
{
    /** Parametros tecnicos do servidor de e-mail. */
    private ConfiguracaoEmail $config;

    /**
     * @param ConfiguracaoEmail|null $config Configuracao injetavel (testes).
     */
    public function __construct(?ConfiguracaoEmail $config = null)
    {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(ConfiguracaoEmail::class);
        }

        $this->config = $config;
    }

    /**
     * Envia uma mensagem transacional em texto simples.
     *
     * @param array{remetente: string, nome: string, app_password_criptografado: string} $remetente
     * @param string                                                                    $destinatario
     * @param string                                                                    $assunto
     * @param string                                                                    $corpo
     * @param string|null                                                               $responderPara Reply-To opcional.
     *
     * @return array{status: string, mensagem: string}
     */
    public function enviar(
        array $remetente,
        string $destinatario,
        string $assunto,
        string $corpo,
        ?string $responderPara = null
    ): array {
        // Destinatario precisa ser um e-mail valido.
        $destinatario = strtolower(trim($destinatario));

        if (
            $destinatario === ''
            || mb_strlen($destinatario) > 180
            || ! filter_var($destinatario, FILTER_VALIDATE_EMAIL)
        ) {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_DESTINATARIO,
                'O destinatario nao tem um e-mail valido.'
            );
        }

        // Remetente da mensagem (conta do administrador).
        $emailRemetente = strtolower(trim(texto_ou($remetente, 'remetente')));

        if ($emailRemetente === '' || ! filter_var($emailRemetente, FILTER_VALIDATE_EMAIL)) {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_CREDENCIAL,
                'O e-mail remetente do sistema nao foi configurado corretamente.'
            );
        }

        // APP PASSWORD cifrada no banco.
        $cifrada = trim(texto_ou($remetente, 'app_password_criptografado'));

        if ($cifrada === '') {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_CREDENCIAL,
                'A APP PASSWORD do remetente do sistema nao foi cadastrada.'
            );
        }

        // Descriptografa apenas neste momento.
        try {
            $senha = (new Criptografia())->descriptografar($cifrada);
        } catch (Throwable $e) {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_CREDENCIAL,
                'Nao foi possivel ler a APP PASSWORD do sistema. Verifique a chave do arquivo .env.'
            );
        }

        if (! is_string($senha) || $senha === '') {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_CREDENCIAL,
                'A APP PASSWORD do sistema esta ilegivel.'
            );
        }

        // Assunto e corpo dentro dos limites seguros.
        $assunto = mb_substr(trim($assunto), 0, 255);

        if ($assunto === '') {
            $assunto = 'Mensagem do sistema';
        }

        // Nome amigavel do remetente (cai para o proprio e-mail).
        $nome = trim(texto_ou($remetente, 'nome'));

        // Sem nome cadastrado: usa o e-mail do remetente.
        if ($nome === '') {
            $nome = $emailRemetente;
        }

        // Copia a configuracao global para nao contaminar outras instancias.
        $configEmail            = clone $this->config;
        $configEmail->protocol  = 'smtp';
        $configEmail->fromEmail = $emailRemetente;
        $configEmail->fromName  = $nome;
        $configEmail->SMTPUser  = $emailRemetente;
        $configEmail->SMTPPass  = $senha;
        $configEmail->mailType  = 'text';

        // Monta o servico de e-mail com as credenciais do administrador.
        try {
            $email = new ServicoEmail($configEmail);
        } catch (Throwable $e) {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_SMTP,
                'Nao foi possivel iniciar o envio pelo servidor de e-mail.'
            );
        }

        // Mensagem.
        $email->setFrom($emailRemetente, $nome);
        $email->setTo($destinatario);
        $email->setSubject($assunto);
        $email->setMessage($corpo);

        // Resposta de contato: o visitante responde direto para a caixa certa.
        if ($responderPara !== null && filter_var($responderPara, FILTER_VALIDATE_EMAIL) !== false) {
            $email->setReplyTo($responderPara, $nome);
        }

        // Dispara o envio. Nenhuma credencial entra na mensagem de retorno.
        try {
            $enviado = $email->send();
        } catch (Throwable $e) {
            $enviado = false;
        }

        // Libera a mensagem da memoria.
        $email->clear(true);

        if (! $enviado) {
            return $this->retorno(
                EnviadorEmailInterface::STATUS_ERRO_SMTP,
                'O servidor de e-mail recusou o envio. Verifique o e-mail remetente e a APP PASSWORD.'
            );
        }

        return $this->retorno(EnviadorEmailInterface::STATUS_ENVIADO, '');
    }

    /**
     * Retorno padronizado do envio.
     *
     * @return array{status: string, mensagem: string}
     */
    private function retorno(string $status, string $mensagem): array
    {
        return [
            'status'   => $status,
            'mensagem' => $mensagem,
        ];
    }
}
