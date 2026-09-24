<?php
/** Remove plugin-owned data only when the site owner explicitly opts in. */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$settings = get_option('press_lms_settings', []);
if (!is_array($settings)) $settings = [];

$delete_all = isset($settings['delete_data_on_uninstall']) && $settings['delete_data_on_uninstall'] === 'yes';

// Retaining data includes branding, CSS and API configuration for reinstalls.
if (!$delete_all) {
    return;
}

foreach ([
    'press_lms_settings',
    'press_lms_rewrite_schema_version',
    'press_lms_backup_users_can_register',
    'press_lms_backup_default_role',
    'press_lms_backup_guest_checkout',
    'press_lms_backup_myaccount_registration',
    'press_lms_backup_generate_password',
    'press_lms_backup_signup_and_login_from_checkout',
    'press_lms_backup_checkout_registration',
] as $option) {
    delete_option($option);
}

wp_clear_scheduled_hook('press_lms_daily_lifecycle_events');
if (get_option('default_role') === 'press_student') {
    update_option('default_role', 'subscriber');
}
remove_role('press_student');

// Include pre-rename types, but never remove WooCommerce products or orders.
$post_types_to_delete = [
    'press_course',
    'press_lesson',
    'press_teacher',
    'mlb_course',
    'mlb_lesson',
];

foreach ($post_types_to_delete as $pt) {
    do {
        $ids = get_posts([
            'post_type'     => $pt,
            'post_status'   => array_values(get_post_stati()),
            'numberposts'   => 100,
            'fields'        => 'ids',
            'orderby'       => 'ID',
            'order'         => 'ASC',
            'no_found_rows' => true,
        ]);
        $deleted = 0;
        foreach ($ids as $id) {
            if (wp_delete_post($id, true)) {
                $deleted++;
            }
        }
        // Stop if another plugin vetoes every deletion in a batch.
    } while (count($ids) === 100 && $deleted > 0);
}

$tables = [
    $wpdb->prefix . 'press_students',
    $wpdb->prefix . 'press_enrollments',
    $wpdb->prefix . 'press_progress',
    $wpdb->prefix . 'press_contato',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

flush_rewrite_rules();
