<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Activator {
    public static function activate() {

        // 1) Banco e roles
        MLB_LMS_Database::migrate();
        MLB_LMS_Roles::add_roles();

        // 2) Registra CPTs antes do flush (IMPORTANTE)
        if (class_exists('MLB_LMS_CPT')) {
            MLB_LMS_CPT::register_course();
            MLB_LMS_CPT::register_lesson();
        }

        // 3) Regras custom do plugin (se você ainda usa)
        if (class_exists('MLB_LMS_Rewrite')) {
            MLB_LMS_Rewrite::add_rules();
        }

        // 4) Agora sim: flush
        flush_rewrite_rules();
    }
}
