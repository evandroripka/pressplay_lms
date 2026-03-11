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
    public static function activate_enrollment(int $user_id, int $course_id, int $order_id, string $provider = 'woocommerce'): void
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

        $data = [
            'status' => 'active',
            'purchased_at' => $now,
            'expires_at' => $expires,
            'payment_provider' => $provider,
            'order_ref' => (string)$order_id,
            'updated_at' => $now,
        ];

        if ($id) {
            $wpdb->update($table, $data, ['id' => (int)$id]);
        } else {
            $data['user_id'] = $user_id;
            $data['course_id'] = $course_id;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
        }
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
