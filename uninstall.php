<?php
/**
 * Pressplay LMS - Uninstall
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Lê settings do plugin (sem depender de classes do plugin carregadas)
 */
$settings = get_option('press_lms_settings', []);
if (!is_array($settings)) $settings = [];

$delete_all = isset($settings['delete_data_on_uninstall']) && $settings['delete_data_on_uninstall'] === 'yes';

/**
 * Sempre remover settings do plugin (não é "dados do aluno", é config)
 * Obs: se você quiser manter settings mesmo após uninstall, comente essa linha.
 */
delete_option('press_lms_settings');

/**
 * Sempre remover backups do Woo (são lixo do plugin)
 */
delete_option('press_lms_backup_guest_checkout');
delete_option('press_lms_backup_myaccount_registration');
delete_option('press_lms_backup_generate_password');
delete_option('press_lms_backup_signup_and_login_from_checkout');
delete_option('press_lms_backup_checkout_registration');

/**
 * Se NÃO for apagar tudo, para por aqui.
 */
if (!$delete_all) {
    return;
}

/**
 * 1) Remover roles
 */
remove_role('press_student');

/**
 * 2) Apagar CPTs (ajuste se você já renomeou)
 * Se você mudou prefixo MLB -> PRESS, coloque os CPTs reais aqui.
 */
$post_types_to_delete = [
    'mlb_course',
    'mlb_lesson',
    // 'press_course',
    // 'press_lesson',
];

foreach ($post_types_to_delete as $pt) {
    $ids = get_posts([
        'post_type'      => $pt,
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }
}

/**
 * 3) Drop tables custom
 * baseado no seu Database.php
 */
$tables = [
    $wpdb->prefix . 'press_students',
    $wpdb->prefix . 'press_enrollments',
    $wpdb->prefix . 'press_progress',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

/**
 * 4) Limpar rewrite
 */
flush_rewrite_rules();