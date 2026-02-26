<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Activator
{

    public static function activate()
    {

        // ===============================
        // Banco de dados
        // ===============================
        PRESS_LMS_Database::migrate();

        // ===============================
        // Roles
        // ===============================
        PRESS_LMS_Roles::add_roles();

        // ===============================
        // Registrar CPTs antes do flush
        // ===============================
        if (class_exists('PRESS_LMS_CPT')) {
            PRESS_LMS_CPT::register_course();
            PRESS_LMS_CPT::register_lesson();
        }

        // ===============================
        // Rewrite custom
        // ===============================
        if (class_exists('PRESS_LMS_Rewrite')) {
            PRESS_LMS_Rewrite::add_rules();
        }

        // ===============================
        // WordPress: permitir registro público
        // ===============================
        update_option('users_can_register', 1);

        if (get_role('press_student')) {
            update_option('default_role', 'press_student');
        }

        // ===============================
        // WooCommerce Account Settings
        // ===============================
        if (class_exists('WooCommerce')) {

            // Backup das configs atuais (caso queira restaurar no futuro)
            update_option('press_lms_backup_guest_checkout', get_option('woocommerce_enable_guest_checkout'));
            update_option('press_lms_backup_myaccount_registration', get_option('woocommerce_enable_myaccount_registration'));
            update_option('press_lms_backup_generate_password', get_option('woocommerce_registration_generate_password'));
            update_option('press_lms_backup_signup_and_login_from_checkout', get_option('woocommerce_enable_signup_and_login_from_checkout'));
            update_option('press_lms_backup_checkout_registration', get_option('woocommerce_enable_checkout_registration')); // fallback legado

            // ❌ Desabilita checkout de convidado
            update_option('woocommerce_enable_guest_checkout', 'no');

            // ✅ Permitir criar conta durante o checkout (opção atual)
            update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');

            // ✅ Fallback legado (não atrapalha se não for usado)
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
