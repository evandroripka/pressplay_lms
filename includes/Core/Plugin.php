<?php
if (!defined('ABSPATH')) exit;

/**
 * Central plugin bootstrap for lifecycle hooks, shared assets, and module boot order.
 */
class PRESS_LMS_Plugin
{
    private static function enqueue_icon_assets(): void
    {
        wp_enqueue_style(
            'presslms-fa-local',
            PRESS_LMS_URL . 'assets/vendor/fontawesome/css/all.css',
            [],
            '5.15.3'
        );

        wp_enqueue_style(
            'presslms-fa-compat',
            PRESS_LMS_URL . 'assets/css/presslms-fa-compat.css',
            ['presslms-fa-local'],
            PRESS_LMS_VERSION
        );
    }

    public static function register(): void
    {
        register_activation_hook(PRESS_LMS_FILE, ['PRESS_LMS_Activator', 'activate']);
        register_deactivation_hook(PRESS_LMS_FILE, ['PRESS_LMS_Deactivator', 'deactivate']);

        add_action('plugins_loaded', [__CLASS__, 'boot']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('admin_body_class', [__CLASS__, 'filter_admin_body_class']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_app_assets'], 20);
    }

    public static function boot(): void
    {
        // Boot infrastructure first so shared settings, routes, and dependency checks
        // are available before domain modules attach their hooks.
        PRESSLMS_Assets::init();
        PRESS_LMS_Dependencies::init();
        PRESS_LMS_Settings::init();
        PRESS_LMS_Roles::init();
        PRESS_LMS_Rewrite::init();
        PRESS_LMS_Frontend::init();
        PRESS_LMS_Mailer::init();
        PRESS_LMS_Menu::init();
        PRESS_LMS_CPT::init();
        PRESSLMS_Teacher_CPT::init();
        PRESS_LMS_Course_Meta::init();
        PRESS_LMS_Lesson_Meta::init();
        PRESS_LMS_Woo::init();
        PRESS_LMS_Templates::init();
        PRESS_LMS_Actions::init();
        PRESS_LMS_Course_Lifecycle::init();
        PRESS_LMS_Teacher_Meta::init();

        // Keep optional integrations isolated so the core LMS can boot without them.
        if (class_exists('PRESS_LMS_Vimeo') && method_exists('PRESS_LMS_Vimeo', 'init')) {
            PRESS_LMS_Vimeo::init();
        }

        PRESS_LMS_Certificate::init();
    }

    public static function enqueue_admin_assets(): void
    {
        if (!self::is_lms_admin_screen()) {
            return;
        }

        self::enqueue_icon_assets();
        wp_enqueue_style('press-lms-admin', PRESS_LMS_URL . 'assets/css/admin.css', [], PRESS_LMS_VERSION);
    }

    public static function filter_admin_body_class(string $classes): string
    {
        if (!self::is_lms_admin_screen()) {
            return $classes;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $tokens = ['presslms-admin-screen'];

        if ($screen && !empty($screen->post_type)) {
            $tokens[] = 'presslms-admin-posttype-' . sanitize_html_class((string) $screen->post_type);
        }

        if ($screen && !empty($screen->base)) {
            $tokens[] = 'presslms-admin-base-' . sanitize_html_class((string) $screen->base);
        }

        if ($screen && !empty($screen->id)) {
            $tokens[] = 'presslms-admin-id-' . sanitize_html_class((string) $screen->id);
        }

        return trim($classes . ' ' . implode(' ', array_unique($tokens)));
    }

    public static function is_lms_admin_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        if (in_array((string) $screen->post_type, ['press_course', 'press_teacher', 'press_lesson'], true)) {
            return true;
        }

        return in_array((string) $screen->id, [
            'toplevel_page_press-lms',
            'pressplay-lms_page_press-lms-settings',
            'pressplay-lms_page_press-lms-enrollments',
            'pressplay-lms_page_press-lms-progress',
        ], true);
    }

    public static function enqueue_app_assets(): void
    {
        wp_enqueue_style('press-lms-app', PRESS_LMS_URL . 'assets/css/app.css', [], PRESS_LMS_VERSION);
    }
}
