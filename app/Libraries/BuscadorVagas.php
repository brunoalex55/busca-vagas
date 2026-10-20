<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use App\Contracts\JobsProviderInterface;
use App\Models\ChaveSerpApiModel;
use App\Models\LogConsultaModel;
use App\Models\PalavraChaveModel;
use App\Models\UsuarioModel;
use App\Models\VagaModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Cron;
use Config\ProvedorVagas;
use DateTimeImmutable;
use Throwable;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: BUSCADOR DE VAGAS (CRON 1)
 * --------------------------------------------------------------------------
 *
 * Orquestra a busca de vagas. E aqui que as regras do sistema vivem:
 *
 *   1. QUEM busca: palavras-chave ativas e "devidas" de cada usuario.
 *   2. QUANTO se pode buscar: a cota mensal do usuario (calculada pelo
 *      DistribuicaoConsultas) e o teto por execucao do cron.
 *   3. COM QUAL credencial: rotacao das chaves cadastradas, sempre a de
 *      menor ordem com saldo.
 *   4. O QUE gravar: vagas normalizadas, com deduplicacao por job_id e
 *      por hash (titulo + empresa + localizacao), por usuario.
 *   5. COMO cobrar: consultas so contam para o usuario quando o provedor
 *      respondeu de fato (sucesso ou "sem resultados").
 *   6. QUANDO rodar de novo: frequencia da palavra-chave, alongada quando
 *      a cota restante nao permite manter o ritmo escolhido.
 *
 * O acesso ao servico externo acontece SOMENTE pela interface
 * JobsProviderInterface. Trocar de provedor nao muda esta classe.
 */
class BuscadorVagas
{
    /**
     * Quantas chaves diferentes tentar antes de desistir de uma consulta.
     */
    private const MAX_TENTATIVAS_CHAVE = 4;

    /**
     * Quantas falhas de rede seguidas fazem a execucao parar.
     */
    private const MAX_FALHAS_SEGUIDAS = 3;

    /**
     * Provedor externo de vagas.
     */
    private JobsProviderInterface $provedor;

    /**
     * Configuracao do provedor (paginas, timeouts...).
     */
    private ProvedorVagas $config;

    /**
     * Limites das execucoes automaticas.
     */
    private Cron $cron;

    /**
     * Models usados pela rotina.
     */
    private ChaveSerpApiModel $chaves;
    private PalavraChaveModel $palavras;
    private VagaModel $vagas;
    private UsuarioModel $usuarios;
    private LogConsultaModel $logs;

    /**
     * Controla a pausa entre consultas: a primeira nao espera.
     */
    private bool $primeiraConsultaFeita = false;

    /**
     * Todas as dependencias sao injetaveis para facilitar testes.
     */
    public function __construct(
        ?JobsProviderInterface $provedor = null,
        ?Cron $cron = null,
        ?ProvedorVagas $config = null
    ) {
        // Configuracoes (podem vir do .env).
        if ($config === null) {
            $config = config(ProvedorVagas::class);
        }

        if ($cron === null) {
            $cron = config(Cron::class);
        }

        $this->config = $config;
        $this->cron   = $cron;

        // Provedor: usa o service configurado quando nenhum e injetado.
        if ($provedor === null) {
            $provedor = service('provedorVagas');
        }

        $this->provedor = $provedor;

        // Models.
        $this->chaves   = new ChaveSerpApiModel();
        $this->palavras = new PalavraChaveModel();
        $this->vagas    = new VagaModel();
        $this->usuarios = new UsuarioModel();
        $this->logs     = new LogConsultaModel();
    }

    /**
     * Nome do provedor em uso (para logs e resposta do cron).
     */
    public function provedor(): string
    {
        return $this->provedor->nome();
    }

