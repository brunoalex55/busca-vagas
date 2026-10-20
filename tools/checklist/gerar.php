<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


/**
 * Renderiza o checklist final do projeto (docs/checklist-final.md).
 *
 * Junta as tres partes verificadas e monta o markdown.
 */

// Raiz do projeto: tools/checklist -> tools -> raiz.
$raiz = dirname(__DIR__, 2);

// Pasta dos arquivos intermediarios (fora do versionamento).
$pastaTrabalho = $raiz . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'checklist';

$itens = [];

foreach (['parte1.json', 'parte2.json', 'parte3.json'] as $arquivo) {
    $caminhoJson = $pastaTrabalho . DIRECTORY_SEPARATOR . $arquivo;

    if (! is_file($caminhoJson)) {
        throw new RuntimeException('Falta o arquivo ' . $caminhoJson . '. Rode antes: php tools/checklist/verificar.php');
    }

    $parte = json_decode((string) file_get_contents($caminhoJson), true);

    if (! is_array($parte)) {
        throw new RuntimeException('Nao consegui ler ' . $arquivo);
    }

    foreach ($parte as $item) {
        $itens[] = $item;
    }
}

/**
 * Texto de evidencia de um item.
 *
 * Nos itens pendentes, deixa claro que a feature nao existe.
 */
function evidenciaDe(array $item): string
{
    $evidencia = (string) $item['evidencia'];

    if ($item['ok'] === false && stripos($evidencia, 'NAO IMPLEMENTADO') === false) {
        $evidencia = 'NAO IMPLEMENTADO: ' . $evidencia;
    }

    return $evidencia;
}

// Garante a ordem do plano: a ordem de descoberta.
$ordem = [
    'Stack e Arquitetura',
    'Seguranca',
    'Limites de Envio',
    'Site Publico',
    'Configuracao do Site (Admin)',
    'Vagas e Priorizacao',
    'Painel do Usuario',
    'Distribuicao de Consultas',
    'Adaptador de Provedor',
    'Extracao de Email',
    'Exibicao no Painel',
    'Envio de Email',
    'Cron e Agendamento (Bloco A)',
    'Cron-job.org (Bloco D)',
    'Execucao Manual pelo Usuario (Bloco E)',
    'Painel Admin',
    'Banco de Dados',
    'Qualidade do Codigo (Bloco C)',
    'Documentacao',
];

$porSecao = [];

foreach ($itens as $item) {
    $porSecao[$item['secao']][] = $item;
}

$total = count($itens);
$verificados = 0;
$pendentes = 0;

foreach ($itens as $item) {
    if ($item['ok']) {
        $verificados++;
    } else {
        $pendentes++;
    }
}

$data = date('d/m/Y');

$linhas = [];

$linhas[] = '# Checklist Final do Projeto';
$linhas[] = '';
$linhas[] = '> Verificacao feita em **' . $data . '**, item por item, contra o codigo-fonte,';
$linhas[] = '> as rotas, as views e o banco de dados em execucao.';
$linhas[] = '>';
$linhas[] = '> **Resultado: ' . $verificados . ' de ' . $total . ' itens verificados.**';
$linhas[] = '> Os ' . $pendentes . ' itens pendentes estao listados no fim do documento,';
$linhas[] = '> com o motivo e o que falta para cada um.';
$linhas[] = '';
$linhas[] = '## Como ler';
$linhas[] = '';
$linhas[] = '- `[x]` o item foi **verificado no codigo**; a evidencia vem entre parenteses.';
$linhas[] = '- `[ ]` o item **nao esta implementado** (ou ficou diferente do plano);';
$linhas[] = '  a justificativa vem entre parenteses.';
$linhas[] = '';
$linhas[] = '## Resumo por secao';
$linhas[] = '';
$linhas[] = '| Secao | Itens | Verificados | Pendentes |';
$linhas[] = '|---|---:|---:|---:|';

foreach ($ordem as $secao) {
    if (! isset($porSecao[$secao])) {
        continue;
    }

    $lista = $porSecao[$secao];
    $ok = 0;

    foreach ($lista as $item) {
        if ($item['ok']) {
            $ok++;
        }
    }

    $linhas[] = '| ' . $secao . ' | ' . count($lista) . ' | ' . $ok . ' | ' . (count($lista) - $ok) . ' |';
}

$linhas[] = '| **Total** | **' . $total . '** | **' . $verificados . '** | **' . $pendentes . '** |';
$linhas[] = '';

foreach ($ordem as $secao) {
    if (! isset($porSecao[$secao])) {
        continue;
    }

    $linhas[] = '## ' . $secao;
    $linhas[] = '';

    foreach ($porSecao[$secao] as $item) {
        $marca = $item['ok'] ? '[x]' : '[ ]';
        $texto = '- ' . $marca . ' ' . $item['texto'];
        $evidencia = evidenciaDe($item);

        if ($evidencia !== '') {
            $texto .= ' (' . $evidencia . ')';
        }

        $linhas[] = $texto;
    }

    $linhas[] = '';
}

