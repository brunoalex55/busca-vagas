<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Libraries;

use App\Models\ChaveSerpApiModel;
use App\Models\PalavraChaveModel;
use App\Models\UsuarioModel;

/**
 * --------------------------------------------------------------------------
 * BIBLIOTECA: DISTRIBUICAO DE CONSULTAS
 * --------------------------------------------------------------------------
 *
 * O sistema nao trabalha com planos. A cota de consultas de cada usuario
 * e recalculada automaticamente a partir do saldo das chaves SerpApi.
 *
 * Regras:
 *   1. Limite manual (quando definido pelo admin) tem prioridade.
 *   2. O restante e dividido por peso. O peso e o numero de combinacoes
 *      ativas (palavra-chave x cidade) do usuario.
 *   3. Nenhum usuario recebe mais que 3x a media (teto).
 *   4. Cada usuario tem piso de min(combinacoes, 30), limitado pelo teto.
 *   5. A sobra e redistribuida entre quem ainda esta abaixo do teto.
 *
 * O resultado fica em cache durante a requisicao: o painel consulta a
 * mesma distribuicao em varios cards.
 */
class DistribuicaoConsultas
{
    /** Model de usuarios. */
    private UsuarioModel $usuarios;

    /** Model de palavras-chave (para contar combinacoes). */
    private PalavraChaveModel $palavras;

    /** Model de chaves SerpApi (para saber o saldo total). */
    private ChaveSerpApiModel $chaves;

    /** Cache da distribuicao na requisicao atual. */
    private ?array $cache = null;

    /**
     * Carrega os models necessarios.
     */
    public function __construct()
    {
        // Instancia os models pelo helper do CodeIgniter.
        $this->usuarios = model(UsuarioModel::class);
        $this->palavras = model(PalavraChaveModel::class);
        $this->chaves  = model(ChaveSerpApiModel::class);
    }

