<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: JANELA DE ENVIO
 * --------------------------------------------------------------------------
 *
 * Verifica se o momento atual esta dentro dos dias e horarios que o
 * usuario permitiu para envio de candidaturas.
 *
 * O envio automatico somente pode ocorrer quando a janela esta aberta
 * e quando o limite diario ainda nao foi atingido.
 */
class JanelaEnvio
{
    /**
     * Avalia a janela de envio da configuracao informada.
     *
     * @param array<string, mixed> $configuracao Registro da tabela configuracoes.
     *
     * @return array{aberta: bool, motivo: string, hora_atual: string, dia_atual: string}
     */
    public function avaliar(array $configuracao, ?DateTimeImmutable $agora = null): array
    {
        // Fuso horario configurado pelo usuario (fallback para o do app).
        $fuso = texto_ou($configuracao, 'fuso_horario', 'America/Sao_Paulo');

        try {
            $zona = new DateTimeZone($fuso);
        } catch (\Throwable $e) {
            // Fuso invalido: usa o fuso padrao da aplicacao.
            $zona = new DateTimeZone(date_default_timezone_get());
        }

        // Momento atual no fuso do usuario.
        $momento = $agora;

        // Sem momento informado: usa o agora.
        if ($momento === null) {
            $momento = new DateTimeImmutable('now');
        }

        $agora = $momento->setTimezone($zona);

        // Dia da semana (1 = segunda ... 7 = domingo).
        $dia = (int) $agora->format('N');

        // Lista de dias permitidos.
        $dias = array_map('intval', array_filter(explode(',', texto_ou($configuracao, 'dias_semana_permitidos'))));

        // Hora atual e faixa permitida (formato HH:MM).
        $horaAtual = $agora->format('H:i');
        $inicio    = substr(texto_ou($configuracao, 'horario_inicio_envio', '08:00:00'), 0, 5);
        $fim       = substr(texto_ou($configuracao, 'horario_fim_envio', '20:00:00'), 0, 5);

        // Dia nao permitido.
        if (! in_array($dia, $dias, true)) {
            return [
                'aberta'     => false,
                'motivo'     => 'Hoje nao e um dia permitido para envio.',
                'hora_atual' => $horaAtual,
                'dia_atual'  => $this->nomeDia($dia),
            ];
        }

        // Antes do horario de inicio.
        if ($horaAtual < $inicio) {
            return [
                'aberta'     => false,
                'motivo'     => 'Fora do horario. Os envios comecam as ' . $inicio . '.',
                'hora_atual' => $horaAtual,
                'dia_atual'  => $this->nomeDia($dia),
            ];
        }

        // Depois do horario de fim.
        if ($horaAtual >= $fim) {
            return [
                'aberta'     => false,
                'motivo'     => 'Fora do horario. Os envios encerram as ' . $fim . '.',
                'hora_atual' => $horaAtual,
                'dia_atual'  => $this->nomeDia($dia),
            ];
        }

        // Dentro da janela.
        return [
            'aberta'     => true,
            'motivo'     => 'Dentro da janela de envio.',
            'hora_atual' => $horaAtual,
            'dia_atual'  => $this->nomeDia($dia),
        ];
    }

    /**
     * Nome do dia da semana a partir do numero ISO (1..7).
     */
    private function nomeDia(int $dia): string
    {
        // Mapa de nomes completos.
        $nomes = [
            1 => 'Segunda-feira',
            2 => 'Terca-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sabado',
            7 => 'Domingo',
        ];

        return valor_ou($nomes, $dia, 'Dia desconhecido');
    }
}