    /**
     * Busca vagas para TODOS os usuarios ativos.
     *
     * @param string   $origem          'cron', 'manual' ou 'manual_usuario'.
     * @param int|null $limiteConsultas Teto de consultas desta execucao.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function buscarGlobal(string $origem = 'cron', ?int $limiteConsultas = null): array
    {
        // Virada de mes: zera e religa as chaves antes de calcular cotas.
        $this->prepararMesDasChaves();

        // Orcamento total de consultas desta execucao.
        $orcamento = $limiteConsultas;

        // Sem limite informado: usa o teto configurado.
        if ($orcamento === null) {
            $orcamento = max(0, (int) $this->cron->limiteConsultasPorExecucao);
        }

        // Resumo geral devolvido ao chamador.
        $resumo = $this->resumoVazio();
        $resumo['provedor'] = $this->provedor->nome();

        // Usuarios ativos, comecando pelos buscados ha mais tempo.
        $usuarios = $this->usuarios->listarParaBusca((int) $this->cron->limiteUsuariosPorExecucao);

        foreach ($usuarios as $usuario) {
            // Sem orcamento sobrando, encerra a execucao.
            if ($orcamento <= 0) {
                $resumo['motivo_parada'] = 'limite_global';
                break;
            }

            // Processa o usuario (a funcao pode reduzir o orcamento).
            $parcial = $this->executarUsuario($usuario, $origem, $orcamento);

            // Acumula os numeros.
            $resumo['usuarios']++;
            $resumo['consultas']        += $parcial['consultas'];
            $resumo['vagas_novas']      += $parcial['vagas_novas'];
            $resumo['vagas_repetidas']  += $parcial['vagas_repetidas'];
            $resumo['erros']            += $parcial['erros'];
            $resumo['detalhes'][]        = $parcial;

            // Sem chave disponivel nao adianta continuar com outros usuarios.
            if ($parcial['motivo_parada'] === 'sem_chave') {
                $resumo['motivo_parada'] = 'sem_chave';
                break;
            }

            // Provedor fora do ar: para tudo e tenta de novo depois.
            if ($parcial['motivo_parada'] === 'provedor_indisponivel') {
                $resumo['motivo_parada'] = 'provedor_indisponivel';
                break;
            }
        }

        $resumo['terminado_em'] = date('Y-m-d H:i:s');

        return $resumo;
    }

    /**
     * Busca vagas para UM usuario especifico.
     *
     * @param int      $usuarioId       Dono das palavras-chave.
     * @param string   $origem          'cron', 'manual' ou 'manual_usuario'.
     * @param int|null $limiteConsultas Teto de consultas desta execucao.
     *
     * @return array<string, mixed> Resumo da execucao.
     */
    public function buscarDoUsuario(int $usuarioId, string $origem = 'cron', ?int $limiteConsultas = null): array
    {
        // Virada de mes: zera e religa as chaves antes de calcular cotas.
        $this->prepararMesDasChaves();

        // Orcamento: explicito > limite por usuario > limite global.
        if ($limiteConsultas !== null) {
            $orcamento = max(0, $limiteConsultas);
        } elseif ((int) $this->cron->limiteConsultasPorUsuario > 0) {
            $orcamento = (int) $this->cron->limiteConsultasPorUsuario;
        } else {
            $orcamento = max(0, (int) $this->cron->limiteConsultasPorExecucao);
        }

        // Usuario precisa existir.
        $usuario = $this->usuarios->find($usuarioId);

        if (! is_array($usuario)) {
            return $this->resumoDeParada('usuario_inexistente', 1);
        }

        // Conta pendente ou bloqueada nao recebe busca de vagas.
        if (valor_ou($usuario, 'status', '') !== 'ativo' || inteiro_ou($usuario, 'ativo', 0) !== 1) {
            return $this->resumoDeParada('usuario_inativo', 1);
        }

        // Executa apenas esse usuario.
        $parcial = $this->executarUsuario($usuario, $origem, $orcamento);

        return [
            'provedor'        => $this->provedor->nome(),
            'usuarios'        => 1,
            'consultas'       => $parcial['consultas'],
            'vagas_novas'     => $parcial['vagas_novas'],
            'vagas_repetidas' => $parcial['vagas_repetidas'],
            'erros'           => $parcial['erros'],
            'motivo_parada'   => $parcial['motivo_parada'],
            'detalhes'        => [$parcial],
            'terminado_em'    => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Resumo devolvido quando a execucao nem chegou a acontecer.
     *
     * @return array<string, mixed>
     */
    private function resumoDeParada(string $motivo, int $erros = 0): array
    {
        return [
            'provedor'        => $this->provedor->nome(),
            'usuarios'        => 0,
            'consultas'       => 0,
            'vagas_novas'     => 0,
            'vagas_repetidas' => 0,
            'erros'           => $erros,
            'motivo_parada'   => $motivo,
            'detalhes'        => [],
            'terminado_em'    => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Executa a busca de um unico usuario.
     *
     * O orcamento e recebido por referencia para que a execucao global
     * saiba quanto ainda resta depois deste usuario.
     *
     * @param array<string, mixed> $usuario
     * @param int                  $orcamento Teto de consultas (por referencia).
     *
     * @return array<string, mixed>
     */
    private function executarUsuario(array $usuario, string $origem, int &$orcamento): array
    {
        // Identificacao do usuario.
        $usuarioId = (int) $usuario['id'];

        // Mes corrente (formato YYYY-MM).
        $mesAtual = date('Y-m');

        // Virada de mes: zera a contagem antes de calcular a cota.
        if (valor_ou($usuario, 'mes_referencia', '') !== $mesAtual) {
            $this->usuarios->reiniciarMesDeConsultas($usuarioId, $mesAtual);
            $usuario['consultas_usadas_mes'] = 0;
            $usuario['mes_referencia']       = $mesAtual;
        }

        // Cota do usuario calculada pelo saldo das chaves.
        $cota        = (new DistribuicaoConsultas())->resumo($usuarioId);
        $cotaTotal   = (int) $cota['cota'];
        $usadasAgora = (int) $cota['usada'];

        // Resumo deste usuario.
        $resultado = [
            'usuario_id'        => $usuarioId,
            'nome'              => texto_ou($usuario, 'nome'),
            'cota'              => $cotaTotal,
            'usadas_antes'      => $usadasAgora,
            'consultas'         => 0,
            'vagas_novas'       => 0,
            'vagas_repetidas'   => 0,
            'palavras_chave'    => 0,
            'palavras_pendentes' => 0,
            'erros'             => 0,
            'mensagens'         => [],
            'motivo_parada'     => null,
        ];

        // Sem nenhuma chave com saldo nao ha como consultar nada. O motivo
        // e registrado antes do calculo da cota para o operador saber que o
        // problema e credencial, e nao cota do usuario.
        if ($this->chaves->totalRestante() <= 0) {
            $resultado['motivo_parada'] = 'sem_chave';

            return $resultado;
        }

        // Sem cota disponivel: nao ha o que fazer.
        if ($usadasAgora >= $cotaTotal) {
            $resultado['motivo_parada'] = 'cota_esgotada';

            return $resultado;
        }

        // Palavras-chave prontas para rodar.
        $palavras = $this->palavras->listarDevidasDoUsuario($usuarioId);

        if ($palavras === []) {
            $resultado['motivo_parada'] = 'sem_palavras';

            return $resultado;
        }

        // Total de combinacoes (usado no agendamento).
        $combinacoesAtivas = max(1, $this->palavras->totalCombinacoesDoUsuario($usuarioId));

        // Controle de falhas de rede seguidas.
        $falhasSeguidas = 0;

        // Houve pelo menos uma chamada ao provedor?
        $houveTentativa = false;

        foreach ($palavras as $palavra) {
            // Verificacoes de orcamento e cota antes de cada palavra-chave.
            if ($orcamento <= 0) {
                $resultado['motivo_parada'] = 'limite_global';
                break;
            }

            if ($usadasAgora >= $cotaTotal) {
                $resultado['motivo_parada'] = 'cota_esgotada';
                break;
            }

            // Cidades desta palavra-chave (uma consulta por cidade).
            $cidades       = $this->cidadesDe($palavra);
            $totalCidades  = count($cidades);
            $processadas   = 0;
            $consultasAqui = 0;

            // Sinaliza que a palavra-chave inteira nao pode ser concluida.
            $encerrarTudo = false;

            foreach ($cidades as $cidade) {
                // Orcamento e cota sao checados a cada consulta.
                if ($orcamento <= 0) {
                    $resultado['motivo_parada'] = 'limite_global';
                    $encerrarTudo               = true;
                    break;
                }

                if ($usadasAgora >= $cotaTotal) {
                    $resultado['motivo_parada'] = 'cota_esgotada';
                    $encerrarTudo               = true;
                    break;
                }

                // Executa a combinacao (termo + cidade).
                $retorno = $this->executarCombinacao($usuarioId, $palavra, $cidade, $origem, $orcamento);

                $houveTentativa = true;
                $processadas++;

                // Cobra a cota do usuario pelas consultas efetivamente feitas.
                for ($unidade = 0; $unidade < $retorno['consumidas']; $unidade++) {
                    $this->usuarios->registrarConsultaConsumida($usuarioId, $mesAtual);
                }

                // Acumula os numeros.
                $resultado['consultas']        += $retorno['consumidas'];
                $resultado['vagas_novas']      += $retorno['vagas_novas'];
                $resultado['vagas_repetidas']  += $retorno['vagas_repetidas'];
                $consultasAqui                 += $retorno['consumidas'];
                $usadasAgora                   += $retorno['consumidas'];

                if ($retorno['erro']) {
                    $resultado['erros']++;

                    foreach ($retorno['mensagens'] as $mensagem) {
                        $resultado['mensagens'][] = $mensagem;
                    }
                }

                // Sem chave: nao ha como continuar nada.
                if ($retorno['sem_chave']) {
                    $resultado['motivo_parada'] = 'sem_chave';
                    $encerrarTudo               = true;
                    break;
                }

                // Falhas de rede seguidas indicam provedor fora do ar.
                if ($retorno['status'] === JobsProviderInterface::STATUS_ERRO_HTTP) {
                    $falhasSeguidas++;

                    if ($falhasSeguidas >= self::MAX_FALHAS_SEGUIDAS) {
                        $resultado['motivo_parada'] = 'provedor_indisponivel';
                        $encerrarTudo               = true;
                        break;
                    }
                } else {
                    $falhasSeguidas = 0;
                }
            }

            // Agenda a proxima execucao desta palavra-chave.
            $this->agendarPalavraChave(
                $palavra,
                $cotaTotal - $usadasAgora,
                $combinacoesAtivas,
                $processadas >= $totalCidades && ! $encerrarTudo,
                $consultasAqui
            );

            $resultado['palavras_chave']++;

            // Encerra o usuario quando o motivo foi global.
            if ($encerrarTudo) {
                break;
            }
        }

        // Registra o horario da busca quando houve alguma tentativa.
        if ($houveTentativa) {
            $this->usuarios->registrarExecucaoBusca($usuarioId);
        }

        // Quantas palavras-chave ainda estao pendentes.
        $resultado['palavras_pendentes'] = count($this->palavras->listarDevidasDoUsuario($usuarioId));

        return $resultado;
    }

    /**
     * Executa uma combinacao (termo + cidade), respeitando o numero de
     * paginas configurado.
     *
     * @param array<string, mixed> $palavra
     * @param int                  $orcamento Teto de consultas (por referencia).
     *
     * @return array{
     *     status: string,
     *     consumidas: int,
     *     vagas_novas: int,
     *     vagas_repetidas: int,
     *     erro: bool,
     *     mensagens: list<string>,
     *     sem_chave: bool
     * }
     */
    private function executarCombinacao(
        int $usuarioId,
        array $palavra,
        string $cidade,
        string $origem,
        int &$orcamento
    ): array {
        // Resultado acumulado da combinacao.
        $resultado = [
            'status'          => JobsProviderInterface::STATUS_SUCESSO,
            'consumidas'      => 0,
            'vagas_novas'     => 0,
            'vagas_repetidas' => 0,
            'erro'            => false,
            'mensagens'       => [],
            'sem_chave'       => false,
        ];

        // Quantas paginas pedir por combinacao.
        $paginas = max(1, (int) $this->config->paginasPorConsulta);

        // Cursor de paginacao (comeca sem).
        $token = null;

        for ($pagina = 1; $pagina <= $paginas; $pagina++) {
            // Cada pagina e uma consulta: respeita o orcamento.
            if ($orcamento <= 0) {
                $resultado['status'] = 'limite_global';

                return $resultado;
            }

            // Executa a pagina (troca de chave acontece dentro daqui).
            $retorno = $this->executarPagina($usuarioId, $palavra, $cidade, $origem, $pagina, $token);

            // Registra o status final visto nesta combinacao.
            $resultado['status'] = $retorno['status'];

            // Nao havia nenhuma chave utilizavel.
            if ($retorno['sem_chave']) {
                $resultado['sem_chave'] = true;
                $resultado['erro']      = true;

                return $resultado;
            }

            // Toda consulta que o provedor respondeu conta na cota.
            if ($retorno['consumiu']) {
                $resultado['consumidas']++;
                $orcamento--;
            }

            // Grava as vagas encontradas.
            if ($retorno['vagas'] !== []) {
                $gravacao = $this->gravarVagas($usuarioId, $palavra, $cidade, $retorno['vagas']);

                $resultado['vagas_novas']     += $gravacao['novas'];
                $resultado['vagas_repetidas'] += $gravacao['repetidas'];
            }

            // Falhas entram no relatorio.
            if (! in_array($retorno['status'], [
                JobsProviderInterface::STATUS_SUCESSO,
                JobsProviderInterface::STATUS_SEM_RESULTADOS,
            ], true)) {
                $resultado['erro'] = true;

                if ($retorno['mensagem'] !== '') {
                    $resultado['mensagens'][] = $retorno['mensagem'];
                }
            }

            // Se a pagina nao consumiu nada, nao vale pedir a proxima.
            if (! $retorno['consumiu']) {
                return $resultado;
            }

            // Sem cursor, nao ha proxima pagina.
            if ($retorno['token'] === null) {
                return $resultado;
            }

            // Prepara a proxima pagina.
            $token = $retorno['token'];
        }

        return $resultado;
    }

    /**
     * Executa UMA pagina de consulta, trocando de chave quando a
     * credencial estiver recusada ou esgotada.
     *
     * @param array<string, mixed> $palavra
     *
     * @return array{
     *     status: string,
     *     mensagem: string,
     *     consumiu: bool,
     *     vagas: list<array<string, mixed>>,
     *     token: string|null,
     *     sem_chave: bool
     * }
     */
    private function executarPagina(
        int $usuarioId,
        array $palavra,
        string $cidade,
        string $origem,
        int $pagina,
        ?string $token
    ): array {
        // Monta os dados da consulta conforme o contrato do provedor.
        $consulta = [
            'termo'          => (string) $palavra['termo'],
            'cidade'         => $cidade,
            'remoto'         => inteiro_ou($palavra, 'remoto', 0) === 1,
            'idioma'         => texto_ou($palavra, 'idioma', $this->config->idiomaPadrao),
            'pais'           => texto_ou($palavra, 'pais', $this->config->paisPadrao),
            'dominio_google' => texto_ou($palavra, 'dominio_google', $this->config->dominioGooglePadrao),
            'pagina'         => $pagina,
            'quantidade'     => (int) $this->config->resultadosPorPagina,
            'token'          => $token,
        ];

        // Tenta algumas chaves antes de desistir.
        for ($tentativa = 1; $tentativa <= self::MAX_TENTATIVAS_CHAVE; $tentativa++) {
            // Chave ativa com menor ordem e com saldo.
            $chave = $this->chaves->obterDisponivel();

            // Nenhuma chave utilizavel.
            if ($chave === null) {
                $this->registrarLog($usuarioId, $palavra, $cidade, null, $origem, 0, 'sem_chave', 'Nenhuma chave de busca disponivel.');

                return $this->retornoPagina('sem_chave', 'Nenhuma chave de busca disponivel.', false, [], null, true);
            }

            // Credencial vazia equivale a credencial invalida.
            if (trim((string) $chave['chave']) === '') {
                $this->chaves->invalidar((int) $chave['id']);
                $this->registrarLog($usuarioId, $palavra, $cidade, (int) $chave['id'], $origem, 0, 'chave_invalida', 'Chave cadastrada vazia.');

                continue;
            }

            // Espera entre consultas (gentileza com o servico externo).
            $this->pausarEntreConsultas();

            // Chama o provedor atraves da interface.
            $retorno = $this->provedor->buscar($consulta, (string) $chave['chave']);

            // Normaliza o retorno (provedores podem omitir chaves).
            $status   = texto_ou($retorno, 'status', JobsProviderInterface::STATUS_ERRO_API);
            $mensagem = texto_ou($retorno, 'mensagem');
            $consumiu = (bool) valor_ou($retorno, 'consumiu', false);
            $vagas    = [];

            if (is_array(valor_ou($retorno, 'vagas', null))) {
                $vagas = $retorno['vagas'];
            }
            $tokenNovo = valor_ou($retorno, 'token_proxima_pagina', null);

            // Token valido: mantem; caso contrario, nulo.
            if (! is_string($tokenNovo) || $tokenNovo === '') {
                $tokenNovo = null;
            }

            // Credencial recusada: inativa e tenta outra chave.
            if ($status === JobsProviderInterface::STATUS_CHAVE_INVALIDA) {
                $this->chaves->invalidar((int) $chave['id']);
                $this->registrarLog($usuarioId, $palavra, $cidade, (int) $chave['id'], $origem, 0, $status, $mensagem);

                continue;
            }

            // Cota do provedor esgotada: aposenta a chave e tenta outra.
            if ($status === JobsProviderInterface::STATUS_LIMITE_ATINGIDO) {
                $this->chaves->esgotar((int) $chave['id']);
                $this->registrarLog($usuarioId, $palavra, $cidade, (int) $chave['id'], $origem, 0, $status, $mensagem);

                continue;
            }

            // Consulta efetivamente respondida: contabiliza o uso da chave.
            if ($consumiu) {
                $this->chaves->registrarUso((int) $chave['id']);
            }

            // Grava o log da consulta.
            $this->registrarLog(
                $usuarioId,
                $palavra,
                $cidade,
                (int) $chave['id'],
                $origem,
                count($vagas),
                $status,
                $mensagem
            );

            // Resultado definitivo desta pagina.
            return $this->retornoPagina(
                $status,
                $mensagem,
                $consumiu,
                array_values($vagas),
                $tokenNovo,
                false
            );
        }

        // Esgotou as tentativas de chave.
        $this->registrarLog($usuarioId, $palavra, $cidade, null, $origem, 0, 'sem_chave', 'Nenhuma chave valida disponivel apos varias tentativas.');

        return $this->retornoPagina('sem_chave', 'Nenhuma chave valida disponivel.', false, [], null, true);
    }

    /**
     * Monta o retorno padronizado de uma pagina.
     *
     * @param list<array<string, mixed>> $vagas
     *
     * @return array<string, mixed>
     */
    private function retornoPagina(
        string $status,
        string $mensagem,
        bool $consumiu,
        array $vagas,
        ?string $token,
        bool $semChave
    ): array {
        return [
            'status'    => $status,
            'mensagem'  => $mensagem,
            'consumiu'  => $consumiu,
            'vagas'     => $vagas,
            'token'     => $token,
            'sem_chave' => $semChave,
        ];
    }

    /**
     * Grava as vagas devolvidas por uma consulta, sem duplicar.
     *
     * Deduplicacao em dois niveis, sempre por usuario:
     *   1. job_id   -> identifica a vaga na origem;
     *   2. hash     -> titulo + empresa + localizacao (entre fontes).
     *
     * Quando a vaga ja existe, apenas campos vazios sao completados
     * (por exemplo: a vaga antiga nao tinha e-mail e agora tem).
     *
     * @param array<string, mixed>       $palavra
     * @param list<array<string, mixed>> $vagas
     *
     * @return array{novas: int, repetidas: int}
     */
    private function gravarVagas(int $usuarioId, array $palavra, string $cidade, array $vagas): array
    {
        $novas     = 0;
        $repetidas = 0;

        foreach ($vagas as $vaga) {
            // Vaga precisa de identificador e titulo para existir.
            $jobId  = trim(texto_ou($vaga, 'job_id'));
            $titulo = trim(texto_ou($vaga, 'titulo'));

            if ($jobId === '' || $titulo === '') {
                continue;
            }

            // Empresa e localizacao (a localizacao cai para a cidade buscada).
            $empresa     = trim(texto_ou($vaga, 'empresa'));
            $localizacao = trim(texto_ou($vaga, 'localizacao'));

            // Sem localizacao na vaga: usa a cidade consultada.
            if ($localizacao === '') {
                $localizacao = $cidade;
            }

            // Hash de deduplicacao entre fontes.
            $hash = VagaModel::gerarHashUnico($titulo, $empresa, $localizacao);

            // Dados prontos para gravar.
            $dados = [
                'palavra_chave_id'   => (int) $palavra['id'],
                'usuario_id'         => $usuarioId,
                'job_id'             => mb_substr($jobId, 0, 255),
                'hash_unico'         => $hash,
                'titulo'             => mb_substr($titulo, 0, 255),
                'empresa'            => mb_substr($empresa, 0, 255),
                'localizacao'        => mb_substr($localizacao, 0, 255),
                'cidade_consultada'  => mb_substr($cidade, 0, 150),
                'fonte'              => mb_substr(trim(texto_ou($vaga, 'fonte')), 0, 60),
                'url_original'       => texto_ou($vaga, 'url_original'),
                'url_candidatura'    => texto_ou($vaga, 'url_candidatura'),
                'descricao'          => texto_ou($vaga, 'descricao'),
                'salario'            => mb_substr(trim(texto_ou($vaga, 'salario')), 0, 120),
                'tipo_contrato'      => mb_substr(trim(texto_ou($vaga, 'tipo_contrato')), 0, 120),
                'publicado_em'       => $this->dataValida(valor_ou($vaga, 'publicado_em', null)),
                'email_contato'      => $this->emailValido(valor_ou($vaga, 'email_contato', null)),
                'emails_encontrados' => $this->jsonEmails(valor_ou($vaga, 'emails_encontrados', [])),
                'origem_email'       => $this->origemEmail(valor_ou($vaga, 'origem_email', null)),
                'ja_candidatado'     => 0,
                'e_prioritaria'      => 0,
            ];

            // Ja existe (por job_id ou por hash)?
            $existente = $this->vagas->buscarExistente($usuarioId, $dados['job_id'], $hash);

            if ($existente !== null) {
                $repetidas++;
                $this->vagas->complementarVazios($existente, $dados);

                continue;
            }

            // Tenta gravar. Uma corrida entre execucoes cai no catch.
            try {
                $this->vagas->inserirVaga($dados);
                $novas++;
            } catch (DatabaseException $e) {
                // A vaga foi gravada por outra execucao no mesmo instante.
                $repetidas++;
            } catch (Throwable $e) {
                // Qualquer outra falha nao derruba a busca inteira.
                $repetidas++;
            }
        }

        return ['novas' => $novas, 'repetidas' => $repetidas];
    }

    /**
     * Define quando a palavra-chave deve rodar de novo.
     *
     * Regra: nunca antes de `frequencia_horas`, mas alongado quando a
     * cota restante do mes nao permite manter esse ritmo. Assim a busca
     * se distribui pelo mes em vez de esgotar tudo de uma vez.
     *
     * @param array<string, mixed> $palavra
     * @param int                  $cotaRestante Cota que ainda sobra no mes.
     * @param int                  $combinacoes Combinacoes ativas do usuario.
     * @param bool                 $completa     A palavra-chave foi toda processada.
     * @param int                  $consultas    Consultas gastas nesta palavra-chave.
     */
    private function agendarPalavraChave(
        array $palavra,
        int $cotaRestante,
        int $combinacoes,
        bool $completa,
        int $consultas
    ): void {
        // Momento atual.
        $agora = date('Y-m-d H:i:s');

        // Palavra-chave incompleta (orcamento/cota acabaram): volta a ser
        // devida assim que o cron rodar, para terminar o que faltou.
        $proxima = null;

        if ($completa) {
            $proxima = $this->calcularProximaExecucao($palavra, $cotaRestante, $combinacoes);
        }

        $this->palavras->registrarExecucao((int) $palavra['id'], $agora, $proxima, $consultas);
    }

    /**
     * Calcula o horario da proxima execucao de uma palavra-chave.
     */
    private function calcularProximaExecucao(array $palavra, int $cotaRestante, int $combinacoes): string
    {
        // Frequencia minima escolhida pelo usuario (em horas).
        $frequencia = max(1, inteiro_ou($palavra, 'frequencia_horas', 24));

        // Horas que ainda restam no mes corrente.
        $diasRestantes  = max(1, ((int) date('t') - (int) date('j')) + 1);
        $horasRestantes = $diasRestantes * 24;

        // Cota do mes esgotada: so volta no comeco do proximo mes.
        if ($cotaRestante <= 0) {
            return (new DateTimeImmutable('first day of next month'))
                ->setTime(0, 5)
                ->format('Y-m-d H:i:s');
        }

        // Quantos ciclos completos ainda cabem para esta palavra-chave.
        $ciclos = $cotaRestante / max(1, $combinacoes);

        // Intervalo que dilui a cota no restante do mes.
        $intervaloNecessario = $horasRestantes;

        if ($ciclos > 0) {
            $intervaloNecessario = $horasRestantes / $ciclos;
        }

        // Nunca mais rapido que a frequencia pedida pelo usuario.
        $intervaloFinal = max($frequencia, (int) ceil($intervaloNecessario));

        return (new DateTimeImmutable('now'))
            ->modify('+' . $intervaloFinal . ' hours')
            ->format('Y-m-d H:i:s');
    }

    /**
     * Grava uma linha de auditoria da consulta.
     *
     * @param array<string, mixed> $palavra
     */
    private function registrarLog(
        int $usuarioId,
        array $palavra,
        string $cidade,
        ?int $chaveId,
        string $origem,
        int $vagas,
        string $status,
        string $mensagem
    ): void {
        // Mensagem de erro so faz sentido quando a busca nao deu certo.
        $mensagemErro = $mensagem;

        if ($status === JobsProviderInterface::STATUS_SUCESSO) {
            $mensagemErro = '';
        }

        $this->logs->registrar([
            'usuario_id'             => $usuarioId,
            'palavra_chave_id'       => (int) $palavra['id'],
            'cidade_consultada'      => $cidade,
            'chave_serpapi_id'       => $chaveId,
            'termo'                  => (string) $palavra['termo'],
            'origem'                 => $this->origemDoLog($origem),
            'total_vagas_retornadas' => $vagas,
            'status'                 => $status,
            'mensagem_erro'          => $mensagemErro,
        ]);
    }

    /**
     * Garante que a origem gravada no log e aceita pela coluna.
     *
     * Valores validos: 'manual' (painel administrativo), 'manual_usuario'
     * (usuario no painel dele) e 'cron' (agendador).
     */
    private function origemDoLog(string $origem): string
    {
        $aceitas = ['manual', 'manual_usuario', 'cron'];

        if (in_array($origem, $aceitas, true)) {
            return $origem;
        }

        return 'cron';
    }

    /**
     * Reinicia o ciclo mensal das chaves, quando o mes virou.
     *
     * Sem isso, o saldo de uma chave ficaria gasto para sempre e a busca
     * de vagas pararia depois do primeiro mes de uso.
     */
    private function prepararMesDasChaves(): void
    {
        $this->chaves->reiniciarMesSeNecessario(date('Y-m'));
    }

    /**
     * Respeita a pausa configurada entre consultas.
     *
     * A primeira consulta da execucao nunca espera.
     */
    private function pausarEntreConsultas(): void
    {
        // Marca a primeira consulta e sai sem esperar.
        if (! $this->primeiraConsultaFeita) {
            $this->primeiraConsultaFeita = true;

            return;
        }

        // Pausa configurada (pode ser zero).
        $pausa = max(0, (int) $this->cron->pausaEntreConsultas);

        if ($pausa > 0) {
            sleep($pausa);
        }
    }

    /**
     * Lista as cidades de uma palavra-chave.
     *
     * Sem cidade valida, faz uma busca nacional (string vazia).
     *
     * @param array<string, mixed> $palavra
     *
     * @return list<string>
     */
    private function cidadesDe(array $palavra): array
    {
        // Cidades ficam em JSON.
        $cidades = json_decode(texto_ou($palavra, 'cidades', '[]'), true);

        if (! is_array($cidades)) {
            return [''];
        }

        $limpas = [];

        foreach ($cidades as $cidade) {
            // Aceita apenas textos.
            if (! is_string($cidade)) {
                continue;
            }

            // Normaliza espacos.
            $cidade = trim((string) preg_replace('/\s+/u', ' ', $cidade));

            // Ignora vazios e repetidos.
            if ($cidade === '' || in_array($cidade, $limpas, true)) {
                continue;
            }

            $limpas[] = $cidade;
        }

        // Sem cidade: busca no pais inteiro.
        if ($limpas === []) {
            return [''];
        }

        return $limpas;
    }

    /**
     * Aceita apenas datas no formato Y-m-d.
     *
     * @param mixed $valor
     */
    private function dataValida($valor): ?string
    {
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
            return $valor;
        }

        return null;
    }

    /**
     * Aceita apenas e-mails que cabem na coluna.
     *
     * @param mixed $valor
     */
    private function emailValido($valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return mb_substr($valor, 0, 180);
    }

    /**
     * Codifica a lista de e-mails encontrados em JSON.
     *
     * @param mixed $valor
     */
    private function jsonEmails($valor): string
    {
        if (! is_array($valor)) {
            return '[]';
        }

        // Somente textos, sem repeticao.
        $emails = array_values(array_unique(array_filter($valor, 'is_string')));

        return (string) json_encode($emails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Garante que a origem do e-mail e um dos valores aceitos pela coluna.
     *
     * @param mixed $valor
     */
    private function origemEmail($valor): ?string
    {
        // Valores aceitos pela coluna ENUM.
        $aceitos = ['descricao', 'apply_options', 'pagina'];

        // Origem valida: mantem; caso contrario, nulo.
        if (! is_string($valor) || ! in_array($valor, $aceitos, true)) {
            return null;
        }

        return $valor;
    }

    /**
     * Resumo vazio usado por buscarGlobal().
     *
     * @return array<string, mixed>
     */
    private function resumoVazio(): array
    {
        return [
            'iniciado_em'     => date('Y-m-d H:i:s'),
            'terminado_em'    => null,
            'provedor'        => null,
            'usuarios'        => 0,
            'consultas'       => 0,
            'vagas_novas'     => 0,
            'vagas_repetidas' => 0,
            'erros'           => 0,
            'motivo_parada'   => null,
            'detalhes'        => [],
        ];
    }
}
