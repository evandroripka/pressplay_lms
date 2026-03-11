<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Menu
{
    public static function init(): void
    {
        if (is_admin()) {
            add_action('admin_head-nav-menus.php', [__CLASS__, 'register_nav_menu_meta_box']);
        }

        add_filter('nav_menu_css_class', [__CLASS__, 'filter_nav_menu_css_class'], 10, 4);
    }

    public static function register_nav_menu_meta_box(): void
    {
        add_meta_box(
            'presslms-student-links',
            'Pressplay LMS',
            [__CLASS__, 'render_nav_menu_meta_box'],
            'nav-menus',
            'side',
            'default'
        );
    }

    public static function render_nav_menu_meta_box(): void
    {
        if (!class_exists('Walker_Nav_Menu_Checklist')) {
            require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
        }

        global $_nav_menu_placeholder, $nav_menu_selected_id;

        $_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
        $items = [];

        foreach (PRESS_LMS_Frontend::get_student_menu_items() as $key => $item) {
            $items[] = (object) [
                'ID' => 0,
                'object_id' => $_nav_menu_placeholder,
                'db_id' => 0,
                'object' => 'press_lms_link',
                'menu_item_parent' => 0,
                'type' => 'custom',
                'type_label' => 'Pressplay LMS',
                'title' => (string) ($item['label'] ?? $key),
                'url' => (string) ($item['url'] ?? ''),
                'target' => '',
                'attr_title' => '',
                'description' => '',
                'classes' => ['presslms-menu-item', 'presslms-menu-item--' . sanitize_html_class($key)],
                'xfn' => '',
            ];

            $_nav_menu_placeholder--;
        }

        $walker = new Walker_Nav_Menu_Checklist([]);
        ?>
        <div id="presslms-links" class="posttypediv">
            <div id="tabs-panel-presslms-links-all" class="tabs-panel tabs-panel-active">
                <p>Adicione atalhos prontos da area do aluno para usar no menu do tema ou no widget Nav Menu do Elementor.</p>
                <ul id="presslms-links-checklist-all" class="categorychecklist form-no-clear">
                    <?php
                    echo walk_nav_menu_tree(
                        array_map('wp_setup_nav_menu_item', $items),
                        0,
                        (object) ['walker' => $walker]
                    );
                    ?>
                </ul>
            </div>

            <p class="button-controls wp-clearfix">
                <span class="list-controls hide-if-no-js">
                    <input type="checkbox" id="presslms-links-select-all" class="select-all">
                    <label for="presslms-links-select-all">Selecionar todos</label>
                </span>

                <span class="add-to-menu">
                    <input
                        type="submit"
                        <?php wp_nav_menu_disabled_check($nav_menu_selected_id); ?>
                        class="button submit-add-to-menu right"
                        value="Adicionar ao menu"
                        name="add-presslms-links-menu-item"
                        id="submit-presslms-links">
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

    public static function filter_nav_menu_css_class(array $classes, $item, $args, $depth): array
    {
        if (!isset($item->url) || !is_string($item->url)) {
            return $classes;
        }

        $current_key = self::get_current_menu_key();
        if ($current_key === '') {
            return $classes;
        }

        $item_key = self::get_menu_key_from_url($item->url);
        if ($item_key === '' || $item_key !== $current_key) {
            return $classes;
        }

        $classes[] = 'current-menu-item';
        $classes[] = 'current_page_item';
        $classes[] = 'current-menu-ancestor';

        return array_values(array_unique($classes));
    }

    private static function get_current_menu_key(): string
    {
        $path = self::get_current_request_path();
        $key = PRESS_LMS_Frontend::resolve_student_area_from_path($path);

        if ($key === 'courses' && isset($_GET['tab'])) {
            $legacy_tab = sanitize_key((string) $_GET['tab']);
            $legacy_map = [
                'courses' => 'courses',
                'certificates' => 'certificates',
                'profile' => 'profile',
                'password' => 'password',
            ];

            if (isset($legacy_map[$legacy_tab])) {
                return $legacy_map[$legacy_tab];
            }
        }

        return $key;
    }

    private static function get_menu_key_from_url(string $url): string
    {
        $menu_items = PRESS_LMS_Frontend::get_student_menu_items();
        $url_path = self::normalize_url_path($url);

        foreach ($menu_items as $key => $item) {
            $item_path = self::normalize_url_path((string) ($item['url'] ?? ''));

            if ($item_path !== '' && $item_path === $url_path) {
                return (string) $key;
            }
        }

        return '';
    }

    private static function get_current_request_path(): string
    {
        $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        if ($home_path && $home_path !== '/' && str_starts_with($path, $home_path)) {
            $path = (string) substr($path, strlen($home_path));
        }

        return trim($path, '/');
    }

    private static function normalize_url_path(string $url): string
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        if ($home_path && $home_path !== '/' && str_starts_with($path, $home_path)) {
            $path = (string) substr($path, strlen($home_path));
        }

        return trim($path, '/');
    }
}
