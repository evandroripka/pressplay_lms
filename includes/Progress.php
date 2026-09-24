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
    ): bool {
        global $wpdb;

        if ($user_id <= 0 || $course_id <= 0 || $lesson_id <= 0) return false;

        $table = PRESS_LMS_Database::table('progress');
        $now   = current_time('mysql');
        $should_check_course_completion = (int) $completed === 1 && class_exists('PRESS_LMS_Mailer');
        $was_course_completed = false;

        if ($should_check_course_completion) {
            $was_course_completed = self::get_course_progress_percent($user_id, $course_id) >= 100;
        }

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

            $result = $wpdb->update(
                $table,
                $data,
                ['id' => (int)$existing->id]
            );
        } else {
            $result = $wpdb->insert($table, [
                'user_id'         => $user_id,
                'course_id'       => $course_id,
                'lesson_id'       => $lesson_id,
                'watched_seconds' => max(0, $watched_seconds),
                'completed'       => $completed ? 1 : 0,
                'completed_at'    => $completed ? $now : null,
                'updated_at'      => $now,
            ]);
        }

        if ($result === false) {
            return false;
        }

        if (
            $should_check_course_completion &&
            !$was_course_completed &&
            self::get_course_progress_percent($user_id, $course_id) >= 100
        ) {
            PRESS_LMS_Mailer::maybe_send_course_completed_email($user_id, $course_id);
        }
        return true;
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

    public static function get_course_progress_percent(int $user_id, int $course_id): float
    {
        $summary = self::get_course_progress_summary($user_id, $course_id);
        return (float) ($summary['percent'] ?? 0);
    }

    public static function get_course_progress_summary(int $user_id, int $course_id): array
    {
        global $wpdb;
        $user_id = (int) $user_id;
        $course_id = (int) $course_id;

        if ($user_id <= 0 || $course_id <= 0) {
            return [
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
                'duration_seconds' => 0, 'watched_seconds' => 0, 'duration_complete' => false,
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
                'duration_seconds' => 0, 'watched_seconds' => 0, 'duration_complete' => false,
            ];
        }

        $table = PRESS_LMS_Database::table('progress');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT lesson_id, watched_seconds, completed FROM {$table} WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        ));
        $progress = [];
        foreach ((array) $rows as $row) $progress[(int) $row->lesson_id] = $row;
        $completed_lessons = 0;
        $duration_total = 0;
        $watched_total = 0;
        $lesson_fractions = 0.0;
        $duration_complete = true;
        foreach ($lesson_ids as $lesson_id) {
            $row = $progress[$lesson_id] ?? null;
            $done = !empty($row->completed);
            $duration = max(0, (int) get_post_meta($lesson_id, '_press_lesson_duration', true));
            $watched = $done ? $duration : min($duration, max(0, (int) ($row->watched_seconds ?? 0)));
            $completed_lessons += (int) $done;
            $duration_total += $duration;
            $watched_total += $watched;
            $lesson_fractions += $done ? 1 : ($duration > 0 ? $watched / $duration : 0);
            if ($duration <= 0) $duration_complete = false;
        }
        // Mixed/text-only courses keep a fractional lesson fallback until every duration is known.
        $fraction = $duration_complete && $duration_total > 0
            ? $watched_total / $duration_total
            : $lesson_fractions / $total_lessons;
        $percent = $completed_lessons === $total_lessons
            ? 100.0
            : min(99.99, round($fraction * 100, 2));

        return [
            'completed' => $completed_lessons,
            'total' => $total_lessons,
            'percent' => $percent,
            'duration_seconds' => $duration_total,
            'watched_seconds' => $watched_total,
            'duration_complete' => $duration_complete,
        ];
    }

    /** Merge overlapping intervals so seeking and replay cannot inflate watched time. */
    public static function normalize_ranges(array $ranges, int $duration): array
    {
        $valid = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) !== 2 || !isset($range[0], $range[1]) ||
                !is_numeric($range[0]) || !is_numeric($range[1])) continue;
            $start = max(0, min($duration, (float) $range[0]));
            $end = max(0, min($duration, (float) $range[1]));
            if (is_finite($start) && is_finite($end) && $end > $start) $valid[] = [$start, $end];
        }
        usort($valid, static fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($valid as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);
            } else {
                $merged[] = $range;
            }
        }
        return $merged;
    }

    public static function get_watch_state(int $user_id, int $lesson_id): array
    {
        $state = get_user_meta($user_id, 'press_lms_watch_' . $lesson_id, true);
        $source = (string) get_post_meta($lesson_id, '_press_lesson_video_url', true);
        return is_array($state) && ($state['source'] ?? $source) === $source ? $state : [];
    }

    public static function record_video_progress(int $user_id, int $course_id, int $lesson_id, array $ranges, float $position)
    {
        $duration = (int) get_post_meta($lesson_id, '_press_lesson_duration', true);
        if ($duration <= 0) return new WP_Error('duration_unknown', 'Duracao indisponivel. Tente novamente em instantes.');
        $previous = self::get_watch_state($user_id, $lesson_id);
        $source = (string) get_post_meta($lesson_id, '_press_lesson_video_url', true);
        $old_ranges = ($previous['source'] ?? $source) === $source ? (array) ($previous['ranges'] ?? []) : [];
        $ranges = self::normalize_ranges(array_merge($old_ranges, $ranges), $duration);
        if (count($ranges) > 2000) return new WP_Error('too_many_ranges', 'Limite de trechos atingido. Contate o suporte.');
        $watched = (int) floor(array_sum(array_map(static fn($r) => $r[1] - $r[0], $ranges)));
        $state = ['ranges' => $ranges, 'position' => max(0, min($duration, $position)), 'source' => $source];
        if ($state !== $previous && !update_user_meta($user_id, 'press_lms_watch_' . $lesson_id, $state)) {
            return new WP_Error('watch_storage_failed', 'Nao foi possivel salvar os trechos assistidos.');
        }
        // One second accommodates rounding differences between oEmbed and the player.
        $completed = $watched >= max(1, $duration - 1) ? 1 : 0;
        if (!self::upsert_progress($user_id, $course_id, $lesson_id, $watched, $completed)) {
            return new WP_Error('progress_storage_failed', 'Nao foi possivel salvar o progresso.');
        }
        return true;
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
