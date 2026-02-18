<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Enrollments
{
    public static function has_active_enrollment($user_id, $course_id)
    {
        global $wpdb;
        $table = MLB_LMS_Database::table('enrollments');
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

    public static function upsert_active_enrollment($user_id, $course_id, $order_id, $provider = 'woocommerce')
    {
        global $wpdb;
        $table = MLB_LMS_Database::table('enrollments');

        $now = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', strtotime('+1 year', current_time('timestamp')));

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d LIMIT 1", $user_id, $course_id)
        );

        $data = [
            'user_id' => (int) $user_id,
            'course_id' => (int) $course_id,
            'status' => 'active',
            'purchased_at' => $now,
            'expires_at' => $expires,
            'payment_provider' => sanitize_text_field($provider),
            'order_ref' => (string) $order_id,
            'updated_at' => $now,
        ];

        if ($existing_id) {
            return (bool) $wpdb->update($table, $data, ['id' => (int) $existing_id]);
        }

        $data['created_at'] = $now;
        return (bool) $wpdb->insert($table, $data);
    }
}
