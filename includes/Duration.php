<?php
if (!defined('ABSPATH')) exit;

/** Server-verified lesson durations and published-course totals. */
class PRESSLMS_Duration
{
    private static $deleted_courses = [];
    public static function init(): void
    {
        add_action('save_post_press_lesson', [__CLASS__, 'sync_saved_lesson'], 30, 2);
        add_action('transition_post_status', [__CLASS__, 'on_status_change'], 30, 3);
        add_action('deleted_post', [__CLASS__, 'on_deleted_lesson'], 30, 2);
        add_action('before_delete_post', [__CLASS__, 'before_delete'], 30, 2);
    }

    public static function sync_saved_lesson(int $post_id, $post): void
    {
        if (!$post instanceof WP_Post || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (!in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            self::sync_lesson_duration($post_id);
        }
        self::recalc_course_total_duration(self::get_course_id(get_post($post_id) ?: $post));
    }

    private static function get_course_id(WP_Post $lesson): int
    {
        return (int) $lesson->post_parent ?: (int) get_post_meta($lesson->ID, '_press_lesson_course_id', true);
    }

    public static function on_status_change(string $new, string $old, $post): void
    {
        if ($new !== $old && $post instanceof WP_Post && $post->post_type === 'press_lesson') {
            self::recalc_course_total_duration(self::get_course_id($post));
        }
    }

    public static function on_deleted_lesson(int $post_id, $post): void
    {
        if ($post instanceof WP_Post && $post->post_type === 'press_lesson') {
            self::recalc_course_total_duration(self::$deleted_courses[$post_id] ?? (int) $post->post_parent);
            unset(self::$deleted_courses[$post_id]);
        }
    }

    public static function before_delete(int $post_id, $post): void
    {
        if ($post instanceof WP_Post && $post->post_type === 'press_lesson') {
            self::$deleted_courses[$post_id] = self::get_course_id($post);
        }
    }

    public static function sync_lesson_duration(int $lesson_id): int
    {
        $lesson = get_post($lesson_id);
        if (!$lesson instanceof WP_Post || $lesson->post_type !== 'press_lesson') return 0;
        $url = (string) get_post_meta($lesson_id, '_press_lesson_video_url', true);
        $source = (string) get_post_meta($lesson_id, '_press_lesson_duration_source', true);
        $duration = max(0, (int) get_post_meta($lesson_id, '_press_lesson_duration', true));

        // A different video must not inherit the previous video's duration.
        if ($source !== '' && $source !== $url) {
            $duration = 0;
            update_post_meta($lesson_id, '_press_lesson_duration', 0);
        }
        update_post_meta($lesson_id, '_press_lesson_duration_source', $url);

        if (!PRESS_LMS_Vimeo::parse_video_id($url)) {
            return $duration;
        }
        $data = PRESS_LMS_Vimeo::get_video_metadata($url);
        if (is_wp_error($data)) {
            // An API outage must not erase a previously verified duration.
            update_post_meta($lesson_id, '_press_lesson_vimeo_error', $data->get_error_message());
            return $duration;
        }
        $duration = (int) $data['duration'];
        update_post_meta($lesson_id, '_press_lesson_duration', $duration);
        update_post_meta($lesson_id, '_press_lesson_vimeo_id', PRESS_LMS_Vimeo::parse_video_id($url));
        delete_post_meta($lesson_id, '_press_lesson_vimeo_error');
        self::recalc_course_total_duration(self::get_course_id($lesson));
        return $duration;
    }

    public static function get_course_total_duration(int $course_id): int
    {
        $total = 0;
        foreach (PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish']) as $lesson) {
            $total += max(0, (int) get_post_meta($lesson->ID, '_press_lesson_duration', true));
        }
        return $total;
    }

    public static function recalc_course_total_duration(int $course_id): int
    {
        if ($course_id <= 0) return 0;
        $total = self::get_course_total_duration($course_id);
        update_post_meta($course_id, '_press_course_total_duration', $total);
        return $total;
    }
}
