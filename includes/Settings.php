<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Settings
{
    const OPTION_KEY = 'mlb_lms_settings';
    const GROUP_KEY  = 'mlb_lms_settings_group';
    const PAGE_SLUG  = 'mlb-lms-settings';

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
            [__CLASS__, 'page_dashboard'],
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page(
            'mlb-lms',
            'Configurações',
            'Configurações',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'page_settings']
        );

        add_submenu_page(
            'mlb-lms',
            'Alunos',
            'Alunos',
            'manage_options',
            'mlb-lms-students',
            [__CLASS__, 'page_students']
        );

        add_submenu_page(
            'mlb-lms',
            'Matrículas',
            'Matrículas',
            'manage_options',
            'mlb-lms-enrollments',
            [__CLASS__, 'page_enrollments']
        );

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
        register_setting(self::GROUP_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);

        // Seções
        add_settings_section(
            'mlb_lms_section_brand',
            'Marca e E-mails',
            function () {
                echo '<p>Configure informações básicas da marca usadas no fluxo do LMS e e-mails.</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_section(
            'mlb_lms_section_vimeo',
            'Vimeo API',
            function () {
                echo '<p>Configure um <strong>Vimeo Access Token</strong> para permitir validação de vídeos e exibição de player com acesso via conta do criador.</p>';
                echo '<p class="description">Observação: para o Vimeo permitir embed, o vídeo precisa estar configurado como embeddable (mesmo que seja “não listado” ou privado com embed liberado).</p>';
            },
            self::PAGE_SLUG
        );

        // Campos
        add_settings_field(
            'brand_name',
            'Nome da marca',
            [__CLASS__, 'field_brand_name'],
            self::PAGE_SLUG,
            'mlb_lms_section_brand'
        );

        add_settings_field(
            'email_logo_url',
            'Logo para e-mails (URL)',
            [__CLASS__, 'field_email_logo_url'],
            self::PAGE_SLUG,
            'mlb_lms_section_brand'
        );

        add_settings_field(
            'vimeo_token',
            'Vimeo Access Token',
            [__CLASS__, 'field_vimeo_token'],
            self::PAGE_SLUG,
            'mlb_lms_section_vimeo'
        );
    }

    public static function sanitize($input)
    {
        $output = [];

        $output['brand_name'] = sanitize_text_field($input['brand_name'] ?? 'Malibu');
        $output['email_logo_url'] = esc_url_raw($input['email_logo_url'] ?? '');

        // Vimeo token: guarda como texto simples (vamos exibir como password field)
        $output['vimeo_token'] = trim(sanitize_text_field($input['vimeo_token'] ?? ''));

        return $output;
    }

    // Helpers pra ler settings em qualquer lugar do plugin
    public static function get($key, $default = null)
    {
        $all = get_option(self::OPTION_KEY, []);
        if (!is_array($all)) $all = [];
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function field_brand_name()
    {
        $val = esc_attr(self::get('brand_name', 'Malibu'));
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[brand_name]" value="' . $val . '" />';
        echo '<p class="description">Ex.: Cursos Espaço Malibu</p>';
    }

    public static function field_email_logo_url()
    {
        $val = esc_attr(self::get('email_logo_url', ''));
        echo '<input type="url" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[email_logo_url]" value="' . $val . '" placeholder="https://..." />';
        echo '<p class="description">URL de uma imagem pública (PNG/JPG). Usada nos e-mails do plugin.</p>';
    }

    public static function field_vimeo_token()
    {
        $val = esc_attr(self::get('vimeo_token', ''));
        echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[vimeo_token]" value="' . $val . '" autocomplete="new-password" />';
        echo '<p class="description">Crie no Vimeo: Developer → Apps → Personal access tokens. Permissões comuns: <code>public</code> e <code>private</code> (dependendo da privacidade dos seus vídeos).</p>';
    }

    public static function page_dashboard()
    {
        echo '<div class="wrap"><h1>Pressplay LMS</h1><p>Dashboard geral (atalhos, métricas, status).</p></div>';
    }

    public static function page_settings()
    {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap">';
        echo '<h1>Configurações</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP_KEY);
        do_settings_sections(self::PAGE_SLUG);
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
