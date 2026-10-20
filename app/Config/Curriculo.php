<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * --------------------------------------------------------------------------
 * CONFIGURACAO DE CURRICULOS
 * --------------------------------------------------------------------------
 *
 * Regras de armazenamento do curriculo em PDF enviado pelo usuario.
 *
 * O arquivo NUNCA fica em public/: e gravado em writable/uploads/curriculos,
 * fora da raiz web, e so pode ser baixado por rota autenticada.
 */
class Curriculo extends BaseConfig
{
    /**
     * Diretorio absoluto onde os PDFs sao gravados.
     * Fica fora de public/ de proposito.
     */
    public string $diretorio = WRITEPATH . 'uploads/curriculos';

    /**
     * Tamanho maximo aceito, em bytes (5 MB).
     */
    public int $tamanhoMaximo = 5 * 1024 * 1024;

    /**
     * Tipo MIME aceito (validado com finfo, nao com o que o navegador diz).
     */
    public string $mimePermitido = 'application/pdf';

    /**
     * Extensao forcada no arquivo salvo.
     */
    public string $extensao = 'pdf';

    /**
     * Assinatura binaria que todo PDF valido possui no inicio.
     * Serve para bloquear arquivos renomeados para .pdf.
     */
    public string $assinaturaPdf = '%PDF-';
}
