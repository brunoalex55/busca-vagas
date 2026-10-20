<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Controllers;

use App\Libraries\Criptografia;
use App\Libraries\JanelaEnvio;
use App\Libraries\ResolvedorPlano;
use App\Models\EmailEnviadoModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: CONFIGURACOES DO USUARIO
 * --------------------------------------------------------------------------
 *
 * Reune tudo que o usuario pode ajustar:
 *   - perfil (nome e e-mail);
 *   - senha de acesso;
 *   - e-mail remetente e APP PASSWORD (cifrada);
 *   - assunto/corpo padrao e assinatura;
 *   - curriculo em PDF (fora de public/);
 *   - limites, intervalos, horarios e dias de envio.
 *
 * Nenhuma senha ou APP PASSWORD volta para a view ou para o old input.
 */
class Configuracoes extends PainelBase
{
    /**
     * Tela unica com todas as secoes de configuracao.
     */
    public function index(): string
    {
        // Configuracao completa (checklist precisa do valor cifrado).
        $configuracao = $this->configuracaoDoUsuario();

        // Dados da conta.
        $usuario = $this->usuarioLogado();

        // Model de envios para contabilizar o dia.
        $emails = model(EmailEnviadoModel::class);

        // Envios concluidos hoje.
        $enviosHoje = $emails->contarHoje($this->usuarioId);

        // Limite diario configurado.
        $limiteDiario = max(1, inteiro_ou($configuracao, 'limite_envios_dia', 20));

        return view('painel/configuracoes', $this->dadosPainel('Configuracoes', [
            'configuracao' => $this->sanitizarConfiguracao($configuracao),
            'usuario'      => $usuario,
            'enviosHoje'   => $enviosHoje,
            'limiteDiario' => $limiteDiario,
            'janela'       => (new JanelaEnvio())->avaliar($configuracao),
            'verificacoes' => $this->verificacoesEnvio($configuracao),
            'fusoHorarios' => $this->fusosDisponiveis(),
        ]));
    }

    /**
     * Salva nome e e-mail do perfil.
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
        $this->usuarios->skipValidation(true)->update($this->usuarioId, [
            'nome'  => $nome,
            'email' => $email,
        ]);

        // Mantem o nome da sessao sincronizado.
        $this->sessao->set('nome', $nome);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Perfil atualizado.');
    }

    /**
     * Troca a senha de acesso.
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
        $usuario = $this->usuarioLogado();

        if ($erros === [] && ! password_verify($atual, texto_ou($usuario, 'senha'))) {
            $erros['senha_atual'] = 'Senha atual incorreta.';
        }

        if ($erros !== []) {
            // Senhas nunca voltam para o formulario.
            return $this->voltarComErros($erros, ['senha_atual', 'nova_senha', 'senha_confirmacao']);
        }

        // Grava o novo hash bcrypt.
        $this->usuarios->skipValidation(true)->update($this->usuarioId, [
            'senha' => password_hash($nova, PASSWORD_DEFAULT),
        ]);

        // Troca o ID da sessao por seguranca.
        $this->sessao->regenerate(true);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Senha alterada com sucesso.');
    }

    /**
     * Salva e-mail remetente, nome, telefone e APP PASSWORD.
     */
    public function salvarEmail(): RedirectResponse
    {
        // Campos informados.
        $email         = strtolower(trim((string) $this->request->getPost('email_remetente')));
        $nomeRemetente = trim((string) $this->request->getPost('nome_remetente'));
        $telefone      = trim((string) $this->request->getPost('telefone'));

        // Remove os espacos que o Google costuma exibir na APP PASSWORD.
        $appPassword = (string) preg_replace('/\s+/', '', (string) $this->request->getPost('app_password'));

        // Validacoes.
        $erros = [];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $erros['email_remetente'] = 'Informe um e-mail remetente valido (Gmail).';
        }

        if ($nomeRemetente === '') {
            $erros['nome_remetente'] = 'Informe o nome que aparecera como remetente.';
        } elseif (mb_strlen($nomeRemetente) > 150) {
            $erros['nome_remetente'] = 'O nome do remetente deve ter no maximo 150 caracteres.';
        }

        if (mb_strlen($telefone) > 40) {
            $erros['telefone'] = 'O telefone deve ter no maximo 40 caracteres.';
        }

