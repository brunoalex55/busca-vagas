<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/

/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - AGENDAMENTO (CRON)
 * --------------------------------------------------------------------------
 *
 * Painel central do agendamento (Fase 7). Mostra:
 *   - os tres modos de agendamento e o que esta em uso;
 *   - as instrucoes de instalacao do modo escolhido;
 *   - a integracao com o cron-job.org (somente no modo spark_manual);
 *   - o limite de execucoes manuais do usuario comum;
 *   - os tokens e os jobs de cada usuario;
 *   - o token global legado e o disparo manual dos comandos.
 *
 * Este arquivo segue o Bloco C: sem eco curto, sem ternario, sem operador
 * de coalescencia e sem sintaxe alternativa de blocos. O fechamento e
 * sempre por chaves.
 *
 * A saida do ultimo comando executado chega como flashdata, porque o
 * disparo acontece por POST e a tela apenas exibe o resultado.
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Agendamento';

// Saida do ultimo comando disparado pelo painel.
$saidaComando = session()->getFlashdata('saida_comando');

// Rotulo do modo em uso, procurado na lista de modos.
$rotuloModoAtual = $modoAtual;

foreach ($modos as $modo) {
    if ($modo['valor'] === $modoAtual) {
        $rotuloModoAtual = $modo['titulo'];
    }
}

// Tipos de job exibidos na tabela de usuarios (vem do registro da
// biblioteca: um provedor novo aparece aqui sem mexer na view).
$tiposJob = [];

foreach (App\Libraries\GerenciadorCron::TIPOS_DE_JOB as $tipo => $definicao) {
    $tiposJob[] = [
        'chave' => 'job_' . $tipo,
        'tipo'  => $tipo,
        'rotulo' => (string) $definicao['rotulo'],
    ];
}
?>

<?php $this->section('conteudo'); ?>

<?php /* Cabecalho com o disparo manual dos dois comandos. */ ?>
<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Agendamento das tarefas</h2>
        <p class="painel__descricao">
            Escolha quem dispara os comandos de busca de vagas e de envio de e-mails.
            Os comandos em si sao sempre os mesmos (php spark); este painel decide
            apenas quem os executa.
        </p>
    </div>

    <div class="painel__acoes">
        <form method="post" action="<?php echo site_url('admin/cron/executar-vagas'); ?>"
              data-confirmar="Executar a busca de vagas agora, para todos os usuarios ativos?">
            <button type="submit" class="botao botao--secundario botao--pequeno">
                <?php echo icone_svg('lupa'); ?> Executar vagas
            </button>
        </form>

        <form method="post" action="<?php echo site_url('admin/cron/executar-emails'); ?>"
              data-confirmar="Executar o envio de e-mails agora, para todos os usuarios ativos?">
            <button type="submit" class="botao botao--fantasma botao--pequeno">
                <?php echo icone_svg('email'); ?> Executar e-mails
            </button>
        </form>
    </div>
</section>

