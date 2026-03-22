<?php
if (!defined('ABSPATH')) exit;

/**
 * Route LMS requests through the plugin templates while preserving theme compatibility.
 */
class PRESS_LMS_Templates
{
    public static function init()
    {
        // Run late so page-builder template loaders do not override LMS virtual routes.
        add_filter('template_include', [__CLASS__, 'template_include'], 9999);
        add_filter('pre_get_document_title', [__CLASS__, 'filter_document_title'], 20);
        add_filter('body_class', [__CLASS__, 'filter_body_class']);
    }

    public static function template_include($template)
    {
        if (class_exists('PRESS_LMS_Frontend') && PRESS_LMS_Frontend::is_theme_compat_request()) {
            self::normalize_virtual_route_state();

            $plugin_template = PRESS_LMS_PATH . 'templates/frontend/theme-compat.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        // Use the plugin template for course singles.
        if (is_singular('press_course')) {
            $plugin_template = PRESS_LMS_PATH . 'templates/frontend/single-press_course.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        // Use the plugin template for lesson singles.
        if (is_singular('press_lesson')) {
            $plugin_template = PRESS_LMS_PATH . 'templates/frontend/single-press_lesson.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        return $template;
    }

    public static function filter_document_title(string $title): string
    {
        if (!class_exists('PRESS_LMS_Frontend') || !PRESS_LMS_Frontend::is_theme_compat_request()) {
            return $title;
        }

        return PRESS_LMS_Frontend::get_theme_compat_page_title();
    }

    public static function filter_body_class(array $classes): array
    {
        if (!class_exists('PRESS_LMS_Frontend') || !PRESS_LMS_Frontend::is_theme_compat_request()) {
            return $classes;
        }

        $context = PRESS_LMS_Frontend::get_current_frontend_route();
        $route_type = sanitize_html_class((string) ($context['type'] ?? 'frontend'));

        $classes[] = 'presslms-theme-route';
        $classes[] = 'presslms-route-' . $route_type;

        return array_values(array_unique($classes));
    }

    private static function normalize_virtual_route_state(): void
    {
        global $wp_query;

        if (!$wp_query instanceof WP_Query) {
            return;
        }

        $wp_query->is_404 = false;
        status_header(200);
        nocache_headers();
    }
}
