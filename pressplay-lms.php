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

// ✅ Constantes padrão
define('PRESS_LMS_VERSION', '1.0.0');
define('PRESS_LMS_FILE', __FILE__);
define('PRESS_LMS_PATH', plugin_dir_path(__FILE__));
define('PRESS_LMS_URL', plugin_dir_url(__FILE__));

// Includes
require_once PRESS_LMS_PATH . 'includes/Materials.php';
require_once PRESS_LMS_PATH . 'includes/Core/Dependencies.php';
require_once PRESS_LMS_PATH . 'includes/Core/Activator.php';
require_once PRESS_LMS_PATH . 'includes/Core/Deactivator.php';
require_once PRESS_LMS_PATH . 'includes/Core/Plugin.php';
require_once PRESS_LMS_PATH . 'includes/Database.php';
require_once PRESS_LMS_PATH . 'includes/Roles.php';
require_once PRESS_LMS_PATH . 'includes/Core/Rewrite.php';
require_once PRESS_LMS_PATH . 'includes/Frontend.php';
require_once PRESS_LMS_PATH . 'includes/Mailer.php';
require_once PRESS_LMS_PATH . 'includes/Settings.php';
require_once PRESS_LMS_PATH . 'includes/Support/Helpers.php';
require_once PRESS_LMS_PATH . 'includes/CPT.php';
require_once PRESS_LMS_PATH . 'includes/CPT_Teacher.php';
require_once PRESS_LMS_PATH . 'includes/Metabox_Course.php';
require_once PRESS_LMS_PATH . 'includes/Metabox_Lesson.php';
require_once PRESS_LMS_PATH . 'includes/Woo.php';
require_once PRESS_LMS_PATH . 'includes/Core/Templates.php';
require_once PRESS_LMS_PATH . 'includes/Enrollments.php';
require_once PRESS_LMS_PATH . 'includes/Actions.php';
require_once PRESS_LMS_PATH . 'includes/Vimeo.php';
require_once PRESS_LMS_PATH . 'includes/Core/Assets.php';
require_once PRESS_LMS_PATH . 'includes/Duration.php';
require_once PRESS_LMS_PATH . 'includes/Metabox_Teacher.php';
require_once PRESS_LMS_PATH . 'includes/Progress.php';
require_once PRESS_LMS_PATH . 'includes/Certificate.php';

PRESS_LMS_Plugin::register();
