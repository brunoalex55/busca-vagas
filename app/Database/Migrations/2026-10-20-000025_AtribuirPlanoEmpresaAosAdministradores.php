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
 * MIGRATION: PLANO DOS ADMINISTRADORES
 * --------------------------------------------------------------------------
 *
 * O administrador administra o sistema: ele precisa de todos os
 * recursos (envio automatico, envio em massa e limites altos). Por isso
 * os administradores entram no plano EMPRESA.
 *
 * Os usuarios comuns continuam no plano que o admin atribuiu (ou no
 * basico, o fallback).
 */
class AtribuirPlanoEmpresaAosAdministradores extends Migration
{
    public function up(): void
    {
        // O plano Empresa (o fallback quando ele nao existe).
        $empresa = $this->db->table('planos')
            ->where('slug', 'empresa')
            ->get()
            ->getRowArray();

        if ($empresa === null || $empresa === []) {
            return;
        }

        // Os administradores vao para o plano Empresa.
        $this->db->table('usuarios')
            ->where('perfil', 'admin')
            ->update([
                'plano_id' => (int) $empresa['id'],
                'plano_inicio_em' => date('Y-m-d H:i:s'),
            ]);
    }

    public function down(): void
    {
        // Os administradores voltam para o basico.
        $basico = $this->db->table('planos')
            ->where('slug', 'basico')
            ->get()
            ->getRowArray();

        if ($basico === null || $basico === []) {
            return;
        }

        $this->db->table('usuarios')
            ->where('perfil', 'admin')
            ->update(['plano_id' => (int) $basico['id']]);
    }
}