    /**
     * Calcula a distribuicao para TODOS os usuarios ativos.
     *
     * @return array{
     *     total_disponivel: int,
     *     pool_automatico: float,
     *     media: float,
     *     teto: int,
     *     usuarios: array<int, array{
     *         usuario_id: int,
     *         combinacoes: int,
     *         manual: int|null,
     *         cota: int
     *     }>
     * }
     */
    public function calcularTodas(): array
    {
        // Reaproveita o calculo dentro da mesma requisicao.
        if ($this->cache !== null) {
            return $this->cache;
        }

        // Saldo total das chaves ativas.
        $totalDisponivel = max(0, $this->chaves->totalRestante());

        // Apenas usuarios com status ativo entram na distribuicao.
        $usuarios = $this->usuarios
            ->where('status', 'ativo')
            ->where('ativo', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        // Sem usuarios ativos, distribuicao vazia.
        if ($usuarios === []) {
            $this->cache = [
                'total_disponivel' => $totalDisponivel,
                'pool_automatico'  => 0.0,
                'media'            => 0.0,
                'teto'             => 0,
                'usuarios'         => [],
            ];

            return $this->cache;
        }

        // Monta a lista base com combinacoes e limite manual.
        $itens         = [];
        $somaManual    = 0;

        foreach ($usuarios as $usuario) {
            $id = (int) $usuario['id'];

            // Numero de combinacoes ativas do usuario.
            $combinacoes = $this->palavras->totalCombinacoesDoUsuario($id);

            // Limite manual definido pelo admin (null = automatico).
            $manual = null;

            if ($usuario['limite_manual'] !== null) {
                $manual = max(0, (int) $usuario['limite_manual']);
            }

            $itens[$id] = [
                'usuario_id'  => $id,
                'combinacoes' => $combinacoes,
                'manual'      => $manual,
                'cota'        => 0,
            ];

            // Soma os limites manuais para reservar a fatia deles.
            if ($manual !== null) {
                $somaManual += $manual;
            }
        }

        // Parte do saldo reservada aos limites manuais.
        $pool = max(0.0, (float) ($totalDisponivel - $somaManual));

        // Usuarios que participam da divisao automatica.
        $automaticos = array_filter(
            $itens,
            static fn (array $item): bool => $item['manual'] === null
        );

        // Quantidade de usuarios automaticos.
        $quantidade = count($automaticos);

        // Soma dos pesos (combinacoes) dos automaticos.
        $somaPesos = array_sum(array_column($automaticos, 'combinacoes'));

        // Media por usuario automatico.
        $media = 0.0;

        if ($quantidade > 0) {
            $media = $pool / $quantidade;
        }

        // Teto de 3x a media. Quando ha saldo, garante pelo menos 1.
        $teto = 0;

        if ($pool > 0) {
            $teto = max(1, (int) floor(3 * $media));
        }

        // Calcula a cota bruta de cada automatico.
        foreach ($automaticos as $id => $item) {
            $peso = $item['combinacoes'];

            // Divisao proporcional ao peso; sem pesos, divide igualmente.
            $bruto = $media;

            if ($somaPesos > 0) {
                $bruto = $pool * $peso / $somaPesos;
            }

            // Piso limitado pelo teto para nunca haver conflito.
            $piso = min($item['combinacoes'], 30, $teto);

            // Aplica teto e piso.
            $cota = min($bruto, (float) $teto);
            $cota = max($cota, (float) $piso);

            // Guarda a parte inteira (consultas sao unidades).
            $itens[$id]['cota'] = (int) floor($cota);
        }

        // Limites manuais entram como cota cheia.
        foreach ($itens as $id => $item) {
            if ($item['manual'] !== null) {
                $itens[$id]['cota'] = $item['manual'];
            }
        }

        // Se o total distribuido passar do saldo, reduz primeiro os
        // automaticos que estao acima do piso.
        $itens = $this->reduzirExcedente($itens, $totalDisponivel);

        // Depois, distribui a sobra entre os automaticos abaixo do teto.
        $itens = $this->distribuirSobra($itens, $totalDisponivel, $teto);

        // Guarda o resultado no cache da requisicao.
        $this->cache = [
            'total_disponivel' => $totalDisponivel,
            'pool_automatico'  => $pool,
            'media'            => $media,
            'teto'             => $teto,
            'usuarios'         => $itens,
        ];

        return $this->cache;
    }

    /**
     * Resume a cota de um usuario especifico para o painel.
     *
     * @return array{
     *     cota: int,
     *     usada: int,
     *     restante: int,
     *     percentual: int,
     *     combinacoes: int,
     *     manual: bool,
     *     total_disponivel: int,
     *     media: float,
     *     teto: int
     * }
     */
    public function resumo(int $usuarioId): array
    {
        // Calculo completo (com cache).
        $distribuicao = $this->calcularTodas();

        // Item do usuario (ou valores neutros quando nao participa).
        $usuarios = valor_ou($distribuicao, 'usuarios', []);
        $item     = valor_ou($usuarios, (string) $usuarioId, [
            'usuario_id'  => $usuarioId,
            'combinacoes' => 0,
            'manual'      => null,
            'cota'        => 0,
        ]);

        // Busca direta para evitar estado residual do model.
        $usuario = $this->usuarios->find($usuarioId);

        // Consultas usadas somente contam no mes corrente.
        $mesAtual = date('Y-m');
        $usada    = 0;

        if (
            is_array($usuario)
            && valor_ou($usuario, 'mes_referencia', '') === $mesAtual
        ) {
            $usada = max(0, (int) $usuario['consultas_usadas_mes']);
        }

        // Cota nunca negativa.
        $cota = max(0, (int) $item['cota']);

        // Percentual usado (limitado a 100 para a barra visual).
        $percentual = 0;

        if ($cota > 0) {
            $percentual = min(100, (int) round($usada * 100 / $cota));
        }

        return [
            'cota'             => $cota,
            'usada'            => $usada,
            'restante'         => max(0, $cota - $usada),
            'percentual'       => $percentual,
            'combinacoes'      => (int) $item['combinacoes'],
            'manual'           => $item['manual'] !== null,
            'total_disponivel' => (int) $distribuicao['total_disponivel'],
            'media'            => (float) $distribuicao['media'],
            'teto'             => (int) $distribuicao['teto'],
        ];
    }

    /**
     * Reduz cotas quando a soma ultrapassa o saldo disponivel.
     *
     * @param array<int, array<string, mixed>> $itens
     *
     * @return array<int, array<string, mixed>>
     */
    private function reduzirExcedente(array $itens, int $totalDisponivel): array
    {
        // Soma atual.
        $soma = array_sum(array_column($itens, 'cota'));

        // Nada a reduzir.
        if ($soma <= $totalDisponivel) {
            return $itens;
        }

        // Quanto precisa sair.
        $excedente = $soma - $totalDisponivel;

        // Piso de cada automatico.
        foreach ($itens as $id => $item) {
            // Automatico: piso limitado pelas combinacoes.
            $piso = $item['cota'];

            if ($item['manual'] === null) {
                $piso = min($item['combinacoes'], 30, $item['cota']);
            }

            $itens[$id]['piso'] = $piso;
        }

        // Total que pode ser reduzido sem furar o piso.
        $redutivel = 0;

        foreach ($itens as $item) {
            if ($item['manual'] === null) {
                $redutivel += max(0, $item['cota'] - $item['piso']);
            }
        }

        // Reduz proporcionalmente a folga acima do piso.
        if ($redutivel > 0) {
            $corte = min($excedente, $redutivel);

            foreach ($itens as $id => $item) {
                if ($item['manual'] !== null) {
                    continue;
                }

                $folga = max(0, $item['cota'] - $item['piso']);
                $parte = (int) floor($corte * $folga / $redutivel);

                $itens[$id]['cota'] -= min($parte, $folga);
            }

            // Ajuste fino para eliminar diferencas de arredondamento.
            $excedente = array_sum(array_column($itens, 'cota')) - $totalDisponivel;

            if ($excedente > 0) {
                foreach ($itens as $id => $item) {
                    if ($excedente <= 0) {
                        break;
                    }

                    if ($item['manual'] !== null) {
                        continue;
                    }

                    $folga = max(0, $item['cota'] - $item['piso']);
                    $tirar = min($folga, $excedente);

                    $itens[$id]['cota'] -= $tirar;
                    $excedente -= $tirar;
                }
            }
        }

        // Se ainda houver excedente, reduz proporcionalmente as cotas
        // automaticas e, em ultimo caso, os limites manuais.
        $somaAtual = array_sum(array_column($itens, 'cota'));

        if ($somaAtual > $totalDisponivel) {
            // Quanto os limites manuais ocupam neste momento.
            $somaManual = 0;

            foreach ($itens as $item) {
                if ($item['manual'] !== null) {
                    $somaManual += $item['cota'];
                }
            }

            // Fatia que sobra para os automaticos.
            $alvoAutomatico = max(0, $totalDisponivel - $somaManual);

            // Soma atual dos automaticos.
            $somaAutomatica = 0;

            foreach ($itens as $item) {
                if ($item['manual'] === null) {
                    $somaAutomatica += $item['cota'];
                }
            }

            // Reduz os automaticos na proporcao necessaria.
            if ($somaAutomatica > $alvoAutomatico && $somaAutomatica > 0) {
                $fator = $alvoAutomatico / $somaAutomatica;

                foreach ($itens as $id => $item) {
                    if ($item['manual'] === null) {
                        $itens[$id]['cota'] = (int) floor($item['cota'] * $fator);
                    }
                }
            }

            // Se nem assim couber, reduz os manuais na mesma proporcao.
            $somaAtual = array_sum(array_column($itens, 'cota'));

            if ($somaAtual > $totalDisponivel) {
                $somaManual = 0;

                foreach ($itens as $item) {
                    if ($item['manual'] !== null) {
                        $somaManual += $item['cota'];
                    }
                }

                if ($somaManual > 0) {
                    $alvoManual = max(0, $totalDisponivel - ($somaAtual - $somaManual));
                    $fator      = min(1.0, $alvoManual / $somaManual);

                    foreach ($itens as $id => $item) {
                        if ($item['manual'] !== null) {
                            $itens[$id]['cota'] = (int) floor($item['cota'] * $fator);
                        }
                    }
                }
            }
        }

        // Remove a chave auxiliar usada apenas aqui.
        foreach ($itens as $id => $item) {
            unset($itens[$id]['piso']);
        }

        return $itens;
    }

    /**
     * Distribui a sobra entre os automaticos que ainda estao abaixo do teto.
     *
     * @param array<int, array<string, mixed>> $itens
     *
     * @return array<int, array<string, mixed>>
     */
    private function distribuirSobra(array $itens, int $totalDisponivel, int $teto): array
    {
        // Sem teto nao ha como distribuir.
        if ($teto <= 0) {
            return $itens;
        }

        // Soma atual e sobra.
        $soma  = array_sum(array_column($itens, 'cota'));
        $sobra = $totalDisponivel - $soma;

        // Nada a distribuir.
        if ($sobra <= 0) {
            return $itens;
        }

        // Capacidade total ainda disponivel abaixo do teto.
        $capacidade = 0;

        foreach ($itens as $item) {
            if ($item['manual'] === null && $item['cota'] < $teto) {
                $capacidade += $teto - $item['cota'];
            }
        }

        // Todos ja estao no teto.
        if ($capacidade <= 0) {
            return $itens;
        }

        // Distribuicao proporcional a capacidade livre.
        $fator = min(1.0, $sobra / $capacidade);

        foreach ($itens as $id => $item) {
            if ($item['manual'] !== null || $item['cota'] >= $teto) {
                continue;
            }

            $livre = $teto - $item['cota'];
            $parte = (int) floor($livre * $fator);

            if ($parte > 0) {
                $itens[$id]['cota'] += $parte;
            }
        }

        // Ajuste fino: entrega uma unidade por vez ate acabar a sobra.
        $sobra = $totalDisponivel - array_sum(array_column($itens, 'cota'));

        foreach ($itens as $id => $item) {
            if ($sobra <= 0) {
                break;
            }

            if ($item['manual'] !== null || $item['cota'] >= $teto) {
                continue;
            }

            $itens[$id]['cota']++;
            $sobra--;
        }

        return $itens;
    }
}
