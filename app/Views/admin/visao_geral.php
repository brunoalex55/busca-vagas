<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: ADMIN - VISAO GERAL
 * --------------------------------------------------------------------------
 *
 * Dashboard administrativo com os numeros reais do sistema:
 *   - cards de usuarios, vagas, candidaturas, consultas, chaves e solicitacoes;
 *   - ultimas contas criadas e situacao das chaves;
 *   - resumo das tarefas automaticas (crons).
 */

$this->extend('layouts/admin');

// Titulo exibido no topo.
$tituloPagina = 'Visao geral';

/**
 * Devolve a classe e o rotulo do selo conforme o status da conta.
 */
$seloStatus = static function (string $status): array {
    return match ($status) {
        'ativo'     => ['selo--sucesso', 'Ativo'],
        'bloqueado' => ['selo--erro', 'Bloqueado'],
        default     => ['selo--aviso', 'Pendente'],
    };
};
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Visao geral</h2>
        <p class="painel__descricao">Panorama do sistema: usuarios, vagas, candidaturas e automacoes.</p>
    </div>

    <div class="painel__acoes">
        <a class="botao botao--secundario botao--pequeno" href="<?php echo  site_url('admin/chaves') ; ?>">
            <?php echo  icone_svg('raio') ; ?> Gerenciar chaves
        </a>
        <?php if ((int) $solicitacoes['pendente'] > 0){ ?>
            <a class="botao botao--primario botao--pequeno" href="<?php echo  site_url('admin/solicitacoes') ; ?>">
                <?php echo  icone_svg('documento') ; ?> <?php echo  (int) $solicitacoes['pendente'] ; ?> solicitacao(oes)
            </a>
        <?php } ?>
    </div>
</section>

