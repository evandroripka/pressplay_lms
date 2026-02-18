<?php

/**
 * Plugin Name: Pressplay LMS
 * Description: LMS enxuto para cursos (Vimeo), matrícula, progresso e certificado.
 * Version: 0.1.0
 * Author: Evandro Ripka
 */

if (!defined('ABSPATH')) exit;

define('MLB_LMS_VERSION', '0.1.0');
define('MLB_LMS_PATH', plugin_dir_path(__FILE__));
define('MLB_LMS_URL', plugin_dir_url(__FILE__));

require_once MLB_LMS_PATH . 'includes/Dependencies.php';
require_once MLB_LMS_PATH . 'includes/Activator.php';
require_once MLB_LMS_PATH . 'includes/Deactivator.php';
require_once MLB_LMS_PATH . 'includes/Database.php';
require_once MLB_LMS_PATH . 'includes/Roles.php';
require_once MLB_LMS_PATH . 'includes/Rewrite.php';
require_once MLB_LMS_PATH . 'includes/Frontend.php';
require_once MLB_LMS_PATH . 'includes/Mailer.php';
require_once MLB_LMS_PATH . 'includes/Settings.php';
require_once MLB_LMS_PATH . 'includes/Helpers.php';
require_once MLB_LMS_PATH . 'includes/CPT.php';
require_once MLB_LMS_PATH . 'includes/Metabox_Course.php';
require_once MLB_LMS_PATH . 'includes/Metabox_Lesson.php';
require_once MLB_LMS_PATH . 'includes/Woo.php';


register_activation_hook(__FILE__, ['MLB_LMS_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['MLB_LMS_Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    MLB_LMS_Dependencies::init();
    MLB_LMS_Settings::init();
    MLB_LMS_Roles::init();
    MLB_LMS_Rewrite::init();
    MLB_LMS_Frontend::init();
    MLB_LMS_CPT::init();
    MLB_LMS_Course_Meta::init();
    MLB_LMS_Lesson_Meta::init();
    MLB_LMS_Woo::init();

});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('mlb-lms-app', MLB_LMS_URL . 'assets/css/app.css', [], MLB_LMS_VERSION);
});

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('mlb-lms-admin', MLB_LMS_URL . 'assets/css/admin.css', [], MLB_LMS_VERSION);
});
