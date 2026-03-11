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
        ];
    }

    public static function get_certificate_html_template(int $course_id): string
    {
        $html = (string) get_post_meta($course_id, '_press_course_certificate_html', true);

        if (trim($html) !== '') {
            return $html;
        }

        $template = PRESS_LMS_PATH . 'templates/certificado/certificado.php';

        if (file_exists($template)) {
            return (string) file_get_contents($template);
        }

        return '';
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

    public static function get_certificate_styles(): string
    {
        return '
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 297mm;
            height: 210mm;
            background: #eef2f7;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            color: #0f172a;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .presslms-cert {
            width: 297mm;
            height: 210mm;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 25%),
                radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.08), transparent 20%),
                #ffffff;
            border-radius: 0;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            position: relative;
        }

        .presslms-cert__topbar {
            height: 4mm;
            background: linear-gradient(90deg, #2563eb, #10b981);
        }

        .presslms-cert__inner {
            padding: 18mm 22mm 16mm;
            height: calc(210mm - 4mm);
            display: flex;
            flex-direction: column;
        }

        .presslms-cert__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #1d4ed8;
            background: rgba(37, 99, 235, 0.08);
            border: 1px solid rgba(37, 99, 235, 0.12);
            margin-bottom: 14px;
        }

        .presslms-cert__logo {
            text-align: center;
            margin-bottom: 14px;
        }

        .presslms-cert__logo img {
            max-height: 22mm;
            max-width: 68mm;
            object-fit: contain;
        }

        .presslms-cert__title {
            text-align: center;
            font-size: 42px;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #0f172a;
            margin: 0 0 8px;
        }

        .presslms-cert__subtitle {
            text-align: center;
            font-size: 17px;
            color: #475569;
            margin: 0 0 18px;
        }

        .presslms-cert__student {
            text-align: center;
            font-size: 38px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #1d4ed8;
            margin: 0 0 16px;
        }

        .presslms-cert__text {
            max-width: 220mm;
            margin: 0 auto 12px;
            text-align: center;
            font-size: 18px;
            line-height: 1.7;
            color: #334155;
        }

        .presslms-cert__course {
            max-width: 220mm;
            margin: 0 auto 16px;
            text-align: center;
            font-size: 28px;
            line-height: 1.3;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }

        .presslms-cert__description {
            max-width: 220mm;
            margin: 0 auto 18px;
            text-align: center;
            font-size: 16px;
            line-height: 1.7;
            color: #475569;
        }

        .presslms-cert__meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            max-width: 190mm;
            margin: 18px auto 0;
        }

        .presslms-cert__meta-card {
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.08);
            text-align: center;
        }

        .presslms-cert__meta-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .presslms-cert__meta-value {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .presslms-cert__footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-top: auto;
            padding-top: 18px;
        }

        .presslms-cert__signature-block {
            flex: 1;
            max-width: 90mm;
            text-align: center;
        }

        .presslms-cert__signature-block img {
            max-height: 20mm;
            max-width: 60mm;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .presslms-cert__line {
            height: 1px;
            background: rgba(15, 23, 42, 0.22);
        }

        .presslms-cert__label {
            margin-top: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .presslms-cert__seal {
            width: 32mm;
            height: 32mm;
            border-radius: 999px;
            border: 2px solid rgba(16, 185, 129, 0.22);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #10b981;
            background: rgba(16, 185, 129, 0.05);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            flex-shrink: 0;
        }

        .presslms-cert__seal strong {
            font-size: 16px;
            line-height: 1;
            margin-top: 4px;
        }

        @media screen {
            body {
                padding: 10mm;
            }

            .presslms-cert {
                box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            }
        }

        @media print {
            html,
            body {
                background: #ffffff;
            }

            body {
                padding: 0;
            }

            .presslms-cert {
                box-shadow: none;
            }
        }
    </style>';
    }

    public static function render_certificate_html(array $data): void
    {
        $course_id = (int) ($data['course_id'] ?? 0);
        $html = self::get_certificate_html_template($course_id);

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
        echo self::get_certificate_styles();
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
        self::render_certificate_for_user($course_id, $user_id, true);
    }

    public static function download_certificate(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
        $user_id   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : get_current_user_id();
        self::render_certificate_for_user($course_id, $user_id, true);
    }
}
