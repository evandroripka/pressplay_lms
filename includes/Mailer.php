<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Mailer
{
    private const DAILY_HOOK = 'press_lms_daily_lifecycle_events';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_schedule_daily_lifecycle_events']);
        add_action(self::DAILY_HOOK, [__CLASS__, 'process_daily_lifecycle_events']);
    }

    public static function maybe_schedule_daily_lifecycle_events(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::DAILY_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK);
    }

    public static function send_set_password_email($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Reuse the default WordPress password reset flow.
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return false;
        }

        $reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');
        $brand = self::get_brand_settings();
        $subject = "Defina sua senha - {$brand['name']}";

        $html = self::build_email_layout([
            'brand' => $brand['name'],
            'logo_url' => $brand['logo_url'],
            'eyebrow' => 'Acesso à plataforma',
            'title' => 'Defina sua senha',
            'intro_html' => '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Para acessar sua área do aluno, clique no botão abaixo e defina sua senha.</p>',
            'button_label' => 'Definir senha',
            'button_url' => $reset_url,
            'footer_html' => '<p style="margin:18px 0 0;color:#94a3b8;font-size:12px;font-family:Arial,sans-serif;line-height:1.5;">Se você não solicitou esse cadastro, ignore este e-mail.</p>',
        ]);

        return self::send_html_email((string) $user->user_email, $subject, $html);
    }

    public static function send_enrollment_activated_email(int $user_id, int $course_id, $enrollment = null): bool
    {
        $user = get_userdata($user_id);
        $course = get_post($course_id);

        if (!$user || !$course instanceof WP_Post || $course->post_type !== 'press_course') {
            return false;
        }

        $enrollment_id = isset($enrollment->id) ? (int) $enrollment->id : 0;
        $notification_key = self::build_notification_key('enrollment_active', [
            $enrollment_id,
            (string) ($enrollment->order_ref ?? ''),
            (string) ($enrollment->updated_at ?? ''),
        ]);

        if (self::has_notification_been_sent($user_id, $notification_key)) {
            return false;
        }

        $brand = self::get_brand_settings();
        $course_url = self::get_course_url($course);
        $dashboard_url = self::get_student_dashboard_url('courses');
        $access_summary = is_object($enrollment) && class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_enrollment_access_summary($enrollment)
            : '';

        $html = self::build_email_layout([
            'brand' => $brand['name'],
            'logo_url' => $brand['logo_url'],
            'eyebrow' => 'Matrícula confirmada',
            'title' => 'Seu acesso foi liberado',
            'intro_html' =>
                '<p style="margin:0 0 12px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Seu acesso ao curso <strong>' . esc_html((string) $course->post_title) . '</strong> já está disponível na área do aluno.</p>' .
                ($access_summary !== ''
                    ? '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">' . esc_html($access_summary) . '</p>'
                    : ''),
            'button_label' => 'Abrir meus cursos',
            'button_url' => $dashboard_url,
            'secondary_button_label' => 'Ver curso',
            'secondary_button_url' => $course_url,
            'footer_html' => '<p style="margin:18px 0 0;color:#94a3b8;font-size:12px;font-family:Arial,sans-serif;line-height:1.5;">Se o botão principal não funcionar, acesse: ' . esc_html($dashboard_url) . '</p>',
        ]);

        $sent = self::send_html_email((string) $user->user_email, 'Seu acesso foi liberado - ' . $brand['name'], $html);

        if ($sent) {
            self::mark_notification_sent($user_id, $notification_key);
        }

        return $sent;
    }

    public static function maybe_send_course_completed_email(int $user_id, int $course_id): bool
    {
        if (!class_exists('PRESS_LMS_Certificate') || !PRESS_LMS_Certificate::is_course_completed($user_id, $course_id)) {
            return false;
        }

        $completed_at = PRESS_LMS_Certificate::get_course_completed_at($user_id, $course_id);
        return self::send_certificate_available_email($user_id, $course_id, $completed_at);
    }

    public static function send_certificate_available_email(int $user_id, int $course_id, string $completed_at = ''): bool
    {
        $user = get_userdata($user_id);
        $course = get_post($course_id);

        if (!$user || !$course instanceof WP_Post || $course->post_type !== 'press_course') {
            return false;
        }

        $notification_key = self::build_notification_key('certificate_available', [
            $course_id,
            $completed_at,
        ]);

        if (self::has_notification_been_sent($user_id, $notification_key)) {
            return false;
        }

        $brand = self::get_brand_settings();
        $certificate_url = self::get_certificate_url($course);
        $course_url = self::get_course_url($course);
        $completed_label = $completed_at !== '' ? date_i18n('d/m/Y', strtotime($completed_at)) : date_i18n('d/m/Y');

        $html = self::build_email_layout([
            'brand' => $brand['name'],
            'logo_url' => $brand['logo_url'],
            'eyebrow' => 'Curso concluído',
            'title' => 'Seu certificado está disponível',
            'intro_html' =>
                '<p style="margin:0 0 12px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Parabéns por concluir o curso <strong>' . esc_html((string) $course->post_title) . '</strong>.</p>' .
                '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Data de conclusão: <strong>' . esc_html($completed_label) . '</strong></p>',
            'button_label' => 'Emitir certificado',
            'button_url' => $certificate_url,
            'secondary_button_label' => 'Ver curso',
            'secondary_button_url' => $course_url,
        ]);

        $sent = self::send_html_email((string) $user->user_email, 'Seu certificado está disponível - ' . $brand['name'], $html);

        if ($sent) {
            self::mark_notification_sent($user_id, $notification_key);
        }

        return $sent;
    }

    public static function send_access_expiring_email(int $user_id, int $course_id, string $expires_at, $enrollment = null): bool
    {
        $user = get_userdata($user_id);
        $course = get_post($course_id);

        if (!$user || !$course instanceof WP_Post || $course->post_type !== 'press_course' || $expires_at === '') {
            return false;
        }

        $notification_key = self::build_notification_key('access_expiring', [
            (int) ($enrollment->id ?? 0),
            $expires_at,
        ]);

        if (self::has_notification_been_sent($user_id, $notification_key)) {
            return false;
        }

        $brand = self::get_brand_settings();
        $course_url = self::get_course_url($course);
        $expires_label = date_i18n('d/m/Y', strtotime($expires_at));

        $html = self::build_email_layout([
            'brand' => $brand['name'],
            'logo_url' => $brand['logo_url'],
            'eyebrow' => 'Validade do acesso',
            'title' => 'Seu acesso está perto de expirar',
            'intro_html' =>
                '<p style="margin:0 0 12px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">O acesso ao curso <strong>' . esc_html((string) $course->post_title) . '</strong> expira em <strong>' . esc_html($expires_label) . '</strong>.</p>' .
                '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Se precisar renovar o acesso, acesse a página do curso antes do vencimento.</p>',
            'button_label' => 'Abrir curso',
            'button_url' => $course_url,
            'secondary_button_label' => 'Área do aluno',
            'secondary_button_url' => self::get_student_dashboard_url('courses'),
        ]);

        $sent = self::send_html_email((string) $user->user_email, 'Seu acesso expira em breve - ' . $brand['name'], $html);

        if ($sent) {
            self::mark_notification_sent($user_id, $notification_key);
        }

        return $sent;
    }

    public static function send_access_expired_email(int $user_id, int $course_id, string $expires_at, $enrollment = null): bool
    {
        $user = get_userdata($user_id);
        $course = get_post($course_id);

        if (!$user || !$course instanceof WP_Post || $course->post_type !== 'press_course' || $expires_at === '') {
            return false;
        }

        $notification_key = self::build_notification_key('access_expired', [
            (int) ($enrollment->id ?? 0),
            $expires_at,
        ]);

        if (self::has_notification_been_sent($user_id, $notification_key)) {
            return false;
        }

        $brand = self::get_brand_settings();
        $course_url = self::get_course_url($course);
        $expired_label = date_i18n('d/m/Y', strtotime($expires_at));

        $html = self::build_email_layout([
            'brand' => $brand['name'],
            'logo_url' => $brand['logo_url'],
            'eyebrow' => 'Acesso expirado',
            'title' => 'Seu acesso chegou ao fim',
            'intro_html' =>
                '<p style="margin:0 0 12px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">O acesso ao curso <strong>' . esc_html((string) $course->post_title) . '</strong> expirou em <strong>' . esc_html($expired_label) . '</strong>.</p>' .
                '<p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.6;">Seu histórico e certificados continuam disponíveis na área do aluno.</p>',
            'button_label' => 'Ver curso',
            'button_url' => $course_url,
            'secondary_button_label' => 'Área do aluno',
            'secondary_button_url' => self::get_student_dashboard_url('courses'),
        ]);

        $sent = self::send_html_email((string) $user->user_email, 'Seu acesso expirou - ' . $brand['name'], $html);

        if ($sent) {
            self::mark_notification_sent($user_id, $notification_key);
        }

        return $sent;
    }

    public static function process_daily_lifecycle_events(): void
    {
        global $wpdb;

        $table = PRESS_LMS_Database::table('enrollments');
        $days_before_expiry = max(1, (int) apply_filters('press_lms_access_expiring_notice_days', 3));
        $now_timestamp = current_time('timestamp');
        $now = date('Y-m-d H:i:s', $now_timestamp);
        $expiring_until = date('Y-m-d H:i:s', strtotime('+' . $days_before_expiry . ' days', $now_timestamp));

        $expiring_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE status = %s
                   AND expires_at IS NOT NULL
                   AND expires_at > %s
                   AND expires_at <= %s",
                'active',
                $now,
                $expiring_until
            )
        );

        foreach ((array) $expiring_rows as $enrollment) {
            self::send_access_expiring_email(
                (int) ($enrollment->user_id ?? 0),
                (int) ($enrollment->course_id ?? 0),
                (string) ($enrollment->expires_at ?? ''),
                $enrollment
            );
        }

        $expired_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE status = %s
                   AND expires_at IS NOT NULL
                   AND expires_at <= %s",
                'active',
                $now
            )
        );

        foreach ((array) $expired_rows as $enrollment) {
            self::send_access_expired_email(
                (int) ($enrollment->user_id ?? 0),
                (int) ($enrollment->course_id ?? 0),
                (string) ($enrollment->expires_at ?? ''),
                $enrollment
            );
        }
    }

    public static function mail_content_type(): string
    {
        return 'text/html; charset=UTF-8';
    }

    private static function get_brand_settings(): array
    {
        $opts = get_option('press_lms_settings', []);

        return [
            'name' => isset($opts['brand_name']) ? sanitize_text_field($opts['brand_name']) : 'Pressplay',
            'logo_url' => isset($opts['email_logo_url']) ? esc_url($opts['email_logo_url']) : '',
        ];
    }

    private static function get_student_dashboard_url(string $screen = 'courses'): string
    {
        if (class_exists('PRESS_LMS_Frontend') && method_exists('PRESS_LMS_Frontend', 'get_student_area_url')) {
            return PRESS_LMS_Frontend::get_student_area_url($screen);
        }

        return home_url('/meus-cursos/');
    }

    private static function get_course_url(WP_Post $course): string
    {
        return home_url('/curso/' . $course->post_name . '/');
    }

    private static function get_certificate_url(WP_Post $course): string
    {
        return home_url('/meus-cursos/certificado/' . $course->post_name . '/');
    }

    private static function build_notification_key(string $type, array $parts): string
    {
        $normalized_parts = array_map(static function ($part): string {
            return sanitize_title((string) $part);
        }, $parts);

        return sanitize_key($type . '_' . implode('_', $normalized_parts));
    }

    private static function get_notification_meta_key(string $notification_key): string
    {
        return 'press_lms_mail_' . md5($notification_key);
    }

    private static function has_notification_been_sent(int $user_id, string $notification_key): bool
    {
        if ($user_id <= 0 || $notification_key === '') {
            return false;
        }

        return get_user_meta($user_id, self::get_notification_meta_key($notification_key), true) === 'yes';
    }

    private static function mark_notification_sent(int $user_id, string $notification_key): void
    {
        if ($user_id <= 0 || $notification_key === '') {
            return;
        }

        update_user_meta($user_id, self::get_notification_meta_key($notification_key), 'yes');
    }

    private static function send_html_email(string $to, string $subject, string $html): bool
    {
        if ($to === '' || $subject === '' || $html === '') {
            return false;
        }

        add_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);
        $sent = wp_mail($to, $subject, $html);
        remove_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);

        return (bool) $sent;
    }

    private static function build_email_layout(array $data): string
    {
        $brand = esc_html((string) ($data['brand'] ?? 'Pressplay'));
        $logo_url = esc_url((string) ($data['logo_url'] ?? ''));
        $eyebrow = esc_html((string) ($data['eyebrow'] ?? ''));
        $title = esc_html((string) ($data['title'] ?? ''));
        $intro_html = wp_kses_post((string) ($data['intro_html'] ?? ''));
        $footer_html = wp_kses_post((string) ($data['footer_html'] ?? ''));
        $button_html = self::build_button_html(
            (string) ($data['button_url'] ?? ''),
            (string) ($data['button_label'] ?? ''),
            '#22c55e',
            '#0b0f17'
        );
        $secondary_button_html = self::build_button_html(
            (string) ($data['secondary_button_url'] ?? ''),
            (string) ($data['secondary_button_label'] ?? ''),
            'transparent',
            '#ffffff',
            true
        );

        $logo_html = $logo_url !== ''
            ? '<img src="' . $logo_url . '" alt="' . $brand . '" style="max-width:160px;height:auto;display:block;margin:0 auto 16px;">'
            : '';

        return '
        <div style="background:#0b0f17;padding:24px 0;">
          <div style="max-width:560px;margin:0 auto;background:#111827;border:1px solid #1f2937;border-radius:14px;overflow:hidden;">
            <div style="padding:26px 24px;text-align:center;">
              ' . $logo_html . '
              ' . ($eyebrow !== '' ? '<div style="margin:0 0 8px;color:#22c55e;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;font-family:Arial,sans-serif;">' . $eyebrow . '</div>' : '') . '
              <h1 style="margin:0 0 10px;color:#fff;font-size:22px;font-family:Arial,sans-serif;">' . $title . '</h1>
              ' . $intro_html . '
              ' . $button_html . '
              ' . $secondary_button_html . '
              ' . $footer_html . '
            </div>
          </div>
        </div>';
    }

    private static function build_button_html(
        string $url,
        string $label,
        string $background,
        string $color,
        bool $outlined = false
    ): string {
        $url = esc_url($url);
        $label = esc_html($label);

        if ($url === '' || $label === '') {
            return '';
        }

        $styles = [
            'display:inline-block',
            'text-decoration:none',
            'padding:12px 16px',
            'border-radius:10px',
            'font-weight:bold',
            'font-family:Arial,sans-serif',
            'margin:0 6px 6px',
        ];

        if ($outlined) {
            $styles[] = 'background:transparent';
            $styles[] = 'border:1px solid rgba(148,163,184,0.45)';
            $styles[] = 'color:' . $color;
        } else {
            $styles[] = 'background:' . $background;
            $styles[] = 'color:' . $color;
            $styles[] = 'border:1px solid ' . $background;
        }

        return '<a href="' . $url . '" style="' . esc_attr(implode(';', $styles)) . '">' . $label . '</a>';
    }
}
