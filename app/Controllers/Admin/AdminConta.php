<?php

namespace App\Controllers\Admin;

use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - MINHA CONTA
 * --------------------------------------------------------------------------
 *
 * O administrador cuida da propria conta: nome, e-mail e senha de acesso.
 *
 * Nenhuma senha volta para a view ou para o old input.
 */
class AdminConta extends AdminBase
{
    /** Model de usuarios. */
    private UsuarioModel $usuarios;

    /**
     * Carrega o model.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->usuarios = model(UsuarioModel::class);
    }

    /**
     * Tela com os formularios de perfil e de senha.
     */
    public function index(): string
    {
        // Dados da conta logada.
        $usuario = $this->usuarios->find($this->adminId);

        // Conta nao encontrada: devolve um array vazio.
        if ($usuario === null) {
            $usuario = [];
        }

        return view('admin/conta', $this->dadosAdmin('Minha conta', [
            'usuario' => $usuario,
        ]));
    }

    /**
     * Salva nome e e-mail do administrador.
     */
    public function salvarPerfil(): RedirectResponse
    {
        // Dados informados.
        $nome  = trim((string) $this->request->getPost('nome'));
        $email = strtolower(trim((string) $this->request->getPost('email')));

        // Validacoes.
        $erros = [];

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
            $erros['nome'] = 'O nome deve ter entre 3 e 150 caracteres.';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $erros['email'] = 'Informe um e-mail valido.';
        } elseif ($this->emailEmUso($email)) {
            $erros['email'] = 'Este e-mail ja esta em uso por outra conta.';
        }

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Atualiza a conta sem disparar as regras completas do model.
        $this->usuarios->skipValidation(true)->update($this->adminId, [
            'nome'  => $nome,
            'email' => $email,
        ]);

        // Mantem a sessao sincronizada.
        $this->sessao->set('nome', $nome);
        $this->sessao->set('email', $email);

        return redirect()
            ->to(site_url('admin/conta'))
            ->with('sucesso', 'Perfil atualizado.');
    }

    /**
     * Troca a senha de acesso do administrador.
     */
    public function alterarSenha(): RedirectResponse
    {
        // Campos informados.
        $atual       = (string) $this->request->getPost('senha_atual');
        $nova        = (string) $this->request->getPost('nova_senha');
        $confirmacao = (string) $this->request->getPost('senha_confirmacao');

        // Validacoes.
        $erros = [];

        if ($atual === '') {
            $erros['senha_atual'] = 'Informe a senha atual.';
        }

        if (mb_strlen($nova) < 6) {
            $erros['nova_senha'] = 'A nova senha deve ter pelo menos 6 caracteres.';
        }

        if ($nova !== $confirmacao) {
            $erros['senha_confirmacao'] = 'A confirmacao nao confere com a nova senha.';
        }

        // Confere a senha atual quando os campos basicos estao ok.
        $usuario = $this->usuarios->find($this->adminId);

        // Conta nao encontrada: devolve um array vazio.
        if ($usuario === null) {
            $usuario = [];
        }

        if ($erros === [] && ! password_verify($atual, texto_ou($usuario, 'senha'))) {
            $erros['senha_atual'] = 'Senha atual incorreta.';
        }

        if ($erros !== []) {
            // Senhas nunca voltam para o formulario.
            return $this->voltarComErros($erros, ['senha_atual', 'nova_senha', 'senha_confirmacao']);
        }

        // Grava o novo hash bcrypt.
        $this->usuarios->skipValidation(true)->update($this->adminId, [
            'senha' => password_hash($nova, PASSWORD_BCRYPT),
        ]);

        // Troca o ID da sessao por seguranca.
        $this->sessao->regenerate(true);

        return redirect()
            ->to(site_url('admin/conta'))
            ->with('sucesso', 'Senha alterada com sucesso.');
    }

    /**
     * Informa se o e-mail ja pertence a outra conta.
     */
    private function emailEmUso(string $email): bool
    {
        return $this->usuarios
            ->where('email', $email)
            ->where('id !=', $this->adminId)
            ->countAllResults() > 0;
    }
}