<?php /* Cards de metrica. */ ?>
<div class="grade-metricas">

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('usuario') ; ?></span>
        <span class="cartao-metrica__rotulo">Usuarios ativos</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $usuarios['ativo'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  (int) $usuarios['total'] ; ?> no total &middot;
            <?php echo  (int) $usuarios['pendente'] ; ?> pendente(s) &middot;
            <?php echo  (int) $usuarios['bloqueado'] ; ?> bloqueado(s)
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--roxo"><?php echo  icone_svg('documento') ; ?></span>
        <span class="cartao-metrica__rotulo">Vagas na base</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $vagasTotal, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  number_format((int) $vagasFila, 0, ',', '.') ; ?> na fila de candidatura.
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--verde"><?php echo  icone_svg('check') ; ?></span>
        <span class="cartao-metrica__rotulo">Vagas candidatadas</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $candidaturasFeitas, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            Vagas em que o usuario ja se candidatou (pelo site ou por e-mail).
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('email') ; ?></span>
        <span class="cartao-metrica__rotulo">Candidaturas por e-mail</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $envios['enviados'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  number_format((int) $envios['hoje'], 0, ',', '.') ; ?> hoje &middot;
            <?php echo  number_format((int) $envios['falhas'], 0, ',', '.') ; ?> falha(s)
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('lupa') ; ?></span>
        <span class="cartao-metrica__rotulo">Consultas ao provedor</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $consultas['total'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  number_format((int) $consultas['sucesso'], 0, ',', '.') ; ?> com sucesso &middot;
            <?php echo  number_format((int) $consultas['falhas'], 0, ',', '.') ; ?> com falha
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--azul"><?php echo  icone_svg('raio') ; ?></span>
        <span class="cartao-metrica__rotulo">Saldo das chaves</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $saldoChaves, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  count($chaves) ; ?> chave(s) cadastrada(s).
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('relogio') ; ?></span>
        <span class="cartao-metrica__rotulo">Solicitacoes pendentes</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $solicitacoes['pendente'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php echo  (int) $solicitacoes['total'] ; ?> no historico.
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--roxo"><?php echo  icone_svg('usuario') ; ?></span>
        <span class="cartao-metrica__rotulo">Usuarios por plano</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $usuarios['total'], 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            <?php
            // A lista de planos com o separador entre eles.
            $separador = '';

            foreach ($planos as $plano){
                echo esc($separador);
                echo esc($plano['nome']);
                echo ': ';
                echo esc((int) $plano['usuarios']);

                // O plano esgotado avisa.
                if ((int) $plano['usuarios'] >= (int) $plano['max_usuarios']) {
                    echo ' (esgotado)';
                }

                $separador = ' &middot; ';
            }
            ?>
        </span>
    </article>

    <article class="cartao cartao-metrica">
        <span class="cartao-metrica__icone cartao-metrica__icone--laranja"><?php echo  icone_svg('email') ; ?></span>
        <span class="cartao-metrica__rotulo">No limite de envios</span>
        <strong class="cartao-metrica__valor"><?php echo  number_format((int) $usuariosNoLimite, 0, ',', '.') ; ?></strong>
        <span class="cartao-metrica__detalhe">
            Usuario(s) que ja gastaram o limite diario de envios.
        </span>
    </article>
</div>

<?php /* O alerta de plano esgotado. */ ?>
<?php foreach ($planos as $plano){ ?>
    <?php if ((int) $plano['ativo'] === 1 && (int) $plano['usuarios'] >= (int) $plano['max_usuarios']){ ?>
        <div class="alerta alerta--atencao">
            <?php echo  icone_svg('info') ; ?>
            <p>
                O plano <strong><?php echo esc($plano['nome']) ; ?></strong> chegou ao limite
                (<?php echo esc((int) $plano['usuarios']) ; ?> de <?php echo esc((int) $plano['max_usuarios']) ; ?>
                usuario(s)). Nao da para atribuir este plano a mais ninguem.
            </p>
        </div>
    <?php } ?>
<?php } ?>

<?php /* As candidaturas realizadas (todos os provedores). */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Candidaturas realizadas</h3>
        <a class="cartao__link" href="<?php echo  site_url('admin/divulgavagas/historico') ; ?>">Ver o historico</a>
    </div>

    <div class="cartao__corpo">
        <?php if (empty($candidaturasFeitasLista)){ ?>
            <p class="texto-suave">
                Nenhuma candidatura confirmada ate agora.
            </p>
        <?php } else { ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Usuario</th>
                            <th scope="col">Vaga</th>
                            <th scope="col">Como foi feita</th>
                            <th scope="col">Confirmacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidaturasFeitasLista as $candidatura){ ?>
                            <?php
                            // A candidatura pelo site (o bot) e a de e-mail.
                            $peloSite = in_array(texto_ou($candidatura, 'candidatura_divulgavagas_status'), ['enviada', 'enviada_email'], true);
                            $porEmail = (int) texto_ou($candidatura, 'ja_candidatado', 0) === 1;
                            $confirmacao = texto_ou($candidatura, 'candidatura_divulgavagas_confirmado_em', '');

                            if ($confirmacao === '') {
                                $confirmacao = texto_ou($candidatura, 'candidatura_divulgavagas_em', '');
                            }
                            ?>
                            <tr>
                                <th scope="row">
                                    <?php echo  esc(primeiro_valor($candidatura['usuario_nome'], 'Usuario #' . (int) $candidatura['usuario_id'])) ; ?>
                                </th>
                                <td>
                                    <?php echo  esc(primeiro_valor($candidatura['titulo'], 'Vaga sem titulo')) ; ?>
                                    <span class="item-lista__meta"><?php echo  esc(primeiro_valor($candidatura['empresa'], '-')) ; ?></span>
                                </td>
                                <td>
                                    <?php if ($peloSite){ ?>
                                        <span class="selo selo--divulgavagas-enviada">Pelo site</span>
                                    <?php } ?>
                                    <?php if ($porEmail){ ?>
                                        <span class="selo selo--sucesso">Por e-mail</span>
                                    <?php } ?>
                                    <?php if (! $peloSite && ! $porEmail){ ?>
                                        <span class="selo selo--neutro">-</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($confirmacao !== ''){ ?>
                                        <span class="selo selo--sucesso">Confirmada</span>
                                        <span class="item-lista__meta">
                                            <?php echo  esc(formatar_data_hora($confirmacao, false)) ; ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="texto-suave texto-pequeno">-</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<?php /* Duas colunas: contas recentes e chaves. */ ?>
<div class="grade grade--painel">

    <?php /* Contas recentes. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Contas recentes</h3>
            <a class="cartao__link" href="<?php echo  site_url('admin/usuarios') ; ?>">Ver todas</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($usuariosRecentes === []){ ?>
                <div class="estado-vazio estado-vazio--compacto">
                    <?php echo  icone_svg('usuario') ; ?>
                    <p>Nenhuma conta cadastrada ainda.</p>
                </div>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($usuariosRecentes as $conta){ ?>
                        <?php [$classeSelo, $rotuloSelo] = $seloStatus((string) $conta['status']); ?>
                        <li class="item-lista item-lista--linha">
                            <div class="item-lista__texto">
                                <a class="item-lista__titulo" href="<?php echo  site_url('admin/usuarios/' . (int) $conta['id']) ; ?>">
                                    <?php echo  esc($conta['nome']) ; ?>
                                </a>
                                <p class="item-lista__meta"><?php echo  esc($conta['email']) ; ?></p>
                            </div>
                            <span class="selo <?php echo  $classeSelo ; ?>"><?php echo  $rotuloSelo ; ?></span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>

    <?php /* Chaves. */ ?>
    <section class="cartao">
        <div class="cartao__cabecalho">
            <h3 class="cartao__titulo">Chaves de busca</h3>
            <a class="cartao__link" href="<?php echo  site_url('admin/chaves') ; ?>">Gerenciar</a>
        </div>
        <div class="cartao__corpo">
            <?php if ($chaves === []){ ?>
                <div class="estado-vazio estado-vazio--compacto">
                    <?php echo  icone_svg('raio') ; ?>
                    <p>Nenhuma chave cadastrada.</p>
                    <p class="texto-suave texto-pequeno">Sem chaves, o CRON 1 nao consegue buscar vagas.</p>
                </div>
            <?php } else { ?>
                <ul class="lista-limpa">
                    <?php foreach ($chaves as $chave){ ?>
                        <?php
                        $total    = max(1, (int) $chave['quantidade_consultas']);
                        $percento = min(100, (int) round((int) $chave['consultas_usadas'] * 100 / $total));
                        ?>
                        <li class="item-lista">
                            <div class="item-lista__texto item-lista__texto--linha">
                                <span class="item-lista__titulo"><?php echo  esc($chave['apelido']) ; ?></span>
                                <span class="texto-suave texto-pequeno">
                                    <?php echo  (int) $chave['consultas_usadas'] ; ?> / <?php echo  (int) $chave['quantidade_consultas'] ; ?>
                                </span>
                            </div>
                            <span class="barra-progresso" role="img" aria-label="<?php echo  $percento ; ?>% utilizada">
                                <span class="barra-progresso__valor" style="width: <?php echo  $percento ; ?>%"></span>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </section>
</div>

<?php /* Tarefas automaticas. */ ?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Tarefas automaticas</h3>
        <a class="cartao__link" href="<?php echo  site_url('admin/cron') ; ?>">Ver agendamento</a>
    </div>
    <div class="cartao__corpo">
        <div class="grade grade--tres">
            <div class="bloco-resumo">
                <span class="bloco-resumo__rotulo">Usuarios no publico dos crons</span>
                <strong class="bloco-resumo__valor"><?php echo  number_format((int) $crons['ativos'], 0, ',', '.') ; ?></strong>
            </div>

            <div class="bloco-resumo">
                <span class="bloco-resumo__rotulo">Buscas nas ultimas 24h</span>
                <strong class="bloco-resumo__valor"><?php echo  number_format((int) $crons['busca_24h'], 0, ',', '.') ; ?></strong>
                <span class="texto-suave texto-pequeno">
                    Ultima: <?php echo  esc(formatar_data_hora(valor_ou($crons, 'ultima_busca', null), false)) ; ?>
                </span>
            </div>

            <div class="bloco-resumo">
                <span class="bloco-resumo__rotulo">Envios automaticos nas ultimas 24h</span>
                <strong class="bloco-resumo__valor"><?php echo  number_format((int) $crons['envio_24h'], 0, ',', '.') ; ?></strong>
                <span class="texto-suave texto-pequeno">
                    Ultimo: <?php echo  esc(formatar_data_hora(valor_ou($crons, 'ultimo_envio', null), false)) ; ?>
                </span>
            </div>
        </div>

        <p class="texto-suave texto-pequeno">
            Envio automatico ligado em <?php echo  (int) $usuarios['envio_ligado'] ; ?> conta(s) &middot;
            <?php echo  (int) $comCredencial ; ?> conta(s) com APP PASSWORD cadastrada.
        </p>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
