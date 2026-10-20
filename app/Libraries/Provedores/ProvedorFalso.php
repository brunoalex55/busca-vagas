<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries\Provedores;

use App\Contracts\JobsProviderInterface;
use App\Libraries\ExtratorEmail;
use Config\ProvedorVagas;
use DateTimeImmutable;

/**
 * --------------------------------------------------------------------------
 * PROVEDOR: FALSO (SOMENTE DESENVOLVIMENTO)
 * --------------------------------------------------------------------------
 *
 * Implementa JobsProviderInterface sem acessar a internet. Devolve vagas
 * ficticias, porem com a MESMA forma das vagas reais, para que seja
 * possivel testar todo o fluxo da busca:
 *
 *   - distribuicao de cota e rotacao de chaves SerpApi;
 *   - gravacao das vagas e deduplicacao (job_id e hash);
 *   - extracao de e-mail da descricao e dos links de candidatura;
 *   - logs de consulta e agendamento da proxima execucao;
 *   - caminhos de erro (chave invalida, cota esgotada, falha de rede).
 *
 * COMO ATIVAR
 *   No .env: provedorVagas.servico = falso
 *
 * NUNCA usar em producao: as vagas devolvidas nao existem.
 *
 * GATILHOS DE TESTE (basta conter o texto no termo da palavra-chave)
 *   erro-http      -> simula falha de rede
 *   chave-invalida -> simula credencial recusada
 *   limite         -> simula cota do provedor esgotada
 *   sem-vagas      -> simula busca sem resultados
 */
class ProvedorFalso implements JobsProviderInterface
{
    /**
     * Configuracao do provedor (mesmo contrato do provedor real).
     */
    private ProvedorVagas $config;

    /**
     * Extrator usado para montar as mesmas chaves do provedor real.
     */
    private ExtratorEmail $extrator;

    /**
     * Portais simulados, na mesma nomenclatura usada pelo painel.
     *
     * @var list<string>
     */
    private const FONTES = [
        'Indeed',
        'LinkedIn',
        'Catho',
        'Gupy',
        'Glassdoor',
    ];

    /**
     * Quantidade de vagas ficticias devolvidas por consulta.
     */
    private const VAGAS_POR_CONSULTA = 4;

    /**
     * Recebe a configuracao e o extrator (injetaveis para teste).
     */
    public function __construct(?ProvedorVagas $config = null, ?ExtratorEmail $extrator = null)
    {
        // Sem configuracao injetada: usa a padrao do projeto.
        if ($config === null) {
            $config = config(ProvedorVagas::class);
        }

        // Sem extrator injetado: cria o padrao.
        if ($extrator === null) {
            $extrator = new ExtratorEmail();
        }

        $this->config   = $config;
        $this->extrator = $extrator;
    }

    /**
     * Nome exibido em logs e diagnosticos.
     */
    public function nome(): string
    {
        return 'falso';
    }

    /**
     * Devolve vagas ficticias e deterministicas para a consulta.
     *
     * @param array<string, mixed> $consulta
     *
     * @return array<string, mixed>
     */
    public function buscar(array $consulta, string $credencial): array
    {
        // Termo informado, ja com o reforco de "remoto" quando aplicavel
        // (mesmo comportamento do provedor real).
        $termo = trim(texto_ou($consulta, 'termo'));

        if (! empty($consulta['remoto'])) {
            $sufixo = trim($this->config->sufixoRemoto);

            if ($sufixo !== '' && ! str_contains(mb_strtolower($termo), mb_strtolower($sufixo))) {
                $termo .= ' ' . $sufixo;
            }
        }

        // Cidade da busca.
        $cidade = trim(texto_ou($consulta, 'cidade'));

        // Texto usado nos gatilhos de teste.
        $gatilho = mb_strtolower($termo . '|' . $cidade);

        // Gatilhos de teste.
        if (str_contains($gatilho, 'erro-http')) {
            return $this->resultado(self::STATUS_ERRO_HTTP, 'Falha de rede simulada.', false);
        }

        if (str_contains($gatilho, 'chave-invalida')) {
            return $this->resultado(self::STATUS_CHAVE_INVALIDA, 'Credencial recusada (simulado).', false);
        }

        if (str_contains($gatilho, 'limite')) {
            return $this->resultado(self::STATUS_LIMITE_ATINGIDO, 'Cota do provedor esgotada (simulado).', false);
        }

        if (str_contains($gatilho, 'sem-vagas')) {
            return $this->resultado(self::STATUS_SEM_RESULTADOS, 'Nenhuma vaga (simulado).', true);
        }

        // Vagas ficticias, sempre as mesmas para a mesma combinacao.
        return $this->resultado(self::STATUS_SUCESSO, '', true, $this->gerarVagas($termo, $cidade), null);
    }

