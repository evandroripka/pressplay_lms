<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Activator {

    public static function activate() {

        // ===============================
        // Banco de dados
        // ===============================
        MLB_LMS_Database::migrate();

        // ===============================
        // Roles
        // ===============================
        MLB_LMS_Roles::add_roles();

        // ===============================
        // Registrar CPTs antes do flush
        // ===============================
        if (class_exists('MLB_LMS_CPT')) {
            MLB_LMS_CPT::register_course();
            MLB_LMS_CPT::register_lesson();
        }

        // ===============================
        // Rewrite custom
        // ===============================
        if (class_exists('MLB_LMS_Rewrite')) {
            MLB_LMS_Rewrite::add_rules();
        }

        // ===============================
        // WordPress: permitir registro público
        // ===============================
        update_option('users_can_register', 1);

        if (get_role('malibu_student')) {
            update_option('default_role', 'malibu_student');
        }

        // ===============================
        // WooCommerce Account Settings
        // ===============================
        if (class_exists('WooCommerce')) {

            // Backup das configs atuais (caso queira restaurar no futuro)
            update_option('mlb_lms_backup_guest_checkout', get_option('woocommerce_enable_guest_checkout'));
            update_option('mlb_lms_backup_checkout_account_creation', get_option('woocommerce_enable_checkout_login_reminder'));
            update_option('mlb_lms_backup_myaccount_registration', get_option('woocommerce_enable_myaccount_registration'));
            update_option('mlb_lms_backup_checkout_registration', get_option('woocommerce_enable_checkout_registration'));

            // ❌ Desabilita checkout de convidado
            update_option('woocommerce_enable_guest_checkout', 'no');

            // ✅ Permitir criar conta durante o checkout
            update_option('woocommerce_enable_checkout_registration', 'yes');

            // ✅ Permitir criar conta na página Minha Conta
            update_option('woocommerce_enable_myaccount_registration', 'yes');

            // ✅ Enviar link de configuração de senha
            update_option('woocommerce_registration_generate_password', 'yes');
        }

        // Flush final
        flush_rewrite_rules();
    }
}
