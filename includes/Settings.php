<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Settings
{
    const OPTION_KEY = 'press_lms_settings';
    const GROUP_KEY  = 'press_lms_settings_group';
    const PAGE_SLUG  = 'press-lms-settings';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function menu()
    {
        // opção A: se você já tem PRESS_LMS_URL definido
        $icon_url = PRESS_LMS_URL . 'assets/pressplay_lms_logo.png';

        // fallback seguro (opcional)
        if (empty($icon_url)) {
            $icon_url = 'dashicons-welcome-learn-more';
        }

        add_menu_page(
            'Pressplay LMS',
            'Pressplay LMS',
            'manage_options',
            'press-lms',
            ['PRESSPLAY_LMS_Admin', 'render'],
            $icon_url,
            6
        );

        add_submenu_page(
            'press-lms',
            'Configurações',
            'Configurações',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'page_settings']
        );

        add_submenu_page(
            'press-lms',
            'Alunos',
            'Alunos',
            'manage_options',
            'press-lms-students',
            [__CLASS__, 'page_students']
        );

        add_submenu_page(
            'press-lms',
            'Matrículas',
            'Matrículas',
            'manage_options',
            'press-lms-enrollments',
            [__CLASS__, 'page_enrollments']
        );

        add_submenu_page(
            'press-lms',
            'Progresso',
            'Progresso',
            'manage_options',
            'press-lms-progress',
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
            'press_lms_section_brand',
            'Marca e E-mails',
            function () {
                echo '<p>Configure informações básicas da marca usadas no fluxo do LMS e e-mails.</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_section(
            'press_lms_section_vimeo',
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
            'press_lms_section_brand'
        );

        add_settings_field(
            'email_logo_url',
            'Logo para e-mails (URL)',
            [__CLASS__, 'field_email_logo_url'],
            self::PAGE_SLUG,
            'press_lms_section_brand'
        );

        add_settings_field(
            'vimeo_token',
            'Vimeo Access Token',
            [__CLASS__, 'field_vimeo_token'],
            self::PAGE_SLUG,
            'press_lms_section_vimeo'
        );
        add_settings_field(
            'delete_data_on_uninstall',
            'Apagar dados ao desinstalar',
            [__CLASS__, 'field_delete_data_on_uninstall'],
            self::PAGE_SLUG,
            'press_lms_section_brand'
        );
    }

    public static function sanitize($input)
    {
        $output = [];

        $output['brand_name'] = sanitize_text_field($input['brand_name'] ?? 'Pressplay');
        $output['email_logo_url'] = esc_url_raw($input['email_logo_url'] ?? '');

        // Vimeo token: guarda como texto simples (vamos exibir como password field)
        $output['vimeo_token'] = trim(sanitize_text_field($input['vimeo_token'] ?? ''));
        $output['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']) ? 'yes' : 'no';
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
        $val = esc_attr(self::get('brand_name', 'Pressplay'));
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[brand_name]" value="' . $val . '" />';
        echo '<p class="description">Ex.: Cursos Espaço Pressplay</p>';
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
    public static function field_delete_data_on_uninstall()
    {
        $val = self::get('delete_data_on_uninstall', 'no');
        $checked = ($val === 'yes') ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[delete_data_on_uninstall]" value="yes" ' . $checked . '> Sim, apagar tudo (tabelas, conteúdos e configs) ao excluir o plugin</label>';
        echo '<p class="description" style="color:#b32d2e;">Atenção: isso é irreversível. Use apenas se tiver certeza.</p>';
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
