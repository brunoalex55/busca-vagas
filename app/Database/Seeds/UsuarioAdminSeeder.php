<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * --------------------------------------------------------------------------
 * SEEDER: USUARIO ADMINISTRADOR PADRAO
 * --------------------------------------------------------------------------
 *
 * Cria a conta inicial de administrador do sistema.
 *
 *   E-mail: admin@admin.com
 *   Senha : admin123
 *
 * IMPORTANTE: troque essa senha no primeiro acesso em producao.
 *
 * A senha e gravada com password_hash(PASSWORD_BCRYPT), nunca em texto
 * puro.
 */
class UsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Verifica se o admin ja existe para nao duplicar o registro
        // quando o seeder for executado mais de uma vez.
        $existente = $this->db->table('usuarios')
            ->where('email', 'admin@admin.com')
            ->countAllResults();

        // Se ja existe, nao faz nada.
        if ($existente > 0) {
            return;
        }

        // Monta o registro do administrador.
        $dados = [
            // Nome exibido no sistema.
            'nome'                  => 'Administrador',

            // Login do administrador.
            'email'                 => 'admin@admin.com',

            // Hash bcrypt da senha "admin123".
            'senha'                 => password_hash('admin123', PASSWORD_BCRYPT),

            // Perfil com acesso total.
            'perfil'                => 'admin',

            // Ja nasce ativo, sem passar pelo fluxo de aprovacao.
            'status'                => 'ativo',

            // Contadores zerados.
            'consultas_usadas_mes'  => 0,
            'mes_referencia'        => date('Y-m'),

            // Sem limite manual: o admin tambem usa o calculo dinamico.
            'limite_manual'         => null,

            // Envio automatico desligado por padrao.
            'envio_automatico'      => 0,
            'emails_automaticos_enviados' => 0,

            // Nenhuma execucao registrada ainda.
            'ultima_execucao_envio_automatico' => null,
            'ultima_execucao_busca_vagas'      => null,

            // O admin recebe o plano EMPRESA (ele precisa de todos os
            // recursos para administrar o sistema).
            'plano_id'                => $this->idDoPlanoEmpresa(),

            // Conta ativa.
            'ativo'                 => 1,

            // Datas de controle.
            'criado_em'             => date('Y-m-d H:i:s'),
            'atualizado_em'         => date('Y-m-d H:i:s'),
        ];

        // Insere o registro na tabela de usuarios.
        $this->db->table('usuarios')->insert($dados);
    }

    /**
     * O id do plano Empresa (o admin precisa de todos os recursos).
     *
     * Devolve null quando o plano ainda nao existe (o seeder dos planos
     * roda antes deste).
     */
    private function idDoPlanoEmpresa(): ?int
    {
        $plano = $this->db->table('planos')
            ->where('slug', 'empresa')
            ->get()
            ->getRowArray();

        if ($plano === null || $plano === []) {
            return null;
        }

        return (int) $plano['id'];
    }
}
