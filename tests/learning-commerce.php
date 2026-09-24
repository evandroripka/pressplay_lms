<?php
/** Isolated access, curriculum ordering and contract acceptance checks. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
$checks = 0;
$posts = $meta = $notices = [];
$editable = true;
function check($ok, $label) { if (!$ok) throw new RuntimeException($label); $GLOBALS['checks']++; echo "PASS: $label\n"; }
function rejects(callable $run) { try { $run(); } catch (Exception $e) { return true; } return false; }
class WP_Post {
    public $ID, $post_parent, $post_status='publish', $post_type='press_lesson', $post_password='', $menu_order=0, $post_title='';
    function __construct($id, $parent) { $this->ID=$id; $this->post_parent=$parent; }
}
class RouteException extends Exception { function __construct($code,$message,$status) { parent::__construct($message,$status); } }
class_alias(RouteException::class, 'Automattic\WooCommerce\StoreApi\Exceptions\RouteException');
class WP_Error { public $errors=[]; function add($key,$message) { $this->errors[$key]=$message; } }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_type($id) { return get_post($id)->post_type ?? ''; }
function get_the_title($id) { return get_post($id)->post_title ?? ''; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function current_user_can(...$args) { return $GLOBALS['editable']; }
function wp_update_post($data) { foreach ($data as $key=>$value) $GLOBALS['posts'][$data['ID']]->$key=$value; }
function wp_unslash($v) { return $v; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_kses_post($s) { return strip_tags($s,'<p><strong>'); }
function sanitize_text_field($s) { return strip_tags($s); }
function wc_add_notice($s,$type) { $GLOBALS['notices'][]=$s; }
function get_posts($args) {
    return array_values(array_filter($GLOBALS['posts'],static function($p) use($args) {
        $parent = isset($args['post_parent']) ? $p->post_parent : get_post_meta($p->ID,'_press_lesson_course_id');
        return $p->post_type==='press_lesson' && in_array($p->post_status,$args['post_status'],true) && (int)$parent===(int)($args['post_parent'] ?? $args['meta_value']);
    }));
}
class PRESS_LMS_Woo { static function get_course_id_from_product_id($id) { return [201=>100,202=>101][$id] ?? 0; } }
class FakeSession {
    public $values=[];
    function get($key,$default=[]) { return $this->values[$key] ?? $default; }
    function set($key,$value) { $this->values[$key]=$value; }
    function get_customer_id() { return 'session-A'; }
}
class FakeCart { function get_cart() { return [['product_id'=>201],['product_id'=>202]]; } }
$wc=(object)['session'=>new FakeSession(),'cart'=>new FakeCart()];
function WC() { return $GLOBALS['wc']; }
class FakeOrder {
    public $meta=[], $saves=0;
    function get_meta($key,$single=true) { return $this->meta[$key] ?? []; }
    function update_meta_data($key,$value) { $this->meta[$key]=$value; }
    function save_meta_data() { $this->saves++; }
    function get_customer_id() { return 7; }
    function get_billing_email() { return 'qa@example.test'; }
    function get_items() { return [new class { function get_product_id() { return 201; } },new class { function get_product_id() { return 202; } }]; }
}
foreach (['Support/Helpers','Metabox_Course','Terms'] as $file) require dirname(__DIR__) . '/includes/' . $file . '.php';
$posts[100]=new WP_Post(100,0); $posts[100]->post_type='press_course'; $posts[100]->post_title='Curso A';
$posts[101]=new WP_Post(101,0); $posts[101]->post_type='press_course'; $posts[101]->post_title='Curso B';
foreach ([12,10,11] as $id) $posts[$id]=new WP_Post($id,100);
$posts[10]->post_title='Z'; $posts[11]->post_title='A';
check(array_column(PRESS_LMS_Helpers::get_course_lessons(100),'ID')===[10,11,12], 'unset lesson positions use registration order, not titles');
$posts[12]->menu_order=1;
check(array_column(PRESS_LMS_Helpers::get_course_lessons(100),'ID')===[12,10,11], 'explicit positions precede automatic lessons');
$posts[11]->menu_order=1;
check(array_column(PRESS_LMS_Helpers::get_course_lessons(100),'ID')===[11,12,10], 'equal explicit positions use deterministic registration order');
$posts[20]=new WP_Post(20,101); $meta[20]['_press_lesson_course_id']=100;
check(count(PRESS_LMS_Helpers::get_course_lessons(100))===3,'stale legacy metadata cannot link a lesson from another course');
PRESS_LMS_Course_Meta::save_lesson_order(100,[10=>2,11=>1,12=>0,20=>4]);
check($posts[10]->menu_order===2 && $posts[20]->menu_order===0,'curriculum saves ignore foreign lesson IDs');
$editable=false;
PRESS_LMS_Course_Meta::save_lesson_order(100,[10=>5]);
check($posts[10]->menu_order===2,'curriculum save requires edit permission');
$editable=true;
check(!PRESS_LMS_Helpers::is_sample_lesson(10,100),'lessons are protected by default');
$meta[10]['_press_lesson_free_preview']='yes';
check(PRESS_LMS_Helpers::is_sample_lesson(10,100),'published sample can be public without an enrollment');
check(!PRESS_LMS_Helpers::is_sample_lesson(10,101),'sample never belongs to an unrelated course');
foreach (['draft','private','pending','future','trash'] as $status) {
    $posts[10]->post_status=$status;
    check(!PRESS_LMS_Helpers::is_sample_lesson(10,100), "sample flag cannot expose $status lessons");
}
$posts[10]->post_status='publish'; $posts[100]->post_status='draft';
check(!PRESS_LMS_Helpers::is_sample_lesson(10,100),'sample cannot expose an unpublished course');
$posts[100]->post_status='publish'; $posts[10]->post_password='secret';
check(!PRESS_LMS_Helpers::is_sample_lesson(10,100),'sample cannot bypass lesson password');
$posts[10]->post_password=''; $posts[100]->post_password='secret';
check(!PRESS_LMS_Helpers::is_sample_lesson(10,100),'sample cannot bypass course password');
$posts[100]->post_password='';
check(PRESS_LMS_Terms::get_contract(100)===[], 'empty contracts do not require acceptance');
$meta[100][PRESS_LMS_Terms::META]='<p>Contrato A</p>';
$meta[101][PRESS_LMS_Terms::META]='<p>Contrato B</p>';
$contracts=PRESS_LMS_Terms::contracts_for_products([201,202,201,999]);
check(count($contracts)===2,'each course in mixed carts has one contract');
check(count(PRESS_LMS_Terms::missing_acceptances($contracts,[]))===2,'missing acceptance rejected for every course');
$accepted=[100=>$contracts[100]['version'],101=>$contracts[101]['version']];
check(PRESS_LMS_Terms::missing_acceptances($contracts,$accepted)===[], 'exact accepted versions pass');
$bad=$accepted; $bad[100]=['injection'];
check(count(PRESS_LMS_Terms::missing_acceptances($contracts,$bad))===1, 'malformed acceptance rejected');
$meta[100][PRESS_LMS_Terms::META]='<p>Contrato A revisado</p>';
$new=PRESS_LMS_Terms::contracts_for_products([201,202]);
check(count(PRESS_LMS_Terms::missing_acceptances($new,$accepted))===1, 'stale contract version requires new consent');
$order=new FakeOrder(); $_POST=[];
check(rejects(static fn()=>PRESS_LMS_Terms::save_checkout($order)), 'classic checkout cannot create accepted snapshot without consent');
$errors=new WP_Error(); PRESS_LMS_Terms::validate_checkout([],$errors);
check(count($errors->errors)===2, 'classic checkout reports consent errors before payment');
$_POST['press_lms_terms']=[100=>$new[100]['version'],101=>$new[101]['version']];
$_SERVER['REMOTE_ADDR']='192.0.2.1'; $_SERVER['HTTP_USER_AGENT']='QA';
PRESS_LMS_Terms::save_checkout($order);
$record=$order->meta[PRESS_LMS_Terms::ORDER_META];
check(count($record)===2 && reset($record)['customer_id']===7 && reset($record)['ip']==='192.0.2.1', 'order stores full contract, actor and server-generated evidence');
$meta[100][PRESS_LMS_Terms::META]='<p>Terceira versao</p>';
PRESS_LMS_Terms::snapshot($order,$new,'classic-checkout');
check($record===$order->meta[PRESS_LMS_Terms::ORDER_META], 'retries and later edits cannot overwrite prior snapshots');
$_POST=[]; PRESS_LMS_Terms::save_order_pay($order);
check(!$notices, 'payment retry preserves the originally accepted contract');
$unaccepted=new FakeOrder(); PRESS_LMS_Terms::save_order_pay($unaccepted);
check(count($notices)===1 && !$unaccepted->meta, 'order-pay rejects missing acceptance without an uncaught exception');
check(rejects(static fn()=>PRESS_LMS_Terms::save_blocks(new FakeOrder())), 'direct Store API checkout cannot bypass consent');
$current=PRESS_LMS_Terms::contracts_for_products([201,202]);
$session=[]; foreach ($current as $id=>$contract) $session[$id]=['version'=>$contract['version'],'time'=>time()];
$wc->session->set(PRESS_LMS_Terms::SESSION_KEY,$session);
$block_order=new FakeOrder(); PRESS_LMS_Terms::save_blocks($block_order);
check($block_order->saves===1 && count($block_order->meta[PRESS_LMS_Terms::ORDER_META])===2,'Store API snapshots accepted contracts through HPOS-safe CRUD');
$session[100]['time']=time()-DAY_IN_SECONDS-1; $wc->session->set(PRESS_LMS_Terms::SESSION_KEY,$session);
check(rejects(static fn()=>PRESS_LMS_Terms::save_blocks(new FakeOrder())), 'expired session acceptance cannot authorize checkout');
PRESS_LMS_Terms::clear_session();
check($wc->session->get(PRESS_LMS_Terms::SESSION_KEY)===[], 'emptying cart clears session consents');
class JsonReply extends RuntimeException { public $success; function __construct($ok,$status) { $this->success=$ok; parent::__construct('JSON',$status); } }
function wp_send_json_error($data=[],$status=400) { throw new JsonReply(false,$status); }
function wp_send_json_success($data=[],$status=200) { throw new JsonReply(true,$status); }
function wp_verify_nonce($nonce,$action) { return $nonce === hash('sha256',$action); }
function absint($n) { return abs((int)$n); }
function reply(callable $fn) { try { $fn(); } catch (JsonReply $e) { return $e; } throw new RuntimeException('No JSON response'); }
$_SERVER['REQUEST_METHOD']='GET';
check(reply(static fn()=>PRESS_LMS_Terms::accept_session())->getCode()===400,'consent endpoint rejects GET');
$_SERVER['REQUEST_METHOD']='POST'; $_POST=['nonce'=>'invalid'];
check(reply(static fn()=>PRESS_LMS_Terms::accept_session())->getCode()===403,'consent endpoint requires session-bound nonce');
$_POST=['nonce'=>hash('sha256','press_lms_terms_'.hash('sha256','session-A')),'course_id'=>100,'version'=>'old','accepted'=>'1'];
check(reply(static fn()=>PRESS_LMS_Terms::accept_session())->getCode()===409,'consent endpoint rejects stale versions');
$_POST['version']=$current[100]['version'];
check(reply(static fn()=>PRESS_LMS_Terms::accept_session())->success && !empty($wc->session->get(PRESS_LMS_Terms::SESSION_KEY)[100]),'explicit consent is recorded in current cart session');
$_POST['accepted']='0';
reply(static fn()=>PRESS_LMS_Terms::accept_session());
check(empty($wc->session->get(PRESS_LMS_Terms::SESSION_KEY)[100]),'unchecking revokes session acceptance');
$_POST['course_id']=999;
check(reply(static fn()=>PRESS_LMS_Terms::accept_session())->getCode()===409,'consent endpoint rejects courses outside the cart');
require dirname(__DIR__).'/includes/Actions.php';
$logged_in=false;
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_current_user_id() { return $GLOBALS['logged_in'] ? 7 : 0; }
function check_ajax_referer(...$args) { return true; }
function post_password_required($post) { return !empty($post->post_password); }
class PRESS_LMS_Enrollments { static function can_access_course($user,$course) { return false; } }
$_POST=['course_id'=>100,'lesson_id'=>10,'completed'=>1];
check(reply(static fn()=>PRESS_LMS_Actions::ajax_track_progress())->getCode()===401,'anonymous sample viewers cannot save learner progress');
$logged_in=true;
check(reply(static fn()=>PRESS_LMS_Actions::ajax_track_progress())->getCode()===403,'logged-in sample viewers still need enrollment to save progress');
echo "\n$checks assertions passed. No network or persistent writes.\n";