<?php if (! empty($saidaComando)) { ?>
    <?php /* Saida do ultimo comando executado pelo painel. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Saida do ultimo comando</h3>
            <span class="selo selo--info">Debug</span>
        </div>
        <div class="cartao__corpo">
            <pre class="caixa-token caixa-token--clara"><code><?php echo esc($saidaComando); ?></code></pre>
        </div>
    </section>
<?php } ?>

<?php /* Modo de agendamento. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Modo de agendamento</h3>
        <span class="selo selo--primario">Atual: <?php echo esc($rotuloModoAtual); ?></span>
    </div>

    <div class="cartao__corpo">
        <div class="grade grade--tres">
            <?php foreach ($modos as $modo) { ?>
                <?php $modoAtivo = ($modo['valor'] === $modoAtual); ?>

                <article class="cartao">
                    <div class="cartao__cabecalho">
                        <h4 class="cartao__titulo"><?php echo esc($modo['titulo']); ?></h4>

                        <?php if ($modoAtivo) { ?>
                            <span class="selo selo--sucesso">Ativo</span>
                        <?php } ?>
                    </div>

                    <div class="cartao__corpo">
                        <p class="texto-suave texto-pequeno"><?php echo esc($modo['resumo']); ?></p>

                        <?php if (! $modoAtivo) { ?>
                            <form class="formulario" method="post"
                                  action="<?php echo site_url('admin/cron/modo'); ?>">
                                <input type="hidden" name="modo" value="<?php echo esc($modo['valor']); ?>">
                                <div class="formulario__acoes">
                                    <button type="submit" class="botao botao--secundario botao--pequeno">
                                        Selecionar
                                    </button>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </article>
            <?php } ?>
        </div>
    </div>
</section>

<?php /* Instrucoes do modo em uso. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Como instalar: <?php echo esc($instrucoes['titulo']); ?></h3>
        <a class="cartao__link" href="<?php echo site_url('admin/logs'); ?>">Ver logs</a>
    </div>

    <div class="cartao__corpo">
        <p class="texto-suave"><?php echo esc($instrucoes['resumo']); ?></p>

        <p class="texto-suave texto-pequeno">
            Passo a passo completo em <code><?php echo esc($instrucoes['documento']); ?></code>.
        </p>

        <ol class="lista-limpa">
            <?php foreach ($instrucoes['passos'] as $indice => $passo) { ?>
                <li class="item-lista">
                    <span class="item-lista__titulo"><?php echo (int) ($indice + 1); ?>. <?php echo esc($passo); ?></span>
                </li>
            <?php } ?>
        </ol>

        <?php foreach ($instrucoes['comandos'] as $indice => $comando) { ?>
            <div class="caixa-token caixa-token--clara">
                <code id="comando-<?php echo (int) $indice; ?>"><?php echo esc($comando); ?></code>
                <button type="button" class="botao botao--fantasma botao--pequeno"
                        data-copiar="comando-<?php echo (int) $indice; ?>">
                    <?php echo icone_svg('documento'); ?> Copiar
                </button>
            </div>
        <?php } ?>

        <?php if ($instrucoes['avisos'] !== []) { ?>
            <div class="alerta alerta--atencao">
                <?php echo icone_svg('info'); ?>
                <div>
                    <?php foreach ($instrucoes['avisos'] as $aviso) { ?>
                        <p><?php echo esc($aviso); ?></p>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</section>

<?php if ($modoEhManual) { ?>
    <?php /* Integracao com o cron-job.org: existe somente no modo manual. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Integracao com o cron-job.org</h3>
            <span class="selo selo--aviso">Somente no modo spark_manual</span>
        </div>

        <div class="cartao__corpo">
            <div class="grade grade--painel">
                <?php /* Chave de API e teste de conexao. */ ?>
                <div>
                    <form class="formulario" method="post" action="<?php echo site_url('admin/cron/api-key'); ?>">
                        <div class="campo">
                            <label class="campo__rotulo" for="api_key_cronjob">Chave de API do cron-job.org</label>

                            <?php if ($apiKeyConfigurada) { ?>
                                <p class="texto-suave texto-pequeno">
                                    Chave atual:
                                    <code class="codigo-mascarado"><?php echo esc($apiKeyMascarada); ?></code>
                                </p>
                            <?php } ?>

                            <div class="campo__com-acao">
                                <input class="campo__entrada" type="password" id="api_key_cronjob"
                                       name="api_key_cronjob" autocomplete="off" maxlength="255"
                                       placeholder="Cole aqui a chave da API">
                                <button type="button" class="campo__ver-senha" data-alvo="api_key_cronjob"
                                        aria-label="Mostrar chave">Ver</button>
                            </div>

                            <span class="campo__ajuda">
                                A chave fica gravada no banco, aparece mascarada nesta tela e nunca vai para os logs.
                                Deixe em branco para remover a chave atual.
                            </span>
                        </div>

                        <div class="formulario__acoes">
                            <button type="submit" class="botao botao--primario botao--pequeno">Salvar chave</button>
                        </div>
                    </form>

                    <form class="formulario" method="post"
                          action="<?php echo site_url('admin/cron/testar-conexao'); ?>">
                        <div class="formulario__acoes">
                            <button type="submit" class="botao botao--fantasma botao--pequeno">
                                <?php echo icone_svg('raio'); ?> Testar conexao
                            </button>
                        </div>
                    </form>
                </div>

                <?php /* Limites do plano gratuito. */ ?>
                <div>
                    <dl class="lista-definicao">
                        <div>
                            <dt>Requisicoes de API por dia</dt>
                            <dd><?php echo number_format((int) $limites['requisicoes_usadas'], 0, ',', '.'); ?>
                                / <?php echo (int) $limites['requisicoes_dia']; ?></dd>
                        </div>
                        <div>
                            <dt>Requisicoes restantes hoje</dt>
                            <dd><?php echo number_format((int) $limites['requisicoes_restantes'], 0, ',', '.'); ?></dd>
                        </div>
                        <div>
                            <dt>Jobs na conta</dt>
                            <dd><?php echo number_format((int) $limites['jobs_criados'], 0, ',', '.'); ?>
                                / <?php echo (int) $limites['jobs_maximos']; ?></dd>
                        </div>
                    </dl>

                    <?php if ($limites['alerta_requisicoes']) { ?>
                        <div class="alerta alerta--atencao">
                            <?php echo icone_svg('info'); ?>
                            <p>Uso de requisicoes da API acima de 90%. As chamadas param ao chegar no limite diario.</p>
                        </div>
                    <?php } ?>

                    <?php if ($limites['alerta_jobs']) { ?>
                        <div class="alerta alerta--atencao">
                            <?php echo icone_svg('info'); ?>
                            <p>Uso de jobs da conta acima de 90% do limite do plano.</p>
                        </div>
                    <?php } ?>

                    <div class="linha-acoes">
                        <form method="post" action="<?php echo site_url('admin/cron/sincronizar'); ?>"
                              data-confirmar="Criar ou atualizar os jobs de todos os usuarios ativos?">
                            <button type="submit" class="botao botao--secundario botao--pequeno">
                                <?php echo icone_svg('raio'); ?> Sincronizar jobs
                            </button>
                        </form>

                        <a class="botao botao--fantasma botao--pequeno" target="_blank" rel="noopener"
                           href="<?php echo site_url('admin/cron/jobs'); ?>">
                            <?php echo icone_svg('documento'); ?> Ver jobs (JSON)
                        </a>
                    </div>

                    <p class="texto-suave texto-pequeno">
                        A sincronizacao cria um job para buscar vagas (a cada 10 minutos) e outro para
                        enviar e-mails (a cada 5 minutos) para cada usuario ativo.
                    </p>
                </div>
            </div>

            <?php /* Tutorial do cron-job.org em bloco expansivel. */ ?>
            <details class="bloco-expandido">
                <summary class="bloco-expandido__titulo">
                    <span>Como configurar o cron-job.org (passo a passo)</span>
                    <span class="bloco-expandido__icone" aria-hidden="true"></span>
                </summary>

                <div class="bloco-expandido__corpo">
                    <ol class="tutorial-passos">
                        <li>
                            <span class="tutorial-passos__titulo">Passo 1: criar conta no cron-job.org</span>
                            Acesse <code>https://cron-job.org</code>, clique em <strong>Sign up</strong>,
                            preencha nome, e-mail e senha, aceite os termos de uso, clique em
                            <strong>Create account</strong> e finalize a verificacao do e-mail pelo link
                            de ativacao.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 2: obter a chave de API</span>
                            Faca login no cron-job.org, acesse <strong>Settings</strong>, clique em
                            <strong>API</strong>, copie a chave exibida e guarde-a em local seguro.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 3: configurar a chave no sistema</span>
                            Volte para <code>/admin/cron</code>, cole a chave no campo
                            <strong>Chave de API do cron-job.org</strong>, clique em <strong>Salvar chave</strong>
                            e depois em <strong>Testar conexao</strong> para validar.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 4: sincronizar os jobs</span>
                            Clique em <strong>Sincronizar jobs</strong>. O sistema cria dois jobs para cada
                            usuario ativo: <code>CI4 - Usuario X - Buscar Vagas</code> (a cada 10 minutos) e
                            <code>CI4 - Usuario X - Enviar E-mails</code> (a cada 5 minutos). Cada job chama
                            <code>POST /cron/usuario/{id}/executar-externo?comando=vagas</code> (ou
                            <code>comando=emails</code>) com o token do usuario no header
                            <code>X-Cron-Token</code>.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 5: verificar os jobs criados</span>
                            Na tabela <strong>Tokens e jobs por usuario</strong> (mais abaixo nesta tela) veja
                            usuario, tipo, job no servico, status e ultima execucao. Se algum job falhar,
                            exclua o job e clique em <strong>Sincronizar jobs</strong> novamente.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 6: monitorar</span>
                            No painel do cron-job.org, acesse <strong>Jobs</strong> e depois
                            <strong>History</strong>. Se algum job falhar, veja os logs do sistema em
                            <code>writable/logs/cron-externo-YYYY-MM-DD.log</code> (e na saida dos comandos em
                            <code>cron-externo-saida-YYYY-MM-DD.log</code>). Quando o comando passa de 25
                            segundos, o sistema responde <strong>202</strong> e ele termina em segundo plano.
                        </li>

                        <li>
                            <span class="tutorial-passos__titulo">Passo 7: limites do plano gratuito</span>
                            Sao 100 requisicoes de API por dia, 50 jobs por conta, criacao limitada a 1
                            requisicao por segundo e 5 por minuto e timeout de 30 segundos por requisicao. Se
                            ultrapassar esses limites, use um plano pago ou migre para o modo
                            <code>cron_linux</code> ou <code>cron_windows</code>.
                        </li>
                    </ol>

                    <div class="alerta alerta--atencao">
                        <?php echo icone_svg('info'); ?>
                        <p>
                            <strong>Dicas de seguranca:</strong> o token do usuario e unico e pode ser
                            regenerado a qualquer momento; se vazar, gere um novo e atualize o job no
                            cron-job.org. A chave de API do cron-job.org e unica para a conta do SaaS e nao
                            deve ser compartilhada. Para muitos usuarios, o plano gratuito pode nao ser
                            suficiente: considere um plano pago ou o agendamento pelo sistema operacional.
                        </p>
                    </div>
                </div>
            </details>
        </div>
    </section>
<?php } ?>

