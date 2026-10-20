<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * --------------------------------------------------------------------------
 * MIGRATION: ORIGEM MANUAL_USUARIO EM LOGS_CONSULTA
 * --------------------------------------------------------------------------
 *
 * A coluna `origem` de `logs_consulta` aceitava apenas 'manual' e 'cron'.
 * Com a execucao manual pelo usuario (Bloco E), o sistema passa a gravar
 * uma terceira origem: 'manual_usuario', usada quando o proprio usuario
 * dispara a busca de vagas pelo painel dele (cartao "Execucoes manuais").
 *
 * Sem essa origem a auditoria nao distinguiria:
 *   - 'manual'         -> execucao disparada pelo painel administrativo;
 *   - 'manual_usuario' -> execucao disparada pelo usuario no painel dele;
 *   - 'cron'           -> execucao disparada pelo agendador.
 */
class AdicionarOrigemManualUsuarioEmLogsConsulta extends Migration
{
    public function up(): void
    {
        // Acrescenta a nova origem ao enum da coluna.
        $this->db->query(
            "ALTER TABLE `logs_consulta` MODIFY COLUMN `origem` "
            . "ENUM('manual','manual_usuario','cron') NOT NULL DEFAULT 'manual'"
        );
    }

    public function down(): void
    {
        // Execucoes do usuario voltam a ser 'manual' antes de remover a origem.
        $this->db->query(
            "UPDATE `logs_consulta` SET `origem` = 'manual' WHERE `origem` = 'manual_usuario'"
        );

        // Volta o enum ao estado anterior.
        $this->db->query(
            "ALTER TABLE `logs_consulta` MODIFY COLUMN `origem` "
            . "ENUM('manual','cron') NOT NULL DEFAULT 'manual'"
        );
    }
}
