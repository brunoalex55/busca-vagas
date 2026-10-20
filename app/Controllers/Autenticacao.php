<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: AUTENTICACAO
 * --------------------------------------------------------------------------
 *
 * Login, cadastro e logout.
 *
 * SEGURANCA:
 *   - Senhas com password_hash(PASSWORD_BCRYPT) / password_verify().
 *   - Sessao nativa do CodeIgniter (session()).
 *   - session()->regenerate() apos login, evitando session fixation.
 *   - session()->destroy() no logout.
 *   - Logout apenas por POST (nunca por link GET).
 *   - Nenhum token CSRF nesta aplicacao.
 */
class Autenticacao extends BaseController
{
    /**
     * Exibe o formulario de login.
     */
    public function login()
    {
        // Se ja esta autenticado, vai direto para a area correta.
        if ($this->sessao->has('usuario_id')) {
            return $this->redirecionarPorPerfil();
        }

        return view('autenticacao/login', $this->dadosComuns + [
            'seo' => [
                'titulo'         => 'Entrar | BuscaVagas PRO',
                'descricao'      => 'Acesse sua conta e continue suas candidaturas automaticas.',
                'palavras_chave' => 'login, entrar, acessar conta',
                'tipo'           => 'website',
            ],
        ]);
    }

    /**
     * Processa o login (SOMENTE POST).
     */
    public function autenticar()
    {
        // Regras de validacao.
        $regras = [
            'email' => 'required|valid_email',
            'senha' => 'required',
        ];

        // Mensagens em portugues.
        $mensagens = [
            'email' => [
                'required'    => 'Informe seu e-mail.',
                'valid_email' => 'Informe um e-mail valido.',
            ],
            'senha' => [
                'required' => 'Informe sua senha.',
            ],
        ];

        // Validacao no servidor.
        if (! $this->validate($regras, $mensagens)) {
            return redirect()->back()
                ->withInput()
                ->with('erros', $this->validator->getErrors());
        }

        // Dados digitados.
        $email = (string) $this->request->getPost('email');
        $senha = (string) $this->request->getPost('senha');

        /** @var UsuarioModel $modelo */
        $modelo = model(UsuarioModel::class);

        // Busca o usuario pelo e-mail.
        $usuario = $modelo->buscarPorEmail($email);

        // Mensagem generica: nao revela se o e-mail existe ou nao.
        if ($usuario === null || ! password_verify($senha, $usuario['senha'])) {
            return redirect()->back()
                ->withInput()
                ->with('erro', 'E-mail ou senha invalidos.');
        }

        // Conta desativada logicamente.
        if ((int) $usuario['ativo'] !== 1) {
            return redirect()->back()
                ->withInput()
                ->with('erro', 'Esta conta esta desativada. Fale com o suporte.');
        }

        // Monta os dados da sessao exatamente como definido nas regras.
        $dadosSessao = [
            'usuario_id' => (int) $usuario['id'],
            'perfil'     => $usuario['perfil'],
            'status'     => $usuario['status'],
            'nome'       => $usuario['nome'],
        ];

        // Grava os dados na sessao.
        $this->sessao->set($dadosSessao);

        // Regenera o ID da sessao apos autenticar (session fixation).
        // O parametro TRUE apaga o arquivo da sessao antiga; sem ele o
        // ID anterior continuaria valido e poderia ser reutilizado.
        $this->sessao->regenerate(true);

        // Define a mensagem de boas-vindas.
        $this->sessao->setFlashdata('sucesso', 'Bem-vindo(a), ' . $usuario['nome'] . '!');

        // Redireciona conforme perfil e status.
        return $this->redirecionarPorPerfil();
    }

    /**
     * Exibe o formulario de cadastro.
     */
    public function registro()
    {
        // Usuario logado nao precisa ver o cadastro.
        if ($this->sessao->has('usuario_id')) {
            return $this->redirecionarPorPerfil();
        }

        return view('autenticacao/registro', $this->dadosComuns + [
            'seo' => [
                'titulo'         => 'Criar conta gratis | BuscaVagas PRO',
                'descricao'      => 'Crie sua conta gratis e comece a receber vagas e enviar candidaturas automaticamente.',
                'palavras_chave' => 'criar conta, cadastro gratis, busca de emprego',
                'tipo'           => 'website',
            ],
        ]);
    }

