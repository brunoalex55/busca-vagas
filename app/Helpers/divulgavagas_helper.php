<?php

/**
 * --------------------------------------------------------------------------
 * HELPER: SELOS DO DIVULGA VAGAS
 * --------------------------------------------------------------------------
 *
 * Situacao da candidatura de uma vaga pelo site do Divulga Vagas (o
 * segundo provedor). E uma funcao (e nao um partial) porque a mesma
 * informacao aparece na listagem de vagas e no detalhe: assim os dois
 * lugares mostram exatamente o mesmo selo.
 */

/**
 * Devolve o HTML do selo da candidatura pelo site do Divulga Vagas.
 *
 * @param array<string, mixed> $vaga Linha da vaga (com as colunas do
 *                                   provider do segundo provedor).
 */
function selo_divulgavagas(array $vaga): string
{
    // So as vagas do segundo provedor tem selo.
    if (texto_ou($vaga, 'provedor_vaga') !== 'divulgavagas') {
        return '';
    }

    $situacao = texto_ou($vaga, 'candidatura_divulgavagas_status', 'pendente');
    $tentativas = (int) texto_ou($vaga, 'candidatura_divulgavagas_tentativas', 0);

    // Classe e rotulo de cada situacao.
    $selos = [
        'pendente'       => ['selo--divulgavagas-pendente', 'Divulga Vagas - aguardando bot'],
        'pendente_email' => ['selo--divulgavagas-espera', 'Divulga Vagas - aguardando e-mail'],
        'enviada'        => ['selo--divulgavagas-enviada', 'Divulga Vagas - enviada (site)'],
        'enviada_email'  => ['badge-duplo-envio', 'Divulga Vagas - enviada (site + e-mail)'],
        'erro'           => ['selo--divulgavagas-erro', 'Divulga Vagas - erro'],
        'cloudflare'     => ['selo--divulgavagas-cloudflare', 'Divulga Vagas - Cloudflare'],
    ];

    // Situacao desconhecida: nao mostra selo.
    if (! isset($selos[$situacao])) {
        return '';
    }

    [$classe, $rotulo] = $selos[$situacao];

    $html = '<span class="selo ' . esc($classe) . '">' . esc($rotulo) . '</span>';

    // A prova: o site confirmou o envio do curriculo.
    $confirmadoEm = texto_ou($vaga, 'candidatura_divulgavagas_confirmado_em', '');

    if ($confirmadoEm !== '' && ($situacao === 'enviada' || $situacao === 'enviada_email')) {
        $html .= '<span class="selo selo--sucesso" title="'
            . esc('O site confirmou o envio do curriculo em ' . formatar_data_hora($confirmadoEm, false))
            . '">Confirmada</span>';
    }

    // Detalhe do erro (quando houver).
    if ($situacao === 'erro' || $situacao === 'cloudflare') {
        $erroBot = trim(texto_ou($vaga, 'candidatura_divulgavagas_erro', ''));

        if ($erroBot !== '') {
            $rotuloTentativas = 'sem tentativas';

            if ($tentativas > 0) {
                $rotuloTentativas = $tentativas . ' tentativa(s)';
            }

            $html .= '<span class="selo selo--neutro" title="'
                . esc(resumir_texto($erroBot, 120)) . '">'
                . esc($rotuloTentativas)
                . '</span>';
        }
    }

    return $html;
}
