<?php
/**
 * --------------------------------------------------------------------------
 * VIEW: PAINEL - CONFIGURACOES
 * --------------------------------------------------------------------------
 *
 * Seis secoes: perfil, seguranca, e-mail, mensagem, curriculo e envio.
 *
 * Nenhum campo sensivel (senha/APP PASSWORD) e repreenchido com old input.
 */

$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Configuracoes';

// Input antigo gravado pelo controller (sem campos sensiveis).
$oldInput = session()->getFlashdata('_ci_old_input');
$temOld   = is_array($oldInput) && isset($oldInput['post']);
$postOld  = [];

if ($temOld) {
    $postOld = $oldInput['post'];
}

// Dias da semana selecionados.
$diasSelecionados = explode(',', texto_ou($configuracao, 'dias_semana_permitidos', '1,2,3,4,5'));

if ($temOld) {
    $diasSelecionados = valor_ou($postOld, 'dias_semana', []);
}

$diasSelecionados = array_map('intval', $diasSelecionados);

// Estado do envio automatico.
$automaticoLigado = inteiro_ou($usuario, 'envio_automatico', 0) === 1;

if ($temOld) {
    $automaticoLigado = isset($postOld['envio_automatico']);
}

// Nomes dos dias da semana.
$nomesDias = [1 => 'Segunda', 2 => 'Terca', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sabado', 7 => 'Domingo'];

// Atalhos de secao.
$secoes = [
    'perfil'      => 'Perfil',
    'seguranca'   => 'Seguranca',
    'email'       => 'E-mail',
    'mensagem'    => 'Mensagem',
    'curriculo'   => 'Curriculo',
    'envio'       => 'Envio',
];
?>

<?php echo  $this->section('conteudo') ; ?>

<section class="painel__cabecalho">
    <div class="painel__cabecalho-texto">
        <h2 class="painel__saudacao">Configuracoes</h2>
        <p class="painel__descricao">Ajuste sua conta, seu e-mail de envio e os limites das candidaturas.</p>
    </div>
</section>

<?php /* Navegacao por ancoras. */ ?>
<nav class="abas abas--ancoras" aria-label="Secoes das configuracoes">
    <?php foreach ($secoes as $id => $rotulo){ ?>
        <a class="aba" href="#<?php echo  esc($id) ; ?>"><?php echo  esc($rotulo) ; ?></a>
    <?php } ?>
</nav>

<?php /* ------------------------------------------------------------------
      1. PERFIL
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="perfil">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Perfil</h3>
        <span class="selo <?php echo  escolher(valor_ou($usuario, 'status', '') === 'ativo', 'selo--sucesso', 'selo--aviso') ; ?>">
            <?php echo  esc(ucfirst(texto_ou($usuario, 'status', 'pendente'))) ; ?>
        </span>
    </div>
    <div class="cartao__corpo">
        <form class="formulario formulario--duas-colunas" method="post"
              action="<?php echo  site_url('painel/configuracoes/perfil') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="nome">Nome completo</label>
                <input class="campo__entrada" type="text" id="nome" name="nome" maxlength="150" required
                       value="<?php echo  esc(old('nome', valor_ou($usuario, 'nome', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="email">E-mail de acesso</label>
                <input class="campo__entrada" type="email" id="email" name="email" maxlength="180" required
                       value="<?php echo  esc(old('email', valor_ou($usuario, 'email', ''))) ; ?>">
                <span class="campo__ajuda">E usado para entrar no sistema (nao e o e-mail de envio).</span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('check') ; ?> Salvar perfil</button>
            </div>
        </form>

        <p class="texto-suave texto-pequeno">
            Conta criada em <?php echo  esc(formatar_data_hora(valor_ou($usuario, 'criado_em', null), false)) ; ?>.
        </p>
    </div>
</section>

<?php /* ------------------------------------------------------------------
      2. SEGURANCA
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="seguranca">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Seguranca</h3>
    </div>
    <div class="cartao__corpo">
        <form class="formulario" method="post" action="<?php echo  site_url('painel/configuracoes/senha') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="senha_atual">Senha atual</label>
                <div class="campo__com-acao">
                    <input class="campo__entrada" type="password" id="senha_atual" name="senha_atual"
                           autocomplete="current-password" required>
                    <button type="button" class="campo__ver-senha" data-alvo="senha_atual"
                            aria-label="Mostrar senha">Ver</button>
                </div>
            </div>

            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="nova_senha">Nova senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="nova_senha" name="nova_senha"
                               autocomplete="new-password" minlength="6" required>
                        <button type="button" class="campo__ver-senha" data-alvo="nova_senha"
                                aria-label="Mostrar senha">Ver</button>
                    </div>
                    <span class="campo__ajuda">Minimo de 6 caracteres.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="senha_confirmacao">Confirmar nova senha</label>
                    <div class="campo__com-acao">
                        <input class="campo__entrada" type="password" id="senha_confirmacao" name="senha_confirmacao"
                               autocomplete="new-password" minlength="6" required>
                        <button type="button" class="campo__ver-senha" data-alvo="senha_confirmacao"
                                aria-label="Mostrar senha">Ver</button>
                    </div>
                </div>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('cadeado') ; ?> Alterar senha</button>
            </div>
        </form>
    </div>
</section>

<?php /* ------------------------------------------------------------------
      3. E-MAIL E APP PASSWORD
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="email">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">E-mail de envio</h3>
        <span class="selo <?php echo  escolher($configuracao['tem_app_password'], 'selo--sucesso', 'selo--aviso') ; ?>">
            APP PASSWORD: <?php echo  escolher($configuracao['tem_app_password'], 'configurada', 'nao configurada') ; ?>
        </span>
    </div>
    <div class="cartao__corpo">
        <div class="alerta alerta--info">
            <?php echo  icone_svg('info') ; ?>
            <p>
                Use uma <strong>senha de aplicativo</strong> do Gmail (16 letras), nunca a senha normal da conta.
                Ela e guardada criptografada e nunca e exibida de volta.
            </p>
        </div>

        <form class="formulario formulario--duas-colunas" method="post"
              action="<?php echo  site_url('painel/configuracoes/email') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="email_remetente">E-mail remetente (Gmail)</label>
                <input class="campo__entrada" type="email" id="email_remetente" name="email_remetente"
                       maxlength="180" required
                       value="<?php echo  esc(old('email_remetente', valor_ou($configuracao, 'email_remetente', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="nome_remetente">Nome do remetente</label>
                <input class="campo__entrada" type="text" id="nome_remetente" name="nome_remetente"
                       maxlength="150" required
                       value="<?php echo  esc(old('nome_remetente', valor_ou($configuracao, 'nome_remetente', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="telefone">Telefone (opcional)</label>
                <input class="campo__entrada" type="tel" id="telefone" name="telefone" maxlength="40"
                       data-mascara="telefone"
                       value="<?php echo  esc(old('telefone', valor_ou($configuracao, 'telefone', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="app_password">APP PASSWORD do Gmail</label>
                <div class="campo__com-acao">
                    <input class="campo__entrada" type="password" id="app_password" name="app_password"
                           autocomplete="new-password" maxlength="19"
                           placeholder="<?php echo  escolher($configuracao['tem_app_password'], 'Deixe em branco para manter', '16 letras') ; ?>">
                    <button type="button" class="campo__ver-senha" data-alvo="app_password"
                            aria-label="Mostrar senha">Ver</button>
                </div>
                <span class="campo__ajuda">
                    So preencha para cadastrar ou trocar. O valor atual nunca e mostrado.
                </span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('email') ; ?> Salvar e-mail</button>
            </div>
        </form>

        <?php if ($configuracao['tem_app_password']){ ?>
            <form method="post" action="<?php echo  site_url('painel/configuracoes/email/remover-app-password') ; ?>"
                  data-confirmar="Remover a APP PASSWORD? O envio automatico sera interrompido.">
                <button type="submit" class="botao botao--fantasma botao--perigo">
                    <?php echo  icone_svg('lixeira') ; ?> Remover APP PASSWORD
                </button>
            </form>
        <?php } ?>
    </div>
</section>

<?php /* ------------------------------------------------------------------
      4. MENSAGEM PADRAO
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="mensagem">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Mensagem padrao</h3>
    </div>
    <div class="cartao__corpo">
        <p class="texto-suave texto-pequeno">
            Variaveis disponiveis:
            <span class="tag">{titulo_vaga}</span>
            <span class="tag">{empresa}</span>
            <span class="tag">{localizacao}</span>
            <span class="tag">{seu_nome}</span>
            <span class="tag">{telefone}</span>
        </p>

        <form class="formulario" method="post" action="<?php echo  site_url('painel/configuracoes/mensagem') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="assunto_padrao">Assunto</label>
                <input class="campo__entrada" type="text" id="assunto_padrao" name="assunto_padrao"
                       maxlength="255" required
                       value="<?php echo  esc(old('assunto_padrao', valor_ou($configuracao, 'assunto_padrao', ''))) ; ?>">
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="corpo_padrao">Corpo do e-mail</label>
                <textarea class="campo__entrada campo__entrada--area" id="corpo_padrao" name="corpo_padrao"
                          rows="10" required><?php echo  esc(old('corpo_padrao', valor_ou($configuracao, 'corpo_padrao', ''))) ; ?></textarea>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="assinatura">Assinatura (opcional)</label>
                <textarea class="campo__entrada campo__entrada--area" id="assinatura" name="assinatura"
                          rows="4"><?php echo  esc(old('assinatura', valor_ou($configuracao, 'assinatura', ''))) ; ?></textarea>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('check') ; ?> Salvar mensagem</button>
            </div>
        </form>
    </div>
</section>

<?php /* ------------------------------------------------------------------
      5. CURRICULO
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="curriculo">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Curriculo em PDF</h3>
        <span class="selo <?php echo  escolher($configuracao['tem_curriculo'], 'selo--sucesso', 'selo--aviso') ; ?>">
            <?php echo  escolher($configuracao['tem_curriculo'], 'Enviado', 'Nenhum arquivo') ; ?>
        </span>
    </div>
    <div class="cartao__corpo">
        <?php if ($configuracao['tem_curriculo']){ ?>
            <div class="arquivo-atual">
                <?php echo  icone_svg('documento') ; ?>
                <div>
                    <strong>Curriculo enviado</strong>
                    <p class="texto-suave texto-pequeno">
                        <?php echo  esc(formatar_tamanho((int) $configuracao['curriculo_tamanho'])) ; ?> &middot; PDF
                    </p>
                </div>
                <a class="botao botao--secundario botao--pequeno"
                   href="<?php echo  site_url('painel/configuracoes/curriculo') ; ?>">
                    Baixar
                </a>
            </div>
        <?php } else { ?>
            <p class="texto-suave">
                Envie seu curriculo em PDF (ate 5 MB). Ele sera anexado automaticamente nas candidaturas.
            </p>
        <?php } ?>

        <form class="formulario" method="post" enctype="multipart/form-data"
              action="<?php echo  site_url('painel/configuracoes/curriculo') ; ?>">

            <div class="campo">
                <label class="campo__rotulo" for="curriculo"><?php echo  escolher($configuracao['tem_curriculo'], 'Substituir curriculo', 'Enviar curriculo') ; ?></label>
                <input class="campo__entrada" type="file" id="curriculo" name="curriculo"
                       accept="application/pdf,.pdf" required
                       data-nome-alvo="curriculo-nome">
                <span class="campo__ajuda" id="curriculo-nome">Formato PDF, ate 5 MB.</span>
            </div>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('upload') ; ?> Enviar PDF</button>
            </div>
        </form>

        <?php if ($configuracao['tem_curriculo']){ ?>
            <form method="post" action="<?php echo  site_url('painel/configuracoes/curriculo/remover') ; ?>"
                  data-confirmar="Remover o curriculo atual?">
                <button type="submit" class="botao botao--fantasma botao--perigo">
                    <?php echo  icone_svg('lixeira') ; ?> Remover curriculo
                </button>
            </form>
        <?php } ?>
    </div>
</section>

<?php /* ------------------------------------------------------------------
      6. ENVIO
      ------------------------------------------------------------------ */ ?>
<section class="cartao" id="envio">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Limites e horarios de envio</h3>
        <span class="selo <?php echo  escolher($janela['aberta'], 'selo--sucesso', 'selo--neutro') ; ?>">
            <?php echo  escolher($janela['aberta'], 'Dentro da janela', 'Fora da janela') ; ?>
        </span>
    </div>
    <div class="cartao__corpo">
        <p class="texto-suave texto-pequeno">
            Hoje: <?php echo  (int) $enviosHoje ; ?> de <?php echo  (int) $limiteDiario ; ?> envio(s) permitido(s).
        </p>

        <form class="formulario" method="post" action="<?php echo  site_url('painel/configuracoes/envio') ; ?>">

            <div class="formulario--duas-colunas">
                <div class="campo">
                    <label class="campo__rotulo" for="limite_envios_dia">Limite de envios por dia</label>
                    <input class="campo__entrada" type="number" id="limite_envios_dia" name="limite_envios_dia"
                           min="1" max="100" required
                           value="<?php echo  esc(old('limite_envios_dia', valor_ou($configuracao, 'limite_envios_dia', 20))) ; ?>">
                    <span class="campo__ajuda">Recomendado: ate 20 envios por dia.</span>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="fuso_horario">Fuso horario</label>
                    <select class="campo__entrada" id="fuso_horario" name="fuso_horario">
                        <?php $fusoAtual = (string) old('fuso_horario', valor_ou($configuracao, 'fuso_horario', 'America/Sao_Paulo')); ?>
                        <?php foreach ($fusoHorarios as $valor => $rotulo){ ?>
                            <option value="<?php echo  esc($valor) ; ?>" <?php echo escolher($valor === $fusoAtual, 'selected', ''); ?>>
                                <?php echo  esc($rotulo) ; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="intervalo_minimo_segundos">Intervalo minimo (segundos)</label>
                    <input class="campo__entrada" type="number" id="intervalo_minimo_segundos"
                           name="intervalo_minimo_segundos" min="30" max="3600" required
                           value="<?php echo  esc(old('intervalo_minimo_segundos', valor_ou($configuracao, 'intervalo_minimo_segundos', 180))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="intervalo_maximo_segundos">Intervalo maximo (segundos)</label>
                    <input class="campo__entrada" type="number" id="intervalo_maximo_segundos"
                           name="intervalo_maximo_segundos" min="30" max="3600" required
                           value="<?php echo  esc(old('intervalo_maximo_segundos', valor_ou($configuracao, 'intervalo_maximo_segundos', 420))) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="horario_inicio_envio">Inicio do envio</label>
                    <input class="campo__entrada" type="time" id="horario_inicio_envio" name="horario_inicio_envio"
                           required
                           value="<?php echo  esc(substr((string) old('horario_inicio_envio', valor_ou($configuracao, 'horario_inicio_envio', '08:00')), 0, 5)) ; ?>">
                </div>

                <div class="campo">
                    <label class="campo__rotulo" for="horario_fim_envio">Fim do envio</label>
                    <input class="campo__entrada" type="time" id="horario_fim_envio" name="horario_fim_envio"
                           required
                           value="<?php echo  esc(substr((string) old('horario_fim_envio', valor_ou($configuracao, 'horario_fim_envio', '20:00')), 0, 5)) ; ?>">
                </div>
            </div>

            <div class="campo">
                <span class="campo__rotulo">Dias permitidos</span>
                <div class="dias-semana">
                    <?php foreach ($nomesDias as $numero => $nome){ ?>
                        <label class="dia-semana">
                            <input type="checkbox" name="dias_semana[]" value="<?php echo  (int) $numero ; ?>"
                                   <?php echo  escolher(in_array($numero, $diasSelecionados, true), 'checked', '') ; ?>>
                            <span><?php echo  esc($nome) ; ?></span>
                        </label>
                    <?php } ?>
                </div>
            </div>

            <?php /* Interruptor do envio automatico. */ ?>
            <input type="hidden" name="envio_automatico_enviado" value="1">

            <label class="interruptor">
                <input type="checkbox" name="envio_automatico" value="1" <?php echo escolher($automaticoLigado, 'checked', ''); ?>>
                <span class="interruptor__trilho"></span>
                <span>Ativar envio automatico</span>
            </label>

            <div class="formulario__acoes">
                <button type="submit" class="botao botao--primario"><?php echo  icone_svg('check') ; ?> Salvar configuracoes de envio</button>
            </div>
        </form>
    </div>
</section>

<?php echo  $this->endSection() ; ?>
