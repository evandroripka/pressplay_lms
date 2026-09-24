<?php
/**
 * Isolated regression tests. No WordPress bootstrap, database or network access.
 * Run: php tests/regression.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');
$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
    echo "PASS: $label\n";
}
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
function sanitize_title($s) { return $s; }
function wp_kses_no_null($s, $args = []) { return str_replace(chr(0), '', $s); }
function wp_unslash($s) { return stripslashes($s); }
function wp_slash($s) { return addslashes($s); }
function current_time($format) { $time = strtotime('2026-09-23 12:00:00 UTC'); return $format === 'timestamp' ? $time : date('Y-m-d H:i:s', $time); }
function user_can($user, $cap) { return false; }
function current_user_can($cap, ...$args) { return $GLOBALS['can_edit'] ?? false; }
function get_current_user_id() { return 0; }
function is_user_logged_in() { return false; }
function get_userdata($id) { return $GLOBALS['test_user']; }
function post_password_required($post) { return !empty($post->post_password); }
function get_post_meta($id, $key, $single = true) {
    $defaults = ['_press_course_access_type'=>'days', '_press_course_access_value'=>30, '_press_course_id'=>99, '_press_course_product_id'=>100];
    return $GLOBALS['meta'][$id][$key] ?? $defaults[$key] ?? '';
}
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_posts($args) { return $GLOBALS['lessons'] ?? []; }
function get_page_by_path($slug, $output, $type) { return $GLOBALS['posts'][99] ?? null; }
function wp_list_pluck($items, $key) { return array_map(static fn($item) => $item->$key, $items); }
function wc_get_order($id) { return $GLOBALS['order']; }
function wc_get_page_permalink($page) { return 'https://example.test/' . $page; }
function wc_get_checkout_url() { return 'https://example.test/checkout/'; }
function wc_get_cart_url() { return 'https://example.test/cart/'; }
function wc_add_notice($message, $type) {}
function WC() { return $GLOBALS['wc']; }
function wp_safe_redirect($url) { throw new TestRedirect($url); }
class TestRedirect extends RuntimeException {}
class WP_Post {
    public $ID, $post_type, $post_status, $post_parent, $post_name, $post_title, $menu_order, $post_password;
    function __construct($data) { foreach ((array) $data as $key=>$value) $this->$key=$value; }
}
class WooCommerce {}
class PRESS_LMS_Course_Lifecycle {
    public static $paused = false;
    static function is_course_paused($id) { return self::$paused; }
}
class WC_Order {
    public $meta = [], $paid = true;
    function is_paid() { return $this->paid; }
    function get_user_id() { return 77; }
    function get_id() { return 123; }
    function get_payment_method() { return 'test_gateway'; }
    function get_meta($key, $single = true) { return $this->meta[$key] ?? []; }
    function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    function save_meta_data() {}
    function get_items() { return [new class {
        function get_product() { return new class { function get_id() { return 100; } }; }
    }]; }
}
class TestDatabase {
    public $prefix = 'test_', $row, $ids = [], $writes = [], $insert_id = 1, $fail = false;
    function prepare($sql, ...$values) { return $sql; }
    function get_var($sql) { return $this->row ? 1 : null; }
    function get_row($sql) { return $this->row; }
    function get_col($sql) { return $this->ids; }
    function get_results($sql) { return array_map(static fn($id) => (object) ['lesson_id'=>$id,'watched_seconds'=>0,'completed'=>1], $this->ids); }
    function update($table, $data, $where) { $this->writes[] = $data; return $this->fail ? false : 1; }
    function insert($table, $data) { $this->writes[] = $data; return $this->fail ? false : 1; }
}
$wpdb = new TestDatabase();
$wc = (object) ['cart' => null];
$test_user = new class {
    public $roles = ['shop_manager'];
    function add_role($role) { $this->roles[] = $role; }
};
$posts[99] = new WP_Post(['ID'=>99, 'post_type'=>'press_course', 'post_status'=>'publish', 'post_password'=>'']);
$posts[100] = new WP_Post(['ID'=>100, 'post_type'=>'product', 'post_status'=>'publish']);
foreach (['Database', 'Support/Helpers', 'Enrollments', 'Woo', 'Certificate', 'Progress', 'Frontend', 'Actions'] as $file) {
    require dirname(__DIR__) . '/includes/' . $file . '.php';
}

foreach ([3, 5, 6] as $count) {
    $args = array_slice([true, 100, 1, 0, [], []], 0, $count);
    check(PRESS_LMS_Woo::validate_add_to_cart(...$args) === true, "cart validation accepts $count arguments");
}
$css = '.icon:before{content:"\\f007"} </style><script>test</script>';
$safe = PRESS_LMS_Certificate::sanitize_css($css);
check(!str_contains($safe, '<'), 'CSS cannot terminate style element');
check(wp_unslash(wp_slash($safe)) === $safe, 'CSS escapes survive metadata slashing');
check(str_contains($safe, '\\f007'), 'icon escapes are preserved');
check(substr_count(PRESS_LMS_Certificate::get_certificate_styles($css), '</style>') === 1, 'legacy CSS is sanitized on output');

PRESS_LMS_Enrollments::ensure_student_role(77);
check($test_user->roles === ['shop_manager', 'press_student'], 'staff roles preserved');
foreach (['active', 'blocked', 'refunded', 'cancelled', 'failed'] as $state) {
    $wpdb->row = (object) ['status'=>$state, 'expires_at'=>'2026-09-01 12:00:00', 'order_ref'=>'123', 'purchased_at'=>'2026-08-01 12:00:00'];
    $wpdb->writes = [];
    check(!PRESS_LMS_Enrollments::activate_enrollment(77, 99, 123) && !$wpdb->writes, "same paid order cannot reactivate $state access");
}
foreach (['failed', 'cancelled'] as $state) {
    $wpdb->row = (object) ['status'=>$state, 'order_ref'=>'123', 'purchased_at'=>null];
    $wpdb->writes = [];
    check(PRESS_LMS_Enrollments::activate_enrollment(77,99,123) && count($wpdb->writes) === 1, "previously unpaid $state order can be paid later");
}
$wpdb->row = (object) ['status'=>'blocked', 'order_ref'=>'123'];
$wpdb->writes = [];
check(!PRESS_LMS_Enrollments::deactivate_enrollment(77,99,'failed',123) && !$wpdb->writes, 'order status cannot replace a manual block');
check(!PRESS_LMS_Enrollments::activate_enrollment(0,99,123), 'invalid enrollment owner rejected');
$order = new WC_Order();
$wpdb->row = (object) ['status'=>'pending', 'expires_at'=>null, 'order_ref'=>'123'];
$wpdb->writes = [];
$order->paid = false;
PRESS_LMS_Woo::handle_payment_complete(123);
check(!$wpdb->writes, 'unpaid orders never grant course access');
$order->paid = true;
PRESS_LMS_Course_Lifecycle::$paused = true;
PRESS_LMS_Woo::handle_payment_complete(123);
check(count($wpdb->writes) === 1 && $wpdb->writes[0]['status'] === 'active', 'paid order is fulfilled while sales are paused');
PRESS_LMS_Woo::handle_payment_complete(123);
check(count($wpdb->writes) === 1, 'fulfilled order is not delivered twice');
PRESS_LMS_Course_Lifecycle::$paused = false;

foreach (['draft', 'private', 'pending', 'future', 'trash'] as $state) {
    $lesson = new WP_Post(['ID'=>11,'post_type'=>'press_lesson','post_status'=>$state,'post_parent'=>99,'post_name'=>'draft','menu_order'=>0,'post_title'=>'Lesson']);
    $lessons = [$lesson];
    $lookup = new ReflectionMethod(PRESS_LMS_Frontend::class, 'find_lesson_for_course');
    $lookup->setAccessible(true);
    check($lookup->invoke(null, 'draft', 99) === null, "route rejects $state lesson");
}
$posts[99]->post_status = 'draft';
check(PRESS_LMS_Helpers::get_visible_course('draft') === null, 'draft course is not publicly resolved');
$posts[99]->post_status = 'publish';
$posts[99]->post_password = 'secret';
check(PRESS_LMS_Helpers::get_visible_course('protected') === null, 'password-protected course is not exposed');
$posts[99]->post_password = '';

$lessons = [];
foreach (range(1,200) as $id) {
    $lessons[] = new WP_Post(['ID'=>$id,'post_type'=>'press_lesson','post_status'=>'publish','menu_order'=>$id,'post_title'=>'Lesson']);
}
$wpdb->ids = range(1,199);
check(PRESS_LMS_Progress::get_course_progress_percent(77,99) === 99.5, '199 of 200 lessons cannot issue a certificate');
$wpdb->ids = range(1,200);
check(PRESS_LMS_Progress::get_course_progress_percent(77,99) === 100.0, 'all lessons reach 100 percent');
$wpdb->row = null;
$wpdb->fail = true;
check(!PRESS_LMS_Progress::upsert_progress(77,99,1,10,1), 'progress storage failures propagate');
check(PRESS_LMS_Enrollments::get_or_create_pending(77,99) === 0, 'failed pending enrollment insert does not return a stale ID');
$wpdb->fail = false;

$wc = (object) ['cart'=>new class {
    public $items = [['product_id'=>999]], $adds = 0, $fail = false;
    function get_cart() { return $this->items; }
    function add_to_cart($id, $qty) {
        $this->adds++;
        if ($this->fail) return false;
        $this->items[] = ['product_id'=>$id];
        return 'test-key';
    }
}];
$checkout = new ReflectionMethod(PRESS_LMS_Actions::class, 'do_enroll_and_redirect_to_checkout');
$checkout->setAccessible(true);
try { $checkout->invoke(null,0,99); } catch (TestRedirect $e) { check($e->getMessage() === wc_get_checkout_url(), 'guest continues to checkout without a cross-session nonce'); }
check(count($wc->cart->items) === 2 && $wc->cart->items[0]['product_id'] === 999, 'other cart products preserved');
try { $checkout->invoke(null,0,99); } catch (TestRedirect $e) {}
check($wc->cart->adds === 1, 'repeated course CTA does not duplicate cart item');
$wc->cart->items = [['product_id'=>999]];
$wc->cart->fail = true;
try { $checkout->invoke(null,0,99); } catch (TestRedirect $e) { check($e->getMessage() === wc_get_cart_url(), 'failed add returns to cart notices instead of checkout'); }
echo "\n$checks assertions passed. No persistent writes.\n";
