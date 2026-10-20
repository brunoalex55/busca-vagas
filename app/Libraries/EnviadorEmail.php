<?php

namespace App\Libraries;

use App\Contracts\EnviadorEmailInterface;
use CodeIgniter\Email\Email as ServicoEmail;
use Config\Email as ConfiguracaoEmail;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: ENVIADOR DE E-MAIL (SMTP Gmail)
 * --------------------------------------------------------------------------
 *
 * Envia UMA candidatura pela conta Gmail de um usuario.
 *
 * Responsabilidades:
 *   - validar o destinatario e o remetente;
 *   - descriptografar a APP PASSWORD somente aqui, no instante do envio;
 *   - montar assunto e corpo a partir dos textos padrao do usuario,
 *     substituindo as variaveis da vaga;
 *   - anexar o curriculo em PDF;
 *   - enviar pelo SMTP do Gmail definido em Config\Email.
 *
 * SEGURANCA:
 *   - a APP PASSWORD nunca e gravada, logada ou devolvida em mensagens;
 *   - os parametros tecnicos do servidor Gmail sao globais (Config\Email),
 *     mas as credenciais (remetente e senha) sao SEMPRE por usuario.
 *
 * O retorno e sempre um array com `status`, `mensagem` e `assunto`.
 */
class EnviadorEmail implements EnviadorEmailInterface
{
    /** Parametros tecnicos do servidor de e-mail. */
    private ConfiguracaoEmail $config;

    /** Leitura segura do curriculo em PDF. */
    private ArmazenamentoCurriculo $curriculos;

    /**
     * Dependencias injetaveis para facilitar testes.
     */
    public function __construct(
        ?ConfiguracaoEmail $config = null,
        ?ArmazenamentoCurriculo $curriculos = null
    ) {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(ConfiguracaoEmail::class);
        }

        // Sem armazenamento injetado: cria o padrao.
        if ($curriculos === null) {
            $curriculos = new ArmazenamentoCurriculo();
        }

