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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_panel_assets']);
    }

    public static function menu()
    {
        // Use the bundled plugin icon when available.
        $icon_url = PRESS_LMS_URL . 'assets/pressplay_lms_logo.png';

        // Fall back to a Dashicon if the custom icon cannot be resolved.
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
            'press-lms', // Parent LMS menu slug.
            'Professores', // Page title.
            'Professores', // Menu label.
            'edit_posts', // Required capability.
            'edit.php?post_type=press_teacher' // Native teacher post type screen.
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
            'Configurações',
            'Configurações',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'page_settings']
        );

    }

    public static function register()
    {
        register_setting(self::GROUP_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);

        // Settings sections.
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

        // Settings fields.
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

        // Store the Vimeo token as plain text and mask it only at render time.
        $output['vimeo_token'] = trim(sanitize_text_field($input['vimeo_token'] ?? ''));
        $output['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']) ? 'yes' : 'no';
        return $output;
    }

    // Shared settings accessor used across the plugin.
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
        if (!current_user_can('manage_options')) return;

        global $wpdb;

        $table_students    = PRESS_LMS_Database::table('students');
        $table_enrollments = PRESS_LMS_Database::table('enrollments');
        $table_progress    = PRESS_LMS_Database::table('progress');
        $posts_table       = $wpdb->posts;
        $postmeta_table    = $wpdb->postmeta;
        $users_table       = $wpdb->users;

        // Admin filters.
        $filter_course = isset($_GET['course']) ? (int) $_GET['course'] : 0;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_sort   = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';
        $now = current_time('mysql');

        $where = [];
        $params = [];

        $where[] = "1=1";

        if ($filter_course > 0) {
            $where[] = "e.course_id = %d";
            $params[] = $filter_course;
        }

        if ($filter_status === 'active') {
            $where[] = "e.status = %s AND (e.expires_at IS NULL OR e.expires_at > %s)";
            $params[] = 'active';
            $params[] = $now;
        } elseif ($filter_status === 'expired') {
            $where[] = "e.status = %s AND e.expires_at IS NOT NULL AND e.expires_at <= %s";
            $params[] = 'active';
            $params[] = $now;
        } elseif ($filter_status !== '') {
            $where[] = "e.status = %s";
            $params[] = $filter_status;
        }

        if ($filter_search !== '') {
            $like = '%' . $wpdb->esc_like($filter_search) . '%';

            $where[] = "(
        st.full_name LIKE %s
        OR u.display_name LIKE %s
        OR u.user_nicename LIKE %s
        OR u.user_login LIKE %s
        OR u.user_email LIKE %s
        OR c.post_title LIKE %s
    )";

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $order_by = "e.created_at DESC";

        switch ($filter_sort) {
            case 'name_asc':
                $order_by = "st.full_name ASC";
                break;
            case 'name_desc':
                $order_by = "st.full_name DESC";
                break;
            case 'date_asc':
                $order_by = "e.created_at ASC";
                break;
            case 'date_desc':
            default:
                $order_by = "e.created_at DESC";
                break;
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
    SELECT
        e.id,
        e.user_id,
        e.course_id,
        e.status,
        e.order_ref,
        e.payment_provider,
        e.created_at,
        e.updated_at,
        e.purchased_at,
        e.expires_at,

        COALESCE(NULLIF(st.full_name, ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), 'Sem nome') AS full_name,
        st.phone_raw,
        st.phone_e164,

        u.user_email,
        u.display_name,

        c.post_title AS course_title,

        COALESCE(pr.completed_lessons, 0) AS completed_lessons,
        COALESCE(tl.total_lessons, 0) AS total_lessons

    FROM {$table_enrollments} e

    LEFT JOIN {$table_students} st
        ON st.user_id = e.user_id

    LEFT JOIN {$users_table} u
        ON u.ID = e.user_id

    LEFT JOIN {$posts_table} c
        ON c.ID = e.course_id

    LEFT JOIN (
        SELECT
            user_id,
            course_id,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) AS completed_lessons
        FROM {$table_progress}
        GROUP BY user_id, course_id
    ) pr
        ON pr.user_id = e.user_id
        AND pr.course_id = e.course_id

    LEFT JOIN (
        SELECT
            pm.meta_value AS course_id,
            COUNT(p.ID) AS total_lessons
        FROM {$posts_table} p
        INNER JOIN {$postmeta_table} pm
            ON pm.post_id = p.ID
            AND pm.meta_key = '_press_lesson_course_id'
        WHERE p.post_type = 'press_lesson'
          AND p.post_status = 'publish'
        GROUP BY pm.meta_value
    ) tl
        ON tl.course_id = e.course_id

    WHERE {$where_sql}
    ORDER BY {$order_by}
";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $students = $wpdb->get_results($sql);

        $courses = get_posts([
            'post_type'      => 'press_course',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        self::render_panel_template('alunos.php', [
            'students'      => $students,
            'courses'       => $courses,
            'filter_course' => $filter_course,
            'filter_status' => $filter_status,
            'filter_search' => $filter_search,
            'filter_sort'   => $filter_sort,
        ]);
    }

    public static function page_enrollments()
    {
        echo '<div class="wrap"><h1>Matrículas</h1><p>Listagem e gerenciamento.</p></div>';
    }

    public static function page_progress()
    {
        echo '<div class="wrap"><h1>Progresso</h1><p>Relatórios de progresso por aluno/curso.</p></div>';
    }

    public static function enqueue_panel_assets($hook)
    {
        if (!is_admin()) return;

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        $allowed_pages = [
            'press-lms',
            self::PAGE_SLUG,
            'press-lms-students',
            'press-lms-enrollments',
            'press-lms-progress',
        ];

        if (!in_array($page, $allowed_pages, true)) {
            return;
        }

        // Load Bulma only on LMS admin screens.
        wp_enqueue_style(
            'presslms-bulma',
            'https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css',
            [],
            '0.9.4'
        );

        wp_enqueue_style(
            'presslms-admin-panels',
            PRESS_LMS_URL . 'assets/css/admin-panels.css',
            ['presslms-bulma', 'press-lms-admin'],
            PRESS_LMS_VERSION
        );
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            [],
            '11',
            true
        );

        wp_enqueue_script(
            'presslms-admin-panels-js',
            PRESS_LMS_URL . 'assets/js/admin-panels.js',
            ['jquery', 'sweetalert2'],
            PRESS_LMS_VERSION,
            true
        );

        wp_localize_script('presslms-admin-panels-js', 'presslmsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('presslms_admin_nonce'),
        ]);
    }
    private static function render_panel_template($template_file, array $vars = [])
    {
        $template = PRESS_LMS_PATH . 'templates/panel/' . $template_file;

        if (!file_exists($template)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Template não encontrado: ' . esc_html($template) . '</p></div></div>';
            return;
        }

        extract($vars, EXTR_SKIP);
        include $template;
    }
}
