<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Rewrite
{
    private const SCHEMA_VERSION = '20260312_student_area_v2';

    public static function get_schema_version(): string
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Return the normalized request path relative to the WordPress home path.
     *
     * This keeps route detection consistent across root installs, subdirectory
     * installs, reverse proxies, and custom domains.
     */
    public static function get_request_path(): string
    {
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        if ($home_path && $home_path !== '/' && str_starts_with($path, $home_path)) {
            $path = (string) substr($path, strlen($home_path));
        }

        return trim($path, '/');
    }

    public static function init()
    {
        add_action('init', [__CLASS__, 'add_rules']);
        add_action('init', [__CLASS__, 'maybe_flush_rules'], 99);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('redirect_canonical', [__CLASS__, 'filter_canonical_redirect'], 10, 2);
        add_action('template_redirect', [__CLASS__, 'template_router'], 0);
    }

    public static function add_rules()
    {
        // Course route: /curso/{slug}
        add_rewrite_rule(
            '^curso/([^/]+)/?$',
            'index.php?press_course_slug=$matches[1]',
            'top'
        );

        // Lesson route: /curso/{course}/aula/{lesson}
        add_rewrite_rule(
            '^curso/([^/]+)/aula/([^/]+)/?$',
            'index.php?press_course_slug=$matches[1]&press_lesson_slug=$matches[2]',
            'top'
        );

        // Student dashboard route: /meus-cursos
        add_rewrite_rule('^meus-cursos/?$', 'index.php?press_my_courses=1', 'top');

        // Student dashboard sections with dedicated URLs.
        add_rewrite_rule(
            '^meus-cursos/certificados/?$',
            'index.php?press_my_courses=1&press_student_area=certificates',
            'top'
        );

        // Profile routes live outside the course library hierarchy.
        add_rewrite_rule(
            '^perfil/?$',
            'index.php?press_my_courses=1&press_student_area=profile',
            'top'
        );

        add_rewrite_rule(
            '^perfil/trocar-senha/?$',
            'index.php?press_my_courses=1&press_student_area=password',
            'top'
        );

        // Student certificate route: /meus-cursos/certificado/{course}
        add_rewrite_rule(
            '^meus-cursos/certificado/([^/]+)/?$',
            'index.php?press_student_certificate=$matches[1]',
            'top'
        );

        // Public LMS catalog route: /cursos
        add_rewrite_rule('^cursos/?$', 'index.php?press_course_archive=1', 'top');

        // Registration route: /cadastro
        add_rewrite_rule('^cadastro/?$', 'index.php?press_register=1', 'top');
    }

    public static function query_vars($vars)
    {
        $vars[] = 'press_course_slug';
        $vars[] = 'press_lesson_slug';
        $vars[] = 'press_my_courses';
        $vars[] = 'press_student_area';
        $vars[] = 'press_student_certificate';
        $vars[] = 'press_course_archive';
        $vars[] = 'press_register';
        return $vars;
    }

    public static function maybe_flush_rules(): void
    {
        $stored_version = (string) get_option('press_lms_rewrite_schema_version', '');

        if ($stored_version === self::SCHEMA_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('press_lms_rewrite_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function template_router()
    {
        $course_slug = sanitize_title((string) get_query_var('press_course_slug'));
        $lesson_slug = sanitize_title((string) get_query_var('press_lesson_slug'));
        $student_area = sanitize_key((string) get_query_var('press_student_area'));
        $student_certificate = sanitize_title((string) get_query_var('press_student_certificate'));
        $my_courses = (bool) get_query_var('press_my_courses');
        $course_archive = (bool) get_query_var('press_course_archive');
        $register = (bool) get_query_var('press_register');

        $request_path = self::get_request_path();

        if ($request_path !== '') {
            if (preg_match('#^meus-cursos/perfil/?$#', $request_path)) {
                self::redirect_legacy_student_area('profile');
            }

            if (preg_match('#^meus-cursos/trocar-senha/?$#', $request_path)) {
                self::redirect_legacy_student_area('password');
            }

            if ($student_certificate === '' && preg_match('#^meus-cursos/certificado/([^/]+)/?$#', $request_path, $matches)) {
                $student_certificate = sanitize_title($matches[1]);
            }

            if ($student_area === '' && preg_match('#^meus-cursos/(certificados)/?$#', $request_path, $matches)) {
                $student_area = sanitize_key($matches[1]);
                $my_courses = true;
            }

            if ($student_area === '' && preg_match('#^perfil/?$#', $request_path)) {
                $student_area = 'profile';
                $my_courses = true;
            }

            if ($student_area === '' && preg_match('#^perfil/trocar-senha/?$#', $request_path)) {
                $student_area = 'password';
                $my_courses = true;
            }

            if (!$course_archive && preg_match('#^cursos/?$#', $request_path)) {
                $course_archive = true;
            }

            if ($course_slug === '' && preg_match('#^curso/([^/]+)/aula/([^/]+)/?$#', $request_path, $matches)) {
                $course_slug = sanitize_title($matches[1]);
                $lesson_slug = sanitize_title($matches[2]);
            } elseif ($course_slug === '' && preg_match('#^curso/([^/]+)/?$#', $request_path, $matches)) {
                $course_slug = sanitize_title($matches[1]);
            }

            if (!$my_courses && preg_match('#^meus-cursos/?$#', $request_path)) {
                $my_courses = true;
            }

            if (!$register && preg_match('#^cadastro/?$#', $request_path)) {
                $register = true;
            }
        }

        // 1) Lesson page
        if ($course_slug && $lesson_slug) {
            set_query_var('press_course_slug', $course_slug);
            set_query_var('press_lesson_slug', $lesson_slug);
            return;
        }

        // 2) Course page
        if ($course_slug && !$lesson_slug) {
            set_query_var('press_course_slug', $course_slug);
            return;
        }

        // 3) Student certificate
        if ($student_certificate) {
            PRESS_LMS_Frontend::render_student_certificate_by_slug($student_certificate);
            exit;
        }

        // 4) Course catalog
        if ($course_archive) {
            set_query_var('press_course_archive', 1);
            return;
        }

        // 5) Student dashboard
        if ($my_courses) {
            set_query_var('press_my_courses', 1);
            if ($student_area !== '') {
                set_query_var('press_student_area', $student_area);
            }
            return;
        }

        // 6) Registration
        if ($register) {
            set_query_var('press_register', 1);
            return;
        }
    }

    public static function filter_canonical_redirect($redirect_url, $requested_url)
    {
        $path = self::get_request_path();

        if ($path !== '' && preg_match('#^(curso/[^/]+(?:/aula/[^/]+)?|meus-cursos(?:/(?:certificados|perfil|trocar-senha|certificado/[^/]+))?|perfil(?:/trocar-senha)?|cursos|cadastro)/?$#', $path)) {
            return false;
        }

        return $redirect_url;
    }

    private static function redirect_legacy_student_area(string $area): void
    {
        if (!class_exists('PRESS_LMS_Frontend') || !method_exists('PRESS_LMS_Frontend', 'get_student_area_url')) {
            return;
        }

        $args = [];
        if (!empty($_GET['notice'])) {
            $args['notice'] = sanitize_key((string) wp_unslash($_GET['notice']));
        }

        wp_safe_redirect(PRESS_LMS_Frontend::get_student_area_url($area, $args));
        exit;
    }
}