        $this->config     = $config;
        $this->curriculos = $curriculos;
    }

    /**
     * Envia a candidatura conforme o contrato EnviadorEmailInterface.
     *
     * @param array<string, mixed> $configuracao
     * @param array<string, mixed> $vaga
     *
     * @return array{status: string, mensagem: string, assunto: string}
     */
    public function enviar(array $configuracao, array $vaga): array
    {
        // Destinatario: e-mail de contato encontrado na vaga.
        $destinatario = trim(texto_ou($vaga, 'email_contato'));

        if (
            $destinatario === ''
            || mb_strlen($destinatario) > 180
            || ! filter_var($destinatario, FILTER_VALIDATE_EMAIL)
        ) {
            return $this->retorno(
                self::STATUS_ERRO_DESTINATARIO,
                'A vaga nao tem um e-mail de contato valido.'
            );
        }

        // Remetente: conta Gmail informada pelo usuario.
        $remetente = strtolower(trim(texto_ou($configuracao, 'email_remetente')));

        if ($remetente === '' || ! filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
            return $this->retorno(
                self::STATUS_ERRO_CREDENCIAL,
                'O e-mail remetente nao foi configurado corretamente.'
            );
        }

        // APP PASSWORD cifrada no banco.
        $cifrada = trim(texto_ou($configuracao, 'app_password_criptografado'));

        if ($cifrada === '') {
            return $this->retorno(
                self::STATUS_ERRO_CREDENCIAL,
                'A APP PASSWORD do Gmail nao foi cadastrada.'
            );
        }

        // Descriptografa apenas neste momento.
        try {
            $senha = (new Criptografia())->descriptografar($cifrada);
        } catch (Throwable $e) {
            return $this->retorno(
                self::STATUS_ERRO_CREDENCIAL,
                'Nao foi possivel ler a APP PASSWORD. Verifique a chave do sistema.'
            );
        }

        if (! is_string($senha) || $senha === '') {
            return $this->retorno(
                self::STATUS_ERRO_CREDENCIAL,
                'A APP PASSWORD cadastrada esta ilegivel.'
            );
        }

        // Curriculo em PDF (caminho absoluto validado).
        $arquivoPdf = $this->curriculos->caminhoAbsoluto(valor_ou($configuracao, 'curriculo_pdf', null));

        if ($arquivoPdf === null) {
            return $this->retorno(
                self::STATUS_ERRO_ANEXO,
                'O curriculo em PDF nao foi encontrado no armazenamento.'
            );
        }

        // Nome amigavel do remetente (cai para o proprio e-mail).
        $nome = trim(texto_ou($configuracao, 'nome_remetente'));

        // Sem nome cadastrado: usa o e-mail do remetente.
        if ($nome === '') {
            $nome = $remetente;
        }

        // Assunto renderizado (nunca vazio e dentro do limite da coluna).
        $assunto = trim($this->renderizar(
            texto_ou($configuracao, 'assunto_padrao'),
            $configuracao,
            $vaga
        ));

        if ($assunto === '') {
            $assunto = 'Candidatura';
        }

        $assunto = mb_substr($assunto, 0, 255);

        // Corpo renderizado + assinatura opcional.
        $corpo = $this->renderizar(
            texto_ou($configuracao, 'corpo_padrao'),
            $configuracao,
            $vaga
        );

        $assinatura = trim(texto_ou($configuracao, 'assinatura'));

        if ($assinatura !== '') {
            $corpo = rtrim($corpo) . "\n\n" . $assinatura;
        }

        // Copia a configuracao global para nao contaminar outras instancias.
        $configEmail             = clone $this->config;
        $configEmail->protocol   = 'smtp';
        $configEmail->fromEmail  = $remetente;
        $configEmail->fromName   = $nome;
        $configEmail->SMTPUser   = $remetente;
        $configEmail->SMTPPass   = $senha;
        $configEmail->mailType   = 'text';

        // Monta o servico de e-mail com as credenciais do usuario.
        try {
            $email = new ServicoEmail($configEmail);
        } catch (Throwable $e) {
            return $this->retorno(
                self::STATUS_ERRO_SMTP,
                'Nao foi possivel iniciar o envio pela conta Gmail.',
                $assunto
            );
        }

        // Mensagem.
        $email->setFrom($remetente, $nome);
        $email->setTo($destinatario);
        $email->setSubject($assunto);
        $email->setMessage($corpo);

        // Curriculo como anexo, com nome amigavel.
        $email->attach($arquivoPdf, 'attachment', $this->curriculos->nomeDownload($nome));

        // Dispara o envio. Nenhuma credencial entra na mensagem de retorno.
        try {
            $enviado = $email->send();
        } catch (Throwable $e) {
            $enviado = false;
        }

        // Libera anexos/mensagem da memoria.
        $email->clear(true);

        if (! $enviado) {
            return $this->retorno(
                self::STATUS_ERRO_SMTP,
                'Nao foi possivel enviar pela conta Gmail. '
                . 'Verifique o e-mail remetente e a APP PASSWORD.',
                $assunto
            );
        }

        return $this->retorno(self::STATUS_ENVIADO, '', $assunto);
    }

    /**
     * Substitui as variaveis dos textos padrao pelos dados da vaga.
     *
     * @param array<string, mixed> $configuracao
     * @param array<string, mixed> $vaga
     */
    private function renderizar(string $texto, array $configuracao, array $vaga): string
    {
        // Mapa de variaveis aceitas nos textos do usuario.
        $variaveis = [
            '{titulo_vaga}' => texto_ou($vaga, 'titulo'),
            '{empresa}'     => texto_ou($vaga, 'empresa'),
            '{localizacao}' => texto_ou($vaga, 'localizacao'),
            '{fonte}'       => texto_ou($vaga, 'fonte'),
            '{url_vaga}'    => texto_ou($vaga, 'url_original'),
            '{seu_nome}'    => texto_ou($configuracao, 'nome_remetente'),
        ];

        return strtr($texto, $variaveis);
    }

    /**
     * Retorno padronizado do envio.
     *
     * @return array{status: string, mensagem: string, assunto: string}
     */
    private function retorno(string $status, string $mensagem, string $assunto = ''): array
    {
        return [
            'status'   => $status,
            'mensagem' => $mensagem,
            'assunto'  => $assunto,
        ];
    }
}
