<?php
/** Duration and weighted watch-time regressions with in-memory WordPress doubles. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
$checks = 0;
$meta = $user_meta = $transients = $posts = [];
$api_calls = $oembed_calls = 0;
$oembed_ok = true;
$storage_ok = true;
function check($condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo "PASS: $label\n";
}
class WP_Post {
    public $ID, $post_parent, $post_status = 'publish', $post_type = 'press_lesson', $menu_order = 0, $post_title = '';
    function __construct($id, $parent) { $this->ID = $id; $this->post_parent = $parent; }
}
class WP_Error {
    public $message;
    function __construct($code, $message) { $this->message = $message; }
    function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function home_url($path) { return 'https://qa.test' . $path; }
function get_option($key, $default = '') { return 'qa-token'; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_remote_get($url, $args) { $GLOBALS['api_calls']++; return ['code'=>404,'body'=>'{"error":"not found"}']; }
function wp_safe_remote_get($url, $args) {
    $GLOBALS['oembed_calls']++;
    if (!str_starts_with($url,'https://vimeo.com/api/oembed.json?')) throw new RuntimeException('Unsafe metadata host');
    return $GLOBALS['oembed_ok'] ? ['code'=>200,'body'=>'{"video_id":123,"duration":100,"title":"QA video"}'] : new WP_Error('timeout','Timeout');
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
function get_user_meta($id, $key, $single = true) { return $GLOBALS['user_meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) {
    if (!$GLOBALS['storage_ok']) return false;
    $GLOBALS['user_meta'][$id][$key] = $value;
    return true;
}
function current_time($format) { return '2026-09-24 12:00:00'; }
function wp_list_pluck($items, $field) { return array_map(static fn($p) => $p->$field, $items); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_posts($args) {
    return array_values(array_filter($GLOBALS['posts'], static function ($post) use ($args) {
        $course = $args['post_parent'] ?? $args['meta_value'] ?? 0;
        $parent = isset($args['post_parent']) ? $post->post_parent : get_post_meta($post->ID, '_press_lesson_course_id');
        return $post->post_type === 'press_lesson' && in_array($post->post_status,$args['post_status'],true) && (int)$parent === (int)$course;
    }));
}
class MemoryDatabase {
    public $prefix = 'qa_', $rows = [], $params = [], $fail = false;
    function prepare($sql, ...$params) { $this->params = $params; return $sql; }
    function get_results($sql) { return array_values($this->rows); }
    function get_row($sql) { return $this->rows[$this->params[count($this->params)-1]] ?? null; }
    function insert($table, $data) {
        if ($this->fail) return false;
        $data['id'] = $data['lesson_id'];
        $this->rows[$data['lesson_id']] = (object)$data;
        return 1;
    }
    function update($table, $data, $where) {
        if ($this->fail) return false;
        foreach ($data as $k=>$v) $this->rows[$where['id']]->$k = $v;
        return 1;
    }
}
$wpdb = new MemoryDatabase();
foreach (['Database','Support/Helpers','Vimeo','Duration','Progress'] as $file) require dirname(__DIR__) . '/includes/' . $file . '.php';
check(PRESS_LMS_Vimeo::parse_video_id('https://not-vimeo.com/123') === null, 'lookalike Vimeo hosts rejected');
check(PRESS_LMS_Vimeo::parse_video_id('https://vimeo.com/123/hash') === 123, 'unlisted URL recognized');
$data = PRESS_LMS_Vimeo::get_video_metadata('https://vimeo.com/123/hash');
check($data['duration'] === 100, 'oEmbed recovers duration after API 404');
PRESS_LMS_Vimeo::get_video_metadata('https://vimeo.com/123/hash');
check($api_calls === 1 && $oembed_calls === 1, 'metadata cache avoids repeated API calls');
$posts[1] = new WP_Post(1,99);
$posts[2] = new WP_Post(2,99);
$meta[1] = ['_press_lesson_video_url'=>'https://vimeo.com/123/hash','_press_lesson_course_id'=>99];
$meta[2] = ['_press_lesson_duration'=>900];
check(PRESSLMS_Duration::sync_lesson_duration(1) === 100, 'lesson stores resolved video duration');
check(PRESSLMS_Duration::recalc_course_total_duration(99) === 1000, 'course includes parent-only and legacy meta lessons without duplicates');
$posts[2]->post_status = 'draft';
check(PRESSLMS_Duration::recalc_course_total_duration(99) === 100, 'unpublished lessons excluded from duration');
$posts[2]->post_status = 'publish';
$transients = [];
$oembed_ok = false;
check(PRESSLMS_Duration::sync_lesson_duration(1) === 100, 'metadata failure preserves verified duration');
$meta[1]['_press_lesson_video_url'] = 'https://vimeo.com/456';
check(PRESSLMS_Duration::sync_lesson_duration(1) === 0, 'changed video never inherits old duration');
$meta[1]['_press_lesson_duration'] = 100;
$meta[1]['_press_lesson_video_url'] = 'https://vimeo.com/123/hash';
check(PRESS_LMS_Progress::normalize_ranges([[50,70],[0,60],[120,130],[-2,4],[9,4],['bad',20]],100) == [[0.0,70.0]], 'ranges are bounded, sorted, merged and validated');
check(PRESS_LMS_Progress::record_video_progress(7,99,1,[[0,50]],50) === true, 'partial playback persisted');
check(PRESS_LMS_Progress::get_course_progress_percent(7,99) === 5.0, '50 watched seconds of 1000-second course yields 5 percent');
PRESS_LMS_Progress::record_video_progress(7,99,1,[[0,50]],99);
check($wpdb->rows[1]->watched_seconds === 50 && $wpdb->rows[1]->completed === 0, 'seeking to end does not count as watching');
check(PRESS_LMS_Progress::get_watch_state(7,1)['position'] === 99.0, 'resume position is separate from watched time');
PRESS_LMS_Progress::record_video_progress(7,99,1,[[20,50]],25);
check($wpdb->rows[1]->watched_seconds === 50, 'replayed intervals do not inflate progress');
PRESS_LMS_Progress::record_video_progress(7,99,1,[[50,80]],80);
check($wpdb->rows[1]->watched_seconds === 80, 'separate sessions merge played intervals');
PRESS_LMS_Progress::record_video_progress(7,99,1,[[80,100]],100);
check($wpdb->rows[1]->completed === 1 && PRESS_LMS_Progress::get_course_progress_percent(7,99) === 10.0, 'short completed video contributes its duration, not half the course');
PRESS_LMS_Progress::record_video_progress(7,99,2,[[0,450]],450);
check(PRESS_LMS_Progress::get_course_progress_percent(7,99) === 55.0, 'partial long lesson advances overall course percentage');
PRESS_LMS_Progress::record_video_progress(7,99,2,[[450,900]],900);
check(PRESS_LMS_Progress::get_course_progress_percent(7,99) === 100.0, 'all unique video time completes course');
$wpdb->rows[2]->completed = 0;
check(PRESS_LMS_Progress::get_course_progress_percent(7,99) === 99.99, 'rounding alone never grants final completion');
$storage_ok = false;
check(is_wp_error(PRESS_LMS_Progress::record_video_progress(7,99,2,[],20)), 'watch-state write failures reported');
$storage_ok = true;
$wpdb->fail = true;
check(is_wp_error(PRESS_LMS_Progress::record_video_progress(7,99,2,[],21)), 'progress-table write failures reported');
$wpdb->fail = false;
$meta[2]['_press_lesson_duration'] = 0;
$summary = PRESS_LMS_Progress::get_course_progress_summary(7,99);
check($summary['duration_complete'] === false && $summary['percent'] === 50.0, 'unknown-duration lessons use an explicit lesson fallback');
check(is_wp_error(PRESS_LMS_Progress::record_video_progress(7,99,2,[[0,900]],900)), 'client cannot supply authoritative course duration');
echo "\n$checks assertions passed. No network or persistent writes.\n";