    /**
     * Processa o cadastro (SOMENTE POST).
     */
    public function cadastrar()
    {
        // Regras de validacao.
        $regras = [
            'nome'               => 'required|min_length[3]|max_length[150]',
            'email'              => 'required|valid_email|max_length[180]|is_unique[usuarios.email]',
            'senha'              => 'required|min_length[6]|max_length[100]',
            'senha_confirmacao'  => 'required|matches[senha]',
        ];

        // Mensagens em portugues.
        $mensagens = [
            'nome' => [
                'required'   => 'Informe seu nome.',
                'min_length' => 'O nome deve ter pelo menos 3 caracteres.',
            ],
            'email' => [
                'required'    => 'Informe seu e-mail.',
                'valid_email' => 'Informe um e-mail valido.',
                'is_unique'   => 'Este e-mail ja esta cadastrado.',
            ],
            'senha' => [
                'required'   => 'Informe uma senha.',
                'min_length' => 'A senha deve ter pelo menos 6 caracteres.',
            ],
            'senha_confirmacao' => [
                'required' => 'Confirme a senha.',
                'matches'  => 'As senhas nao conferem.',
            ],
        ];

        // Validacao no servidor.
        if (! $this->validate($regras, $mensagens)) {
            return redirect()->back()
                ->withInput()
                ->with('erros', $this->validator->getErrors());
        }

        // Dados do formulario.
        $nome  = trim((string) $this->request->getPost('nome'));
        $email = trim((string) $this->request->getPost('email'));
        $senha = (string) $this->request->getPost('senha');

        /** @var UsuarioModel $modelo */
        $modelo = model(UsuarioModel::class);

        // Grava o usuario ja com a senha em hash bcrypt.
        $id = $modelo->insert([
            'nome'                => $nome,
            'email'               => $email,
            'senha'               => password_hash($senha, PASSWORD_BCRYPT),
            'perfil'              => 'usuario',
            'status'              => 'pendente',
            'consultas_usadas_mes' => 0,
            'mes_referencia'      => date('Y-m'),
            'envio_automatico'    => 0,
            'ativo'               => 1,
        ]);

        // Se o insert falhar por qualquer motivo, volta com erro.
        if ($id === false) {
            return redirect()->back()
                ->withInput()
                ->with('erro', 'Nao foi possivel criar sua conta. Tente novamente.');
        }

        // Autentica automaticamente o recem-cadastrado.
        $this->sessao->set([
            'usuario_id' => (int) $id,
            'perfil'     => 'usuario',
            'status'     => 'pendente',
            'nome'       => $nome,
        ]);

        // Regenera o ID da sessao e apaga o arquivo da sessao anterior.
        $this->sessao->regenerate(true);

        // Usuario pendente cai na tela de aguardando aprovacao.
        return redirect()->to(site_url('aguardando'))
            ->with('sucesso', 'Conta criada! Agora siga as instrucoes para liberar o acesso.');
    }

    /**
     * Encerra a sessao (SOMENTE POST).
     */
    public function sair()
    {
        // Destroi completamente a sessao atual: apaga os dados no servidor
        // e envia ao navegador o cookie de sessao expirado.
        $this->sessao->destroy();

        // Nada e gravado depois do destroy(). Assim nenhum dado do usuario
        // (usuario_id, perfil, status, nome) sobrevive ao logout.
        return redirect()->to(site_url('/'));
    }

    /**
     * Redireciona o usuario autenticado conforme perfil e status.
     *
     * - status diferente de ativo -> /aguardando
     * - perfil admin              -> /admin
     * - perfil usuario            -> /painel
     */
    private function redirecionarPorPerfil()
    {
        // Status lido da sessao.
        $status = $this->sessao->get('status');

        // Pendente ou bloqueado vai para a tela de espera.
        if ($status !== 'ativo') {
            return redirect()->to(site_url('aguardando'));
        }

        // Admin vai para o painel administrativo.
        if ($this->sessao->get('perfil') === 'admin') {
            return redirect()->to(site_url('admin'));
        }

        // Usuario comum vai para o painel.
        return redirect()->to(site_url('painel'));
    }
}
