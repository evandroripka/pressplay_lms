<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Helpers {

    public static function get_course_lessons($course_id, $post_status = ['publish']) {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return [];

        $statuses = is_array($post_status) ? array_values(array_filter($post_status)) : [$post_status];
        if (empty($statuses)) {
            $statuses = ['publish'];
        }

        $lessons_by_parent = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'post_parent'    => $course_id,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        $lessons_by_meta = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'meta_key'       => '_press_lesson_course_id',
            'meta_value'     => $course_id,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        $indexed = [];

        foreach (array_merge($lessons_by_parent, $lessons_by_meta) as $lesson) {
            if (!$lesson instanceof WP_Post) {
                continue;
            }

            $indexed[$lesson->ID] = $lesson;
        }

        $lessons = array_values($indexed);

        usort($lessons, function ($a, $b) {
            $order_compare = (int) $a->menu_order <=> (int) $b->menu_order;
            if ($order_compare !== 0) {
                return $order_compare;
            }

            return strcasecmp($a->post_title, $b->post_title);
        });

        return $lessons;
    }

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
        $table = PRESS_LMS_Database::table('students');
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
