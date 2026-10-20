<?php

namespace App\Controllers\Admin;

use App\Libraries\ArmazenamentoImagem;
use App\Models\ConfiguracaoPagamentoModel;
use App\Models\ConfiguracaoSiteModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * --------------------------------------------------------------------------
 * CONTROLLER: ADMIN - CONFIGURACAO DO SITE
 * --------------------------------------------------------------------------
 *
 * Edita a identidade do site publico (nome, SEO, contatos, redes, textos e
 * imagens) e os dados de pagamento exibidos na tela de aprovacao (PIX).
 *
 * As imagens (logo, favicon e Open Graph) sao enviadas por upload validado
 * e gravadas em public/uploads/site.
 */
class AdminConfiguracaoSite extends AdminBase
{
    /** Model da configuracao do site. */
    private ConfiguracaoSiteModel $site;

    /** Model da configuracao de pagamento. */
    private ConfiguracaoPagamentoModel $pagamento;

    /** Armazenamento validado das imagens do site. */
    private ArmazenamentoImagem $imagens;

    /**
     * Carrega os models e a biblioteca de imagens.
     */
    public function initController($request, $response, $logger)
    {
        parent::initController($request, $response, $logger);

        $this->site      = model(ConfiguracaoSiteModel::class);
        $this->pagamento = model(ConfiguracaoPagamentoModel::class);
        $this->imagens   = new ArmazenamentoImagem();
    }

    /**
     * Tela unica com as duas secoes de configuracao.
     */
    public function index(): string
    {
        return view('admin/configuracao_site', $this->dadosAdmin('Configuracao do site', [
            'site'      => $this->site->obter(),
            'pagamento' => $this->pagamento->obter(),
        ]));
    }

    /**
     * Salva os dados institucionais do site (campos + imagens).
     */
    public function salvarSite(): RedirectResponse
    {
        // Campos de texto normalizados.
        $dados = $this->dadosDoFormularioSite();

        // Validacao.
        $erros = $this->validarSite($dados);

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Configuracao atual (para trocar imagens sem perder as antigas).
        $atual = $this->site->obter();

        // Trata os tres uploads opcionais.
        foreach (['logo' => false, 'favicon' => true, 'imagem_og' => false] as $campo => $eFavicon) {
            $resultado = $this->processarImagem($campo, $eFavicon, valor_ou($atual, $campo, null));

            // Erro de validacao do arquivo.
            if (is_string($resultado) && str_starts_with($resultado, 'ERRO:')) {
                return $this->voltarComErros([$campo => substr($resultado, 5)]);
            }

            // Novo caminho salvo (ou null quando nada mudou).
            if ($resultado !== null) {
                $dados[$campo] = $resultado;
            }
        }

        // Persiste a configuracao.
        $this->site->salvar($dados);

        return redirect()
            ->to(site_url('admin/configuracao-site'))
            ->with('sucesso', 'Configuracao do site atualizada.');
    }

    /**
     * Salva a configuracao de pagamento (PIX).
     */
    public function salvarPagamento(): RedirectResponse
    {
        // Campos informados.
        $chavePix = trim((string) $this->request->getPost('chave_pix'));
        $valor    = trim((string) $this->request->getPost('valor_mensal'));
        $texto    = trim((string) $this->request->getPost('texto_instrucoes'));

        // Validacoes.
        $erros = [];

        if ($chavePix === '' || mb_strlen($chavePix) > 255) {
            $erros['chave_pix'] = 'Informe a chave PIX (ate 255 caracteres).';
        }

        // Valor no formato 0.00 (aceita virgula).
        $valorBruto = str_replace(',', '.', $valor);

        if ($valorBruto === '' || ! is_numeric($valorBruto) || (float) $valorBruto < 0) {
            $erros['valor_mensal'] = 'Informe um valor mensal valido (ex.: 49.90).';
        }

        if (mb_strlen($texto) > 2000) {
            $erros['texto_instrucoes'] = 'As instrucoes devem ter no maximo 2000 caracteres.';
        }

        if ($erros !== []) {
            return $this->voltarComErros($erros);
        }

        // Persiste.
        $this->pagamento->salvar([
            'chave_pix'        => $chavePix,
            'valor_mensal'     => number_format((float) $valorBruto, 2, '.', ''),
            'texto_instrucoes' => $texto,
        ]);

        return redirect()
            ->to(site_url('admin/configuracao-site'))
            ->with('sucesso', 'Configuracao de pagamento atualizada.');
    }

    /**
     * Remove uma das imagens do site (logo, favicon ou imagem de OG).
     */
    public function removerImagem(): RedirectResponse
    {
        // Campo alvo, restrito aos tres aceitos.
        $campo = (string) $this->request->getPost('campo');

        if (! in_array($campo, ['logo', 'favicon', 'imagem_og'], true)) {
            return redirect()
                ->to(site_url('admin/configuracao-site'))
                ->with('erro', 'Imagem invalida.');
        }

        // Configuracao atual.
        $atual = $this->site->obter();

        // Apaga o arquivo do disco (se existir) e limpa o campo.
        $this->imagens->remover(valor_ou($atual, $campo, null));
        $this->site->salvar([$campo => null]);

        return redirect()
            ->to(site_url('admin/configuracao-site'))
            ->with('sucesso', 'Imagem removida.');
    }

    // =========================================================================
    // APOIO INTERNO
    // =========================================================================

