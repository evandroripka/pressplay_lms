<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Activator {
    public static function activate() {
        // Cria/atualiza tabelas
        MLB_LMS_Database::migrate();
        // Cria papéis de usuário
        MLB_LMS_Roles::add_roles();
        // Registra CPTs antes do flush
        if (class_exists('MLB_LMS_CPT')) {
            MLB_LMS_CPT::register_course();
            MLB_LMS_CPT::register_lesson();
        }
        // Regras personalizadas
        if (class_exists('MLB_LMS_Rewrite')) {
            MLB_LMS_Rewrite::add_rules();
        }
        // Flush das rewrite rules
        flush_rewrite_rules();
    }
}
