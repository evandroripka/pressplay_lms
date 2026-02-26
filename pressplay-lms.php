<?php
/**
 * Plugin Name: Pressplay LMS
 * Description: LMS enxuto para cursos (Vimeo), matrícula, progresso e certificado.
 * Version: 1.0.0
 * Author: Evandro Ripkas
 * Author URI: https://evandroripka.dev
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: pressplay-lms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// ✅ Constantes padrão (sem repetição)
define('PRESS_LMS_VERSION', '1.0.0');
define('PRESS_LMS_FILE', __FILE__);
define('PRESS_LMS_PATH', plugin_dir_path(__FILE__));
define('PRESS_LMS_URL', plugin_dir_url(__FILE__));

// Includes
require_once PRESS_LMS_PATH . 'includes/Materials.php';
require_once PRESS_LMS_PATH . 'includes/Dependencies.php';
require_once PRESS_LMS_PATH . 'includes/Activator.php';
require_once PRESS_LMS_PATH . 'includes/Deactivator.php';
require_once PRESS_LMS_PATH . 'includes/Database.php';
require_once PRESS_LMS_PATH . 'includes/Roles.php';
require_once PRESS_LMS_PATH . 'includes/Rewrite.php';
require_once PRESS_LMS_PATH . 'includes/Frontend.php';
require_once PRESS_LMS_PATH . 'includes/Mailer.php';
require_once PRESS_LMS_PATH . 'includes/Settings.php';
require_once PRESS_LMS_PATH . 'includes/Helpers.php';
require_once PRESS_LMS_PATH . 'includes/CPT.php';
require_once PRESS_LMS_PATH . 'includes/Metabox_Course.php';
require_once PRESS_LMS_PATH . 'includes/Metabox_Lesson.php';
require_once PRESS_LMS_PATH . 'includes/Woo.php';
require_once PRESS_LMS_PATH . 'includes/Templates.php';
require_once PRESS_LMS_PATH . 'includes/Enrollments.php';
require_once PRESS_LMS_PATH . 'includes/Actions.php';
require_once PRESS_LMS_PATH . 'includes/Vimeo.php';
require_once PRESS_LMS_PATH . 'includes/class-presslms-assets.php';

// Hooks ativação
register_activation_hook(__FILE__, ['PRESS_LMS_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PRESS_LMS_Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
  PRESSLMS_Assets::init();
  PRESS_LMS_Dependencies::init();
  PRESS_LMS_Settings::init();
  PRESS_LMS_Roles::init();
  PRESS_LMS_Rewrite::init();
  PRESS_LMS_Frontend::init();
  PRESS_LMS_CPT::init();
  PRESS_LMS_Course_Meta::init();
  PRESS_LMS_Lesson_Meta::init();
  PRESS_LMS_Woo::init();
  PRESS_LMS_Templates::init();
  PRESS_LMS_Actions::init();

  if (class_exists('PRESS_LMS_Vimeo') && method_exists('PRESS_LMS_Vimeo', 'init')) {
    PRESS_LMS_Vimeo::init();
  }
});

// Admin CSS
add_action('admin_enqueue_scripts', function () {
  wp_enqueue_style('press-lms-admin', PRESS_LMS_URL . 'assets/css/admin.css', [], PRESS_LMS_VERSION);
});

// Front app.css (se você quiser manter global, deixe assim)
// Se quiser isolar só nas páginas do LMS, eu te passo abaixo.
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style('press-lms-app', PRESS_LMS_URL . 'assets/css/app.css', [], PRESS_LMS_VERSION);
}, 20);