// Pendentes, com a acao sugerida.
$acoes = [
    'Link direto para gerar APP PASSWORD do Gmail' =>
        'Incluir no painel do usuario um link para https://myaccount.google.com/apppasswords, '
        . 'ao lado do campo da APP PASSWORD (app/Views/painel/configuracoes.php).',
    'Card Proximo envio em MM min' =>
        'Mostrar no painel do usuario o tempo ate o proximo envio, calculado a partir de '
        . 'data_ultimo_envio e do intervalo sorteado.',
    'Card Ultimo envio' =>
        'Mostrar no painel do usuario o horario do ultimo envio (a lista Ultimos envios existe, '
        . 'mas nao ha um card com o horario).',
    'AdaptadorScraperApi esqueleto com TODO' =>
        'Criar app/Libraries/Provedores/ScraperApi.php como esqueleto da interface '
        . 'JobsProviderInterface, com os TODO marcados, para o segundo provedor de vagas.',
    'Etapa 2: scraping da pagina (fallback)' =>
        'Implementar a leitura da pagina do anuncio quando a descricao e os links nao trouxerem '
        . 'e-mail (novo metodo no ExtratorEmail, com timeout, user-agent e limite de redirecionamento).',
    'Scraping com timeout 5s, user-agent, max 3 redirects' =>
        'Vem junto com a etapa 2: o scraping da pagina precisa de tempo limite de 5 segundos, '
        . 'user-agent e no maximo 3 redirecionamentos.',
    'Badge Possivel e-mail (nao confirmado) (amarelo)' =>
        'Mostrar o selo amarelo quando a vaga tiver e-mail possivel, mas ainda nao confirmado '
        . '(hoje as views de vagas mostram apenas Com e-mail e Sem e-mail).',
    'Badge Aguardando envio (amarelo)' =>
        'Mostrar o selo amarelo nas vagas que estao na fila de envio, aguardando a janela de envio.',
    'Botao Enviar E-mail (POST, com anexo PDF)' =>
        'Criar a rota POST de envio avulso por vaga, reaproveitando o EnviadorEmail, que ja '
        . 'anexa o curriculo em PDF.',
    'Alerta usuario consumindo desproporcionalmente' =>
        'Avisar no painel do admin quando um usuario consumir consultas muito acima da sua cota.',
    'Grafico CSS puro de novos cadastros (30 dias)' =>
        'Montar, com barras em CSS puro, o grafico de cadastros dos ultimos 30 dias na visao geral '
        . 'do admin (hoje existe a lista de contas recentes).',
];

$linhas[] = '## Itens pendentes';
$linhas[] = '';
$linhas[] = 'Estes itens do plano **nao estao implementados** ou ficaram diferentes do que o';
$linhas[] = 'plano previa. Nada aqui trava o funcionamento do sistema: sao complementos.';
$linhas[] = '';

foreach ($porSecao as $secao => $lista) {
    $temPendente = false;

    foreach ($lista as $item) {
        if (! $item['ok']) {
            $temPendente = true;
        }
    }

    if (! $temPendente) {
        continue;
    }

    $linhas[] = '### ' . $secao;
    $linhas[] = '';

    foreach ($lista as $item) {
        if ($item['ok']) {
            continue;
        }

        $linhas[] = '- **' . $item['texto'] . '**';
        $linhas[] = '  - Situacao: ' . evidenciaDe($item);

        if (isset($acoes[$item['texto']])) {
            $linhas[] = '  - O que falta: ' . $acoes[$item['texto']];
        }
    }

    $linhas[] = '';
}

$linhas[] = '## Como reverificar';
$linhas[] = '';
$linhas[] = 'A verificacao e automatica: cada item acima e conferido por script, contra os';
$linhas[] = 'arquivos do projeto e o banco de dados. Para repetir:';
$linhas[] = '';
$linhas[] = '```bash';
$linhas[] = '# detector do Bloco C (ternario, sintaxe alternativa, eco curto)';
$linhas[] = 'php tools/detector-c1.php';
$linhas[] = '';
$linhas[] = '# checklist completo (gera este arquivo)';
$linhas[] = 'php tools/checklist/verificar.php';
$linhas[] = '```';
$linhas[] = '';
$linhas[] = '## Revisao final';
$linhas[] = '';
$linhas[] = 'Depois do checklist, o projeto passa por uma revisao final de ponta a';
$linhas[] = 'ponta, com o app em execucao, o banco em uso e a API externa:';
$linhas[] = '';
$linhas[] = '1. fluxo completo do usuario (cadastro, aprovacao, login e painel);';
$linhas[] = '2. fluxo completo do admin (todas as telas e as acoes de escrita);';
$linhas[] = '3. os tres modos de cron (cron_linux, cron_windows e spark_manual);';
$linhas[] = '4. integracao com o cron-job.org (chave, conexao, sincronizacao, execucao);';
$linhas[] = '5. execucao manual pelo usuario (limite, contador, botoes desabilitados);';
$linhas[] = '6. os tres modos produzem o mesmo resultado;';
$linhas[] = '7. greps de verificacao do Bloco C.';
$linhas[] = '';
$linhas[] = 'O relatorio da ultima rodada fica em [docs/revisao-final.md](../docs/revisao-final.md),';
$linhas[] = 'e o script que o produz roda com:';
$linhas[] = '';
$linhas[] = '```bash';
$linhas[] = 'php tools/revisao-final.php';
$linhas[] = '```';
$linhas[] = '';

$caminho = $raiz . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'checklist-final.md';

file_put_contents($caminho, implode(PHP_EOL, $linhas));

echo 'checklist-final.md gerado: ' . $total . ' itens, ' . $verificados . ' verificados, ' . $pendentes . " pendentes\n";
