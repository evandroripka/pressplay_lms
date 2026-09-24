<?php
/**
 * Read-only WordPress integration checks and in-memory browser fixtures.
 * Run on a configured installation: php tests/runtime.php
 * Browser fixtures: php tests/runtime.php --fixtures (contains temporary nonces).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('DISABLE_WP_CRON', true);
define('WP_ADMIN', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
// Match the HTTPS browser origin when generating asset URLs from the CLI.
if (getenv('PRESS_LMS_QA_HTTPS') !== '0') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}
$root = getenv('PRESS_LMS_WP_ROOT') ?: dirname(__DIR__, 4);
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$blocked_writes = 0;
add_filter('query', static function ($sql) use (&$blocked_writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i', $sql)) {
        $blocked_writes++;
        return '';
    }
    return $sql;
}, PHP_INT_MAX);
add_filter('pre_wp_mail', '__return_false');
add_filter('pre_http_request', static fn() => new WP_Error('qa_offline', 'Network disabled during fixture generation.'));
$results = [];
function verify($condition, string $name): void {
    global $results;
    $results[$name] = (bool) $condition;
    if (!$condition) throw new RuntimeException($name);
}
function capture(callable $render): string {
    ob_start();
    try { $render(); return (string) ob_get_contents(); }
    finally { ob_end_clean(); }
}
$courses = get_posts(['post_type'=>'press_course','post_status'=>'publish','posts_per_page'=>1]);
if (!$courses) throw new RuntimeException('A published course is required for read-only integration checks.');
$course = $courses[0];
$admins = get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);
if (!$admins) throw new RuntimeException('An administrator is required to render the editor in memory.');
wp_set_current_user((int) $admins[0]);
foreach ([3,5,6] as $count) {
    $args = array_slice(['woocommerce_add_to_cart_validation',true,0,1,0,[],[]],0,$count+1);
    verify(apply_filters(...$args) === true, "woocommerce_hook_$count");
}
verify(has_action('woocommerce_store_api_checkout_order_processed', ['PRESS_LMS_Woo','handle_store_api_order_processed']) === 10, 'checkout_blocks_order_hook');
$css = '.icon:before{content:"\\f007"} </style><script>qa</script>';
$sanitize = new ReflectionMethod(PRESS_LMS_Course_Meta::class, 'sanitize_certificate_css');
$sanitize->setAccessible(true);
$safe = $sanitize->invoke(null, wp_slash($css));
$stored = null;
$intercept = static function ($check, $id, $key, $value) use (&$stored) {
    if ($key === '_press_lms_qa_css') { $stored = $value; return true; }
    return $check;
};
add_filter('update_post_metadata',$intercept,PHP_INT_MAX,4);
update_post_meta($course->ID,'_press_lms_qa_css',wp_slash($safe));
remove_filter('update_post_metadata',$intercept,PHP_INT_MAX);
verify($stored === $safe && str_contains($stored,'\\f007') && !str_contains($stored,'<'), 'real_metadata_css_roundtrip');
set_current_screen('press_course');
$GLOBALS['post'] = $course;
PRESS_LMS_Course_Meta::enqueue_admin_assets('post.php');
wp_enqueue_script('jquery');
$editor = capture(static function () use ($course) { PRESS_LMS_Course_Meta::render($course); });
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $editor);
$xpath = new DOMXPath($dom);
verify($xpath->query('//*[@id="press-course-tabs"]/*[@data-tab-panel]')->length === 4, 'four_sibling_course_tabs');
verify($xpath->query('//*[@data-tab-panel="certificate"]//*[@data-tab-panel="lessons"]')->length === 0, 'lesson_tab_not_nested');
verify($xpath->query('//input[starts-with(@name,"press_lesson_order[")]')->length > 0, 'curriculum_order_inputs');
$embed = PRESS_LMS_Vimeo::get_embed_html(123, 960, 'https://vimeo.com/123/abcdef', true);
verify(str_contains($embed, 'h=abcdef') && str_contains($embed, 'fullscreen=1') && str_contains($embed, 'vimeo_logo=0') && str_contains($embed, 'allowfullscreen'), 'trailer_permissions_preserve_private_hash');
verify(has_action('woocommerce_store_api_checkout_order_processed', ['PRESS_LMS_Terms','save_blocks']) === 1, 'contract_validated_before_store_api_fulfillment');
$saved_materials = null;
$capture_materials = static function ($check,$id,$key,$value) use (&$saved_materials) {
    if ($key === PRESS_LMS_Materials::COURSE_META) { $saved_materials=$value; return true; }
    return $check;
};
add_filter('update_post_metadata',$capture_materials,PHP_INT_MAX,4);
$_POST = ['press_material_type'=>['link','link'], 'press_material_name'=>['<b>Apostila</b>','Invalido'], 'press_material_url_link'=>['https://example.test/apostila.pdf','javascript:alert(1)']];
PRESS_LMS_Materials::save_course($course->ID,$course);
verify($saved_materials === null, 'course_materials_save_requires_nonce');
$_POST['press_course_materials_nonce']=wp_create_nonce('press_course_materials_save');
PRESS_LMS_Materials::save_course($course->ID,$course);
verify(count($saved_materials)===1 && $saved_materials[0]['name']==='Apostila' && $saved_materials[0]['kind']==='pdf', 'course_materials_sanitize_and_store_separately');
remove_filter('update_post_metadata',$capture_materials,PHP_INT_MAX);
$_POST=[];
$material_meta = static fn($check,$id,$key) => $id===$course->ID && $key===PRESS_LMS_Materials::COURSE_META ? [[$saved_materials[0]]] : $check;
add_filter('get_post_metadata',$material_meta,100,3);
verify(count(PRESS_LMS_Materials::get_accessible_course_items((int)$course->ID))===1, 'authorized_course_materials_visible');
wp_set_current_user(0);
verify(PRESS_LMS_Materials::get_accessible_course_items((int)$course->ID)===[], 'guest_cannot_obtain_course_material_list');
remove_filter('get_post_metadata',$material_meta,100);
wp_set_current_user((int)$admins[0]);
$colors = PRESS_LMS_Settings::get_css_variable_suggestion_tabs();
verify(array_column($colors, 'key') === ['elementor','wordpress','theme'], 'color_categories');
wp_set_current_user(0);
$draft = new WP_Post((object) ['ID'=>999991,'post_type'=>'press_lesson','post_status'=>'draft','post_parent'=>999990,'post_name'=>'qa-draft','post_title'=>'QA','filter'=>'raw']);
$before = static function ($q) { if ($q->get('name') === 'qa-draft') { $q->set('suppress_filters',false); $q->set('cache_results',false); } };
$rows = static fn($posts,$q) => $q->get('name') === 'qa-draft' ? [$draft] : $posts;
add_action('pre_get_posts',$before);
add_filter('posts_results',$rows,100,2);
$lookup = new ReflectionMethod(PRESS_LMS_Frontend::class,'find_lesson_for_course');
$lookup->setAccessible(true);
verify($lookup->invoke(null,'qa-draft',999990) === null,'draft_lesson_hidden_in_wordpress');
remove_action('pre_get_posts',$before);
remove_filter('posts_results',$rows,100);
set_query_var('press_course_slug',$course->post_name);
PRESS_LMS_Templates::prepare_route();
verify(is_singular('press_course') && get_the_ID() === $course->ID && !is_home(),'course_query_context');
wp_dequeue_style('press-lms-app');
PRESS_LMS_Plugin::enqueue_app_assets();
verify(wp_style_is('press-lms-app','enqueued'), 'lms_shared_styles_loaded');
set_query_var('press_course_slug','');
wp_dequeue_style('press-lms-app');
$previous_post = $GLOBALS['post'];
$GLOBALS['post'] = new WP_Post((object) ['ID'=>999992,'post_type'=>'page','post_content'=>'Plain page','filter'=>'raw']);
PRESS_LMS_Plugin::enqueue_app_assets();
verify(!wp_style_is('press-lms-app','enqueued'), 'unrelated_pages_skip_lms_shared_styles');
$GLOBALS['post']->post_content = '[press_register]';
PRESS_LMS_Plugin::enqueue_app_assets();
verify(wp_style_is('press-lms-app','enqueued'), 'registration_shortcode_keeps_shared_styles');
$GLOBALS['post'] = $previous_post;
set_query_var('press_course_slug',$course->post_name);

if (!in_array('--fixtures',$argv,true)) {
    echo wp_json_encode(['checks'=>$results,'blocked_writes'=>$blocked_writes],JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
wp_set_current_user((int) $admins[0]);
$head = capture(static function () { wp_print_styles(['code-editor']); wp_print_scripts(['jquery','code-editor']); });
$footer = capture(static function () { do_action('admin_print_footer_scripts'); });
$wrap = static function ($body,$extra = '',$class = '') use ($head,$footer) {
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
        $head . '<link rel="stylesheet" href="' . esc_url(admin_url('load-styles.php?c=0&load=common,forms,buttons,dashicons')) . '">' .
        '<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL . 'assets/css/admin.css') . '">' . $extra .
        '</head><body class="' . esc_attr($class) . '"><div style="max-width:1180px;margin:24px auto;padding:12px">' . $body . '</div>' . $footer . '</body></html>';
};
$fixtures = [];
$fixtures['editor'] = $wrap('<input id="title" value="Curso demonstrativo"><div class="postbox"><div class="inside">' . $editor . '</div></div>','','presslms-admin-screen presslms-admin-posttype-press_course');
$materials_editor=capture(static fn()=>PRESS_LMS_Materials::render_course_editor($course));
$fixtures['materials']=$wrap($materials_editor,'','presslms-admin-screen presslms-admin-posttype-press_course');
$_GET['manual_user_search']='QA';
$fake_users=static fn()=>[(object)['ID'=>999990,'display_name'=>'Aluno QA','user_email'=>'qa@example.test']];
add_filter('users_pre_query',$fake_users);
$manual=capture(static fn()=>PRESS_LMS_Manual_Enrollments::render([$course]));
remove_filter('users_pre_query',$fake_users);
unset($_GET['manual_user_search']);
$fixtures['manual_access']=$wrap($manual,'','presslms-admin-screen');
$settings = capture(static function () { PRESS_LMS_Settings::field_frontend_custom_css(); });
$config = wp_enqueue_code_editor(['type'=>'text/css']);
$settings_scripts = '<script>window.presslmsAdmin=' . wp_json_encode(['cssEditor'=>['fieldId'=>'press_lms_frontend_custom_css','settings'=>$config]]) . ';</script><script src="' . esc_url(PRESS_LMS_URL.'assets/js/admin-panels.js') . '"></script>';
$fixtures['settings'] = $wrap('<div class="presslms-admin-page">' . $settings . '</div>' . $settings_scripts,'<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/admin-panels.css') . '">');
$contracts = [1=>['course_id'=>1,'title'=>'Curso demonstrativo','content'=>'<p>Contrato ficticio para testes de leitura e aceite.</p>','version'=>hash('sha256','qa')]];
$fields = new ReflectionMethod(PRESS_LMS_Terms::class, 'render_fields');
$fields->setAccessible(true);
$terms = capture(static fn()=>$fields->invoke(null,$contracts));
$fixtures['terms'] = $wrap('<form>' . $terms . '</form><script src="' . esc_url(PRESS_LMS_URL.'assets/js/terms.js') . '"></script>', '<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/terms.css') . '">');
$lessons = PRESS_LMS_Helpers::get_course_lessons((int) $course->ID,['publish']);
if (!$lessons) {
    $all = get_posts(['post_type'=>'press_lesson','post_status'=>'publish','posts_per_page'=>1]);
    $lessons = $all;
}
if ($lessons) {
    $lesson = clone $lessons[0];
    $lesson->post_title = 'Aula demonstrativa de leitura';
    $lesson->post_content = '<p>Conteudo ficticio para testar conclusao manual e navegacao.</p>';
    $mask_video = static fn($check,$id,$key) => $id === $lesson->ID && in_array($key,['_press_lesson_video_url','_press_lesson_vimeo_id'],true) ? [''] : $check;
    add_filter('get_post_metadata',$mask_video,100,3);
    $body = capture(static function () use ($course,$lesson) {
        $course_var = $course; $lesson_var = $lesson; $course_slug_var = $course->post_name; $lesson_slug_var = $lesson->post_name;
        include PRESS_LMS_PATH . 'templates/frontend/single-press_lesson.php';
    });
    remove_filter('get_post_metadata',$mask_video,100);
    $styles = '';
    foreach (['presslms-base','presslms-lesson'] as $name) $styles .= '<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/'.$name.'.css') . '">';
    $fixtures['lesson'] = $wrap($body . '<script src="' . esc_url(PRESS_LMS_URL.'assets/js/lesson-progress.js') . '"></script>',$styles);

    wp_set_current_user(0);
    $sample_meta = static fn($check,$id,$key) => $id === $lesson->ID && $key === '_press_lesson_free_preview' ? ['yes'] : $check;
    add_filter('get_post_metadata',$sample_meta,100,3);
    set_query_var('press_lesson_slug',$lesson->post_name);
    $sample_post = PRESS_LMS_Frontend::resolve_route_post();
    verify($sample_post && (int)$sample_post->ID === (int)$lesson->ID, 'sample_route_exposes_only_selected_lesson');
    $sample_body = capture(static function () use ($course,$lesson) {
        $course_var=$course; $lesson_var=$lesson; $course_slug_var=$course->post_name; $lesson_slug_var=$lesson->post_name;
        include PRESS_LMS_PATH . 'templates/frontend/single-press_lesson.php';
    });
    verify(!str_contains($sample_body,'window.presslmsLessonData') && !str_contains($sample_body,'id="presslms-complete-lesson"'), 'sample_has_no_progress_controls_or_tracking_nonce');
    $fixtures['sample'] = $wrap($sample_body . '<script src="' . esc_url(PRESS_LMS_URL.'assets/js/lesson-progress.js') . '"></script>',$styles . '<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/app.css') . '">');
    set_query_var('press_lesson_slug','');
    $course_body = capture(static function () use ($course) {
        $course_var=$course; $course_slug_var=$course->post_name; $can_access_var=false; $trailer_var='https://vimeo.com/123/abcdef';
        include PRESS_LMS_PATH . 'templates/frontend/single-press_course.php';
    });
    $fixtures['sample_course'] = $wrap($course_body . '<script src="' . esc_url(PRESS_LMS_URL.'assets/js/course-access-guard.js') . '"></script>', '<link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/presslms-base.css') . '"><link rel="stylesheet" href="' . esc_url(PRESS_LMS_URL.'assets/css/presslms-course.css') . '">');
    remove_filter('get_post_metadata',$sample_meta,100);
}
echo wp_json_encode(['checks'=>$results,'fixtures'=>$fixtures,'base'=>home_url('/'),'course_path'=>'/curso/'.$course->post_name.'/','blocked_writes'=>$blocked_writes],JSON_UNESCAPED_SLASHES);
