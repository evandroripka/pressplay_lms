<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Database {
    public static function migrate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'mlb_';

        $sql_students = "CREATE TABLE {$prefix}students (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            full_name VARCHAR(190) NOT NULL,
            phone_raw VARCHAR(50) NOT NULL,
            phone_e164 VARCHAR(25) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) {$charset};";

        $sql_enrollments = "CREATE TABLE {$prefix}enrollments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            purchased_at DATETIME NULL,
            expires_at DATETIME NULL,
            payment_provider VARCHAR(40) NULL,
            order_ref VARCHAR(120) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_course (user_id, course_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) {$charset};";

        $sql_progress = "CREATE TABLE {$prefix}progress (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            lesson_id BIGINT UNSIGNED NOT NULL,
            watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            completed TINYINT(1) NOT NULL DEFAULT 0,
            completed_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq (user_id, lesson_id),
            KEY course_user (course_id, user_id)
        ) {$charset};";

        dbDelta($sql_students);
        dbDelta($sql_enrollments);
        dbDelta($sql_progress);
    }

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'mlb_' . $name;
    }
}