        // APP PASSWORD do Gmail: 16 caracteres alfanumericos.
        if ($appPassword !== '' && ! preg_match('/^[A-Za-z0-9]{16}$/', $appPassword)) {
            $erros['app_password'] = 'A APP PASSWORD do Gmail tem 16 letras/numeros, sem espacos.';
        }

        if ($erros !== []) {
            return $this->voltarComErros($erros, ['app_password']);
        }

        // Dados que serao salvos.
        $dados = [
            'email_remetente' => $email,
            'nome_remetente'  => $nomeRemetente,
            'telefone'        => $telefone,
        ];

        // Cifra a APP PASSWORD somente quando o usuario digitou uma nova.
        if ($appPassword !== '') {
            try {
                $dados['app_password_criptografado'] = (new Criptografia())->criptografar($appPassword);
            } catch (RuntimeException $e) {
                return $this->voltarComErros([
                    'app_password' => 'Nao foi possivel proteger a APP PASSWORD. Verifique a chave do sistema.',
                ], ['app_password']);
            }
        }

        // Cria ou atualiza a configuracao.
        $this->configuracoes->salvarPorUsuario($this->usuarioId, $dados);

        // Mensagem conforme o que foi salvo.
        $mensagem = 'Dados de e-mail atualizados.';

        if ($appPassword !== '') {
            $mensagem = 'E-mail e APP PASSWORD salvos.';
        }

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', $mensagem);
    }

    /**
     * Remove a APP PASSWORD cadastrada (mantem o e-mail).
     */
    public function removerAppPassword(): RedirectResponse
    {
        // Zera apenas o campo da APP PASSWORD.
        $this->configuracoes->salvarPorUsuario($this->usuarioId, [
            'app_password_criptografado' => null,
        ]);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'APP PASSWORD removida. O envio automatico sera interrompido.');
    }

    /**
     * Salva assunto, corpo padrao e assinatura.
     */
    public function salvarMensagem(): RedirectResponse
    {
        // Campos informados.
        $assunto    = trim((string) $this->request->getPost('assunto_padrao'));
        $corpo      = trim((string) $this->request->getPost('corpo_padrao'));
        $assinatura = trim((string) $this->request->getPost('assinatura'));

        // Validacoes.
        $erros = [];

        if ($assunto === '' || mb_strlen($assunto) > 255) {
            $erros['assunto_padrao'] = 'Informe um assunto de ate 255 caracteres.';
        }

        if (mb_strlen($corpo) < 10) {
            $erros['corpo_padrao'] = 'O corpo do e-mail deve ter pelo menos 10 caracteres.';
        }

        if (mb_strlen($assinatura) > 1000) {
            $erros['assinatura'] = 'A assinatura deve ter no maximo 1000 caracteres.';
        }

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Persiste a mensagem padrao.
        $this->configuracoes->salvarPorUsuario($this->usuarioId, [
            'assunto_padrao' => $assunto,
            'corpo_padrao'   => $corpo,
            'assinatura'     => $assinatura,
        ]);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Mensagem padrao atualizada.');
    }

    /**
     * Recebe e valida o upload do curriculo em PDF.
     */
    public function enviarCurriculo(): RedirectResponse
    {
        // Arquivo enviado.
        $arquivo = $this->request->getFile('curriculo');

        // Nenhum arquivo escolhido.
        if ($arquivo === null) {
            return redirect()
                ->to(site_url('painel/configuracoes'))
                ->with('erro', 'Selecione um arquivo PDF para enviar.');
        }

        // Caminho anterior (para remover depois do sucesso).
        $configuracao = $this->configuracaoDoUsuario();
        $anterior     = valor_ou($configuracao, 'curriculo_pdf', null);

        try {
            // Valida e grava fora de public/.
            $relativo = $this->curriculos->salvar($this->usuarioId, $arquivo);
        } catch (RuntimeException $e) {
            return redirect()
                ->to(site_url('painel/configuracoes'))
                ->with('erro', $e->getMessage());
        }

        // Atualiza o caminho no banco.
        $this->configuracoes->salvarPorUsuario($this->usuarioId, [
            'curriculo_pdf' => $relativo,
        ]);

        // O arquivo anterior NAO e apagado.
        //
        // O curriculo e o unico documento que prova a candidatura: se
        // ele sumisse, o bot pararia de candidatar sem o usuario saber
        // por que. O arquivo antigo fica no disco (a pasta e do
        // usuario) e so e removido pela acao explicita de remover o
        // curriculo.

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Curriculo enviado com sucesso.');
    }

    /**
     * Remove o curriculo atual.
     */
    public function removerCurriculo(): RedirectResponse
    {
        // Caminho salvo.
        $configuracao = $this->configuracaoDoUsuario();
        $relativo     = valor_ou($configuracao, 'curriculo_pdf', null);

        // Apaga o arquivo e limpa o campo.
        $this->curriculos->remover($relativo);
        $this->configuracoes->salvarPorUsuario($this->usuarioId, [
            'curriculo_pdf' => null,
        ]);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Curriculo removido.');
    }

    /**
     * Download autenticado do proprio curriculo.
     */
    public function baixarCurriculo(): DownloadResponse
    {
        // Caminho salvo do usuario logado.
        $configuracao = $this->configuracaoDoUsuario();
        $relativo     = valor_ou($configuracao, 'curriculo_pdf', null);

        // Resolve o caminho absoluto com protecao contra path traversal.
        $absoluto = $this->curriculos->caminhoAbsoluto($relativo);

        // Arquivo inexistente.
        if ($absoluto === null) {
            throw PageNotFoundException::forPageNotFound('Curriculo nao encontrado.');
        }

        // Nome do usuario para batizar o arquivo.
        $usuario = $this->usuarioLogado();
        $nome    = $this->curriculos->nomeDownload(texto_ou($usuario, 'nome', 'curriculo'));

        // Envia como anexo, sem expor o caminho interno.
        return $this->response
            ->download($absoluto, null)
            ->setFileName($nome);
    }

    /**
     * Salva limites, intervalos, horarios e dias de envio.
     */
    public function salvarEnvio(): RedirectResponse
    {
        // Campos informados.
        $limite  = (int) $this->request->getPost('limite_envios_dia');
        $minimo  = (int) $this->request->getPost('intervalo_minimo_segundos');
        $maximo  = (int) $this->request->getPost('intervalo_maximo_segundos');
        $inicio  = trim((string) $this->request->getPost('horario_inicio_envio'));
        $fim     = trim((string) $this->request->getPost('horario_fim_envio'));
        $fuso    = trim((string) $this->request->getPost('fuso_horario'));
        $dias    = $this->request->getPost('dias_semana');

        // O formulario avisa se o interruptor de envio automatico foi exibido.
        $campoAutomatico = $this->request->getPost('envio_automatico_enviado') === '1';
        $ligar           = $campoAutomatico && $this->request->getPost('envio_automatico') === '1';

        // O plano do usuario permite o envio automatico? (a tela esconde
        // o interruptor, mas quem decide e o servidor).
        $permitidoAutomatico = (new ResolvedorPlano())->podeUsarEnvioAutomatico($this->usuarioId);

        if ($ligar && $permitidoAutomatico === false) {
            $plano = (new ResolvedorPlano())->obterPlanoDoUsuario($this->usuarioId);

            return redirect()
                ->to(site_url('painel/configuracoes'))
                ->with('erro', 'O envio automatico esta disponivel nos planos Profissional e Empresa. Seu plano e '
                    . (string) $plano['nome'] . '.');
        }

        // Validacoes.
        $erros = [];

        if ($limite < 1 || $limite > 100) {
            $erros['limite_envios_dia'] = 'O limite diario deve ficar entre 1 e 100 envios.';
        }

        if ($minimo < 30 || $minimo > 3600) {
            $erros['intervalo_minimo_segundos'] = 'O intervalo minimo deve ficar entre 30 e 3600 segundos.';
        }

        if ($maximo < $minimo || $maximo > 3600) {
            $erros['intervalo_maximo_segundos'] = 'O intervalo maximo deve ser maior ou igual ao minimo (ate 3600).';
        }

        if (! $this->horaValida($inicio)) {
            $erros['horario_inicio_envio'] = 'Informe um horario de inicio valido.';
        }

        if (! $this->horaValida($fim)) {
            $erros['horario_fim_envio'] = 'Informe um horario de fim valido.';
        }

        // Inicio precisa ser antes do fim.
        if (! isset($erros['horario_inicio_envio'], $erros['horario_fim_envio']) && $inicio >= $fim) {
            $erros['horario_fim_envio'] = 'O horario de fim deve ser maior que o de inicio.';
        }

        // Fuso horario da lista permitida.
        $fusos = $this->fusosDisponiveis();

        if (! isset($fusos[$fuso])) {
            $erros['fuso_horario'] = 'Escolha um fuso horario valido.';
        }

        // Dias da semana (1 a 7, sem repetir).
        $diasValidos = [];

        if (is_array($dias)) {
            foreach ($dias as $dia) {
                $numero = (int) $dia;

                if ($numero >= 1 && $numero <= 7 && ! in_array($numero, $diasValidos, true)) {
                    $diasValidos[] = $numero;
                }
            }
        }

        if ($diasValidos === []) {
            $erros['dias_semana'] = 'Escolha pelo menos um dia da semana.';
        }

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        sort($diasValidos);

        // Persiste a configuracao de envio.
        $this->configuracoes->salvarPorUsuario($this->usuarioId, [
            'limite_envios_dia'         => $limite,
            'intervalo_minimo_segundos' => $minimo,
            'intervalo_maximo_segundos' => $maximo,
            'horario_inicio_envio'      => $inicio . ':00',
            'horario_fim_envio'         => $fim . ':00',
            'dias_semana_permitidos'    => implode(',', $diasValidos),
            'fuso_horario'              => $fuso,
        ]);

        // Desligar sempre e permitido.
        if (! $ligar) {
            // Sem o campo no formulario, nao mexe no estado do envio automatico.
            if (! $campoAutomatico) {
                return redirect()
                    ->to(site_url('painel/configuracoes'))
                    ->with('sucesso', 'Configuracoes de envio salvas.');
            }

            $this->usuarios->skipValidation(true)->update($this->usuarioId, [
                'envio_automatico' => 0,
            ]);

            return redirect()
                ->to(site_url('painel/configuracoes'))
                ->with('sucesso', 'Configuracoes de envio salvas. Envio automatico desligado.');
        }

        // Para ligar, todos os requisitos precisam estar prontos.
        $pendencias = $this->pendenciasParaAtivarEnvio($this->configuracaoDoUsuario());

        if ($pendencias !== []) {
            $this->usuarios->skipValidation(true)->update($this->usuarioId, [
                'envio_automatico' => 0,
            ]);

            return redirect()
                ->to(site_url('painel/configuracoes'))
                ->with('erro', 'Configuracoes salvas, mas o envio automatico continua desligado. Complete: ' . implode(', ', $pendencias) . '.');
        }

        // Tudo pronto: liga o envio automatico.
        $this->usuarios->skipValidation(true)->update($this->usuarioId, [
            'envio_automatico' => 1,
        ]);

        return redirect()
            ->to(site_url('painel/configuracoes'))
            ->with('sucesso', 'Configuracoes de envio salvas. Envio automatico ligado.');
    }

    /**
     * Informa se o e-mail ja pertence a outra conta.
     */
    private function emailEmUso(string $email): bool
    {
        return $this->usuarios
            ->where('email', $email)
            ->where('id !=', $this->usuarioId)
            ->countAllResults() > 0;
    }

    /**
     * Valida um horario no formato HH:MM.
     */
    private function horaValida(string $hora): bool
    {
        // Formato exato HH:MM.
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            return false;
        }

        return true;
    }

    /**
     * Fusos horarios aceitos na configuracao de envio.
     *
     * @return array<string, string>
     */
    private function fusosDisponiveis(): array
    {
        return [
            'America/Sao_Paulo'   => 'Brasilia (Sao Paulo)',
            'America/Bahia'       => 'Bahia (Salvador)',
            'America/Belem'       => 'Para (Belem)',
            'America/Fortaleza'   => 'Ceara (Fortaleza)',
            'America/Recife'      => 'Pernambuco (Recife)',
            'America/Manaus'      => 'Amazonas (Manaus)',
            'America/Cuiaba'      => 'Mato Grosso (Cuiaba)',
            'America/Porto_Velho' => 'Rondonia (Porto Velho)',
            'America/Rio_Branco'  => 'Acre (Rio Branco)',
            'America/Noronha'     => 'Fernando de Noronha',
        ];
    }
}
