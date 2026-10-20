<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER BASE
 * --------------------------------------------------------------------------
 *
 * Classe da qual todos os controllers do projeto herdam.
 *
 * Centraliza:
 *   - carregamento dos helpers comuns;
 *   - acesso a sessao;
 *   - dados compartilhados com as views (configuracao do site).
 */
abstract class BaseController extends Controller
{
    /**
     * Helpers carregados em todos os controllers.
     * O helper "site" traz configuracao_site(), resumir_texto() etc.
     *
     * @var list<string>
     */
    protected $helpers = ['site', 'url', 'form'];

    /**
     * Instancia da sessao nativa do CodeIgniter.
     * Nao existe token CSRF: a seguranca vem apenas desta sessao.
     */
    protected $sessao;

    /**
     * Dados comuns enviados a todas as views.
     *
     * @var array<string, mixed>
     */
    protected array $dadosComuns = [];

    /**
     * Inicializa o controller.
     *
     * @param CLIRequest|IncomingRequest $request
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Executa a inicializacao padrao do framework.
        parent::initController($request, $response, $logger);

        // Guarda a sessao para uso nos controllers filhos.
        $this->sessao = session();

        // Disponibiliza a configuracao do site em todas as views.
        $this->dadosComuns['configuracaoSite'] = configuracao_site();
    }

    /**
     * Redireciona de volta exibindo erros, sem gravar campos sensiveis.
     *
     * Usado tanto pelo painel do usuario quanto pelo painel administrativo.
     *
     * @param array<string, string> $erros           Mensagens por campo.
     * @param list<string>          $camposSensiveis Campos que nunca voltam no old input.
     */
    protected function voltarComErros(array $erros, array $camposSensiveis = []): RedirectResponse
    {
        // Copia os dados enviados.
        $post = $this->request->getPost();

        // Remove senhas e segredos do old input.
        foreach ($camposSensiveis as $campo) {
            unset($post[$campo]);
        }

        // Grava apenas os campos seguros.
        $this->sessao->setFlashdata('_ci_old_input', [
            'get'  => $this->request->getGet(),
            'post' => $post,
        ]);

        return redirect()->back()->with('erros', $erros);
    }
}
