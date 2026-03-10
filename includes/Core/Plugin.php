<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Plugin
{
    public static function register(): void
    {
        register_activation_hook(PRESS_LMS_FILE, ['PRESS_LMS_Activator', 'activate']);
        register_deactivation_hook(PRESS_LMS_FILE, ['PRESS_LMS_Deactivator', 'deactivate']);

        add_action('plugins_loaded', [__CLASS__, 'boot']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_app_assets'], 20);
    }

    public static function boot(): void
    {
        PRESSLMS_Assets::init();
        PRESS_LMS_Dependencies::init();
        PRESS_LMS_Settings::init();
        PRESS_LMS_Roles::init();
        PRESS_LMS_Rewrite::init();
        PRESS_LMS_Frontend::init();
        PRESS_LMS_CPT::init();
        PRESSLMS_Teacher_CPT::init();
        PRESS_LMS_Course_Meta::init();
        PRESS_LMS_Lesson_Meta::init();
        PRESS_LMS_Woo::init();
        PRESS_LMS_Templates::init();
        PRESS_LMS_Actions::init();
        PRESS_LMS_Teacher_Meta::init();

        if (class_exists('PRESS_LMS_Vimeo') && method_exists('PRESS_LMS_Vimeo', 'init')) {
            PRESS_LMS_Vimeo::init();
        }

        PRESS_LMS_Certificate::init();
    }

    public static function enqueue_admin_assets(): void
    {
        wp_enqueue_style('press-lms-admin', PRESS_LMS_URL . 'assets/css/admin.css', [], PRESS_LMS_VERSION);
    }

    public static function enqueue_app_assets(): void
    {
        wp_enqueue_style('press-lms-app', PRESS_LMS_URL . 'assets/css/app.css', [], PRESS_LMS_VERSION);
    }
}
