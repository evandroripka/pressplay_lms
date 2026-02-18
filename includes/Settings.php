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
        // Menu principal
        add_menu_page(
            'Pressplay LMS',
            'Pressplay LMS',
            'manage_options',
            'mlb-lms',
            [__CLASS__, 'page_dashboard'], // página padrão do menu principal
            'dashicons-welcome-learn-more',
            58
        );

    
        // Submenu: Configurações (pode ser o que já existe hoje)
        add_submenu_page(
            'mlb-lms',
            'Configurações',
            'Configurações',
            'manage_options',
            'mlb-lms-settings',
            [__CLASS__, 'page_settings']
        );

        // Submenu: Alunos
        add_submenu_page(
            'mlb-lms',
            'Alunos',
            'Alunos',
            'manage_options',
            'mlb-lms-students',
            [__CLASS__, 'page_students']
        );

        // Submenu: Matrículas
        add_submenu_page(
            'mlb-lms',
            'Matrículas',
            'Matrículas',
            'manage_options',
            'mlb-lms-enrollments',
            [__CLASS__, 'page_enrollments']
        );

        // Submenu: Progresso
        add_submenu_page(
            'mlb-lms',
            'Progresso',
            'Progresso',
            'manage_options',
            'mlb-lms-progress',
            [__CLASS__, 'page_progress']
        );
    }

    public static function register()
    {
        register_setting('mlb_lms_settings_group', 'mlb_lms_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);
    }

    public static function sanitize($input)
    {
        return [
            'brand_name' => sanitize_text_field($input['brand_name'] ?? 'Malibu'),
            'email_logo_url' => esc_url_raw($input['email_logo_url'] ?? ''),
        ];
    }

    public static function page_dashboard()
    {
        echo '<div class="wrap"><h1>Pressplay LMS</h1><p>Dashboard geral (atalhos, métricas, status).</p></div>';
    }

    public static function page_settings()
    {
        // aqui você pode reaproveitar o conteúdo atual do page()
        echo '<div class="wrap"><h1>Configurações</h1><p>Configurações do sistema.</p></div>';
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
