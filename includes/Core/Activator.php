<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Activator
{
    private static function backup_option_once(string $backup_option, string $target_option): void
    {
        if (get_option($backup_option, null) !== null) {
            return;
        }

        add_option($backup_option, get_option($target_option), '', false);
    }

    public static function activate()
    {
        // Create or update custom database tables.
        PRESS_LMS_Database::migrate();

        // Register plugin roles before setting defaults.
        PRESS_LMS_Roles::add_roles();

        // Register post types before flushing rewrite rules.
        if (class_exists('PRESS_LMS_CPT')) {
            PRESS_LMS_CPT::register_course();
            PRESS_LMS_CPT::register_lesson();
        }

        // Register custom rewrite rules used by the LMS frontend.
        if (class_exists('PRESS_LMS_Rewrite')) {
            PRESS_LMS_Rewrite::add_rules();
        }

        if (class_exists('PRESS_LMS_Woo') && method_exists('PRESS_LMS_Woo', 'register_account_endpoint')) {
            PRESS_LMS_Woo::register_account_endpoint();
        }

        self::backup_option_once('press_lms_backup_users_can_register', 'users_can_register');
        self::backup_option_once('press_lms_backup_default_role', 'default_role');

        // Enable public registration for the student flow.
        update_option('users_can_register', 1);

        if (get_role('press_student')) {
            update_option('default_role', 'press_student');
        }

        // Align WooCommerce account settings with the LMS enrollment flow.
        if (class_exists('WooCommerce')) {
            // Preserve the current WooCommerce settings so they can be restored later.
            self::backup_option_once('press_lms_backup_guest_checkout', 'woocommerce_enable_guest_checkout');
            self::backup_option_once('press_lms_backup_myaccount_registration', 'woocommerce_enable_myaccount_registration');
            self::backup_option_once('press_lms_backup_generate_password', 'woocommerce_registration_generate_password');
            self::backup_option_once('press_lms_backup_signup_and_login_from_checkout', 'woocommerce_enable_signup_and_login_from_checkout');
            self::backup_option_once('press_lms_backup_checkout_registration', 'woocommerce_enable_checkout_registration');

            // Force account creation to keep enrollments linked to a user account.
            update_option('woocommerce_enable_guest_checkout', 'no');

            // Allow account creation during checkout.
            update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');

            // Keep the legacy option enabled for older WooCommerce setups.
            update_option('woocommerce_enable_checkout_registration', 'yes');

            // Allow registration from My Account as well.
            update_option('woocommerce_enable_myaccount_registration', 'yes');

            // Use the standard password setup email flow.
            update_option('woocommerce_registration_generate_password', 'yes');
        }

        // Refresh rewrite rules once activation is complete.
        flush_rewrite_rules();

        if (class_exists('PRESS_LMS_Rewrite') && method_exists('PRESS_LMS_Rewrite', 'get_schema_version')) {
            update_option('press_lms_rewrite_schema_version', PRESS_LMS_Rewrite::get_schema_version(), false);
        }
    }
}
