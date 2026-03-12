<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Frontend
{
    private static bool $theme_compat_mode = false;

    public static function init()
    {
        add_shortcode('press_register', [__CLASS__, 'shortcode_register']);
    }

    public static function header($title = 'Pressplay')
    {
        status_header(200);
        nocache_headers();

        if (self::$theme_compat_mode) {
            return;
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head><body class="press-body">';
    }

    public static function footer()
    {
        if (self::$theme_compat_mode) {
            return;
        }

        wp_footer();
        echo '</body></html>';
    }

    public static function is_theme_compat_request(): bool
    {
        $context = self::get_current_frontend_route();

        return in_array((string) ($context['type'] ?? ''), ['lesson', 'course', 'catalog', 'student', 'register'], true);
    }

    public static function get_current_frontend_route(): array
    {
        $course_slug = sanitize_title((string) get_query_var('press_course_slug'));
        $lesson_slug = sanitize_title((string) get_query_var('press_lesson_slug'));
        $student_certificate = sanitize_title((string) get_query_var('press_student_certificate'));
        $my_courses = (bool) get_query_var('press_my_courses');
        $course_archive = (bool) get_query_var('press_course_archive');
        $register = (bool) get_query_var('press_register');
        $student_area = sanitize_key((string) get_query_var('press_student_area'));

        if ($course_slug !== '' && $lesson_slug !== '') {
            return [
                'type' => 'lesson',
                'course_slug' => $course_slug,
                'lesson_slug' => $lesson_slug,
            ];
        }

        if ($course_slug !== '') {
            return [
                'type' => 'course',
                'course_slug' => $course_slug,
            ];
        }

        if ($student_certificate !== '') {
            return [
                'type' => 'certificate',
                'course_slug' => $student_certificate,
            ];
        }

        if ($course_archive) {
            return ['type' => 'catalog'];
        }

        if ($my_courses) {
            return [
                'type' => 'student',
                'student_area' => $student_area,
            ];
        }

        if ($register) {
            return ['type' => 'register'];
        }

        return ['type' => ''];
    }

    public static function get_theme_compat_page_title(): string
    {
        $context = self::get_current_frontend_route();
        $route_type = (string) ($context['type'] ?? '');

        if ($route_type === 'student') {
            return self::get_student_area_page_title(self::get_student_dashboard_tab());
        }

        if ($route_type === 'catalog') {
            return 'Cursos - Pressplay';
        }

        if ($route_type === 'register') {
            return 'Cadastro - Pressplay';
        }

        if ($route_type === 'course') {
            $course = get_page_by_path((string) ($context['course_slug'] ?? ''), OBJECT, 'press_course');

            return $course instanceof WP_Post
                ? sprintf('%s - Pressplay', (string) $course->post_title)
                : 'Curso - Pressplay';
        }

        if ($route_type === 'lesson') {
            $course = get_page_by_path((string) ($context['course_slug'] ?? ''), OBJECT, 'press_course');
            $lesson = $course instanceof WP_Post
                ? self::find_lesson_for_course((string) ($context['lesson_slug'] ?? ''), (int) $course->ID)
                : null;

            if ($course instanceof WP_Post && $lesson instanceof WP_Post) {
                return sprintf('%s - %s', (string) $lesson->post_title, (string) $course->post_title);
            }

            return 'Aula - Pressplay';
        }

        return 'Pressplay';
    }

    public static function render_theme_compat_content(): void
    {
        $context = self::get_current_frontend_route();
        $route_type = (string) ($context['type'] ?? '');

        if (!in_array($route_type, ['lesson', 'course', 'catalog', 'student', 'register'], true)) {
            return;
        }

        $previous_mode = self::$theme_compat_mode;
        self::$theme_compat_mode = true;

        try {
            self::render_frontend_route($context);
        } finally {
            self::$theme_compat_mode = $previous_mode;
        }
    }

    private static function render_frontend_route(array $context): void
    {
        $route_type = (string) ($context['type'] ?? '');

        switch ($route_type) {
            case 'lesson':
                self::render_lesson_by_slug(
                    (string) ($context['course_slug'] ?? ''),
                    (string) ($context['lesson_slug'] ?? '')
                );
                return;

            case 'course':
                self::render_course_by_slug((string) ($context['course_slug'] ?? ''));
                return;

            case 'catalog':
                self::render_course_archive();
                return;

            case 'student':
                self::render_my_courses();
                return;

            case 'register':
                self::render_register();
                return;
        }
    }

    public static function render_register()
    {
        self::header('Cadastro - Pressplay');
        echo '<div class="press-container">';
        echo '<div class="press-card">';
        echo '<h1 class="press-title">Criar conta</h1>';
        echo do_shortcode('[press_register]');
        echo '</div></div>';
        self::footer();
    }

    public static function shortcode_register()
    {
        if (is_user_logged_in()) {
            return '<div class="press-alert press-alert--info">Você já está logado. <a class="press-link" href="' . esc_url(self::get_student_area_url('courses')) . '">Ir para Meus Cursos</a></div>';
        }

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['press_register_nonce']) && wp_verify_nonce($_POST['press_register_nonce'], 'press_register')) {
            $full_name = sanitize_text_field($_POST['full_name'] ?? '');
            $phone     = sanitize_text_field($_POST['phone'] ?? '');
            $email     = sanitize_email($_POST['email'] ?? '');

            if (strlen($full_name) < 5) $errors[] = 'Informe seu nome completo.';
            if (!PRESS_LMS_Helpers::is_valid_phone_br($phone)) $errors[] = 'Telefone inválido. Use DDD + número.';
            if (!is_email($email)) $errors[] = 'E-mail inválido.';

            if (empty($errors)) {
                if (email_exists($email)) {
                    $errors[] = 'Este e-mail já está cadastrado. Faça login.';
                } else {
                    $username = PRESS_LMS_Helpers::username_from_email($email);
                    $password = wp_generate_password(24, true, true);

                    $user_id = wp_create_user($username, $password, $email);
                    if (is_wp_error($user_id)) {
                        $errors[] = 'Não foi possível criar o usuário. Tente outro e-mail.';
                    } else {
                        wp_update_user([
                            'ID' => $user_id,
                            'display_name' => $full_name,
                            'first_name' => $full_name,
                            'role' => 'press_student',
                        ]);

                        PRESS_LMS_Helpers::upsert_student_profile($user_id, $full_name, $phone);

                        // Send the standard password setup email for the new student.
                        PRESS_LMS_Mailer::send_set_password_email($user_id);

                        $success = true;
                    }
                }
            }
        }

        ob_start();

        if ($success) {
            echo '<div class="press-alert press-alert--success">Conta criada! Enviamos um e-mail para você definir sua senha.</div>';
            return ob_get_clean();
        }

        if (!empty($errors)) {
            echo '<div class="press-alert press-alert--error"><ul class="press-list">';
            foreach ($errors as $e) echo '<li>' . esc_html($e) . '</li>';
            echo '</ul></div>';
        }

        echo '<form method="post" class="press-form">';
        wp_nonce_field('press_register', 'press_register_nonce');

        echo '<label class="press-label">Nome completo</label>';
        echo '<input class="press-input" name="full_name" required placeholder="Seu nome completo" />';

        echo '<label class="press-label">Telefone (DDD)</label>';
        echo '<input class="press-input" name="phone" required placeholder="(11) 91234-5678" />';

        echo '<label class="press-label">E-mail</label>';
        echo '<input class="press-input" type="email" name="email" required placeholder="seuemail@exemplo.com" />';

        echo '<button class="press-btn press-btn--primary" type="submit">Criar conta</button>';
        echo '</form>';

        return ob_get_clean();
    }

    public static function get_student_area_definitions(): array
    {
        $areas = [
            'catalog' => [
                'label' => 'Cursos',
                'path' => '/cursos/',
                'page_title' => 'Cursos - Pressplay',
                'menu' => true,
                'student_nav' => true,
            ],
            'courses' => [
                'label' => 'Meus cursos',
                'path' => '/meus-cursos/',
                'page_title' => 'Meus cursos - Pressplay',
                'menu' => true,
                'student_nav' => true,
            ],
            'certificates' => [
                'label' => 'Certificados',
                'path' => '/meus-cursos/certificados/',
                'page_title' => 'Certificados - Pressplay',
                'menu' => true,
                'student_nav' => true,
            ],
            'profile' => [
                'label' => 'Meu perfil',
                'path' => '/perfil/',
                'page_title' => 'Meu perfil - Pressplay',
                'menu' => true,
                'student_nav' => true,
            ],
            'password' => [
                'label' => 'Trocar senha',
                'path' => '/perfil/trocar-senha/',
                'page_title' => 'Trocar senha - Pressplay',
                'menu' => true,
                'student_nav' => true,
            ],
        ];

        return apply_filters('press_lms_student_area_definitions', $areas);
    }

    public static function get_student_area_url(string $area = 'courses', array $args = []): string
    {
        $areas = self::get_student_area_definitions();

        if (!isset($areas[$area])) {
            $area = 'courses';
        }

        $url = home_url((string) $areas[$area]['path']);

        return !empty($args) ? add_query_arg($args, $url) : $url;
    }

    public static function get_student_menu_items(): array
    {
        $items = [];

        foreach (self::get_student_area_definitions() as $key => $definition) {
            if (empty($definition['menu'])) {
                continue;
            }

            $items[$key] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'url' => self::get_student_area_url($key),
                'student_nav' => !empty($definition['student_nav']),
            ];
        }

        return $items;
    }

    public static function resolve_student_area_from_path(string $path): string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        if (preg_match('#^cursos/?$#', $path)) {
            return 'catalog';
        }

        if (preg_match('#^meus-cursos/certificado/[^/]+/?$#', $path)) {
            return 'certificates';
        }

        if (preg_match('#^meus-cursos/certificados/?$#', $path)) {
            return 'certificates';
        }

        if (preg_match('#^(perfil|meus-cursos/perfil)/?$#', $path)) {
            return 'profile';
        }

        if (preg_match('#^(perfil/trocar-senha|meus-cursos/trocar-senha)/?$#', $path)) {
            return 'password';
        }

        if (preg_match('#^meus-cursos/?$#', $path)) {
            return 'courses';
        }

        return '';
    }

    private static function get_student_dashboard_tab(): string
    {
        $area = sanitize_key((string) get_query_var('press_student_area'));
        $map = [
            'courses' => 'courses',
            'certificates' => 'certificates',
            'certificados' => 'certificates',
            'profile' => 'profile',
            'perfil' => 'profile',
            'password' => 'password',
            'trocar-senha' => 'password',
        ];

        if ($area !== '' && isset($map[$area])) {
            return $map[$area];
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'courses';
        $legacy_map = [
            'courses' => 'courses',
            'certificates' => 'certificates',
            'profile' => 'profile',
            'password' => 'password',
        ];

        return $legacy_map[$tab] ?? 'courses';
    }

    private static function get_student_area_page_title(string $area): string
    {
        $areas = self::get_student_area_definitions();

        return (string) ($areas[$area]['page_title'] ?? 'Área do Aluno - Pressplay');
    }

    private static function get_student_login_url(string $redirect_to = ''): string
    {
        $dashboard_url = $redirect_to !== '' ? $redirect_to : self::get_student_area_url('courses');

        if (class_exists('WooCommerce') && function_exists('wc_get_page_permalink')) {
            $myaccount = wc_get_page_permalink('myaccount');
            if ($myaccount) {
                return add_query_arg('redirect_to', $dashboard_url, $myaccount);
            }
        }

        return wp_login_url($dashboard_url);
    }

    private static function get_student_catalog_url(): string
    {
        return self::get_student_area_url('catalog');
    }

    private static function get_student_dashboard_notice(?string $notice): ?array
    {
        $notice = sanitize_key((string) $notice);
        if ($notice === '') {
            return null;
        }

        $map = [
            'password_updated' => [
                'type' => 'success',
                'message' => 'Sua senha foi atualizada com sucesso.',
            ],
            'profile_updated' => [
                'type' => 'success',
                'message' => 'Seu perfil foi atualizado com sucesso.',
            ],
            'password_current_invalid' => [
                'type' => 'error',
                'message' => 'A senha atual informada não confere.',
            ],
            'profile_name_invalid' => [
                'type' => 'error',
                'message' => 'Informe seu nome completo.',
            ],
            'profile_phone_invalid' => [
                'type' => 'error',
                'message' => 'Informe um telefone válido com DDD.',
            ],
            'profile_email_invalid' => [
                'type' => 'error',
                'message' => 'Informe um e-mail válido.',
            ],
            'profile_email_exists' => [
                'type' => 'error',
                'message' => 'Este e-mail já está em uso por outra conta.',
            ],
            'profile_avatar_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível enviar a foto de perfil. Tente novamente com uma imagem válida.',
            ],
            'profile_nonce_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível validar a atualização do perfil. Tente novamente.',
            ],
            'profile_update_failed' => [
                'type' => 'error',
                'message' => 'Não foi possível atualizar o seu perfil agora.',
            ],
            'password_mismatch' => [
                'type' => 'error',
                'message' => 'A nova senha e a confirmação precisam ser iguais.',
            ],
            'password_too_short' => [
                'type' => 'error',
                'message' => 'A nova senha precisa ter pelo menos 6 caracteres.',
            ],
            'password_nonce_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível validar a solicitação. Tente novamente.',
            ],
            'password_user_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível localizar a sua conta.',
            ],
            'certificate_course_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível localizar o certificado solicitado.',
            ],
            'certificate_unavailable' => [
                'type' => 'error',
                'message' => 'O certificado fica disponível somente após concluir 100% do curso.',
            ],
            'certificate_forbidden' => [
                'type' => 'error',
                'message' => 'Você não tem permissão para acessar esse certificado.',
            ],
        ];

        return $map[$notice] ?? null;
    }

    private static function get_course_frontend_notice(?string $notice): ?array
    {
        $notice = sanitize_key((string) $notice);
        if ($notice === '') {
            return null;
        }

        $map = [
            'enroll_invalid_request' => [
                'type' => 'error',
                'message' => 'Não foi possível iniciar a matrícula. Tente novamente.',
            ],
            'enroll_course_paused' => [
                'type' => 'error',
                'message' => 'Este curso está pausado no momento e não aceita novas matrículas.',
            ],
            'enroll_woo_required' => [
                'type' => 'error',
                'message' => 'O WooCommerce é obrigatório para concluir esta matrícula.',
            ],
            'enroll_product_missing' => [
                'type' => 'error',
                'message' => 'O produto deste curso ainda não está disponível para compra.',
            ],
            'enroll_cart_unavailable' => [
                'type' => 'error',
                'message' => 'Não foi possível iniciar o carrinho da matrícula agora. Tente novamente.',
            ],
            'enroll_login_required' => [
                'type' => 'error',
                'message' => 'Faça login para concluir a matrícula.',
            ],
        ];

        return $map[$notice] ?? null;
    }

    private static function get_student_certificate_url(WP_Post $course): string
    {
        return home_url('/meus-cursos/certificado/' . $course->post_name . '/');
    }

    private static function get_course_excerpt(WP_Post $course, int $words = 26): string
    {
        $source = (string) ($course->post_excerpt ?: $course->post_content);
        $source = trim(wp_strip_all_tags(strip_shortcodes($source)));

        if ($source === '') {
            return '';
        }

        return wp_trim_words($source, $words, '...');
    }

    private static function get_student_profile_data(int $user_id): array
    {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $student_table = PRESS_LMS_Database::table('students');
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT full_name, phone_raw, created_at
                 FROM {$student_table}
                 WHERE user_id = %d
                 LIMIT 1",
                $user_id
            )
        );

        $display_name = '';
        if ($student && !empty($student->full_name)) {
            $display_name = (string) $student->full_name;
        } elseif (!empty($user->display_name)) {
            $display_name = (string) $user->display_name;
        } elseif (!empty($user->first_name)) {
            $display_name = (string) $user->first_name;
        } else {
            $display_name = (string) $user->user_login;
        }

        $initials_source = trim($display_name);
        $initials_parts = preg_split('/\s+/', $initials_source) ?: [];
        $initials = '';

        foreach (array_slice($initials_parts, 0, 2) as $part) {
            $initials .= function_exists('mb_substr')
                ? mb_substr($part, 0, 1)
                : substr($part, 0, 1);
        }

        if ($initials === '') {
            $initials = strtoupper(substr($display_name, 0, 2));
        }

        $registered_at = '';
        if ($student && !empty($student->created_at)) {
            $registered_at = date_i18n('d/m/Y', strtotime((string) $student->created_at));
        } elseif (!empty($user->user_registered)) {
            $registered_at = date_i18n('d/m/Y', strtotime((string) $user->user_registered));
        }

        return [
            'id' => $user_id,
            'display_name' => $display_name,
            'email' => (string) $user->user_email,
            'phone' => $student ? (string) ($student->phone_raw ?? '') : '',
            'registered_at' => $registered_at,
            'avatar_url' => class_exists('PRESS_LMS_Helpers')
                ? PRESS_LMS_Helpers::get_student_avatar_url($user_id, 96)
                : (string) get_avatar_url($user_id, ['size' => 96]),
            'has_custom_avatar' => class_exists('PRESS_LMS_Helpers')
                ? PRESS_LMS_Helpers::has_student_avatar($user_id)
                : false,
            'initials' => strtoupper((string) $initials),
        ];
    }

    private static function build_student_dashboard_course(int $user_id, $enrollment): ?array
    {
        $course_id = isset($enrollment->course_id) ? (int) $enrollment->course_id : 0;
        $course = $course_id > 0 ? get_post($course_id) : null;

        if (!$course instanceof WP_Post || $course->post_type !== 'press_course') {
            return null;
        }

        $course_slug = (string) $course->post_name;
        $course_url = home_url('/curso/' . $course_slug . '/');
        $lessons = PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish']);
        $first_lesson = !empty($lessons[0]) && $lessons[0] instanceof WP_Post ? $lessons[0] : null;
        $has_access = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::is_enrollment_currently_active($enrollment)
            : false;
        $next_lesson = ($has_access && class_exists('PRESS_LMS_Progress'))
            ? PRESS_LMS_Progress::get_next_lesson_for_user($user_id, $course_id)
            : $first_lesson;

        $progress = class_exists('PRESS_LMS_Progress')
            ? PRESS_LMS_Progress::get_course_progress_summary($user_id, $course_id)
            : ['completed' => 0, 'total' => count($lessons), 'percent' => 0];

        $is_completed = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::is_course_completed($user_id, $course_id)
            : ((int) ($progress['percent'] ?? 0) >= 100);

        $completed_at_raw = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::get_course_completed_at($user_id, $course_id)
            : '';

        $course_duration_seconds = (int) get_post_meta($course_id, '_press_course_total_duration', true);
        $duration_label = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::format_seconds($course_duration_seconds)
            : '';

        $teacher_name = '';
        $teacher_id = (int) get_post_meta($course_id, '_press_course_teacher', true);
        if ($teacher_id > 0) {
            $teacher = get_post($teacher_id);
            if ($teacher instanceof WP_Post) {
                $teacher_name = (string) $teacher->post_title;
            }
        }

        $thumbnail_url = get_the_post_thumbnail_url($course_id, 'medium_large');
        if (!$thumbnail_url && $first_lesson instanceof WP_Post) {
            $thumbnail_url = PRESS_LMS_Helpers::get_lesson_thumbnail_url((int) $first_lesson->ID, $course_id, 'medium_large');
        }

        $certificate_url = $is_completed ? self::get_student_certificate_url($course) : '';
        $course_access_label = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_course_access_label($course_id)
            : '';
        $access_expires_label = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_enrollment_access_summary($enrollment)
            : '';
        $access_status_label = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_enrollment_status_label($enrollment)
            : 'Desconhecido';
        $resume_url = $has_access && $next_lesson instanceof WP_Post
            ? home_url('/curso/' . $course_slug . '/aula/' . $next_lesson->post_name . '/')
            : $course_url;
        $resume_label = $has_access
            ? ($is_completed ? 'Revisar curso' : 'Continuar curso')
            : 'Ver curso';
        $learning_status_label = $is_completed
            ? 'Concluído'
            : ((int) ($progress['completed'] ?? 0) > 0 ? 'Em andamento' : 'Não iniciado');

        return [
            'course_id' => $course_id,
            'course_title' => (string) $course->post_title,
            'course_url' => $course_url,
            'resume_url' => $resume_url,
            'resume_label' => $resume_label,
            'thumbnail_url' => (string) $thumbnail_url,
            'teacher_name' => $teacher_name,
            'purchased_at' => !empty($enrollment->purchased_at) ? date_i18n('d/m/Y', strtotime((string) $enrollment->purchased_at)) : '',
            'expires_at' => !empty($enrollment->expires_at) ? date_i18n('d/m/Y', strtotime((string) $enrollment->expires_at)) : '',
            'duration_seconds' => $course_duration_seconds,
            'duration_label' => $duration_label,
            'access_label' => $course_access_label,
            'access_expires_label' => $access_expires_label,
            'access_status_label' => $access_status_label,
            'has_access' => $has_access,
            'progress_percent' => (int) ($progress['percent'] ?? 0),
            'completed_lessons' => (int) ($progress['completed'] ?? 0),
            'total_lessons' => (int) ($progress['total'] ?? 0),
            'status_label' => $access_status_label,
            'learning_status_label' => $learning_status_label,
            'certificate_available' => $is_completed,
            'certificate_url' => $certificate_url,
            'completed_at' => $completed_at_raw ? date_i18n('d/m/Y', strtotime($completed_at_raw)) : '',
        ];
    }

    private static function get_student_dashboard_data(int $user_id): array
    {
        $courses = [];
        $total_duration_seconds = 0;
        $total_completed_lessons = 0;
        $total_lessons = 0;
        $active_courses = 0;

        $enrollments = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_user_enrollments($user_id, ['include_pending' => true])
            : [];

        foreach ($enrollments as $enrollment) {
            $course_data = self::build_student_dashboard_course($user_id, $enrollment);
            if (!$course_data) {
                continue;
            }

            $courses[] = $course_data;
            if (!empty($course_data['has_access'])) {
                $active_courses++;
            }
            $total_duration_seconds += (int) ($course_data['duration_seconds'] ?? 0);
            $total_completed_lessons += (int) ($course_data['completed_lessons'] ?? 0);
            $total_lessons += (int) ($course_data['total_lessons'] ?? 0);
        }

        $certificates = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::get_available_certificates_for_user($user_id)
            : [];

        return [
            'courses' => $courses,
            'certificates' => $certificates,
            'stats' => [
                'active_courses' => $active_courses,
                'library_courses' => count($courses),
                'available_certificates' => count($certificates),
                'completed_lessons' => $total_completed_lessons,
                'total_lessons' => $total_lessons,
                'content_duration_label' => class_exists('PRESS_LMS_Certificate')
                    ? PRESS_LMS_Certificate::format_seconds($total_duration_seconds)
                    : '',
            ],
        ];
    }

    private static function build_catalog_course(WP_Post $course, int $user_id = 0): array
    {
        $course_id = (int) $course->ID;
        $course_url = home_url('/curso/' . $course->post_name . '/');
        $lessons = PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish']);
        $first_lesson = !empty($lessons[0]) && $lessons[0] instanceof WP_Post ? $lessons[0] : null;
        $can_access = $user_id > 0 && class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::can_access_course($user_id, $course_id)
            : false;

        $next_lesson = ($can_access && class_exists('PRESS_LMS_Progress'))
            ? PRESS_LMS_Progress::get_next_lesson_for_user($user_id, $course_id)
            : $first_lesson;

        $primary_url = $course_url;
        $primary_label = 'Ver curso';
        $progress_percent = 0;

        if ($can_access) {
            $primary_url = $next_lesson instanceof WP_Post
                ? home_url('/curso/' . $course->post_name . '/aula/' . $next_lesson->post_name . '/')
                : $course_url;

            $progress = class_exists('PRESS_LMS_Progress')
                ? PRESS_LMS_Progress::get_course_progress_summary($user_id, $course_id)
                : ['percent' => 0];

            $progress_percent = (int) ($progress['percent'] ?? 0);
            $primary_label = $progress_percent >= 100 ? 'Revisar curso' : 'Continuar curso';
        }

        $teacher_name = '';
        $teacher_id = (int) get_post_meta($course_id, '_press_course_teacher', true);
        if ($teacher_id > 0) {
            $teacher = get_post($teacher_id);
            if ($teacher instanceof WP_Post) {
                $teacher_name = (string) $teacher->post_title;
            }
        }

        $thumbnail_url = get_the_post_thumbnail_url($course_id, 'large');
        if (!$thumbnail_url && $first_lesson instanceof WP_Post) {
            $thumbnail_url = PRESS_LMS_Helpers::get_lesson_thumbnail_url((int) $first_lesson->ID, $course_id, 'large');
        }

        $course_duration_seconds = (int) get_post_meta($course_id, '_press_course_total_duration', true);
        $duration_label = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::format_seconds($course_duration_seconds)
            : '';
        $access_label = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_course_access_label($course_id)
            : '';

        $features = class_exists('PRESS_LMS_Course_Meta')
            ? array_slice(PRESS_LMS_Course_Meta::get_selected_features($course_id), 0, 4)
            : [];

        $is_paused = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::is_course_paused($course_id)
            : false;

        $status_label = 'Curso online';
        $status_class = 'available';

        if ($can_access) {
            $status_label = 'Na sua biblioteca';
            $status_class = 'owned';
        } elseif ($is_paused) {
            $status_label = 'Matrículas pausadas';
            $status_class = 'paused';
        }

        return [
            'course_id' => $course_id,
            'course_title' => (string) $course->post_title,
            'course_url' => $course_url,
            'thumbnail_url' => (string) $thumbnail_url,
            'teacher_name' => $teacher_name,
            'lessons_count' => count($lessons),
            'duration_label' => $duration_label,
            'access_label' => $access_label,
            'updated_at' => get_the_modified_date('d/m/Y', $course),
            'excerpt' => self::get_course_excerpt($course),
            'features' => $features,
            'primary_url' => $primary_url,
            'primary_label' => $primary_label,
            'is_paused' => $is_paused,
            'can_access' => $can_access,
            'progress_percent' => $progress_percent,
            'status_label' => $status_label,
            'status_class' => $status_class,
        ];
    }

    public static function render_student_certificate_by_slug(string $course_slug)
    {
        $course_slug = sanitize_title($course_slug);
        $certificate_url = home_url('/meus-cursos/certificado/' . $course_slug . '/');

        if (!is_user_logged_in()) {
            wp_safe_redirect(self::get_student_login_url($certificate_url));
            exit;
        }

        $course = get_page_by_path($course_slug, OBJECT, 'press_course');
        if (!$course instanceof WP_Post) {
            wp_safe_redirect(self::get_student_area_url('certificates', [
                'notice' => 'certificate_course_invalid',
            ]));
            exit;
        }

        if (!class_exists('PRESS_LMS_Certificate')) {
            wp_safe_redirect(self::get_student_area_url('certificates', [
                'notice' => 'certificate_unavailable',
            ]));
            exit;
        }

        $validation = PRESS_LMS_Certificate::validate_certificate_access(get_current_user_id(), (int) $course->ID, true);

        if (is_wp_error($validation)) {
            $error_code = $validation->get_error_code();
            $notice = in_array($error_code, ['course_invalid', 'certificate_unavailable', 'forbidden'], true)
                ? 'certificate_' . ($error_code === 'course_invalid' ? 'course_invalid' : $error_code)
                : 'certificate_unavailable';

            wp_safe_redirect(self::get_student_area_url('certificates', [
                'notice' => $notice,
            ]));
            exit;
        }

        PRESS_LMS_Certificate::render_certificate_for_user((int) $course->ID, get_current_user_id(), true);
    }

    public static function render_course_archive()
    {
        self::header('Cursos - Pressplay');

        $user_id = get_current_user_id();
        $course_posts = get_posts([
            'post_type' => 'press_course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
        ]);

        $courses = [];
        $total_duration_seconds = 0;
        $total_lessons = 0;

        foreach ($course_posts as $course) {
            if (!$course instanceof WP_Post) {
                continue;
            }

            $course_data = self::build_catalog_course($course, $user_id);
            $courses[] = $course_data;
            $total_lessons += (int) ($course_data['lessons_count'] ?? 0);

            $course_duration_seconds = (int) get_post_meta((int) $course->ID, '_press_course_total_duration', true);
            $total_duration_seconds += max(0, $course_duration_seconds);
        }

        $catalog_stats_var = [
            'total_courses' => count($courses),
            'total_lessons' => $total_lessons,
            'total_duration_label' => class_exists('PRESS_LMS_Certificate')
                ? PRESS_LMS_Certificate::format_seconds($total_duration_seconds)
                : '',
        ];
        $catalog_courses_var = $courses;
        $catalog_page_title_var = 'Cursos Pressplay LMS';

        $template = trailingslashit(PRESS_LMS_PATH) . 'templates/frontend/course-archive.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="presslms"><div class="presslms__container"><div class="presslms-card">';
            echo '<h1 class="presslms-h1">Template do catálogo não encontrado</h1>';
            echo '<p class="presslms-muted">Esperado em: ' . esc_html($template) . '</p>';
            echo '</div></div></div>';
        }

        self::footer();
    }

    public static function render_my_courses()
    {
        $tab = self::get_student_dashboard_tab();
        $certificate_slug = isset($_GET['certificate']) ? sanitize_title((string) $_GET['certificate']) : '';

        if ($certificate_slug !== '') {
            wp_safe_redirect(home_url('/meus-cursos/certificado/' . $certificate_slug . '/'));
            exit;
        }

        self::header(self::get_student_area_page_title($tab));

        if (!is_user_logged_in()) {
            $login_url = self::get_student_login_url(self::get_student_area_url($tab));
            $register_url = home_url('/cadastro/');

            echo '<div class="presslms presslms-student"><div class="presslms__container">';
            echo '<section class="presslms-card presslms-student-login">';
            echo '<span class="presslms-student-login__eyebrow">Área do aluno</span>';
            echo '<h1 class="presslms-h1">Entre para acessar seus cursos, progresso e certificados.</h1>';
            echo '<p class="presslms-muted">Seu painel reúne tudo em um único lugar: cursos ativos, certificados disponíveis e dados da conta.</p>';
            echo '<div class="presslms-student-login__actions">';
            echo '<a class="presslms-btn presslms-btn--primary" href="' . esc_url($login_url) . '">Entrar</a>';
            echo '<a class="presslms-btn" href="' . esc_url($register_url) . '">Criar conta</a>';
            echo '</div>';
            echo '</section>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $user_id = get_current_user_id();
        $profile = self::get_student_profile_data($user_id);
        $dashboard_data = self::get_student_dashboard_data($user_id);
        $notice = self::get_student_dashboard_notice($_GET['notice'] ?? '');
        $base_url = self::get_student_area_url('courses');
        $logout_url = wp_logout_url($base_url);
        $student_urls = self::get_student_menu_items();

        $student_profile_var = $profile;
        $student_courses_var = $dashboard_data['courses'];
        $student_certificates_var = $dashboard_data['certificates'];
        $student_stats_var = $dashboard_data['stats'];
        $student_tab_var = $tab;
        $student_notice_var = $notice;
        $student_urls_var = [
            'base' => $base_url,
            'catalog' => (string) ($student_urls['catalog']['url'] ?? self::get_student_catalog_url()),
            'courses' => (string) ($student_urls['courses']['url'] ?? self::get_student_area_url('courses')),
            'certificates' => (string) ($student_urls['certificates']['url'] ?? self::get_student_area_url('certificates')),
            'profile' => (string) ($student_urls['profile']['url'] ?? self::get_student_area_url('profile')),
            'password' => (string) ($student_urls['password']['url'] ?? self::get_student_area_url('password')),
            'logout' => $logout_url,
            'shop' => (string) ($student_urls['catalog']['url'] ?? self::get_student_catalog_url()),
        ];

        $template = trailingslashit(PRESS_LMS_PATH) . 'templates/frontend/student-dashboard.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="presslms"><div class="presslms__container"><div class="presslms-card">';
            echo '<h1 class="presslms-h1">Template da área do aluno não encontrado</h1>';
            echo '<p class="presslms-muted">Esperado em: ' . esc_html($template) . '</p>';
            echo '</div></div></div>';
        }

        self::footer();
    }

    private static function render_enroll_cta($course_id)
    {
        $product_id = PRESS_LMS_Enrollments::get_course_product_id($course_id);

        echo '<div class="press-card" style="margin-top:16px;padding:16px;border:1px solid #ddd;border-radius:10px;">';
        echo '<p><strong>Conteúdo restrito.</strong> Você precisa estar matriculado para ver as aulas.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="press_lms_enroll">';
        echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
        echo wp_nonce_field('press_lms_enroll_' . $course_id, '_wpnonce', true, false);

        $disabled = (!$product_id) ? 'disabled' : '';
        $label = $product_id ? 'Matricular' : 'Produto ainda não gerado';

        echo '<button type="submit" class="button button-primary" ' . $disabled . '>' . esc_html($label) . '</button>';
        echo '</form>';

        echo '</div>';
    }

    private static function find_lesson_for_course(string $lesson_slug, int $course_id)
    {
        $lesson_slug = sanitize_title($lesson_slug);
        if ($lesson_slug === '' || $course_id <= 0) {
            return null;
        }

        $candidates = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'name'           => $lesson_slug,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        foreach ($candidates as $lesson) {
            if (!$lesson instanceof WP_Post) {
                continue;
            }

            $lesson_course_id = (int) $lesson->post_parent;
            if ($lesson_course_id <= 0) {
                $lesson_course_id = (int) get_post_meta($lesson->ID, '_press_lesson_course_id', true);
            }

            if ($lesson_course_id === $course_id) {
                return $lesson;
            }
        }

        foreach (PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish', 'draft', 'pending', 'private', 'future']) as $lesson) {
            if ($lesson instanceof WP_Post && $lesson->post_name === $lesson_slug) {
                return $lesson;
            }
        }

        return null;
    }

    public static function render_course_by_slug($slug)
    {
        $course = get_page_by_path($slug, OBJECT, 'press_course');

        self::header('Curso - Pressplay');

        if (!$course) {
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Curso não encontrado</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $user_id = get_current_user_id();
        $can_access = PRESS_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);
        $trailer = get_post_meta($course->ID, '_press_course_trailer', true);
        $lessons = PRESS_LMS_Helpers::get_course_lessons((int) $course->ID, ['publish']);
        $first_lesson_url = '';
        if (!empty($lessons[0]) && $lessons[0] instanceof WP_Post) {
            $first_lesson_url = home_url('/curso/' . $slug . '/aula/' . $lessons[0]->post_name . '/');
        }
        $course_access_label = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_course_access_label((int) $course->ID)
            : '';

        $course_slug_var      = $slug;
        $course_var           = $course;
        $can_access_var       = $can_access;
        $trailer_var          = (string) $trailer;
        $first_lesson_url_var = $first_lesson_url;
        $product_id_var       = PRESS_LMS_Enrollments::get_course_product_id((int) $course->ID);
        $course_access_label_var = $course_access_label;
        $course_notice_var = self::get_course_frontend_notice($_GET['notice'] ?? '');

        $template = trailingslashit(PRESS_LMS_PATH) . 'templates/frontend/single-press_course.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Template não encontrado</h1>';
            echo '<p class="press-muted">Esperado em: ' . esc_html($template) . '</p>';
            echo '</div></div>';
        }

        self::footer();
    }

    public static function render_lesson_by_slug($course_slug, $lesson_slug)
    {
        $course = get_page_by_path($course_slug, OBJECT, 'press_course');
        $lesson = $course ? self::find_lesson_for_course((string) $lesson_slug, (int) $course->ID) : null;

        // Stop early when either the course or the lesson cannot be resolved.
        if (!$course || !$lesson) {
            self::header('Aula - Pressplay');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Aula não encontrada</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $lesson_course_id = (int) $lesson->post_parent;

        if ($lesson_course_id <= 0) {
            $lesson_course_id = (int) get_post_meta($lesson->ID, '_press_lesson_course_id', true);
        }

        if ($lesson_course_id !== (int) $course->ID) {
            self::header('Aula - Pressplay');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Aula não pertence a este curso</h1>';
            echo '<p><a class="press-link" href="' . esc_url(home_url('/curso/' . $course_slug)) . '">← Voltar para o curso</a></p>';
            echo '</div></div>';
            self::footer();
            return;
        }

        // Check access before loading any lesson content.
        $user_id = get_current_user_id();
        $can_access = PRESS_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);

        if (!$can_access) {
            self::header('Aula - Restrita');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Conteúdo restrito</h1>';
            echo '<p>Você precisa estar matriculado para acessar esta aula.</p>';
            self::render_enroll_cta((int)$course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        // Render the lesson page for enrolled users and administrators.
        self::header('Aula - Pressplay');

        $video_url = get_post_meta($lesson->ID, '_press_lesson_video_url', true);
        $vimeo_id  = (int) get_post_meta($lesson->ID, '_press_lesson_vimeo_id', true);

        // Normalize lesson materials before exposing them to the template.
        $materials = get_post_meta($lesson->ID, '_press_lesson_materials_v2', true);
        if (!is_array($materials)) $materials = [];

        if (class_exists('PRESS_LMS_Materials')) {
            $materials = PRESS_LMS_Materials::normalize_items($materials);
        }

        // Expose the resolved entities to the lesson template.
        $course_slug_var = $course_slug;
        $lesson_slug_var = $lesson_slug;
        $course_var      = $course;
        $lesson_var      = $lesson;

        // Load the dedicated lesson template.
        $template = trailingslashit(PRESS_LMS_PATH) . 'templates/frontend/single-press_lesson.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Template não encontrado</h1>';
            echo '<p class="press-muted">Esperado em: ' . esc_html($template) . '</p>';
            echo '</div></div>';
        }

        self::footer();
    }
}
