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

        // Ajustes globais do WP (cadastro + role padrão)
        self::configure_wp_registration_defaults();

        // Flush das rewrite rules
        flush_rewrite_rules();
    }

    private static function configure_wp_registration_defaults() {
        // Salva valores antigos 1x (pra você poder restaurar manualmente depois, se quiser)
        if (get_option('mlb_lms_prev_users_can_register', null) === null) {
            add_option('mlb_lms_prev_users_can_register', get_option('users_can_register', 0));
        }
        if (get_option('mlb_lms_prev_default_role', null) === null) {
            add_option('mlb_lms_prev_default_role', get_option('default_role', 'subscriber'));
        }

        // Habilita cadastro público
        update_option('users_can_register', 1);

        // Define role padrão como aluno (somente se a role existir)
        $role = 'malibu_student';
        if (get_role($role)) {
            update_option('default_role', $role);
        }
    }
}
