<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Helpers {
    private static $lesson_thumbnail_cache = [];
    private const STUDENT_AVATAR_META_KEY = 'press_lms_avatar_id';

    public static function is_viewable_post($post, string $type): bool
    {
        if (!$post instanceof WP_Post || $post->post_type !== $type || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            return false;
        }
        if ($post->post_status !== 'publish' && !current_user_can('edit_post', $post->ID)) {
            return false;
        }
        return !post_password_required($post) || current_user_can('edit_post', $post->ID);
    }

    public static function get_visible_course(string $slug)
    {
        $course = get_page_by_path($slug, OBJECT, 'press_course');
        return self::is_viewable_post($course, 'press_course') ? $course : null;
    }

    public static function is_sample_lesson(int $lesson_id, int $course_id): bool
    {
        $lesson = get_post($lesson_id);
        $course = get_post($course_id);
        if (!$lesson instanceof WP_Post || !$course instanceof WP_Post
            || $lesson->post_type !== 'press_lesson' || $course->post_type !== 'press_course'
            || $lesson->post_status !== 'publish' || $course->post_status !== 'publish'
            || !empty($lesson->post_password) || !empty($course->post_password)) {
            return false;
        }
        $parent = (int) $lesson->post_parent ?: (int) get_post_meta($lesson_id, '_press_lesson_course_id', true);
        return $parent === $course_id && get_post_meta($lesson_id, '_press_lesson_free_preview', true) === 'yes';
    }

    public static function render_post_content(WP_Post $content_post): string
    {
        // Elementor and shortcodes resolve their document from the global post.
        $previous = $GLOBALS['post'] ?? null;
        try {
            $GLOBALS['post'] = $content_post;
            setup_postdata($content_post);
            return (string) apply_filters('the_content', $content_post->post_content);
        } finally {
            $GLOBALS['post'] = $previous;
            if ($previous instanceof WP_Post) {
                setup_postdata($previous);
            } else {
                wp_reset_postdata();
            }
        }
    }

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
            'update_post_term_cache' => false,
            'orderby'        => 'menu_order ID',
            'order'          => 'ASC',
        ]);

        $lessons_by_meta = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => $statuses,
            'posts_per_page' => -1,
            'meta_key'       => '_press_lesson_course_id',
            'meta_value'     => $course_id,
            'update_post_term_cache' => false,
            'orderby'        => 'menu_order ID',
            'order'          => 'ASC',
        ]);

        $indexed = [];

        foreach (array_merge($lessons_by_parent, $lessons_by_meta) as $lesson) {
            if (!$lesson instanceof WP_Post) {
                continue;
            }
            if ((int) $lesson->post_parent > 0 && (int) $lesson->post_parent !== $course_id) continue;

            $indexed[$lesson->ID] = $lesson;
        }

        $lessons = array_values($indexed);

        usort($lessons, function ($a, $b) {
            // Explicit positions come first; zero means registration order (stable ID).
            $order_compare = ((int) $a->menu_order > 0 ? (int) $a->menu_order : PHP_INT_MAX)
                <=> ((int) $b->menu_order > 0 ? (int) $b->menu_order : PHP_INT_MAX);
            if ($order_compare !== 0) {
                return $order_compare;
            }

            return (int) $a->ID <=> (int) $b->ID;
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

    public static function username_from_email($email): string {
        $email = sanitize_email((string) $email);
        $email_parts = explode('@', $email);
        $base = sanitize_user((string) ($email_parts[0] ?? ''), true);

        if ($base === '') {
            $base = 'aluno';
        }

        $username = $base;
        $suffix = 1;

        while (username_exists($username)) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
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

    /**
     * Persist the normalized student profile that powers the LMS account area.
     */
    public static function upsert_student_profile($user_id, $full_name, $phone) {
        global $wpdb;

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        $table = PRESS_LMS_Database::table('students');
        $now = current_time('mysql');
        $full_name = sanitize_text_field((string) $full_name);
        $phone = sanitize_text_field((string) $phone);

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