<?php /* Limite de execucoes manuais do usuario comum. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Execucoes manuais do usuario</h3>
        <span class="selo selo--neutro">Nao vale para o admin</span>
    </div>

    <div class="cartao__corpo">
        <form class="formulario formulario--duas-colunas" method="post"
              action="<?php echo site_url('admin/cron/limite-manual'); ?>">
            <div class="campo">
                <label class="campo__rotulo" for="limite_execucoes_manuais">Limite por dia (por usuario)</label>
                <input class="campo__entrada" type="number" id="limite_execucoes_manuais"
                       name="limite_execucoes_manuais" min="0" max="100" required
                       value="<?php echo (int) $limiteManual; ?>">
                <span class="campo__ajuda">
                    Soma busca de vagas e envio de e-mails. Use 0 para desabilitar as execucoes manuais.
                    O contador zera sozinho a cada dia.
                </span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario botao--pequeno">Salvar limite</button>
            </div>
        </form>

        <?php if ($usuariosDoPainel === []) { ?>
            <p class="texto-suave">Nenhum usuario ativo no momento.</p>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Executadas hoje</th>
                            <th scope="col">Restantes</th>
                            <th scope="col">Situacao</th>
                            <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuariosDoPainel as $usuario) { ?>
                            <?php
                            // Situacao visual do limite deste usuario.
                            $classeSelo = 'selo--neutro';
                            $rotuloSelo = 'Bloqueado';

                            if ($usuario['motivo'] === 'disponivel') {
                                $classeSelo = 'selo--sucesso';
                                $rotuloSelo = 'Disponivel';
                            } elseif ($usuario['motivo'] === 'limite_atingido') {
                                $classeSelo = 'selo--aviso';
                                $rotuloSelo = 'Limite atingido';
                            }
                            ?>
                            <tr>
                                <th scope="row"><?php echo esc($usuario['nome']); ?></th>
                                <td>
                                    <?php echo (int) $usuario['execucoes_hoje']; ?>
                                    / <?php echo (int) $limiteManual; ?>
                                </td>
                                <td><?php echo (int) $usuario['restantes']; ?></td>
                                <td><span class="selo <?php echo esc($classeSelo); ?>"><?php echo esc($rotuloSelo); ?></span></td>
                                <td>
                                    <div class="acoes-tabela">
                                        <form method="post"
                                              action="<?php echo site_url('admin/cron/zerar-manual/' . (int) $usuario['id']); ?>"
                                              data-confirmar="Zerar o contador de execucoes manuais deste usuario hoje?">
                                            <button type="submit" class="botao botao--fantasma botao--pequeno">
                                                <?php echo icone_svg('relogio'); ?> Zerar contador
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Tokens e jobs por usuario. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Tokens e jobs por usuario</h3>

        <?php if ($modoEhManual) { ?>
            <span class="selo selo--info">Token externo ativo</span>
        <?php } else { ?>
            <span class="selo selo--neutro">Jobs indisponiveis neste modo</span>
        <?php } ?>
    </div>

    <div class="cartao__corpo">
        <?php if ($usuariosDoPainel === []) { ?>
            <p class="texto-suave">Nenhum usuario ativo no momento.</p>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Token externo</th>
                            <?php foreach ($tiposJob as $item) { ?>
                                <th scope="col">Job de <?php echo esc($item['rotulo']); ?></th>
                            <?php } ?>
                            <th scope="col"><span class="visualmente-oculto">Acoes</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuariosDoPainel as $usuario) { ?>
                            <tr>
                                <th scope="row">
                                    <span class="item-lista__titulo"><?php echo esc($usuario['nome']); ?></span>
                                    <span class="item-lista__meta">
                                        #<?php echo (int) $usuario['id']; ?> &middot;
                                        <?php echo esc($usuario['email']); ?>
                                    </span>
                                </th>

                                <td>
                                    <?php if ($usuario['tem_token']) { ?>
                                        <code class="codigo-mascarado"><?php echo esc($usuario['job_vagas']['token_mascarado']); ?></code>
                                    <?php } else { ?>
                                        <span class="texto-suave texto-pequeno">Sem token</span>
                                    <?php } ?>
                                </td>

                                <?php foreach ($tiposJob as $item) { ?>
                                    <?php $job = $usuario[$item['chave']]; ?>
                                    <td>
                                        <?php if (! $job['existe']) { ?>
                                            <span class="texto-suave texto-pequeno">Sem job</span>

                                            <?php if ($modoEhManual) { ?>
                                                <form method="post"
                                                      action="<?php echo site_url('admin/cron/jobs/criar'); ?>"
                                                      data-confirmar="Criar o job de <?php echo esc($item['rotulo']); ?> para este usuario?">
                                                    <input type="hidden" name="usuario_id"
                                                           value="<?php echo (int) $usuario['id']; ?>">
                                                    <input type="hidden" name="tipo" value="<?php echo esc($item['tipo']); ?>">
                                                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                                                        <?php echo icone_svg('raio'); ?> Criar
                                                    </button>
                                                </form>
                                            <?php } ?>
                                        <?php } elseif ($job['job_id'] === null) { ?>
                                            <span class="selo selo--aviso">Ainda nao criado no servico</span>
                                        <?php } else { ?>
                                            <span class="selo selo--sucesso">Job #<?php echo (int) $job['job_id']; ?></span>

                                            <p class="item-lista__meta">
                                                Ultimo status: <?php echo esc($job['ultimo_status']); ?>
                                                &middot; <?php echo esc(formatar_data_hora($job['ultima_execucao'])); ?>
                                            </p>

                                            <?php if ($modoEhManual) { ?>
                                                <form method="post"
                                                      action="<?php echo site_url('admin/cron/jobs/excluir/' . (int) $usuario['id'] . '/' . $item['tipo']); ?>"
                                                      data-confirmar="Excluir o job de <?php echo esc($item['rotulo']); ?> no cron-job.org?">
                                                    <button type="submit" class="botao botao--fantasma botao--pequeno">
                                                        <?php echo icone_svg('lixeira'); ?> Excluir
                                                    </button>
                                                </form>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                <?php } ?>

                                <td>
                                    <div class="acoes-tabela">
                                        <form method="post"
                                              action="<?php echo site_url('admin/cron/usuario/' . (int) $usuario['id'] . '/token'); ?>"
                                              data-confirmar="Gerar um token novo? Os jobs deste usuario precisam ser atualizados depois.">
                                            <button type="submit" class="botao botao--fantasma botao--pequeno">
                                                <?php echo icone_svg('cadeado'); ?> Regenerar token
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Ultimas execucoes manuais registradas. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Ultimas execucoes manuais</h3>
        <span class="selo selo--neutro">Ate 10 registros</span>
    </div>

    <div class="cartao__corpo">
        <?php if ($execucoesRecentes === []) { ?>
            <div class="estado-vazio estado-vazio--compacto">
                <?php echo icone_svg('relogio'); ?>
                <h3>Nenhuma execucao manual registrada</h3>
                <p class="texto-suave">
                    O contador so aparece quando um usuario dispara a busca ou o envio pelo painel dele.
                </p>
            </div>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">Execucoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($execucoesRecentes as $execucao) { ?>
                            <tr>
                                <th scope="row"><?php echo esc(formatar_data_hora($execucao['data'], false)); ?></th>
                                <td>
                                    <?php echo esc($execucao['usuario_nome']); ?>
                                    <span class="item-lista__meta"><?php echo esc($execucao['usuario_email']); ?></span>
                                </td>
                                <td><?php echo (int) $execucao['total_execucoes']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Segundo provedor: o bot do Divulga Vagas. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Divulga Vagas (segundo provedor)</h3>
        <span class="selo selo--info">Bot no site</span>
    </div>

    <div class="cartao__corpo">
        <div class="alerta alerta--info">
            <?php echo icone_svg('info'); ?>
            <p>
                O segundo provedor usa um bot (Python + Playwright) que trabalha no site do
                Divulga Vagas. Ele tem <strong>dois agendadores separados</strong> e nao
                divide agenda com o SerpApi:
            </p>
        </div>

        <ul class="lista-limpa lista-marcada">
            <li>
                <strong>CRON 1 (buscar)</strong>: procura vagas no site e salva na fila do
                usuario. <code>POST /cron/global/divulgavagas/buscar-vagas?token=...</code>
            </li>
            <li>
                <strong>CRON 2 (candidatar)</strong>: o bot se candidata pelo site e, quando a
                vaga tem e-mail de contacto, o e-mail tambem e enviado.
                <code>POST /cron/global/divulgavagas/candidatar?token=...</code>
            </li>
        </ul>

        <p class="texto-suave texto-pequeno">
            O token global e o mesmo das rotas antigas. As execucoes manuais (com alvo em
            todos os usuarios ou em um so) ficam na tela do segundo provedor.
        </p>

        <div class="linha-acoes">
            <a class="botao botao--secundario botao--pequeno" href="<?php echo site_url('admin/divulgavagas'); ?>">
                <?php echo icone_svg('raio'); ?> Abrir o painel do Divulga Vagas
            </a>
            <a class="botao botao--fantasma botao--pequeno" href="<?php echo site_url('admin/divulgavagas/historico'); ?>">
                <?php echo icone_svg('relogio'); ?> Historico das execucoes
            </a>
        </div>
    </div>
</section>

<?php /* Token global legado, usado apenas pelas rotas antigas /cron/... */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Token global (legado)</h3>
        <span class="selo selo--aviso">Segredo</span>
    </div>

    <div class="cartao__corpo">
        <div class="alerta alerta--info">
            <?php echo icone_svg('info'); ?>
            <p>
                Token unico do sistema, usado apenas pelas rotas antigas <code>/cron/...</code>.
                No modo <code>spark_manual</code> o disparo externo usa o token individual de cada
                usuario, mostrado na tabela acima.
            </p>
        </div>

        <div class="caixa-token">
            <code id="cron-token-global"><?php echo esc($tokenGlobal); ?></code>
            <button type="button" class="botao botao--secundario botao--pequeno" data-copiar="cron-token-global">
                <?php echo icone_svg('documento'); ?> Copiar
            </button>
        </div>

        <form method="post" action="<?php echo site_url('admin/cron/gerar-token-global'); ?>"
              data-confirmar="Gerar um token global novo? Quem usa as rotas antigas precisa ser atualizado.">
            <div class="formulario__acoes">
                <button type="submit" class="botao botao--fantasma botao--pequeno">
                    <?php echo icone_svg('cadeado'); ?> Regenerar token global
                </button>
            </div>
        </form>
    </div>
</section>

<?php $this->endSection(); ?>
