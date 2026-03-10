<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Progress
{
    public static function upsert_progress(
        int $user_id,
        int $course_id,
        int $lesson_id,
        int $watched_seconds,
        int $completed = 0
    ): void {
        global $wpdb;

        if ($user_id <= 0 || $course_id <= 0 || $lesson_id <= 0) return;

        $table = PRESS_LMS_Database::table('progress');
        $now   = current_time('mysql');

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d AND lesson_id = %d
                 LIMIT 1",
                $user_id,
                $lesson_id
            )
        );

        if ($existing) {
            $new_watched = max((int)$existing->watched_seconds, $watched_seconds);

            $data = [
                'watched_seconds' => $new_watched,
                'updated_at'      => $now,
            ];

            if ((int)$completed === 1 && (int)$existing->completed !== 1) {
                $data['completed'] = 1;
                $data['completed_at'] = $now;
            }

            $wpdb->update(
                $table,
                $data,
                ['id' => (int)$existing->id]
            );
        } else {
            $wpdb->insert($table, [
                'user_id'         => $user_id,
                'course_id'       => $course_id,
                'lesson_id'       => $lesson_id,
                'watched_seconds' => max(0, $watched_seconds),
                'completed'       => $completed ? 1 : 0,
                'completed_at'    => $completed ? $now : null,
                'updated_at'      => $now,
            ]);
        }
    }

    public static function get_lesson_progress(int $user_id, int $lesson_id)
    {
        global $wpdb;
        $table = PRESS_LMS_Database::table('progress');
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d AND lesson_id = %d
                 LIMIT 1",
                $user_id,
                $lesson_id
            )
        );
    }

    public static function get_course_progress_percent(int $user_id, int $course_id): int
    {
        global $wpdb;

        $table_progress = PRESS_LMS_Database::table('progress');
        $posts_table    = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        $total_lessons = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(p.ID)
                 FROM {$posts_table} p
                 INNER JOIN {$postmeta_table} pm
                    ON pm.post_id = p.ID
                    AND pm.meta_key = '_press_lesson_course_id'
                 WHERE p.post_type = 'press_lesson'
                   AND p.post_status = 'publish'
                   AND pm.meta_value = %d",
                $course_id
            )
        );

        if ($total_lessons <= 0) return 0;

        $completed_lessons = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id)
                 FROM {$table_progress}
                 WHERE user_id = %d
                   AND course_id = %d
                   AND completed = 1",
                $user_id,
                $course_id
            )
        );

        return (int) round(($completed_lessons / $total_lessons) * 100);
    }
}