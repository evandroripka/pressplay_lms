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
        add_action('wp', [__CLASS__, 'prepare_route'], 20);
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

    public static function prepare_route(): void
    {
        if (PRESS_LMS_Frontend::is_theme_compat_request()) {
            self::normalize_virtual_route_state();
        }
    }

    private static function normalize_virtual_route_state(): void
    {
        global $wp_query;

        if (!$wp_query instanceof WP_Query) {
            return;
        }

        $status = PRESS_LMS_Frontend::get_route_status();
        $entity = PRESS_LMS_Frontend::resolve_route_post();
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_page = false;
        $wp_query->is_404 = $status === 404;
        $wp_query->is_single = $entity instanceof WP_Post;
        $wp_query->is_singular = $entity instanceof WP_Post;
        $wp_query->queried_object = $entity;
        $wp_query->queried_object_id = $entity ? (int) $entity->ID : 0;
        $wp_query->posts = $entity ? [$entity] : [];
        $wp_query->post_count = count($wp_query->posts);
        $wp_query->post = $entity;
        $GLOBALS['post'] = $entity;
        if ($entity) {
            setup_postdata($entity);
        }
        status_header($status);
        nocache_headers();
    }
}
