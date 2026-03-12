<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Helpers {
    private static $lesson_thumbnail_cache = [];
    private const STUDENT_AVATAR_META_KEY = 'press_lms_avatar_id';

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

    public static function get_lesson_thumbnail_url(int $lesson_id, int $course_id = 0, string $size = 'medium_large'): string
    {
        $lesson_id = (int) $lesson_id;
        $course_id = (int) $course_id;
        $size = trim($size);

        if ($lesson_id <= 0) {
            return '';
        }

        $cache_key = $lesson_id . ':' . $course_id . ':' . ($size !== '' ? $size : 'medium_large');
        if (array_key_exists($cache_key, self::$lesson_thumbnail_cache)) {
            return self::$lesson_thumbnail_cache[$cache_key];
        }

        $thumbnail_url = get_the_post_thumbnail_url($lesson_id, $size ?: 'medium_large');

        if (!$thumbnail_url) {
            $thumbnail_url = (string) get_post_meta($lesson_id, '_press_lesson_vimeo_thumbnail_url', true);
        }

        if (!$thumbnail_url) {
            $vimeo_id = (int) get_post_meta($lesson_id, '_press_lesson_vimeo_id', true);

            if (
                $vimeo_id > 0 &&
                class_exists('PRESS_LMS_Vimeo') &&
                method_exists('PRESS_LMS_Vimeo', 'get_video_thumbnail_url')
            ) {
                $thumbnail_url = PRESS_LMS_Vimeo::get_video_thumbnail_url($vimeo_id);

                if ($thumbnail_url !== '') {
                    update_post_meta($lesson_id, '_press_lesson_vimeo_thumbnail_url', esc_url_raw($thumbnail_url));
                }
            }
        }

        if (!$thumbnail_url && $course_id > 0) {
            $thumbnail_url = get_the_post_thumbnail_url($course_id, $size ?: 'medium_large');
        }

        self::$lesson_thumbnail_cache[$cache_key] = (string) $thumbnail_url;

        return self::$lesson_thumbnail_cache[$cache_key];
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
        // Use a permissive validation rule for Brazilian phone numbers.
        $digits = preg_replace('/\D+/', '', $phone);
        // Accept landline and mobile formats with DDD.
        return (strlen($digits) >= 10 && strlen($digits) <= 13);
    }

    public static function phone_to_e164_br($phone) {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        // Keep numbers that already include the country code.
        if (str_starts_with($digits, '55')) {
            return '+' . $digits;
        }
        // Otherwise, assume a Brazilian number.
        return '+55' . $digits;
    }

    public static function get_student_avatar_id(int $user_id): int
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }

        return (int) get_user_meta($user_id, self::STUDENT_AVATAR_META_KEY, true);
    }

    public static function has_student_avatar(int $user_id): bool
    {
        return self::get_student_avatar_id($user_id) > 0;
    }

    public static function get_student_avatar_url(int $user_id, $size = 96): string
    {
        $avatar_id = self::get_student_avatar_id($user_id);

        if ($avatar_id > 0) {
            $custom_avatar_url = wp_get_attachment_image_url($avatar_id, is_string($size) ? $size : 'thumbnail');
            if ($custom_avatar_url) {
                return (string) $custom_avatar_url;
            }
        }

        $avatar_args = [];
        if (is_numeric($size)) {
            $avatar_args['size'] = (int) $size;
        } elseif (is_string($size) && $size !== '') {
            $avatar_args['size'] = 96;
        }

        return (string) get_avatar_url($user_id, $avatar_args);
    }

    public static function set_student_avatar_id(int $user_id, int $attachment_id): void
    {
        $user_id = (int) $user_id;
        $attachment_id = (int) $attachment_id;

        if ($user_id <= 0) {
            return;
        }

        if ($attachment_id > 0) {
            update_user_meta($user_id, self::STUDENT_AVATAR_META_KEY, $attachment_id);
            return;
        }

        delete_user_meta($user_id, self::STUDENT_AVATAR_META_KEY);
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
