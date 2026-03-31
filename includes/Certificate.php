<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Certificate
{
    private const ADMIN_CERTIFICATE_NONCE_ACTION = 'press_lms_admin_certificate';

    public static function init(): void
    {
        add_action('admin_post_press_lms_preview_certificate', [__CLASS__, 'preview_certificate']);
        add_action('admin_post_press_lms_download_certificate', [__CLASS__, 'download_certificate']);
    }

    public static function get_admin_certificate_url(string $action, int $course_id, int $user_id): string
    {
        $action = $action === 'download' ? 'press_lms_download_certificate' : 'press_lms_preview_certificate';

        return wp_nonce_url(
            admin_url(
                'admin-post.php?action=' . $action .
                '&course_id=' . (int) $course_id .
                '&user_id=' . (int) $user_id
            ),
            self::ADMIN_CERTIFICATE_NONCE_ACTION . '_' . $action . '_' . (int) $course_id . '_' . (int) $user_id
        );
    }

    public static function get_course_completed_at(int $user_id, int $course_id): string
    {
        global $wpdb;

        $table_progress = PRESS_LMS_Database::table('progress');

        $completed_at = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(completed_at)
                 FROM {$table_progress}
                 WHERE user_id = %d
                   AND course_id = %d
                   AND completed = 1",
                $user_id,
                $course_id
            )
        );

        return $completed_at ? (string) $completed_at : '';
    }

    public static function is_course_completed(int $user_id, int $course_id): bool
    {
        if (!class_exists('PRESS_LMS_Progress')) return false;
        return PRESS_LMS_Progress::get_course_progress_percent($user_id, $course_id) >= 100;
    }

    public static function format_seconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($h > 0) {
            return sprintf('%dh %02dmin', $h, $m);
        }

        return sprintf('%dmin', $m);
    }

    private static function resolve_image_url(int $attachment_id, string $fallback_relative_path = ''): string
    {
        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
                return (string) $url;
            }
        }

        if ($fallback_relative_path !== '') {
            $file_path = PRESS_LMS_PATH . ltrim($fallback_relative_path, '/');
            if (file_exists($file_path)) {
                return PRESS_LMS_URL . ltrim($fallback_relative_path, '/');
            }
        }

        return '';
    }

    public static function get_certificate_data(int $user_id, int $course_id): array
    {
        $course = get_post($course_id);
        $user   = get_userdata($user_id);

        if (!$course || !$user) {
            return [];
        }

        $course_duration = (int) get_post_meta($course_id, '_press_course_total_duration', true);
        $description     = (string) get_post_meta($course_id, '_press_course_certificate_description', true);

        $logo_id         = (int) get_post_meta($course_id, '_press_course_certificate_logo_id', true);
        $signature_id    = (int) get_post_meta($course_id, '_press_course_certificate_signature_id', true);

        $custom_html     = (string) get_post_meta($course_id, '_press_course_certificate_html', true);
        $custom_css      = (string) get_post_meta($course_id, '_press_course_certificate_css', true);

        $logo_url = self::resolve_image_url(
            $logo_id,
            'templates/certificado/files/logo.png'
        );

        $signature_url = self::resolve_image_url(
            $signature_id,
            'templates/certificado/files/assinatura.png'
        );

        $completed_at = self::get_course_completed_at($user_id, $course_id);

        return [
            'course_id'        => $course_id,
            'course_name'      => $course->post_title,
            'course_duration'  => self::format_seconds($course_duration),
            'student_name'     => $user->display_name ?: $user->user_login,
            'completion_date'  => $completed_at ? date_i18n('d/m/Y', strtotime($completed_at)) : date_i18n('d/m/Y'),
            'description'      => $description,
            'logo_url'         => $logo_url,
            'signature_url'    => $signature_url,
            'custom_html'      => $custom_html,
            'custom_css'       => $custom_css,
        ];
    }

    public static function get_certificate_html_template(int $course_id): string
    {
        $html = (string) get_post_meta($course_id, '_press_course_certificate_html', true);

        if (
            trim($html) !== '' &&
            (
                !class_exists('PRESS_LMS_Course_Meta') ||
                !method_exists('PRESS_LMS_Course_Meta', 'is_legacy_default_certificate_html') ||
                !PRESS_LMS_Course_Meta::is_legacy_default_certificate_html($html)
            )
        ) {
            return $html;
        }

        $template = PRESS_LMS_PATH . 'templates/certificado/certificado.php';

        if (file_exists($template)) {
            return (string) file_get_contents($template);
        }

        return '';
    }

    public static function get_certificate_css_template(int $course_id): string
    {
        $css = (string) get_post_meta($course_id, '_press_course_certificate_css', true);

        if (trim($css) !== '') {
            return trim($css);
        }

        if (
            class_exists('PRESS_LMS_Course_Meta') &&
            method_exists('PRESS_LMS_Course_Meta', 'get_default_certificate_css')
        ) {
            return (string) PRESS_LMS_Course_Meta::get_default_certificate_css();
        }

        return '';
    }

    public static function get_available_certificates_for_user(int $user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $enrollments = class_exists('PRESS_LMS_Enrollments') && method_exists('PRESS_LMS_Enrollments', 'get_user_enrollments')
            ? PRESS_LMS_Enrollments::get_user_enrollments($user_id, ['include_pending' => false])
            : [];

        $certificates = [];
        $seen_courses = [];

        foreach ($enrollments as $enrollment) {
            $course_id = isset($enrollment->course_id) ? (int) $enrollment->course_id : 0;
            if ($course_id <= 0 || isset($seen_courses[$course_id])) {
                continue;
            }

            if (!self::is_course_completed($user_id, $course_id)) {
                continue;
            }

            $course = get_post($course_id);
            if (!$course instanceof WP_Post || $course->post_type !== 'press_course') {
                continue;
            }

            $completed_at_raw = self::get_course_completed_at($user_id, $course_id);

            $certificates[] = [
                'course_id' => $course_id,
                'course_title' => (string) $course->post_title,
                'thumbnail_url' => (string) get_the_post_thumbnail_url($course_id, 'medium_large'),
                'completed_at' => $completed_at_raw ? date_i18n('d/m/Y', strtotime($completed_at_raw)) : '',
                'completed_at_sort' => $completed_at_raw ?: '',
                'certificate_url' => home_url('/meus-cursos/certificado/' . $course->post_name . '/'),
                'course_url' => home_url('/curso/' . $course->post_name . '/'),
            ];

            $seen_courses[$course_id] = true;
        }

        usort($certificates, static function (array $left, array $right): int {
            return strcmp((string) ($right['completed_at_sort'] ?? ''), (string) ($left['completed_at_sort'] ?? ''));
        });

        foreach ($certificates as &$certificate) {
            unset($certificate['completed_at_sort']);
        }
        unset($certificate);

        return $certificates;
    }

    public static function replace_placeholders(string $html, array $data): string
    {
        $map = [
            '{{student_name}}'            => esc_html($data['student_name'] ?? ''),
            '{{course_name}}'             => esc_html($data['course_name'] ?? ''),
            '{{course_duration}}'         => esc_html($data['course_duration'] ?? ''),
            '{{completion_date}}'         => esc_html($data['completion_date'] ?? ''),
            '{{certificate_description}}' => wp_kses_post($data['description'] ?? ''),
            '{{logo_url}}'                => esc_url($data['logo_url'] ?? ''),
            '{{signature_url}}'           => esc_url($data['signature_url'] ?? ''),
        ];

        return strtr($html, $map);
    }

    public static function get_certificate_styles(string $css = ''): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        return "<style>\n" . $css . "\n</style>";
    }

    public static function render_certificate_html(array $data): void
    {
        $course_id = (int) ($data['course_id'] ?? 0);
        $html = self::get_certificate_html_template($course_id);
        $css = self::get_certificate_css_template($course_id);

        if (trim($html) === '') {
            wp_die('Template do certificado não encontrado.');
        }

        $html = self::replace_placeholders($html, $data);

        echo '<!DOCTYPE html>';
        echo '<html lang="pt-BR">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Certificado - ' . esc_html($data['course_name'] ?? 'Curso') . '</title>';
        echo self::get_certificate_styles($css);
        echo '</head>';
        echo '<body>';
        echo wp_kses_post($html);
        echo '</body>';
        echo '</html>';
    }

    public static function validate_certificate_access(int $user_id, int $course_id, bool $allow_admin_override = true)
    {
        if (!is_user_logged_in()) {
            return new WP_Error('login_required', 'Você precisa estar logado.');
        }

        if ($course_id <= 0 || get_post_type($course_id) !== 'press_course') {
            return new WP_Error('course_invalid', 'Curso inválido.');
        }

        if ($allow_admin_override && current_user_can('manage_options')) {
            return true;
        }

        $current_user_id = get_current_user_id();

        if ($user_id <= 0 || $user_id !== $current_user_id) {
            return new WP_Error('forbidden', 'Permissão negada.');
        }

        if (!self::is_course_completed($user_id, $course_id)) {
            return new WP_Error('certificate_unavailable', 'Certificado disponível somente após concluir o curso.');
        }

        return true;
    }

    public static function render_certificate_for_user(int $course_id, int $user_id = 0, bool $allow_admin_override = true): void
    {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        $validation = self::validate_certificate_access($user_id, $course_id, $allow_admin_override);

        if (is_wp_error($validation)) {
            wp_die($validation->get_error_message());
        }

        $data = self::get_certificate_data($user_id, $course_id);

        if (!$data) {
            wp_die('Não foi possível gerar o certificado.');
        }

        self::render_certificate_html($data);
        exit;
    }

    public static function preview_certificate(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $user_id   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : get_current_user_id();
        self::guard_admin_certificate_request('press_lms_preview_certificate', $course_id, $user_id);
        self::render_certificate_for_user($course_id, $user_id, true);
    }

    public static function download_certificate(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $user_id   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : get_current_user_id();
        self::guard_admin_certificate_request('press_lms_download_certificate', $course_id, $user_id);
        self::render_certificate_for_user($course_id, $user_id, true);
    }

    private static function guard_admin_certificate_request(string $action, int $course_id, int $user_id): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('Sem permissão para emitir este certificado.');
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, self::ADMIN_CERTIFICATE_NONCE_ACTION . '_' . $action . '_' . $course_id . '_' . $user_id)) {
            wp_die('Não foi possível validar a solicitação do certificado.');
        }
    }
}