    /**
     * Le e normaliza os campos de texto do site.
     *
     * @return array<string, mixed>
     */
    private function dadosDoFormularioSite(): array
    {
        // Links de redes sociais (mesmo tratamento).
        $links = [];

        foreach (['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'github', 'site_pessoal'] as $rede) {
            $links['link_' . $rede] = trim((string) $this->request->getPost('link_' . $rede));
        }

        return [
            'nome_site'                  => trim((string) $this->request->getPost('nome_site')),
            'slogan'                     => trim((string) $this->request->getPost('slogan')),
            'descricao_site'             => trim((string) $this->request->getPost('descricao_site')),
            'palavras_chave_seo'         => trim((string) $this->request->getPost('palavras_chave_seo')),
            'email_contato'              => trim((string) $this->request->getPost('email_contato')),
            'telefone_contato'           => trim((string) $this->request->getPost('telefone_contato')),
            'whatsapp_contato'           => trim((string) $this->request->getPost('whatsapp_contato')),
            'endereco'                   => trim((string) $this->request->getPost('endereco')),
            'texto_rodape'               => trim((string) $this->request->getPost('texto_rodape')),
            'texto_sobre'                => trim((string) $this->request->getPost('texto_sobre')),
            'google_analytics_id'        => trim((string) $this->request->getPost('google_analytics_id')),
            'google_search_console_code' => trim((string) $this->request->getPost('google_search_console_code')),
        ] + $links;
    }

    /**
     * Valida os campos de texto do site.
     *
     * @param array<string, mixed> $dados
     *
     * @return array<string, string>
     */
    private function validarSite(array $dados): array
    {
        $erros = [];

        // Identidade.
        if ($dados['nome_site'] === '' || mb_strlen($dados['nome_site']) > 120) {
            $erros['nome_site'] = 'Informe o nome do site (ate 120 caracteres).';
        }

        if (mb_strlen((string) $dados['slogan']) > 200) {
            $erros['slogan'] = 'O slogan deve ter no maximo 200 caracteres.';
        }

        if (mb_strlen((string) $dados['descricao_site']) > 255) {
            $erros['descricao_site'] = 'A descricao deve ter no maximo 255 caracteres.';
        }

        if (mb_strlen((string) $dados['palavras_chave_seo']) > 500) {
            $erros['palavras_chave_seo'] = 'As palavras-chave devem ter no maximo 500 caracteres.';
        }

        // Contatos.
        if ($dados['email_contato'] !== ''
            && (! filter_var($dados['email_contato'], FILTER_VALIDATE_EMAIL) || mb_strlen($dados['email_contato']) > 180)) {
            $erros['email_contato'] = 'Informe um e-mail de contato valido.';
        }

        if (mb_strlen((string) $dados['telefone_contato']) > 40) {
            $erros['telefone_contato'] = 'O telefone deve ter no maximo 40 caracteres.';
        }

        if (mb_strlen((string) $dados['whatsapp_contato']) > 40) {
            $erros['whatsapp_contato'] = 'O WhatsApp deve ter no maximo 40 caracteres.';
        }

        if (mb_strlen((string) $dados['endereco']) > 255) {
            $erros['endereco'] = 'O endereco deve ter no maximo 255 caracteres.';
        }

        // Links: vazio e permitido; preenchido precisa ser http/https.
        foreach (['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'github', 'site_pessoal'] as $rede) {
            $campo = 'link_' . $rede;
            $valor = (string) $dados[$campo];

            if ($valor !== '' && (url_segura($valor) === null || mb_strlen($valor) > 255)) {
                $erros[$campo] = 'Informe uma URL http(s) valida (ate 255 caracteres).';
            }
        }

        // Textos e integracoes.
        if (mb_strlen((string) $dados['texto_rodape']) > 500) {
            $erros['texto_rodape'] = 'O texto do rodape deve ter no maximo 500 caracteres.';
        }

        if (mb_strlen((string) $dados['texto_sobre']) > 8000) {
            $erros['texto_sobre'] = 'O texto "Sobre" deve ter no maximo 8000 caracteres.';
        }

        if (mb_strlen((string) $dados['google_analytics_id']) > 60) {
            $erros['google_analytics_id'] = 'O ID do Analytics deve ter no maximo 60 caracteres.';
        }

        if (mb_strlen((string) $dados['google_search_console_code']) > 2000) {
            $erros['google_search_console_code'] = 'O codigo do Search Console deve ter no maximo 2000 caracteres.';
        }

        return $erros;
    }

    /**
     * Trata o upload opcional de uma imagem.
     *
     * Devolve:
     *   - null            quando nenhum arquivo foi enviado;
     *   - "ERRO:mensagem" quando o arquivo nao passa na validacao;
     *   - o caminho salvo  quando o upload deu certo (removendo o antigo).
     *
     * @param string      $campo    Nome do campo/caminho.
     * @param bool        $eFavicon Aceita ICO.
     * @param string|null $anterior Caminho atual da imagem.
     *
     * @return string|null
     */
    private function processarImagem(string $campo, bool $eFavicon, ?string $anterior): ?string
    {
        // Arquivo enviado (ou null quando o campo veio vazio).
        $arquivo = $this->request->getFile($campo);

        if ($arquivo === null || ! $arquivo->isValid() || $arquivo->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            // Valida e grava a nova imagem.
            $novo = $this->imagens->salvar($arquivo, $campo, $eFavicon);
        } catch (RuntimeException $e) {
            return 'ERRO:' . $e->getMessage();
        }

        // Remove a imagem anterior, se existia e mudou.
        if ($anterior !== null && $anterior !== $novo) {
            $this->imagens->remover($anterior);
        }

        return $novo;
    }
}
