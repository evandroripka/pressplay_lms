<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Rewrite
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'add_rules']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_router']);
    }

    public static function add_rules()
    {
        // Rota do curso: /curso/{slug}
        add_rewrite_rule(
            '^curso/([^/]+)/?$',
            'index.php?mlb_course_slug=$matches[1]',
            'top'
        );

        // Página de aulas: /curso/{curso}/aula/{aula}
        add_rewrite_rule(
            '^curso/([^/]+)/aula/([^/]+)/?$',
            'index.php?mlb_course_slug=$matches[1]&mlb_lesson_slug=$matches[2]',
            'top'
        );

        // Meus cursos: /meus-cursos
        add_rewrite_rule('^meus-cursos/?$', 'index.php?mlb_my_courses=1', 'top');

        // Cadastro: /cadastro
        add_rewrite_rule('^cadastro/?$', 'index.php?mlb_register=1', 'top');
    }

    public static function query_vars($vars)
    {
        $vars[] = 'mlb_course_slug';
        $vars[] = 'mlb_lesson_slug';
        $vars[] = 'mlb_my_courses';
        $vars[] = 'mlb_register';
        return $vars;
    }

    public static function template_router()
    {
        $course_slug = get_query_var('mlb_course_slug');
        $lesson_slug = get_query_var('mlb_lesson_slug');

        // 1) Aula de curso
        if ($course_slug && $lesson_slug) {
            MLB_LMS_Frontend::render_lesson_by_slug($course_slug, $lesson_slug);
            exit;
        }

        // 2) Página de curso
        if ($course_slug && !$lesson_slug) {
            MLB_LMS_Frontend::render_course_by_slug($course_slug);
            exit;
        }

        // 3) Meus Cursos
        if (get_query_var('mlb_my_courses')) {
            MLB_LMS_Frontend::render_my_courses();
            exit;
        }

        // 4) Cadastro
        if (get_query_var('mlb_register')) {
            MLB_LMS_Frontend::render_register();
            exit;
        }
    }
}
