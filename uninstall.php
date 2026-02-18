<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$opts = get_option('mlb_lms_settings', []);
$allow_cleanup = !empty($opts['danger_allow_uninstall_cleanup']);

delete_option('mlb_lms_settings');

if (!$allow_cleanup) {
    return;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'mlb_students',
    $wpdb->prefix . 'mlb_enrollments',
    $wpdb->prefix . 'mlb_progress',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}
