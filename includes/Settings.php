<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Settings
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function menu()
    {
        add_menu_page(
            'Pressplay LMS',
            'Pressplay LMS',
            'manage_options',
            'mlb-lms',
            [__CLASS__, 'page_dashboard'],
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page('mlb-lms', 'Configurações', 'Configurações', 'manage_options', 'mlb-lms-settings', [__CLASS__, 'page_settings']);
        add_submenu_page('mlb-lms', 'Alunos', 'Alunos', 'manage_options', 'mlb-lms-students', [__CLASS__, 'page_students']);
        add_submenu_page('mlb-lms', 'Matrículas', 'Matrículas', 'manage_options', 'mlb-lms-enrollments', [__CLASS__, 'page_enrollments']);
        add_submenu_page('mlb-lms', 'Progresso', 'Progresso', 'manage_options', 'mlb-lms-progress', [__CLASS__, 'page_progress']);
    }

    public static function register()
    {
        register_setting('mlb_lms_settings_group', 'mlb_lms_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);

        add_settings_section('mlb_lms_main', 'Configurações Gerais', '__return_false', 'mlb-lms-settings');

        add_settings_field('brand_name', 'Nome da marca', [__CLASS__, 'field_brand_name'], 'mlb-lms-settings', 'mlb_lms_main');
        add_settings_field('email_logo_url', 'URL do logo para e-mail', [__CLASS__, 'field_logo_url'], 'mlb-lms-settings', 'mlb_lms_main');
        add_settings_field('vimeo_token', 'Vimeo Token (futuro)', [__CLASS__, 'field_vimeo_token'], 'mlb-lms-settings', 'mlb_lms_main');
        add_settings_field('danger_allow_uninstall_cleanup', 'Permitir limpeza completa no uninstall', [__CLASS__, 'field_uninstall_cleanup'], 'mlb-lms-settings', 'mlb_lms_main');
    }

    public static function sanitize($input)
    {
        return [
            'brand_name' => sanitize_text_field($input['brand_name'] ?? 'Malibu'),
            'email_logo_url' => esc_url_raw($input['email_logo_url'] ?? ''),
            'vimeo_token' => sanitize_text_field($input['vimeo_token'] ?? ''),
            'danger_allow_uninstall_cleanup' => !empty($input['danger_allow_uninstall_cleanup']) ? 1 : 0,
        ];
    }

    private static function options()
    {
        return get_option('mlb_lms_settings', []);
    }

    public static function field_brand_name()
    {
        $opts = self::options();
        echo '<input type="text" class="regular-text" name="mlb_lms_settings[brand_name]" value="' . esc_attr($opts['brand_name'] ?? 'Malibu') . '">';
    }

    public static function field_logo_url()
    {
        $opts = self::options();
        echo '<input type="url" class="regular-text" name="mlb_lms_settings[email_logo_url]" value="' . esc_attr($opts['email_logo_url'] ?? '') . '" placeholder="https://...">';
    }

    public static function field_vimeo_token()
    {
        $opts = self::options();
        echo '<input type="text" class="regular-text" name="mlb_lms_settings[vimeo_token]" value="' . esc_attr($opts['vimeo_token'] ?? '') . '">';
        echo '<p class="description">Campo reservado para integrações futuras.</p>';
    }

    public static function field_uninstall_cleanup()
    {
        $opts = self::options();
        $checked = !empty($opts['danger_allow_uninstall_cleanup']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="mlb_lms_settings[danger_allow_uninstall_cleanup]" value="1" ' . $checked . '> Remover tabelas customizadas ao desinstalar.</label>';
    }

    public static function page_dashboard()
    {
        echo '<div class="wrap"><h1>Pressplay LMS</h1><p>Dashboard geral (atalhos, métricas, status).</p></div>';
    }

    public static function page_settings()
    {
        echo '<div class="wrap">';
        echo '<h1>Configurações</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('mlb_lms_settings_group');
        do_settings_sections('mlb-lms-settings');
        submit_button('Salvar configurações');
        echo '</form>';
        echo '</div>';
    }

    public static function page_students()
    {
        echo '<div class="wrap"><h1>Alunos</h1><p>Listagem e gerenciamento.</p></div>';
    }

    public static function page_enrollments()
    {
        echo '<div class="wrap"><h1>Matrículas</h1><p>Listagem e gerenciamento.</p></div>';
    }

    public static function page_progress()
    {
        echo '<div class="wrap"><h1>Progresso</h1><p>Relatórios de progresso por aluno/curso.</p></div>';
    }
}
