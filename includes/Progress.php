<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Progress
{
    private static function get_completed_lesson_ids_for_course(int $user_id, int $course_id): array
    {
        global $wpdb;

        $table_progress = PRESS_LMS_Database::table('progress');
        $completed_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT lesson_id
                 FROM {$table_progress}
                 WHERE user_id = %d
                   AND course_id = %d
                   AND completed = 1",
                $user_id,
                $course_id
            )
        );

        if (!is_array($completed_ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $completed_ids)));
    }

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
                 WHERE user_id = %d
                   AND course_id = %d
                   AND lesson_id = %d
                 LIMIT 1",
                $user_id,
                $course_id,
                $lesson_id
            )
        );

        if ($existing) {
            $new_watched = max((int)$existing->watched_seconds, $watched_seconds);

            $data = [
                'watched_seconds' => $new_watched,
                'updated_at'      => $now,
            ];

            // Keep completion immutable once the lesson is marked as complete.
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
                 WHERE user_id = %d
                   AND lesson_id = %d
                 LIMIT 1",
                $user_id,
                $lesson_id
            )
        );
    }

    public static function get_course_progress_percent(int $user_id, int $course_id): int
    {
        $summary = self::get_course_progress_summary($user_id, $course_id);
        return (int) ($summary['percent'] ?? 0);
    }

    public static function get_course_progress_summary(int $user_id, int $course_id): array
    {
        $user_id = (int) $user_id;
        $course_id = (int) $course_id;

        if ($user_id <= 0 || $course_id <= 0) {
            return [
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
            ];
        }

        $lessons = class_exists('PRESS_LMS_Helpers')
            ? PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish'])
            : [];

        $lesson_ids = array_map('intval', wp_list_pluck($lessons, 'ID'));
        $total_lessons = count($lesson_ids);

        if ($total_lessons <= 0) {
            return [
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
            ];
        }

        $completed_ids = self::get_completed_lesson_ids_for_course($user_id, $course_id);
        $completed_lessons = count(array_intersect($lesson_ids, $completed_ids));
        $percent = (int) round(($completed_lessons / $total_lessons) * 100);

        return [
            'completed' => $completed_lessons,
            'total' => $total_lessons,
            'percent' => $percent,
        ];
    }

    public static function get_next_lesson_for_user(int $user_id, int $course_id)
    {
        $user_id = (int) $user_id;
        $course_id = (int) $course_id;

        if ($user_id <= 0 || $course_id <= 0 || !class_exists('PRESS_LMS_Helpers')) {
            return null;
        }

        $lessons = PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish']);
        if (empty($lessons)) {
            return null;
        }

        $completed_ids = self::get_completed_lesson_ids_for_course($user_id, $course_id);

        foreach ($lessons as $lesson) {
            if (!$lesson instanceof WP_Post) {
                continue;
            }

            if (!in_array((int) $lesson->ID, $completed_ids, true)) {
                return $lesson;
            }
        }

        return $lessons[0] instanceof WP_Post ? $lessons[0] : null;
    }
}
