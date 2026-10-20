<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: PLANO DOS USUARIOS QUE JA EXISTEM
 * --------------------------------------------------------------------------
 *
 * Deixa a base coerente com os planos:
 *
 *   - garante que os tres planos existam (roda o PlanoSeeder);
 *   - usuarios ATIVOS sem plano: entram no plano basico;
 *   - usuarios PENDENTES: ficam sem plano (NULL) ate o administrador
 *     aprovar e escolher o plano (o plano escolhido no cadastro entra
 *     junto com a solicitacao).
 */
class AtribuirPlanoAosUsuariosExistentes extends Migration
{
    public function up(): void
    {
        // Garante que os planos existam (o mesmo seeder que o admin
        // pode rodar na mao).
        $seeder = new \App\Database\Seeds\PlanoSeeder(config('Database'));
        $seeder->run();

        // O id do plano basico (o fallback do sistema).
        $basico = $this->db->table('planos')
            ->where('slug', 'basico')
            ->get()
            ->getRowArray();

        // Sem o plano basico: nao ha o que atribuir.
        if ($basico === null || $basico === []) {
            return;
        }

        $planoBasico = (int) $basico['id'];

        // Os usuarios ativos sem plano entram no basico.
        $this->db->table('usuarios')
            ->where('status', 'ativo')
            ->where('plano_id', null)
            ->update([
                'plano_id' => $planoBasico,
                'plano_inicio_em' => date('Y-m-d H:i:s'),
            ]);

        // Os pendentes ficam sem plano (o admin escolhe na aprovacao).
        $this->db->table('usuarios')
            ->where('status', 'pendente')
            ->where('plano_id', null)
            ->update(['plano_id' => null]);
    }

    public function down(): void
    {
        // A migration nao tem volta: os planos ficam (a tabela e usada
        // pelo resto do sistema).
    }
}
