<?php
/** Manual grants use in-memory doubles; never create real users or enrollments. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__.'/');
$checks=0; $admin=true; $member=true; $audit=[]; $mail=0;
function check($ok,$label) { if (!$ok) throw new RuntimeException($label); $GLOBALS['checks']++; echo "PASS: $label\n"; }
class WP_Error { public $code,$message; function __construct($code,$message) { $this->code=$code; $this->message=$message; } }
class WP_Post { public $post_type='press_course',$post_status='publish'; }
$user=(object)['ID'=>7,'roles'=>['administrator'],'display_name'=>'QA Admin'];
$course=new WP_Post();
function is_wp_error($v) { return $v instanceof WP_Error; }
function current_user_can(...$args) { return $GLOBALS['admin']; }
function get_userdata($id) { return $id===7 ? $GLOBALS['user'] : false; }
function is_user_member_of_blog($id) { return $GLOBALS['member']; }
function get_post($id) { return $id===99 ? $GLOBALS['course'] : null; }
function wp_timezone() { return new DateTimeZone('America/Sao_Paulo'); }
function sanitize_text_field($s) { return strip_tags($s); }
function sanitize_key($s) { return $s; }
function wp_unslash($s) { return $s; }
function wp_verify_nonce($nonce,$action) { return $nonce==='valid' && $action==='press_lms_grant_access'; }
function get_current_user_id() { return 42; }
function current_time($format) { return (new DateTimeImmutable('now',wp_timezone()))->format('Y-m-d H:i:s'); }
function add_user_meta($id,$key,$value) { $GLOBALS['audit'][]=$value; return 1; }
class PRESS_LMS_Mailer { static function send_enrollment_activated_email(...$args) { $GLOBALS['mail']++; } }
class MemoryDatabase {
    public $prefix='qa_', $active=false, $lock=true, $manual_id=0, $insert_id=123, $fail=false, $writes=[], $released=0, $queries=[];
    function prepare($sql,...$values) { return $sql; }
    function get_var($sql) {
        $this->queries[]=$sql;
        if (str_contains($sql,'GET_LOCK')) return $this->lock ? 1 : 0;
        if (str_contains($sql,'RELEASE_LOCK')) { $this->released++; return 1; }
        if (str_contains($sql,"payment_provider='manual'")) return $this->manual_id;
        return $this->active ? 123 : null;
    }
    function get_row($sql) { return (object)['id'=>123]; }
    function insert($table,$data) { if ($this->fail) return false; $this->writes[]=['kind'=>'insert','data'=>$data]; return 1; }
    function update($table,$data,$where) { if ($this->fail) return false; $this->writes[]=['kind'=>'update','data'=>$data,'where'=>$where]; return 1; }
}
$wpdb=new MemoryDatabase();
foreach (['Database','Enrollments','Manual_Enrollments'] as $file) require dirname(__DIR__).'/includes/'.$file.'.php';
check(is_wp_error(PRESS_LMS_Manual_Enrollments::validate_request([])),'manual form requires a nonce');
check(PRESS_LMS_Manual_Enrollments::validate_request(['press_lms_manual_nonce'=>'valid'])===true,'manual form accepts an authenticated admin nonce');
$admin=false;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::validate_request(['press_lms_manual_nonce'=>'valid'])),'manual form rejects unauthorized callers even with a nonce');
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30)) && !$wpdb->writes,'grant service also requires administrator permission');
$admin=true;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(0,99,'days',30)),'grant rejects nonexistent users');
$member=false;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30)),'grant rejects users outside the current site');
$member=true; $course->post_status='draft';
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30)),'grant requires a published course');
$course->post_status='publish';
foreach ([['invalid',1],['days',0],['years',101],['months',1201]] as [$type,$value]) {
    check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,$type,$value)),"invalid manual period rejected: $type/$value");
}
$wpdb->lock=false;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30)) && !$wpdb->writes,'manual grant fails closed if its concurrency lock is unavailable');
$wpdb->lock=true; $wpdb->active=true;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30)) && !$wpdb->writes,'existing paid or manual access is not duplicated or shortened');
check($wpdb->released===1,'lock released on existing-access rejection');
$wpdb->active=false;
$before=new DateTimeImmutable('+30 days',wp_timezone());
check(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',30,'Cortesia')===123,'manual grant creates an enrollment without an order');
$data=$wpdb->writes[0]['data'];
check(abs((new DateTimeImmutable($data['expires_at'],wp_timezone()))->getTimestamp()-$before->getTimestamp())<3,'manual expiry uses chosen duration and site timezone');
check($data['payment_provider']==='manual' && $data['order_ref']===null,'manual access remains separate from payment providers');
check($user->roles===['administrator'],'administrator keeps every original role');
check($audit[0]['actor_id']===42 && $audit[0]['reason']==='Cortesia','manual grants record acting admin and reason');
check($mail===0,'manual access does not send unsolicited email by default');
$wpdb->manual_id=123;
check(PRESS_LMS_Manual_Enrollments::grant(7,99,'lifetime',0)===123,'unlimited grant can reactivate an expired manual record');
$last=end($wpdb->writes);
check($last['kind']==='update' && $last['where']['id']===123 && $last['data']['expires_at']===null,'unlimited access has no expiration');
foreach (['subscriber','editor','shop_manager','custom_role'] as $role) {
    $user->roles=[$role]; PRESS_LMS_Manual_Enrollments::grant(7,99,'days',1);
    check($user->roles===[$role],"manual access preserves $role roles");
}
PRESS_LMS_Manual_Enrollments::grant(7,99,'days',1,'',true);
check($mail===1,'email is sent only when explicitly requested');
$wpdb->fail=true; $audit_count=count($audit); $release_count=$wpdb->released;
check(is_wp_error(PRESS_LMS_Manual_Enrollments::grant(7,99,'days',1)),'database failure never reports successful access');
check(count($audit)===$audit_count && $wpdb->released===$release_count+1,'failed writes do not create success audit records and release the lock');
$wpdb->fail=false;
PRESS_LMS_Enrollments::block_enrollment_by_id(123);
$last=end($wpdb->writes);
check($last['where']===['id'=>123] && $last['data']['status']==='blocked','admin block targets the selected enrollment, not unrelated orders');
echo "\n$checks assertions passed. No database, network or real accounts.\n";
