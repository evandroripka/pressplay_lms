<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Certificate
{
    public static function init(): void
    {
        add_action('admin_post_press_lms_preview_certificate', [__CLASS__, 'preview_certificate']);
        add_action('admin_post_press_lms_download_certificate', [__CLASS__, 'download_certificate']);
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

        $default_logo      = PRESS_LMS_PATH . 'templates/certificado/files/logo.png';
        $default_signature = PRESS_LMS_PATH . 'templates/certificado/files/assinatura.png';

        $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
        $signature_url = $signature_id ? wp_get_attachment_url($signature_id) : '';

        if (!$logo_url && file_exists($default_logo)) {
            $logo_url = PRESS_LMS_URL . 'templates/certificado/files/logo.png';
        }

        if (!$signature_url && file_exists($default_signature)) {
            $signature_url = PRESS_LMS_URL . 'templates/certificado/files/assinatura.png';
        }

        $completed_at = self::get_course_completed_at($user_id, $course_id);

        return [
            'course_id'         => $course_id,
            'course_name'       => $course->post_title,
            'course_duration'   => self::format_seconds($course_duration),
            'student_name'      => $user->display_name ?: $user->user_login,
            'completion_date'   => $completed_at ? date_i18n('d/m/Y', strtotime($completed_at)) : '',
            'description'       => $description,
            'logo_url'          => $logo_url,
            'signature_url'     => $signature_url,
        ];
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

    public static function render_certificate_html(array $data): void
    {
        $template = PRESS_LMS_PATH . 'templates/certificado/certificado.php';

        if (!file_exists($template)) {
            wp_die('Template do certificado não encontrado.');
        }

        extract($data, EXTR_SKIP);
        include $template;
    }

    public static function preview_certificate(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Você precisa estar logado.');
        }

        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $user_id   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : get_current_user_id();

        if ($course_id <= 0) {
            wp_die('Curso inválido.');
        }

        // se for admin pode emitir para qualquer aluno
        if (!current_user_can('manage_options')) {

            // aluno só pode emitir o próprio certificado
            if ($user_id !== get_current_user_id()) {
                wp_die('Permissão negada.');
            }

            if (!self::is_course_completed($user_id, $course_id)) {
                wp_die('Certificado disponível somente após concluir o curso.');
            }
        }

        $data = self::get_certificate_data($user_id, $course_id);

        if (!$data) {
            wp_die('Não foi possível gerar o certificado.');
        }

        self::render_certificate_html($data);
        exit;
    }

    public static function download_certificate(): void
    {
        // aqui depois a gente liga com DOMPDF / mPDF
        self::preview_certificate();
    }
}
