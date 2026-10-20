<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Curriculo;
use RuntimeException;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: ARMAZENAMENTO DE CURRICULOS
 * --------------------------------------------------------------------------
 *
 * Centraliza o upload, a validacao, a leitura e a remocao do curriculo
 * em PDF de cada usuario.
 *
 * Seguranca:
 *   - valida tamanho, extensao, MIME real (finfo) e assinatura binaria;
 *   - grava em writable/uploads/curriculos, fora de public/;
 *   - usa nome aleatorio (nunca o nome enviado pelo navegador);
 *   - impede path traversal na leitura e na remocao.
 */
class ArmazenamentoCurriculo
{
    /**
     * Configuracao de curriculos.
     */
    private Curriculo $config;

    /**
     * Recebe a configuracao (ou usa a padrao do projeto).
     */
    public function __construct(?Curriculo $config = null)
    {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(Curriculo::class);
        }

        // Guarda a configuracao global.
        $this->config = $config;
    }

    /**
     * Valida e salva o PDF enviado, devolvendo o caminho relativo.
     *
     * O caminho relativo tem o formato "{usuario_id}/{nome_aleatorio}.pdf".
     *
     * @throws RuntimeException Quando o arquivo nao passa na validacao.
     */
    public function salvar(int $usuarioId, UploadedFile $arquivo): string
    {
        // O PHP precisa ter recebido o arquivo sem erro.
        if (! $arquivo->isValid() || $arquivo->hasMoved()) {
            throw new RuntimeException('Nao foi possivel receber o arquivo. Tente novamente.');
        }

        // Limite de tamanho definido na configuracao.
        if ($arquivo->getSize() > $this->config->tamanhoMaximo) {
            throw new RuntimeException(
                'O curriculo deve ter no maximo ' . formatar_tamanho($this->config->tamanhoMaximo) . '.'
            );
        }

        // Extensao informada pelo navegador (apenas uma primeira barreira).
        if (strtolower((string) $arquivo->getExtension()) !== $this->config->extensao) {
            throw new RuntimeException('Envie o curriculo em formato PDF.');
        }

        // MIME real do conteudo, detectado pelo finfo.
        if ($arquivo->getMimeType() !== $this->config->mimePermitido) {
            throw new RuntimeException('O arquivo enviado nao e um PDF valido.');
        }

        // Assinatura binaria: todo PDF comeca com "%PDF-".
        $ponteiro = fopen($arquivo->getTempName(), 'rb');
        $inicio   = '';

        if ($ponteiro !== false) {
            $inicio = (string) fread($ponteiro, strlen($this->config->assinaturaPdf));
        }

        if ($ponteiro !== false) {
            fclose($ponteiro);
        }

        if ($inicio !== $this->config->assinaturaPdf) {
            throw new RuntimeException('O arquivo enviado nao e um PDF valido.');
        }

        // Diretorio individual do usuario.
        $diretorio = $this->config->diretorio . DIRECTORY_SEPARATOR . $usuarioId;

        // Cria o diretorio (e os pais) quando ainda nao existe.
        if (! is_dir($diretorio) && ! mkdir($diretorio, 0755, true) && ! is_dir($diretorio)) {
            throw new RuntimeException('Nao foi possivel preparar o armazenamento do curriculo.');
        }

        // Nome aleatorio, sempre com a extensao .pdf.
        $nome = pathinfo($arquivo->getRandomName(), PATHINFO_FILENAME) . '.' . $this->config->extensao;

        // Move o arquivo temporario para o destino definitivo.
        try {
            $arquivo->move($diretorio, $nome);
        } catch (\Throwable $e) {
            throw new RuntimeException('Nao foi possivel salvar o curriculo. Tente novamente.');
        }

        // Caminho relativo salvo no banco (sempre com barra normal).
        return $usuarioId . '/' . $nome;
    }

    /**
     * Remove um curriculo salvo, com protecao contra path traversal.
     */
    public function remover(?string $caminhoRelativo): void
    {
        // Resolve o caminho absoluto com seguranca.
        $absoluto = $this->caminhoAbsoluto($caminhoRelativo);

        // Arquivo inexistente: nada a fazer.
        if ($absoluto === null) {
            return;
        }

        // Remove o arquivo.
        @unlink($absoluto);

        // Remove o diretorio do usuario se ele ficou vazio.
        $diretorio = dirname($absoluto);
        $base      = realpath($this->config->diretorio);

        if ($base !== false && $diretorio !== $base && is_dir($diretorio)) {
            // rmdir so funciona quando o diretorio esta vazio.
            @rmdir($diretorio);
        }
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
        $alvo = realpath($base . DIRECTORY_SEPARATOR . ltrim($caminhoRelativo, '/\\'));

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
     * Informa se o curriculo salvo existe no disco.
     */
    public function existe(?string $caminhoRelativo): bool
    {
        return $this->caminhoAbsoluto($caminhoRelativo) !== null;
    }

    /**
     * Devolve o tamanho do arquivo em bytes (0 quando nao existe).
     */
    public function tamanho(?string $caminhoRelativo): int
    {
        // Resolve o arquivo.
        $absoluto = $this->caminhoAbsoluto($caminhoRelativo);

        // Arquivo inexistente.
        if ($absoluto === null) {
            return 0;
        }

        // filesize devolve false em caso de falha.
        $tamanho = filesize($absoluto);

        if ($tamanho === false) {
            return 0;
        }

        return (int) $tamanho;
    }

    /**
     * Monta um nome amigavel para o download.
     */
    public function nomeDownload(string $nomeUsuario): string
    {
        // Remove acentos e caracteres especiais do nome.
        $slug = url_title($nomeUsuario, '-', true);

        // Fallback quando o nome nao gera slug.
        if ($slug === '') {
            $slug = 'curriculo';
        }

        return 'curriculo-' . $slug . '.pdf';
    }
}
