<?php
if (!defined('ABSPATH')) exit;

class PRESSLMS_Duration
{
    public static function recalc_course_total_duration(int $course_id): int
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return 0;

        $lesson_ids = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_press_lesson_course_id',
            'meta_value'     => $course_id,
        ]);

        $total = 0;
        foreach ($lesson_ids as $lesson_id) {
            $d = (int) get_post_meta((int)$lesson_id, '_press_lesson_duration', true);
            if ($d > 0) $total += $d;
        }

        update_post_meta($course_id, '_press_course_total_duration', $total);
        return $total;
    }
}