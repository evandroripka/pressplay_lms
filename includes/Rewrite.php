<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Rewrite {
    public static function init() {
        add_action('init', [__CLASS__, 'add_rules']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_router']);
    }

    public static function add_rules() {
        add_rewrite_rule('^curso/([^/]+)/?$', 'index.php?mlb_course_slug=$matches[1]', 'top');
        add_rewrite_rule('^meus-cursos/?$', 'index.php?mlb_my_courses=1', 'top');
        add_rewrite_rule('^cadastro/?$', 'index.php?mlb_register=1', 'top');
    }

    public static function query_vars($vars) {
        $vars[] = 'mlb_course_slug';
        $vars[] = 'mlb_my_courses';
        $vars[] = 'mlb_register';
        return $vars;
    }

    public static function template_router() {
        $course_slug = get_query_var('mlb_course_slug');
        if ($course_slug) {
            MLB_LMS_Frontend::render_course_by_slug($course_slug);
            exit;
        }

        if (get_query_var('mlb_my_courses')) {
            MLB_LMS_Frontend::render_my_courses();
            exit;
        }

        if (get_query_var('mlb_register')) {
            MLB_LMS_Frontend::render_register();
            exit;
        }
    }
}
