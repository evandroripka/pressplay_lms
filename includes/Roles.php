<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Roles {
    public static function init() {
        add_action('init', [__CLASS__, 'add_roles']);
        add_action('admin_init', [__CLASS__, 'block_admin_for_students']);
    }

    public static function add_roles() {
        add_role('malibu_student', 'Aluno (Malibu)', [
            'read' => true,
        ]);
    }

    public static function block_admin_for_students() {
        if (!is_user_logged_in()) return;
        $user = wp_get_current_user();
        if (in_array('malibu_student', (array)$user->roles, true)) {
            if (is_admin() && !wp_doing_ajax()) {
                wp_safe_redirect(home_url('/meus-cursos'));
                exit;
            }
        }
    }
}
