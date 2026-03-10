<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Rewrite
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
            'index.php?press_course_slug=$matches[1]',
            'top'
        );

        // Página de aulas: /curso/{curso}/aula/{aula}
        add_rewrite_rule(
            '^curso/([^/]+)/aula/([^/]+)/?$',
            'index.php?press_course_slug=$matches[1]&press_lesson_slug=$matches[2]',
            'top'
        );

        // Meus cursos: /meus-cursos
        add_rewrite_rule('^meus-cursos/?$', 'index.php?press_my_courses=1', 'top');

        // Cadastro: /cadastro
        add_rewrite_rule('^cadastro/?$', 'index.php?press_register=1', 'top');
    }

    public static function query_vars($vars)
    {
        $vars[] = 'press_course_slug';
        $vars[] = 'press_lesson_slug';
        $vars[] = 'press_my_courses';
        $vars[] = 'press_register';
        return $vars;
    }

    public static function template_router()
    {
        $course_slug = get_query_var('press_course_slug');
        $lesson_slug = get_query_var('press_lesson_slug');

        // 1) Aula de curso
        if ($course_slug && $lesson_slug) {
            PRESS_LMS_Frontend::render_lesson_by_slug($course_slug, $lesson_slug);
            exit;
        }

        // 2) Página de curso
        if ($course_slug && !$lesson_slug) {
            PRESS_LMS_Frontend::render_course_by_slug($course_slug);
            exit;
        }

        // 3) Meus Cursos
        if (get_query_var('press_my_courses')) {
            PRESS_LMS_Frontend::render_my_courses();
            exit;
        }

        // 4) Cadastro
        if (get_query_var('press_register')) {
            PRESS_LMS_Frontend::render_register();
            exit;
        }
    }
}
