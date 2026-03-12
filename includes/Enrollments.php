<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Enrollments
{
    private const DEFAULT_ACCESS_TYPE = 'years';
    private const DEFAULT_ACCESS_VALUE = 1;

    public static function is_course_paused(int $course_id): bool
    {
        if (class_exists('PRESS_LMS_Course_Lifecycle') && method_exists('PRESS_LMS_Course_Lifecycle', 'is_course_paused')) {
            return PRESS_LMS_Course_Lifecycle::is_course_paused($course_id);
        }

        return false;
    }

    public static function is_admin_user($user_id = null): bool
    {
        if (!$user_id) $user_id = get_current_user_id();
        return $user_id && user_can($user_id, 'manage_options');
    }

    public static function get_supported_access_types(): array
    {
        return [
            'lifetime' => 'Vitalício',
            'days' => 'Dias',
            'months' => 'Meses',
            'years' => 'Anos',
        ];
    }

    public static function get_default_access_settings(): array
    {
        return [
            'type' => self::DEFAULT_ACCESS_TYPE,
            'value' => self::DEFAULT_ACCESS_VALUE,
        ];
    }

    public static function normalize_access_settings(string $type, int $value): array
    {
        $type = sanitize_key($type);
        $allowed_types = array_keys(self::get_supported_access_types());

        if (!in_array($type, $allowed_types, true)) {
            return self::get_default_access_settings();
        }

        if ($type === 'lifetime') {
            return [
                'type' => 'lifetime',
                'value' => 0,
            ];
        }

        $value = max(1, (int) $value);

        return [
            'type' => $type,
            'value' => $value,
        ];
    }

    public static function get_course_access_settings(int $course_id): array
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) {
            return self::get_default_access_settings();
        }

        $type = (string) get_post_meta($course_id, '_press_course_access_type', true);
        $value = (int) get_post_meta($course_id, '_press_course_access_value', true);

        if ($type === '' && $value <= 0) {
            return self::get_default_access_settings();
        }

        return self::normalize_access_settings($type, $value);
    }

    public static function format_access_settings(array $settings): string
    {
        $type = sanitize_key((string) ($settings['type'] ?? ''));
        $value = (int) ($settings['value'] ?? 0);
        $settings = self::normalize_access_settings($type, $value);

        if ($settings['type'] === 'lifetime') {
            return 'Acesso vitalício';
        }

        $value = (int) $settings['value'];
        $label_map = [
            'days' => $value === 1 ? 'dia' : 'dias',
            'months' => $value === 1 ? 'mês' : 'meses',
            'years' => $value === 1 ? 'ano' : 'anos',
        ];

        $unit_label = $label_map[$settings['type']] ?? 'dias';

        return sprintf('%d %s de acesso', $value, $unit_label);
    }

    public static function get_course_access_label(int $course_id): string
    {
        return self::format_access_settings(self::get_course_access_settings($course_id));
    }

    public static function calculate_enrollment_expiration(int $course_id, ?int $from_timestamp = null): ?string
    {
        $settings = self::get_course_access_settings($course_id);

        if ($settings['type'] === 'lifetime') {
            return null;
        }

        $from_timestamp = $from_timestamp ?: current_time('timestamp');
        $interval = '+' . (int) $settings['value'] . ' ' . $settings['type'];
        $expires_timestamp = strtotime($interval, $from_timestamp);

        if (!$expires_timestamp) {
            $expires_timestamp = strtotime('+1 year', $from_timestamp);
        }

        return date('Y-m-d H:i:s', $expires_timestamp);
    }

    public static function is_expired_at(?string $expires_at): bool
    {
        if (!$expires_at) {
            return false;
        }

        $expires_timestamp = strtotime($expires_at);
        if (!$expires_timestamp) {
            return false;
        }

        return $expires_timestamp <= current_time('timestamp');
    }

    public static function format_enrollment_expires_at(?string $expires_at): string
    {
        if (!$expires_at) {
            return 'Acesso vitalício';
        }

        $expires_timestamp = strtotime($expires_at);
        if (!$expires_timestamp) {
            return 'Validade não definida';
        }

        return 'Acesso até ' . date_i18n('d/m/Y', $expires_timestamp);
    }

    public static function get_enrollment_status_key($enrollment): string
    {
        if (!is_object($enrollment)) {
            return 'unknown';
        }

        $status = sanitize_key((string) ($enrollment->status ?? ''));
        if ($status === '') {
            return 'unknown';
        }

        if ($status === 'active' && self::is_expired_at(!empty($enrollment->expires_at) ? (string) $enrollment->expires_at : null)) {
            return 'expired';
        }

        return $status;
    }

    public static function is_enrollment_currently_active($enrollment): bool
    {
        return self::get_enrollment_status_key($enrollment) === 'active';
    }

    public static function get_enrollment_status_label($enrollment): string
    {
        $labels = [
            'active' => 'Ativo',
            'expired' => 'Expirado',
            'pending' => 'Pendente',
            'blocked' => 'Bloqueado',
            'cancelled' => 'Cancelado',
            'failed' => 'Pagamento falhou',
            'refunded' => 'Reembolsado',
            'unknown' => 'Desconhecido',
        ];

        $status_key = self::get_enrollment_status_key($enrollment);
        return $labels[$status_key] ?? $labels['unknown'];
    }

    public static function get_enrollment_status_class($enrollment): string
    {
        $classes = [
            'active' => 'is-success is-light',
            'expired' => 'is-danger is-light',
            'pending' => 'is-warning is-light',
            'blocked' => 'is-danger is-light',
            'cancelled' => 'is-light',
            'failed' => 'is-danger is-light',
            'refunded' => 'is-info is-light',
            'unknown' => 'is-light',
        ];

        $status_key = self::get_enrollment_status_key($enrollment);
        return $classes[$status_key] ?? $classes['unknown'];
    }

    public static function get_enrollment_access_summary($enrollment): string
    {
        $status_key = self::get_enrollment_status_key($enrollment);
        $expires_at = !empty($enrollment->expires_at) ? (string) $enrollment->expires_at : '';

        switch ($status_key) {
            case 'active':
                return self::format_enrollment_expires_at($expires_at !== '' ? $expires_at : null);

            case 'expired':
                return $expires_at !== ''
                    ? 'Expirou em ' . date_i18n('d/m/Y', strtotime($expires_at))
                    : 'Acesso expirado';

            case 'pending':
                return 'Aguardando confirmação do pagamento';

            case 'blocked':
                return 'Acesso bloqueado manualmente';

            case 'cancelled':
                return 'Pedido cancelado';

            case 'failed':
                return 'Pagamento não aprovado';

            case 'refunded':
                return 'Pedido reembolsado';

            default:
                return 'Status da matrícula indisponível';
        }
    }

    public static function get_enrollment_by_id(int $enrollment_id)
    {
        global $wpdb;

        $enrollment_id = (int) $enrollment_id;
        if ($enrollment_id <= 0) {
            return null;
        }

        $table = PRESS_LMS_Database::table('enrollments');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE id = %d
                 LIMIT 1",
                $enrollment_id
            )
        );
    }

    public static function has_any_enrollment(int $user_id): bool
    {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE user_id = %d
                 LIMIT 1",
                $user_id
            )
        );

        return !empty($id);
    }

    public static function get_user_enrollments(int $user_id, array $args = []): array
    {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $defaults = [
            'include_pending' => true,
        ];
        $args = wp_parse_args($args, $defaults);

        $table = PRESS_LMS_Database::table('enrollments');
        $posts_table = $wpdb->posts;
        $now = current_time('mysql');

        $where = [
            'e.user_id = %d',
        ];
        $where_params = [$user_id];

        if (empty($args['include_pending'])) {
            $where[] = 'e.status <> %s';
            $where_params[] = 'pending';
        }

        $sql = "
            SELECT
                e.id,
                e.course_id,
                e.status,
                e.purchased_at,
                e.expires_at,
                e.payment_provider,
                e.order_ref,
                e.created_at,
                e.updated_at,
                p.post_title AS course_title,
                p.post_name AS course_slug,
                p.post_status AS course_post_status
            FROM {$table} e
            INNER JOIN {$posts_table} p
                ON p.ID = e.course_id
               AND p.post_type = %s
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE
                WHEN e.status = 'active' AND (e.expires_at IS NULL OR e.expires_at > %s) THEN 0
                WHEN e.status = 'pending' THEN 1
                ELSE 2
            END,
            COALESCE(e.purchased_at, e.created_at) DESC,
            e.id DESC
        ";

        $params = array_merge(['press_course'], $where_params, [$now]);

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        return is_array($rows) ? $rows : [];
    }

    public static function attach_order_to_pending_enrollment(int $user_id, int $course_id, int $order_id, string $provider = 'woocommerce'): void
    {
        global $wpdb;

        $user_id = (int) $user_id;
        $course_id = (int) $course_id;
        $order_id = (int) $order_id;

        if ($user_id <= 0 || $course_id <= 0 || $order_id <= 0) {
            return;
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $now = current_time('mysql');

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET order_ref = %s,
                     payment_provider = %s,
                     updated_at = %s
                 WHERE user_id = %d
                   AND course_id = %d
                   AND status = %s",
                (string) $order_id,
                $provider,
                $now,
                $user_id,
                $course_id,
                'pending'
            )
        );
    }

    public static function deactivate_enrollment(int $user_id, int $course_id, string $status = 'blocked', int $order_id = 0): bool
    {
        global $wpdb;

        $user_id = (int) $user_id;
        $course_id = (int) $course_id;
        $order_id = (int) $order_id;

        if ($user_id <= 0 || $course_id <= 0) {
            return false;
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $now_ts = current_time('timestamp');
        $now = date('Y-m-d H:i:s', $now_ts);

        $where_sql = "user_id = %d AND course_id = %d";
        $params = [$user_id, $course_id];

        if ($order_id > 0) {
            $where_sql .= " AND order_ref = %s";
            $params[] = (string) $order_id;
        }

        $where_sql .= " LIMIT 1";

        $enrollment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE {$where_sql}",
                ...$params
            )
        );

        if (!$enrollment) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            [
                'status' => sanitize_key($status),
                'expires_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => (int) $enrollment->id]
        );

        return $result !== false;
    }

    public static function reactivate_enrollment_by_id(int $enrollment_id): bool
    {
        global $wpdb;

        $enrollment = self::get_enrollment_by_id($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        $expires_at = self::calculate_enrollment_expiration((int) $enrollment->course_id, current_time('timestamp'));
        $now = current_time('mysql');
        $table = PRESS_LMS_Database::table('enrollments');

        $result = $wpdb->update(
            $table,
            [
                'status' => 'active',
                'purchased_at' => !empty($enrollment->purchased_at) ? $enrollment->purchased_at : $now,
                'expires_at' => $expires_at,
                'updated_at' => $now,
            ],
            ['id' => (int) $enrollment_id]
        );

        if ($result !== false && class_exists('PRESS_LMS_Mailer')) {
            $updated_enrollment = self::get_enrollment_by_id($enrollment_id);
            PRESS_LMS_Mailer::send_enrollment_activated_email(
                (int) $enrollment->user_id,
                (int) $enrollment->course_id,
                $updated_enrollment ?: $enrollment
            );
        }

        return $result !== false;
    }

    public static function extend_enrollment_by_id(int $enrollment_id, int $amount, string $unit = 'days'): bool
    {
        global $wpdb;

        $enrollment = self::get_enrollment_by_id($enrollment_id);
        if (!$enrollment) {
            return false;
        }

        $amount = max(1, (int) $amount);
        $unit = sanitize_key($unit);
        if (!in_array($unit, ['days', 'months', 'years'], true)) {
            $unit = 'days';
        }

        $base_timestamp = current_time('timestamp');
        if (!empty($enrollment->expires_at)) {
            $current_expiry = strtotime((string) $enrollment->expires_at);
            if ($current_expiry && $current_expiry > $base_timestamp) {
                $base_timestamp = $current_expiry;
            }
        }

        $new_expiry = strtotime('+' . $amount . ' ' . $unit, $base_timestamp);
        if (!$new_expiry) {
            return false;
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $result = $wpdb->update(
            $table,
            [
                'status' => 'active',
                'expires_at' => date('Y-m-d H:i:s', $new_expiry),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $enrollment_id]
        );

        return $result !== false;
    }

    public static function has_active_enrollment(int $user_id, int $course_id): bool
    {
        global $wpdb;
        $table = PRESS_LMS_Database::table('enrollments');
        $now = current_time('mysql');

        $sql = "SELECT id FROM {$table}
                WHERE user_id = %d
                  AND course_id = %d
                  AND status = %s
                  AND (expires_at IS NULL OR expires_at > %s)
                LIMIT 1";

        $id = $wpdb->get_var($wpdb->prepare($sql, $user_id, $course_id, 'active', $now));
        return !empty($id);
    }

    public static function get_active_enrollments(int $user_id): array
    {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $posts_table = $wpdb->posts;
        $now = current_time('mysql');

        $sql = "
            SELECT
                e.id,
                e.course_id,
                e.status,
                e.purchased_at,
                e.expires_at,
                e.payment_provider,
                e.order_ref,
                e.created_at,
                e.updated_at,
                p.post_title AS course_title,
                p.post_name AS course_slug,
                p.post_status AS course_post_status
            FROM {$table} e
            INNER JOIN {$posts_table} p
                ON p.ID = e.course_id
            WHERE e.user_id = %d
              AND e.status = %s
              AND (e.expires_at IS NULL OR e.expires_at > %s)
              AND p.post_type = %s
            ORDER BY COALESCE(e.purchased_at, e.created_at) DESC, e.id DESC
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $user_id, 'active', $now, 'press_course')
        );

        return is_array($rows) ? $rows : [];
    }

    public static function get_or_create_pending(int $user_id, int $course_id, string $provider = 'woocommerce'): int
    {
        global $wpdb;

        if (self::is_course_paused($course_id)) {
            return 0;
        }

        self::ensure_student_role($user_id);
        $table = PRESS_LMS_Database::table('enrollments');
        $now = current_time('mysql');

        // Reuse the active enrollment when access is still valid.
        $sql_active = "SELECT id FROM {$table}
                       WHERE user_id=%d AND course_id=%d
                         AND status=%s
                         AND (expires_at IS NULL OR expires_at > %s)
                       LIMIT 1";
        $active_id = $wpdb->get_var($wpdb->prepare($sql_active, $user_id, $course_id, 'active', $now));
        if ($active_id) return (int)$active_id;

        // Reuse a pending enrollment instead of creating duplicates.
        $sql_pending = "SELECT id FROM {$table}
                        WHERE user_id=%d AND course_id=%d AND status=%s
                        LIMIT 1";
        $pending_id = $wpdb->get_var($wpdb->prepare($sql_pending, $user_id, $course_id, 'pending'));
        if ($pending_id) {
            $wpdb->update($table, [
                'updated_at' => $now,
                'payment_provider' => $provider,
            ], ['id' => (int)$pending_id]);
            return (int)$pending_id;
        }

        // Create a fresh pending enrollment.
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'status' => 'pending',
            'purchased_at' => null,
            'expires_at' => null,
            'payment_provider' => $provider,
            'order_ref' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int)$wpdb->insert_id;
    }
    public static function ensure_student_role(int $user_id): void
    {
        if ($user_id <= 0) return;

        $user = get_userdata($user_id);
        if (!$user) return;

        // Never override administrator roles.
        if (user_can($user_id, 'manage_options')) return;

        // Leave the role untouched when the user is already a student.
        if (in_array('press_student', (array) $user->roles, true)) {
            return;
        }

        // Make the student role the primary role for LMS users.
        $user->set_role('press_student');
    }
    public static function activate_enrollment(int $user_id, int $course_id, int $order_id, string $provider = 'woocommerce'): bool
    {
        global $wpdb;

        self::ensure_student_role($user_id);

        $table = PRESS_LMS_Database::table('enrollments');

        $now_ts = current_time('timestamp');
        $now = date('Y-m-d H:i:s', $now_ts);
        $expires = self::calculate_enrollment_expiration($course_id, $now_ts);

        // Update the existing enrollment or create a new one if needed.
        $sql = "SELECT id FROM {$table} WHERE user_id=%d AND course_id=%d LIMIT 1";
        $id = $wpdb->get_var($wpdb->prepare($sql, $user_id, $course_id));

        if ($id) {
            $existing = self::get_enrollment_by_id((int) $id);
            if (
                $existing &&
                $existing->status === 'active' &&
                (string) ($existing->order_ref ?? '') === (string) $order_id &&
                !self::is_expired_at((string) ($existing->expires_at ?? ''))
            ) {
                return false;
            }
        }

        $data = [
            'status' => 'active',
            'purchased_at' => $now,
            'expires_at' => $expires,
            'payment_provider' => $provider,
            'order_ref' => (string)$order_id,
            'updated_at' => $now,
        ];

        if ($id) {
            $result = $wpdb->update($table, $data, ['id' => (int)$id]);
            $enrollment_id = (int) $id;
        } else {
            $data['user_id'] = $user_id;
            $data['course_id'] = $course_id;
            $data['created_at'] = $now;
            $result = $wpdb->insert($table, $data);
            $enrollment_id = (int) $wpdb->insert_id;
        }

        if ($result === false) {
            return false;
        }

        if (class_exists('PRESS_LMS_Mailer')) {
            $enrollment = $enrollment_id > 0 ? self::get_enrollment_by_id($enrollment_id) : null;
            PRESS_LMS_Mailer::send_enrollment_activated_email($user_id, $course_id, $enrollment);
        }

        return true;
    }

    public static function get_course_product_id(int $course_id): int
    {
        return (int) get_post_meta($course_id, '_press_course_product_id', true);
    }

    public static function can_access_course(int $user_id, int $course_id): bool
    {
        if (self::is_admin_user($user_id)) return true;
        if ($user_id <= 0) return false;
        return self::has_active_enrollment($user_id, $course_id);
    }
}
