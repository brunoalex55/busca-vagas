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
 * TELA: HISTORICO DO DIVULGA VAGAS (PAINEL DO USUARIO)
 * --------------------------------------------------------------------------
 *
 * As ultimas execucoes do usuario no segundo provedor. E uma tela de
 * leitura: nada e disparado daqui.
 *
 * A variavel $execucoes chega pronta do controller
 * (PainelDivulgaVagas::historico).
 */

// A view usa o layout do painel (menu, toasts e scripts).
$this->extend('layouts/painel');

// Titulo exibido no topo do painel.
$tituloPagina = 'Historico do Divulga Vagas';

// Abre a secao de conteudo do layout do painel.
echo $this->section('conteudo');
?>
<section class="cartao">
    <div class="cartao__cabecalho">
        <h3 class="cartao__titulo">Historico de execucoes</h3>
        <a class="cartao__link" href="<?php echo esc(site_url('painel/divulgavagas')) ; ?>">Voltar a execucao manual</a>
    </div>

    <div class="cartao__corpo historico-execucoes">
        <?php if ($execucoes === []){ ?>
            <p class="texto-suave">Nenhuma execucao registrada ate agora.</p>
        <?php } else { ?>
            <div class="tabela-envolucao">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Tipo</th>
                            <th scope="col">Origem</th>
                            <th scope="col">Status</th>
                            <th scope="col">Vagas</th>
                            <th scope="col">Candidaturas</th>
                            <th scope="col">E-mails</th>
                            <th scope="col">Confirmadas</th>
                            <th scope="col">Erros</th>
                            <th scope="col">Quando</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($execucoes as $execucao){ ?>
                            <?php
                            // Rotulo do tipo da execucao.
                            $rotuloExecucao = 'Candidatura';

                            if (texto_ou($execucao, 'tipo') === 'buscar') {
                                $rotuloExecucao = 'Busca';
                            }

                            // Rotulo da origem.
                            $rotuloOrigem = 'Agendador';

                            if (texto_ou($execucao, 'origem') === 'manual_usuario') {
                                $rotuloOrigem = 'Manual';
                            } elseif (texto_ou($execucao, 'origem') === 'manual_admin') {
                                $rotuloOrigem = 'Administrador';
                            }

                            // Classe do selo de status.
                            $classeStatus = 'selo--neutro';

                            if (texto_ou($execucao, 'status') === 'sucesso') {
                                $classeStatus = 'selo--sucesso';
                            } elseif (texto_ou($execucao, 'status') === 'erro') {
                                $classeStatus = 'selo--perigo';
                            } elseif (texto_ou($execucao, 'status') === 'limite_atingido') {
                                $classeStatus = 'selo--aviso';
                            }
                            ?>
                            <tr>
                                <td><?php echo esc($rotuloExecucao) ; ?></td>
                                <td><?php echo esc($rotuloOrigem) ; ?></td>
                                <td><span class="selo <?php echo esc($classeStatus) ; ?>"><?php echo esc(texto_ou($execucao, 'status')) ; ?></span></td>
                                <td><?php echo esc((int) texto_ou($execucao, 'vagas_inseridas', 0)) ; ?></td>
                                <td><?php echo esc((int) texto_ou($execucao, 'candidaturas_enviadas', 0)) ; ?></td>
                                <td><?php echo esc((int) texto_ou($execucao, 'emails_enviados', 0)) ; ?></td>
                                <td>
                                    <?php $confirmadas = (int) texto_ou($execucao, 'confirmacoes', 0); ?>
                                    <?php if ($confirmadas > 0){ ?>
                                        <span class="selo selo--sucesso"><?php echo esc($confirmadas) ; ?></span>
                                    <?php } else { ?>
                                        <span class="texto-suave texto-pequeno">-</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo esc((int) texto_ou($execucao, 'erros', 0)) ; ?></td>
                                <td><?php echo esc(formatar_data_hora(texto_ou($execucao, 'iniciado_em'), false)) ; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <?php /* O detalhe de cada execucao: vaga por vaga. */ ?>
        <?php foreach ($execucoes as $execucao){ ?>
            <?php $detalhes = App\Models\ExecucaoDivulgaVagasModel::detalhesDe($execucao); ?>

            <?php if ($detalhes === []) { continue; } ?>

            <div class="cartao__corpo historico-execucoes">
                <p class="texto-suave texto-pequeno">
                    Execucao #<?php echo esc((int) $execucao['id']) ?>:
                    <?php echo esc(count($detalhes)) ; ?> vaga(s) processada(s).
                </p>

                <ul class="lista-limpa">
                    <?php foreach ($detalhes as $detalhe){ ?>
                        <?php
                        // O bot confirmou o envio nesta vaga?
                        $confirmou = trim((string) texto_ou($detalhe, 'confirmacao_url', '')) !== '';
                        ?>
                        <li class="item-lista item-lista--linha">
                            <div class="item-lista__texto">
                                <span class="item-lista__titulo">
                                    <?php echo esc(texto_ou($detalhe, 'vaga_titulo', 'Vaga sem titulo')) ; ?>
                                </span>
                                <p class="item-lista__meta">
                                    <?php echo esc(texto_ou($detalhe, 'vaga_empresa', '-')) ; ?>
                                    <?php if ((int) texto_ou($detalhe, 'emails_enviados', 0) > 0){ ?>
                                        | e-mail enviado
                                    <?php } ?>
                                </p>
                            </div>

                            <?php if ($confirmou){ ?>
                                <span class="selo selo--sucesso">Confirmada pelo site</span>
                            <?php } elseif ((int) texto_ou($detalhe, 'candidaturas_enviadas', 0) > 0){ ?>
                                <span class="selo selo--divulgavagas-enviada">Candidatura feita</span>
                            <?php } else { ?>
                                <span class="selo selo--erro">
                                    <?php echo esc(texto_ou($detalhe, 'erro', 'Nao foi possivel candatar')) ; ?>
                                </span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
</section>

<?php
// Fecha a secao de conteudo.
echo $this->endSection();
?>
