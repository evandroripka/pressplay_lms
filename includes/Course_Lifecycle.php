<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Course_Lifecycle
{
    const META_PAUSED = '_press_course_paused';

    private static $processed_trash = [];
    private static $processed_delete = [];

    public static function init(): void
    {
        add_filter('pre_trash_post', [__CLASS__, 'intercept_course_trash'], 10, 3);
        add_filter('pre_delete_post', [__CLASS__, 'intercept_course_delete'], 10, 3);
        add_action('wp_trash_post', [__CLASS__, 'trash_related_content'], 10, 2);
        add_action('before_delete_post', [__CLASS__, 'delete_related_content'], 10, 2);
        add_action('admin_post_press_lms_pause_course', [__CLASS__, 'handle_pause_course']);
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('post_row_actions', [__CLASS__, 'decorate_course_row_actions'], 10, 2);
    }

    public static function is_course_paused(int $course_id): bool
    {
        return get_post_meta($course_id, self::META_PAUSED, true) === 'yes';
    }

    public static function course_has_enrollments(int $course_id): bool
    {
        global $wpdb;

        $course_id = (int) $course_id;
        if ($course_id <= 0) {
            return false;
        }

        $table = PRESS_LMS_Database::table('enrollments');
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id) FROM {$table} WHERE course_id = %d",
                $course_id
            )
        );

        return $count > 0;
    }

    public static function pause_course(int $course_id): void
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0 || get_post_type($course_id) !== 'press_course') {
            return;
        }

        update_post_meta($course_id, self::META_PAUSED, 'yes');

        if (class_exists('PRESS_LMS_Woo') && method_exists('PRESS_LMS_Woo', 'sync_course_product_state')) {
            PRESS_LMS_Woo::sync_course_product_state($course_id);
        }
    }

    private static function is_course_post($post): bool
    {
        return $post instanceof WP_Post && $post->post_type === 'press_course';
    }

    private static function get_pause_url(int $course_id): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=press_lms_pause_course&course_id=' . $course_id),
            'press_lms_pause_course_' . $course_id
        );
    }

    private static function get_notice_redirect_url(int $course_id, string $notice): string
    {
        $referer = wp_get_referer();
        if ($referer) {
            $target = remove_query_arg(['action', 'action2', 'post', 'ids', '_wpnonce', 'trashed', 'untrashed', 'deleted'], $referer);
        } elseif (isset($_GET['post_type']) && $_GET['post_type'] === 'press_course') {
            $target = admin_url('edit.php?post_type=press_course');
        } else {
            $target = admin_url('post.php?post=' . $course_id . '&action=edit');
        }

        return add_query_arg('press_lms_course_notice', $notice, $target);
    }

    private static function maybe_redirect_after_pause(int $course_id): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        wp_safe_redirect(self::get_notice_redirect_url($course_id, 'paused'));
        exit;
    }

    public static function intercept_course_trash($trash, $post, $previous_status)
    {
        if (!self::is_course_post($post)) {
            return $trash;
        }

        if (!self::course_has_enrollments((int) $post->ID)) {
            return $trash;
        }

        self::pause_course((int) $post->ID);
        self::maybe_redirect_after_pause((int) $post->ID);

        return $post;
    }

    public static function intercept_course_delete($delete, $post, $force_delete)
    {
        if (!self::is_course_post($post)) {
            return $delete;
        }

        if (!self::course_has_enrollments((int) $post->ID)) {
            return $delete;
        }

        self::pause_course((int) $post->ID);
        self::maybe_redirect_after_pause((int) $post->ID);

        return $post;
    }

    public static function trash_related_content($post_id, $previous_status): void
    {
        $post = get_post($post_id);
        if (!self::is_course_post($post)) {
            return;
        }

        if (!empty(self::$processed_trash[$post_id]) || self::course_has_enrollments((int) $post_id)) {
            return;
        }

        self::$processed_trash[$post_id] = true;

        foreach (PRESS_LMS_Helpers::get_course_lessons((int) $post_id, ['publish', 'draft', 'pending', 'private', 'future']) as $lesson) {
            if ($lesson instanceof WP_Post && get_post_status($lesson->ID) !== 'trash') {
                wp_trash_post($lesson->ID);
            }
        }

        self::trash_or_delete_product((int) $post_id, false);
    }

    public static function delete_related_content($post_id, $post): void
    {
        if (!self::is_course_post($post)) {
            return;
        }

        if (!empty(self::$processed_delete[$post_id]) || self::course_has_enrollments((int) $post_id)) {
            return;
        }

        self::$processed_delete[$post_id] = true;

        foreach (PRESS_LMS_Helpers::get_course_lessons((int) $post_id, ['publish', 'draft', 'pending', 'private', 'future', 'trash']) as $lesson) {
            if ($lesson instanceof WP_Post) {
                wp_delete_post($lesson->ID, true);
            }
        }

        self::trash_or_delete_product((int) $post_id, true);
    }

    private static function trash_or_delete_product(int $course_id, bool $force_delete): void
    {
        $product_id = (int) get_post_meta($course_id, '_press_course_product_id', true);
        if ($product_id <= 0 || !get_post($product_id)) {
            return;
        }

        if ($force_delete) {
            wp_delete_post($product_id, true);
            return;
        }

        if (get_post_status($product_id) !== 'trash') {
            wp_trash_post($product_id);
        }
    }

    public static function handle_pause_course(): void
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

        if ($course_id <= 0 || get_post_type($course_id) !== 'press_course') {
            wp_die('Curso inválido.');
        }

        if (!current_user_can('delete_post', $course_id)) {
            wp_die('Sem permissão para pausar este curso.');
        }

        check_admin_referer('press_lms_pause_course_' . $course_id);

        self::pause_course($course_id);

        wp_safe_redirect(self::get_notice_redirect_url($course_id, 'paused'));
        exit;
    }

    public static function render_admin_notices(): void
    {
        if (empty($_GET['press_lms_course_notice'])) {
            return;
        }

        $notice = sanitize_key((string) $_GET['press_lms_course_notice']);

        if ($notice !== 'paused') {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible"><p><strong>Pressplay LMS:</strong> este curso possui matrículas vinculadas, então ele foi pausado em vez de ser excluído. Novas matrículas foram bloqueadas e o produto do WooCommerce foi ocultado da vitrine.</p></div>';
    }

    public static function enqueue_admin_assets($hook): void
    {
        if (!in_array($hook, ['post.php', 'edit.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'press_course') {
            return;
        }

        wp_enqueue_script(
            'press-lms-course-delete-guard',
            PRESS_LMS_URL . 'assets/js/course-delete-guard.js',
            ['jquery'],
            PRESS_LMS_VERSION,
            true
        );

        $current_post = null;
        if ($hook === 'post.php' && !empty($_GET['post'])) {
            $course_id = (int) $_GET['post'];
            if (get_post_type($course_id) === 'press_course') {
                $current_post = [
                    'id' => $course_id,
                    'hasEnrollments' => self::course_has_enrollments($course_id),
                    'pauseUrl' => self::get_pause_url($course_id),
                ];
            }
        }

        wp_localize_script('press-lms-course-delete-guard', 'pressLmsCourseDeleteGuard', [
            'currentPost' => $current_post,
            'messages' => [
                'deleteTitle' => 'Excluir curso?',
                'deleteText' => 'Excluir este curso tambem vai excluir todas as aulas vinculadas.',
                'pauseTitle' => 'Pausar curso?',
                'pauseText' => 'Este curso possui matrículas vinculadas. Em vez de excluir, ele sera pausado: novas matrículas serao bloqueadas e o produto sera ocultado da vitrine do WooCommerce.',
                'confirmDelete' => 'Sim, continuar',
                'confirmPause' => 'Sim, pausar curso',
                'cancel' => 'Cancelar',
            ],
        ]);
    }

    public static function decorate_course_row_actions(array $actions, $post): array
    {
        if (!$post instanceof WP_Post || $post->post_type !== 'press_course' || empty($actions['trash'])) {
            return $actions;
        }

        $has_enrollments = self::course_has_enrollments((int) $post->ID);
        $attrs = sprintf(
            ' data-press-course-id="%d" data-press-course-has-enrollments="%d" data-press-course-pause-url="%s"',
            (int) $post->ID,
            $has_enrollments ? 1 : 0,
            esc_url(self::get_pause_url((int) $post->ID))
        );

        $actions['trash'] = str_replace('<a ', '<a ' . $attrs . ' ', $actions['trash']);

        if ($has_enrollments) {
            $actions['trash'] = preg_replace('/>(.*?)</', '>Pausar curso<', $actions['trash'], 1);
        }

        return $actions;
    }
}
