<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: CRON (DESCONTINUADO)
 * --------------------------------------------------------------------------
 *
 * Este controller foi DESCONTINUADO. O agendamento do sistema nao acontece
 * mais por HTTP: o caminho oficial e a linha de comando.
 *
 * O QUE USAR NO LUGAR:
 *   - php spark vagas:consultar             (busca de vagas / CRON 1)
 *   - php spark emails:enviar-automaticos    (envio automatico / CRON 2)
 *   - scripts/cron-linux.sh e scripts/cron-windows.bat (modo do sistema)
 *   - POST /cron/usuario/{id}/executar-externo (modo 'spark_manual', com o
 *     token individual do usuario)
 *
 * O modo de agendamento e escolhido pelo admin no painel /admin/cron e
 * NAO altera o codigo dos comandos Spark: ele apenas decide QUEM chama.
 *
 * POR QUE O CONTROLLER CONTINUA AQUI:
 *   As rotas /cron/... foram removidas do Routes.php, entao quem ainda usar
 *   uma URL antiga recebe 404. O controller foi mantido apenas como aviso:
 *   qualquer chamada direta devolve 410 (Gone) com a orientacao do que usar
 *   no lugar. Ele nao executa mais nenhuma rotina.
 */
class Cron extends BaseController
{
    /**
     * Aviso devolvido por qualquer chamada a este controller.
     */
    public const MENSAGEM_DESCONTINUACAO = 'Este endpoint foi descontinuado. '
        . 'Use php spark vagas:consultar ou php spark emails:enviar-automaticos, '
        . 'ou configure o modo no painel admin em /admin/cron.';

    /**
     * Caminhos que substituem este endpoint.
     *
     * @var list<string>
     */
    public const ALTERNATIVAS = [
        'php spark vagas:consultar',
        'php spark emails:enviar-automaticos',
        'scripts/cron-linux.sh | scripts/cron-windows.bat',
        'POST /cron/usuario/{id}/executar-externo (token individual do usuario)',
    ];

    /**
     * CRON 1 global: descontinuado.
     */
    public function buscarVagasGlobal(): ResponseInterface
    {
        return $this->respostaDescontinuada();
    }

    /**
     * CRON 2 global: descontinuado.
     */
    public function enviarEmailsGlobal(): ResponseInterface
    {
        return $this->respostaDescontinuada();
    }

    /**
     * CRON 1 de um usuario especifico: descontinuado.
     */
    public function buscarVagasUsuario(int $usuarioId): ResponseInterface
    {
        return $this->respostaDescontinuada();
    }

    /**
     * CRON 2 de um usuario especifico: descontinuado.
     */
    public function enviarEmailsUsuario(int $usuarioId): ResponseInterface
    {
        return $this->respostaDescontinuada();
    }

    /**
     * Resposta de descontinuacao (410 Gone) com o caminho alternativo.
     */
    private function respostaDescontinuada(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(410)
            ->setJSON([
                'sucesso'      => false,
                'erro'         => 'endpoint_descontinuado',
                'mensagem'     => self::MENSAGEM_DESCONTINUACAO,
                'alternativas' => self::ALTERNATIVAS,
            ]);
    }
}
