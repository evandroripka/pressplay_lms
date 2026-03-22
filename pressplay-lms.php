<?php

/**
 * Plugin Name:       Pressplay LMS
 * Description:       Sell online courses with WooCommerce-powered enrollments, protected lessons, student dashboards, progress tracking, and certificates.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   10.6.1
 * Author:            Evandro Ripka
 * Author URI:        https://evandroripka.dev/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressplay-lms
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
add_action('before_woocommerce_init', static function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_instance_caching', __FILE__, true);
    }
});

// Define plugin-wide constants used across bootstrap, assets, and templates.
define('PRESS_LMS_VERSION', '1.0.1');
define('PRESS_LMS_FILE', __FILE__);
define('PRESS_LMS_PATH', plugin_dir_path(__FILE__));
define('PRESS_LMS_URL', plugin_dir_url(__FILE__));

// Load shared helpers, infrastructure, and domain modules.
require_once PRESS_LMS_PATH . 'includes/Materials.php';
require_once PRESS_LMS_PATH . 'includes/Core/Dependencies.php';
require_once PRESS_LMS_PATH . 'includes/Core/Activator.php';
require_once PRESS_LMS_PATH . 'includes/Core/Deactivator.php';
require_once PRESS_LMS_PATH . 'includes/Core/Plugin.php';
require_once PRESS_LMS_PATH . 'includes/Database.php';
require_once PRESS_LMS_PATH . 'includes/Roles.php';
require_once PRESS_LMS_PATH . 'includes/Core/Rewrite.php';
require_once PRESS_LMS_PATH . 'includes/Frontend.php';
require_once PRESS_LMS_PATH . 'includes/Menu.php';
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
require_once PRESS_LMS_PATH . 'includes/Course_Lifecycle.php';

// Register lifecycle hooks and boot the plugin.
PRESS_LMS_Plugin::register();