    /**
     * Gera a lista de vagas ficticias.
     *
     * @return list<array<string, mixed>>
     */
    private function gerarVagas(string $termo, string $cidade): array
    {
        // Localizacao exibida nos anuncios.
        $localizacao = 'Brasil';

        if ($cidade !== '') {
            $localizacao = $cidade . ', BR';
        }

        $vagas = [];

        for ($indice = 1; $indice <= self::VAGAS_POR_CONSULTA; $indice++) {
            // Fonte gira entre os portais simulados.
            $fonte = self::FONTES[($indice - 1) % count(self::FONTES)];

            // Empresa ficticia, estavel para a combinacao.
            $empresa = 'Empresa ' . mb_substr($fonte, 0, 4) . ' ' . $indice;

            // Titulo da vaga.
            $titulo = $termo . ' - Nivel ' . $indice;

            // Descricao com e-mail em texto simples (vaga 1), ofuscado
            // (vaga 2) e sem e-mail (vagas 3 e 4).
            $descricao = $this->montarDescricao($titulo, $empresa, $localizacao, $indice);

            // Link de candidatura da vaga.
            $urlVaga = 'https://' . mb_strtolower(str_replace(' ', '', $fonte)) . '.test/vaga/' . $indice;

            // A vaga 3 tem e-mail apenas no link de candidatura.
            $links = [$urlVaga];

            if ($indice === 3) {
                $links[] = 'mailto:selecao@' . mb_strtolower(str_replace(' ', '', $fonte)) . '.test';
            }

            // E-mail escolhido com o MESMO extrator usado em producao.
            $escolhido = $this->extrator->escolher(
                $this->extrator->extrairDeTexto($descricao),
                $this->extrator->extrairDeLinks($links)
            );

            // Salario e contrato alternam conforme o indice (dados ficticios).
            $salario       = '';
            $tipoContrato  = 'Tempo integral';

            if ($indice % 2 === 0) {
                $salario      = 'R$ 4.000 - R$ 6.000 por mes';
                $tipoContrato = 'CLT';
            }

            $vagas[] = [
                'job_id'             => 'falso-' . hash('sha256', mb_strtolower($termo . '|' . $cidade . '|' . $indice)),
                'titulo'             => $titulo,
                'empresa'            => $empresa,
                'localizacao'        => $localizacao,
                'fonte'              => $fonte,
                'url_original'       => $urlVaga,
                'url_candidatura'    => $urlVaga,
                'descricao'          => $descricao,
                'salario'            => $salario,
                'tipo_contrato'      => $tipoContrato,
                'publicado_em'       => (new DateTimeImmutable('now'))->modify('-' . $indice . ' days')->format('Y-m-d'),
                'via'                => 'via ' . $fonte,
                'emails_encontrados' => $escolhido['todos'],
                'email_contato'      => $escolhido['email'],
                'origem_email'       => $escolhido['origem'],
            ];
        }

        return $vagas;
    }

    /**
     * Monta o texto da descricao com casos reais de e-mail.
     */
    private function montarDescricao(string $titulo, string $empresa, string $localizacao, int $indice): string
    {
        // Texto comum a todas as vagas (com marcação HTML, como no real).
        $texto = '<p><strong>' . $titulo . '</strong> em ' . $localizacao . '.</p>'
            . '<p>A ' . $empresa . ' busca profissionais com experiencia comprovada. '
            . 'Atividades: analise, desenvolvimento e manutencao de sistemas.</p>';

        // Vaga 1: e-mail em texto simples.
        if ($indice === 1) {
            $texto .= '<p>Envie seu curriculo para recrutamento@empresa-exemplo.com.br '
                . 'com o assunto "' . $titulo . '".</p>';
        }

        // Vaga 2: e-mail ofuscado + um endereco de disparo automatico que
        // deve ser descartado pelo extrator.
        if ($indice === 2) {
            $texto .= '<p>Contato: rh (at) grupo-empresa2.com.br</p>'
                . '<p>Nao responder para noreply@grupo-empresa2.com.br</p>';
        }

        // Vagas 3 e 4: nenhum e-mail na descricao.
        if ($indice >= 3) {
            $texto .= '<p>Candidaturas pelo site do anunciante.</p>';
        }

        return $texto;
    }

    /**
     * Monta a estrutura de retorno padronizada.
     *
     * @param list<array<string, mixed>> $vagas
     *
     * @return array<string, mixed>
     */
    private function resultado(
        string $status,
        string $mensagem,
        bool $consumiu,
        array $vagas = [],
        ?string $token = null
    ): array {
        return [
            'status'               => $status,
            'mensagem'             => $mensagem,
            'consumiu'             => $consumiu,
            'vagas'                => $vagas,
            'token_proxima_pagina' => $token,
        ];
    }
}
