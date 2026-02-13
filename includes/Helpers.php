<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Helpers {

    public static function username_from_email($email) {
        $base = sanitize_user(current(explode('@', $email)), true);
        if (!$base) $base = 'aluno';
        $u = $base;
        $i = 1;
        while (username_exists($u)) {
            $u = $base . $i;
            $i++;
        }
        return $u;
    }

    public static function is_valid_phone_br($phone) {
        // bem permissivo: você pode refinar depois
        $digits = preg_replace('/\D+/', '', $phone);
        // (11) 9xxxx-xxxx => 11 dígitos + DDD => 11
        return (strlen($digits) >= 10 && strlen($digits) <= 13);
    }

    public static function phone_to_e164_br($phone) {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        // se já vier com 55...
        if (str_starts_with($digits, '55')) {
            return '+' . $digits;
        }
        // assume BR
        return '+55' . $digits;
    }

    public static function upsert_student_profile($user_id, $full_name, $phone) {
        global $wpdb;
        $table = MLB_LMS_Database::table('students');
        $now = current_time('mysql');

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d", $user_id));
        $data = [
            'user_id' => $user_id,
            'full_name' => $full_name,
            'phone_raw' => $phone,
            'phone_e164' => self::phone_to_e164_br($phone),
            'updated_at' => $now,
        ];

        if ($exists) {
            $wpdb->update($table, $data, ['user_id' => $user_id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
        }
    }
}
