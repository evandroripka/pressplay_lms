<?php
/** Lifecycle contract tests using only in-memory doubles; no WordPress database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
define('WP_UNINSTALL_PLUGIN', 'pressplay-lms.php');
$checks = 0;
$options = [];
$queried_types = [];
$deleted_posts = [];
$deleted_roles = [];
$events = [];
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
    echo "PASS: $label\n";
}
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['options'][$name] = $value; }
function delete_option($name) { unset($GLOBALS['options'][$name]); }
function remove_role($name) { $GLOBALS['deleted_roles'][] = $name; }
function wp_clear_scheduled_hook($name) { $GLOBALS['events'][] = $name; }
function flush_rewrite_rules() { $GLOBALS['events'][] = 'flush'; }
function get_post_stati() { return ['publish','draft','private','future','pending','trash','auto-draft']; }
function get_posts($args) { $GLOBALS['queried_types'][] = $args; return [count($GLOBALS['queried_types'])]; }
function wp_delete_post($id, $force) { $GLOBALS['deleted_posts'][] = $id; return (object) ['ID'=>$id]; }
class PRESS_LMS_Database { static function migrate() { $GLOBALS['events'][] = 'migrate'; } }
class PRESS_LMS_Roles { static function add_roles() { $GLOBALS['events'][] = 'roles'; } }
class PRESS_LMS_CPT {
    static function register_course() { $GLOBALS['events'][] = 'course'; }
    static function register_lesson() { $GLOBALS['events'][] = 'lesson'; }
}
class PRESS_LMS_Rewrite {
    static function add_rules() { $GLOBALS['events'][] = 'rewrites'; }
    static function get_schema_version() { return 'qa-schema'; }
}
class PRESS_LMS_Woo { static function register_account_endpoint() { $GLOBALS['events'][] = 'account'; } }
$wpdb = new class {
    public $prefix = 'qa_', $sql = [];
    function query($sql) { $this->sql[] = $sql; }
};
require dirname(__DIR__) . '/includes/Core/Activator.php';
require dirname(__DIR__) . '/includes/Core/Deactivator.php';
$options = [
    'users_can_register'=>0, 'default_role'=>'subscriber',
    'woocommerce_enable_guest_checkout'=>'yes', 'woocommerce_enable_myaccount_registration'=>'no',
];
$before = $options;
PRESS_LMS_Activator::activate();
check(array_diff_assoc($before,$options) === [] && count($options) === count($before)+1, 'activation leaves store-wide account preferences untouched');
check($events === ['migrate','roles','course','lesson','rewrites','account','flush'], 'activation prepares schema and routes before flushing');
check(get_option('press_lms_rewrite_schema_version') === 'qa-schema', 'activation records route schema');
$options['press_lms_backup_default_role'] = 'subscriber';
$options['default_role'] = 'customer';
$options['press_lms_backup_users_can_register'] = 0;
$options['users_can_register'] = 1;
$options['press_lms_backup_myaccount_registration'] = false;
$options['woocommerce_enable_myaccount_registration'] = 'yes';
PRESS_LMS_Deactivator::deactivate();
check(get_option('default_role') === 'customer', 'deactivation preserves a newer owner choice');
check(get_option('users_can_register') === 0, 'deactivation restores unchanged legacy overrides');
check(!isset($options['woocommerce_enable_myaccount_registration']), 'deactivation restores an originally absent option');
check(!isset($options['press_lms_backup_default_role']), 'obsolete activation backup removed');
check(in_array('press_lms_daily_lifecycle_events',$events,true), 'deactivation clears lifecycle schedule');
$options = ['press_lms_settings'=>['delete_data_on_uninstall'=>'no','brand_name'=>'QA']];
$before = $options;
require dirname(__DIR__) . '/uninstall.php';
check($options === $before && !$queried_types && !$wpdb->sql, 'retain-data uninstall keeps settings and all records');
$options['press_lms_settings']['delete_data_on_uninstall'] = 'yes';
$options['default_role'] = 'press_student';
require dirname(__DIR__) . '/uninstall.php';
check(!isset($options['press_lms_settings']), 'opted-in uninstall removes plugin configuration');
check(array_column($queried_types,'post_type') === ['press_course','press_lesson','press_teacher','mlb_course','mlb_lesson'], 'cleanup targets current and legacy LMS post types only');
check(in_array('trash',$queried_types[0]['post_status'],true) && $queried_types[0]['numberposts'] === 100, 'cleanup includes trash and uses bounded batches');
check(count($deleted_posts) === 5 && count($wpdb->sql) === 4, 'opted-in cleanup removes plugin posts and four owned tables');
check(get_option('default_role') === 'subscriber' && $deleted_roles === ['press_student'], 'removed student role is not left as the default');
echo "\n$checks assertions passed. No persistent writes.\n";
