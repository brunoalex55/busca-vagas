<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\ImagemSite;
use RuntimeException;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: ARMAZENAMENTO DE IMAGENS DO SITE
 * --------------------------------------------------------------------------
 *
 * Centraliza o upload, a validacao, a leitura e a remocao das imagens
 * institucionais editaveis pelo administrador (logo, favicon e imagem
 * de compartilhamento).
 *
 * Seguranca:
 *   - valida tamanho, extensao, MIME real (finfo) e conteudo (getimagesize);
 *   - grava em public/uploads/site, com nome aleatorio;
 *   - nunca usa o nome enviado pelo navegador;
 *   - impede path traversal na leitura e na remocao.
 */
class ArmazenamentoImagem
{
    /** Configuracao de imagens do site. */
    private ImagemSite $config;

    /**
     * Recebe a configuracao (ou usa a padrao do projeto).
     */
    public function __construct(?ImagemSite $config = null)
    {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(ImagemSite::class);
        }

        $this->config = $config;
    }

    /**
     * Valida e salva a imagem enviada, devolvendo o caminho relativo.
     *
     * O caminho tem o formato "uploads/site/{prefixo}-{aleatorio}.{ext}",
     * pronto para ser usado com base_url().
     *
     * @param string $prefixo   Nome logico da imagem (ex.: "logo").
     * @param bool   $eFavicon  Quando verdadeiro, aceita tambem .ico.
     *
     * @throws RuntimeException Quando o arquivo nao passa na validacao.
     */
    public function salvar(UploadedFile $arquivo, string $prefixo, bool $eFavicon = false): string
    {
        // O PHP precisa ter recebido o arquivo sem erro.
        if (! $arquivo->isValid() || $arquivo->hasMoved()) {
            throw new RuntimeException('Nao foi possivel receber o arquivo. Tente novamente.');
        }

        // Limite de tamanho definido na configuracao.
        if ($arquivo->getSize() > $this->config->tamanhoMaximo) {
            throw new RuntimeException(
                'A imagem deve ter no maximo ' . formatar_tamanho($this->config->tamanhoMaximo) . '.'
            );
        }

        // Extensao informada pelo navegador (primeira barreira).
        $extensaoEnviada = strtolower((string) $arquivo->getExtension());

        if ($extensaoEnviada === 'jpeg') {
            $extensaoEnviada = 'jpg';
        }

        // Lista de extensoes aceitas para este campo.
        $permitidas = $this->config->extensoesPermitidas;

        if ($eFavicon) {
            $permitidas = array_merge($permitidas, $this->config->extensoesFavicon);
        }

        if (! in_array($extensaoEnviada, $permitidas, true)) {
            // Mensagem conforme o campo (favicon ou logo).
            $complemento = ' ou GIF.';

            if ($eFavicon) {
                $complemento = ' ou ICO.';
            }

            throw new RuntimeException('Formato de imagem nao aceito. Use PNG, JPG, WEBP' . $complemento);
        }

        // MIME real do conteudo, detectado pelo finfo.
        $mime = (string) $arquivo->getMimeType();

        // Extensao canonica derivada do MIME (nunca a do navegador).
        $extensao = valor_ou($this->config->mapaMime, $mime, null);

        if ($extensao === null) {
            throw new RuntimeException('O arquivo enviado nao e uma imagem valida.');
        }

        // ICO so e aceito no favicon.
        if ($extensao === 'ico' && ! $eFavicon) {
            throw new RuntimeException('O formato ICO so e aceito no favicon.');
        }

        // Para imagens raster, confere a assinatura e as dimensoes.
        if ($extensao !== 'ico') {
            $dados = @getimagesize($arquivo->getTempName());

            if ($dados === false || empty($dados[0]) || empty($dados[1])) {
                throw new RuntimeException('O arquivo enviado nao e uma imagem valida.');
            }

            if ($dados[0] > $this->config->dimensaoMaxima || $dados[1] > $this->config->dimensaoMaxima) {
                throw new RuntimeException('A imagem e grande demais. Envie um arquivo menor.');
            }
        }

        // Garante que o diretorio exista.
        $diretorio = $this->config->diretorio;

        if (! is_dir($diretorio) && ! mkdir($diretorio, 0755, true) && ! is_dir($diretorio)) {
            throw new RuntimeException('Nao foi possivel preparar o armazenamento de imagens.');
        }

        // Nome aleatorio e seguro.
        $nome = $prefixo . '-' . bin2hex(random_bytes(8)) . '.' . $extensao;

        // Move o arquivo temporario para o destino definitivo.
        try {
            $arquivo->move($diretorio, $nome);
        } catch (\Throwable $e) {
            throw new RuntimeException('Nao foi possivel salvar a imagem. Tente novamente.');
        }

        return $this->config->diretorioRelativo . '/' . $nome;
    }

    /**
     * Remove uma imagem salva, com protecao contra path traversal.
     */
    public function remover(?string $caminhoRelativo): void
    {
        // Resolve o caminho absoluto com seguranca.
        $absoluto = $this->caminhoAbsoluto($caminhoRelativo);

        // Arquivo inexistente: nada a fazer.
        if ($absoluto === null) {
            return;
        }

        @unlink($absoluto);
    }

    /**
     * Converte o caminho relativo em caminho absoluto valido.
     *
     * Devolve null quando o arquivo nao existe ou quando o caminho tenta
     * sair do diretorio permitido.
     */
    public function caminhoAbsoluto(?string $caminhoRelativo): ?string
    {
        // Sem caminho salvo, nao ha arquivo.
        if ($caminhoRelativo === null || trim($caminhoRelativo) === '') {
            return null;
        }

        // Raiz real do armazenamento.
        $base = realpath($this->config->diretorio);

        // Se a raiz nunca foi criada, tambem nao existe arquivo.
        if ($base === false) {
            return null;
        }

        // Resolve o caminho final (elimina "..", links etc.).
        $alvo = realpath($base . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $caminhoRelativo)));

        // Caminho inexistente.
        if ($alvo === false || ! is_file($alvo)) {
            return null;
        }

        // Precisa obrigatoriamente estar dentro da raiz.
        if (! str_starts_with($alvo, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $alvo;
    }

    /**
     * Informa se a imagem salva existe no disco.
     */
    public function existe(?string $caminhoRelativo): bool
    {
        return $this->caminhoAbsoluto($caminhoRelativo) !== null;
    }
}
