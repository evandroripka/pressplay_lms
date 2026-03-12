<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Deactivator {
    public static function deactivate() {
        self::restore_option('users_can_register', 'press_lms_backup_users_can_register');
        self::restore_option('default_role', 'press_lms_backup_default_role');
        self::restore_option('woocommerce_enable_guest_checkout', 'press_lms_backup_guest_checkout');
        self::restore_option('woocommerce_enable_myaccount_registration', 'press_lms_backup_myaccount_registration');
        self::restore_option('woocommerce_registration_generate_password', 'press_lms_backup_generate_password');
        self::restore_option('woocommerce_enable_signup_and_login_from_checkout', 'press_lms_backup_signup_and_login_from_checkout');
        self::restore_option('woocommerce_enable_checkout_registration', 'press_lms_backup_checkout_registration');

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('press_lms_daily_lifecycle_events');
        }

        flush_rewrite_rules();
    }

    private static function restore_option(string $target_option, string $backup_option): void
    {
        $missing = '__press_lms_option_missing__';
        $backup_value = get_option($backup_option, $missing);

        if ($backup_value === $missing) {
            return;
        }

        update_option($target_option, $backup_value);
        delete_option($backup_option);
    }
}
