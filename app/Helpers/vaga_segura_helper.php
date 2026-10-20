<?php

/**
 * --------------------------------------------------------------------------
 * HELPER: VAGA SEGURA
 * --------------------------------------------------------------------------
 *
 * Toda rota que age sobre uma vaga (detalhar, candidatar, enviar e-mail,
 * excluir...) precisa garantir que a vaga pertence ao usuario da sessao.
 * Este helper faz essa checagem em um lugar so.
 *
 * REGRA DO PROJETO: uma vaga e sempre de um usuario. Nenhuma consulta em
 * `vagas` pode devolver a vaga de outra pessoa. As consultas que sao
 * globais (relatorios do admin) ficam nos controllers de admin e sao
 * marcadas com o comentario
 *
 *     // CONSULTA GLOBAL - uso exclusivo do admin
 *
 * Quando a vaga existe mas pertence a outro usuario, o helper registra a
 * tentativa em writable/logs/acesso-indevido-YYYY-MM-DD.log (apenas ids,
 * rota e IP: nunca e-mail, CPF ou conteudo da vaga).
 */

if (! function_exists('obterVagaDoUsuario')) {
    /**
     * A vaga, mas somente se ela for do usuario.
     *
     * @param int $vagaId    O id da vaga pedido.
     * @param int $usuarioId O dono da sessao.
     *
     * @return array<string, mixed>|null Null quando a vaga nao existe
     *                                ou pertence a outro usuario.
     */
    function obterVagaDoUsuario(int $vagaId, int $usuarioId): ?array
    {
        // Sem id nao ha vaga.
        if ($vagaId <= 0) {
            return null;
        }

        $vaga = model('App\Models\VagaModel')->find($vagaId);

        // A vaga nao existe.
        if ($vaga === null) {
            return null;
        }

        // A vaga e de outro usuario: nao devolve nada.
        $donoRealId = (int) valor_ou($vaga, 'usuario_id', 0);

        if ($donoRealId !== $usuarioId) {
            registrarAcessoIndevido($vagaId, $usuarioId, $donoRealId);

            return null;
        }

        return $vaga;
    }
}

if (! function_exists('obterVagaDoUsuarioOu404')) {
    /**
     * A vaga do usuario ou um erro 404.
     *
     * @param int $vagaId    O id da vaga pedido.
     * @param int $usuarioId O dono da sessao.
     *
     * @return array<string, mixed>
     *
     * @throws \CodeIgniter\Exceptions\PageNotFoundException
     */
    function obterVagaDoUsuarioOu404(int $vagaId, int $usuarioId): array
    {
        $vaga = obterVagaDoUsuario($vagaId, $usuarioId);

        // A vaga nao existe ou nao e do usuario: 404 (o mesmo que a
        // pagina inexistente, para nao revelar que a vaga existe).
        if ($vaga === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vaga nao encontrada.');
        }

        return $vaga;
    }
}

if (! function_exists('registrarAcessoIndevido')) {
    /**
     * Registra a tentativa de acesso a vaga de outro usuario.
     *
     * Guarda apenas identificadores (nunca e-mail, CPF ou o conteudo
     * da vaga).
     *
     * @param int $vagaId        A vaga pedida.
     * @param int $usuarioId     Quem tentou acessar (da sessao).
     * @param int $donoRealId    Quem e o dono de verdade da vaga.
     */
    function registrarAcessoIndevido(int $vagaId, int $usuarioId, int $donoRealId): void
    {
        // A pasta dos logs.
        $pasta = WRITEPATH . 'logs';

        if (! is_dir($pasta)) {
            @mkdir($pasta, 0777, true);
        }

        // O arquivo do dia.
        $arquivo = $pasta . '/acesso-indevido-' . date('Y-m-d') . '.log';

        // A rota acessada.
        $rota = uri_string();

        // O IP de origem.
        $ip = 'desconhecido';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        // A linha do registro.
        $linha = sprintf(
            "%s | usuario=%d | vaga=%d | dono=%d | ip=%s | rota=%s",
            date('Y-m-d H:i:s'),
            $usuarioId,
            $vagaId,
            $donoRealId,
            $ip,
            $rota
        );

        // Grava no log (a falha nao pode quebrar a requisicao).
        @file_put_contents($arquivo, $linha . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
