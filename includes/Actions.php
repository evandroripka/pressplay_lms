<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Actions
{
    public static function init()
    {
        add_action('admin_post_press_lms_enroll', [__CLASS__, 'handle_enroll']);
        add_action('admin_post_nopriv_press_lms_enroll', [__CLASS__, 'handle_enroll']);

        add_action('admin_post_press_lms_enroll_continue', [__CLASS__, 'handle_enroll_continue']);
        add_action('admin_post_nopriv_press_lms_enroll_continue', [__CLASS__, 'handle_enroll_continue']);
        add_action('admin_post_press_lms_update_account_profile', [__CLASS__, 'handle_account_profile_update']);
        add_action('admin_post_press_lms_update_account_password', [__CLASS__, 'handle_account_password_update']);
        add_action('wp_ajax_press_lms_track_progress', [__CLASS__, 'ajax_track_progress']);
        // Preserve redirect_to values across WooCommerce login and registration.
        add_filter('woocommerce_login_redirect', [__CLASS__, 'woo_login_redirect'], 10, 2);
        add_filter('woocommerce_registration_redirect', [__CLASS__, 'woo_registration_redirect'], 10, 1);
        add_filter('login_redirect', [__CLASS__, 'default_login_redirect'], 10, 3);
        add_action('wp_ajax_press_lms_change_student_password', [__CLASS__, 'ajax_change_student_password']);
        add_action('save_post_press_lesson', function ($post_id, $post, $update) {

            // Ignore autosaves, revisions, and invalid post objects.
            if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
            if (!$post || !($post instanceof WP_Post)) return;

            $post_id = (int) $post_id;

            // Refresh lesson metadata only for published lessons.
            if ($post->post_status !== 'publish') return;

            // Rate-limit the Vimeo sync to avoid burst saves or loops.
            $rate_key = 'presslms_vimeo_duration_lock_' . $post_id;
            if (get_transient($rate_key)) {
                // Still recalculate course totals even when the API sync is skipped.
                $course_id = (int) get_post_meta($post_id, '_press_lesson_course_id', true);
                if ($course_id > 0 && class_exists('PRESSLMS_Duration')) {
                    PRESSLMS_Duration::recalc_course_total_duration($course_id);
                }
                return;
            }
            set_transient($rate_key, 1, 30); // 30-second cooldown.

            // Resolve the linked course.
            $course_id = (int) get_post_meta($post_id, '_press_lesson_course_id', true);

            // Read the current Vimeo metadata cache.
            $vimeo_id = (int) get_post_meta($post_id, '_press_lesson_vimeo_id', true);

            $cached_vimeo_id   = (int) get_post_meta($post_id, '_press_lesson_vimeo_id_cached', true);
            $cached_modified   = (string) get_post_meta($post_id, '_press_lesson_vimeo_modified_cached', true);
            $current_duration  = (int) get_post_meta($post_id, '_press_lesson_duration', true);

            // Reset duration data when the lesson no longer points to Vimeo.
            if ($vimeo_id <= 0) {
                update_post_meta($post_id, '_press_lesson_duration', 0);
                update_post_meta($post_id, '_press_lesson_vimeo_id_cached', 0);
                update_post_meta($post_id, '_press_lesson_vimeo_modified_cached', '');

                if ($course_id > 0 && class_exists('PRESSLMS_Duration')) {
                    PRESSLMS_Duration::recalc_course_total_duration($course_id);
                }
                return;
            }

            // Decide whether the remote Vimeo payload needs to be refreshed.
            $need_refresh = false;

            if ($cached_vimeo_id !== $vimeo_id) $need_refresh = true;

            if ($current_duration <= 0) $need_refresh = true;

            // Use modified_time when a token is available to invalidate caches safely.
            if (!$need_refresh && class_exists('PRESS_LMS_Vimeo') && method_exists('PRESS_LMS_Vimeo', 'has_token') && PRESS_LMS_Vimeo::has_token()) {
                if (method_exists('PRESS_LMS_Vimeo', 'get_video_modified_time')) {
                    $remote_modified = PRESS_LMS_Vimeo::get_video_modified_time($vimeo_id);
                    if ($remote_modified && $remote_modified !== $cached_modified) {
                        $need_refresh = true;
                    }
                }
            }

            // Refresh duration and remote metadata through the Vimeo API.
            if ($need_refresh && class_exists('PRESS_LMS_Vimeo') && method_exists('PRESS_LMS_Vimeo', 'has_token') && PRESS_LMS_Vimeo::has_token()) {

                $duration = 0;
                $remote_modified = '';

                if (method_exists('PRESS_LMS_Vimeo', 'get_video_duration_seconds')) {
                    $duration = (int) PRESS_LMS_Vimeo::get_video_duration_seconds($vimeo_id);
                }
                if (method_exists('PRESS_LMS_Vimeo', 'get_video_modified_time')) {
                    $remote_modified = (string) PRESS_LMS_Vimeo::get_video_modified_time($vimeo_id);
                }

                update_post_meta($post_id, '_press_lesson_duration', max(0, $duration));
                update_post_meta($post_id, '_press_lesson_vimeo_id_cached', $vimeo_id);
                update_post_meta($post_id, '_press_lesson_vimeo_modified_cached', $remote_modified);
            } else {
                // Without a token, keep the local duration and only recalculate totals.
            }

            // Always refresh the total course duration after lesson updates.
            if ($course_id > 0 && class_exists('PRESSLMS_Duration')) {
                PRESSLMS_Duration::recalc_course_total_duration($course_id);
            }
        }, 20, 3);
    }
    public static function ajax_track_progress()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não autenticado.'], 401);
        }

        check_ajax_referer('presslms_track_progress', 'nonce');

        $user_id = get_current_user_id();
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $lesson_id = isset($_POST['lesson_id']) ? (int) $_POST['lesson_id'] : 0;
        $watched_seconds = isset($_POST['watched_seconds']) ? (int) $_POST['watched_seconds'] : 0;
        $completed = !empty($_POST['completed']) ? 1 : 0;
        $watched_seconds = max(0, $watched_seconds);

        if ($course_id <= 0 || $lesson_id <= 0) {
            wp_send_json_error(['message' => 'Dados inválidos.'], 400);
        }

        $lesson = get_post($lesson_id);
        $lesson_course_id = $lesson instanceof WP_Post ? (int) $lesson->post_parent : 0;

        if ($lesson_course_id <= 0) {
            $lesson_course_id = (int) get_post_meta($lesson_id, '_press_lesson_course_id', true);
        }

        if (
            !$lesson instanceof WP_Post ||
            $lesson->post_type !== 'press_lesson' ||
            $lesson_course_id !== $course_id
        ) {
            wp_send_json_error(['message' => 'A aula informada não pertence ao curso.'], 400);
        }

        if (!class_exists('PRESS_LMS_Enrollments') || !PRESS_LMS_Enrollments::can_access_course($user_id, $course_id)) {
            wp_send_json_error(['message' => 'Sem acesso ao curso.'], 403);
        }

        if (class_exists('PRESS_LMS_Progress')) {
            PRESS_LMS_Progress::upsert_progress(
                $user_id,
                $course_id,
                $lesson_id,
                $watched_seconds,
                $completed
            );
        }

        $percent = class_exists('PRESS_LMS_Progress')
            ? PRESS_LMS_Progress::get_course_progress_percent($user_id, $course_id)
            : 0;

        wp_send_json_success([
            'message' => 'Progresso salvo.',
            'course_progress_percent' => $percent,
        ]);
    }
    public static function ajax_change_student_password()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        check_ajax_referer('presslms_admin_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $new_password = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';

        if ($user_id <= 0) {
            wp_send_json_error(['message' => 'Usuário inválido.'], 400);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'Usuário não encontrado.'], 404);
        }

        $new_password = trim($new_password);

        if (strlen($new_password) < 6) {
            wp_send_json_error(['message' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
        }

        // Use the WordPress password API for compatibility with the current auth stack.
        wp_set_password($new_password, $user_id);

        wp_send_json_success([
            'message' => 'Senha alterada com sucesso.'
        ]);
    }

    private static function get_student_dashboard_url(string $screen = 'courses', array $args = []): string
    {
        if (class_exists('PRESS_LMS_Frontend') && method_exists('PRESS_LMS_Frontend', 'get_student_area_url')) {
            return PRESS_LMS_Frontend::get_student_area_url($screen, $args);
        }

        $base_url = home_url('/meus-cursos/');
        return !empty($args) ? add_query_arg($args, $base_url) : $base_url;
    }

    private static function get_course_frontend_url(int $course_id): string
    {
        $course = get_post($course_id);
        if (!$course instanceof WP_Post || $course->post_type !== 'press_course') {
            return '';
        }

        return home_url('/curso/' . $course->post_name . '/');
    }

    private static function redirect_to_enrollment_notice(string $notice, int $course_id = 0, string $fallback_url = ''): void
    {
        $target = '';

        if ($course_id > 0) {
            $target = self::get_course_frontend_url($course_id);
            if ($target !== '') {
                $target = add_query_arg('notice', sanitize_key($notice), $target);
            }
        }

        if ($target === '' && $fallback_url !== '') {
            $target = wp_validate_redirect($fallback_url, '');
            if ($target !== '') {
                $target = add_query_arg('notice', sanitize_key($notice), $target);
            }
        }

        if ($target === '') {
            $target = class_exists('PRESS_LMS_Frontend') && method_exists('PRESS_LMS_Frontend', 'get_student_area_url')
                ? PRESS_LMS_Frontend::get_student_area_url('catalog')
                : home_url('/');
        }

        wp_safe_redirect($target);
        exit;
    }

    private static function handle_account_avatar_upload(int $user_id)
    {
        if (
            empty($_FILES['profile_avatar']) ||
            !is_array($_FILES['profile_avatar']) ||
            (int) ($_FILES['profile_avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return 0;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_handle_upload('profile_avatar', 0, [], [
            'test_form' => false,
        ]);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $mime_type = (string) get_post_mime_type($attachment_id);
        if ($mime_type === '' || !str_starts_with($mime_type, 'image/')) {
            wp_delete_attachment((int) $attachment_id, true);
            return new WP_Error('profile_avatar_invalid', 'Invalid avatar upload.');
        }

        wp_update_post([
            'ID' => (int) $attachment_id,
            'post_author' => $user_id,
        ]);

        return (int) $attachment_id;
    }

    public static function handle_account_profile_update()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(self::get_student_dashboard_url('courses'));
            exit;
        }

        $user_id = get_current_user_id();

        if (
            !isset($_POST['press_lms_account_profile_nonce']) ||
            !wp_verify_nonce((string) wp_unslash($_POST['press_lms_account_profile_nonce']), 'press_lms_update_account_profile')
        ) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_nonce_invalid']));
            exit;
        }

        $full_name = sanitize_text_field((string) wp_unslash($_POST['full_name'] ?? ''));
        $phone = sanitize_text_field((string) wp_unslash($_POST['phone'] ?? ''));
        $email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
        $remove_avatar = !empty($_POST['remove_avatar']);

        if (strlen(trim($full_name)) < 5) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_name_invalid']));
            exit;
        }

        if ($phone === '' || !PRESS_LMS_Helpers::is_valid_phone_br($phone)) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_phone_invalid']));
            exit;
        }

        if (!is_email($email)) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_email_invalid']));
            exit;
        }

        $email_owner_id = email_exists($email);
        if ($email_owner_id && (int) $email_owner_id !== $user_id) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_email_exists']));
            exit;
        }

        $uploaded_avatar_id = self::handle_account_avatar_upload($user_id);
        if (is_wp_error($uploaded_avatar_id)) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_avatar_invalid']));
            exit;
        }

        $updated_user = wp_update_user([
            'ID' => $user_id,
            'display_name' => $full_name,
            'first_name' => $full_name,
            'nickname' => $full_name,
            'user_email' => $email,
        ]);

        if (is_wp_error($updated_user)) {
            wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_update_failed']));
            exit;
        }

        PRESS_LMS_Helpers::upsert_student_profile($user_id, $full_name, $phone);

        if ($remove_avatar) {
            PRESS_LMS_Helpers::set_student_avatar_id($user_id, 0);
        }

        if ((int) $uploaded_avatar_id > 0) {
            PRESS_LMS_Helpers::set_student_avatar_id($user_id, (int) $uploaded_avatar_id);
        }

        wp_safe_redirect(self::get_student_dashboard_url('profile', ['notice' => 'profile_updated']));
        exit;
    }

    public static function handle_account_password_update()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(self::get_student_dashboard_url('courses'));
            exit;
        }

        $user_id = get_current_user_id();
        $redirect_screen = isset($_POST['redirect_screen'])
            ? sanitize_key((string) $_POST['redirect_screen'])
            : (isset($_POST['redirect_tab']) ? sanitize_key((string) $_POST['redirect_tab']) : 'password');
        $redirect_map = [
            'courses' => 'courses',
            'certificates' => 'certificates',
            'profile' => 'profile',
            'password' => 'password',
        ];
        $redirect_screen = $redirect_map[$redirect_screen] ?? 'password';

        if (
            !isset($_POST['press_lms_account_password_nonce']) ||
            !wp_verify_nonce((string) wp_unslash($_POST['press_lms_account_password_nonce']), 'press_lms_update_account_password')
        ) {
            wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_nonce_invalid']));
            exit;
        }

        $current_password = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
        $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';

        $user = get_userdata($user_id);
        if (!$user) {
            wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_user_invalid']));
            exit;
        }

        if ($current_password === '' || !wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_current_invalid']));
            exit;
        }

        if (strlen(trim($new_password)) < 6) {
            wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_too_short']));
            exit;
        }

        if ($new_password !== $confirm_password) {
            wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_mismatch']));
            exit;
        }

        wp_set_password($new_password, $user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(self::get_student_dashboard_url($redirect_screen, ['notice' => 'password_updated']));
        exit;
    }
    /**
     * Handle the main LMS enrollment CTA.
     */
    public static function handle_enroll()
    {
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        $fallback_url = wp_get_referer() ?: '';

        if (!$course_id || get_post_type($course_id) !== 'press_course') {
            self::redirect_to_enrollment_notice('enroll_invalid_request', 0, $fallback_url);
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce((string) wp_unslash($_POST['_wpnonce']), 'press_lms_enroll_' . $course_id)) {
            self::redirect_to_enrollment_notice('enroll_invalid_request', $course_id, $fallback_url);
        }

        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
            self::redirect_to_enrollment_notice('enroll_course_paused', $course_id, $fallback_url);
        }

        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_permalink')) {
            self::redirect_to_enrollment_notice('enroll_woo_required', $course_id, $fallback_url);
        }

        // Unauthenticated users must go through account login or registration first.
        if (!is_user_logged_in()) {
            $myaccount = wc_get_page_permalink('myaccount');

            $continue_url = add_query_arg([
                'action'    => 'press_lms_enroll_continue',
                'course_id' => $course_id,
                '_wpnonce'  => wp_create_nonce('press_lms_enroll_continue_' . $course_id),
            ], admin_url('admin-post.php'));

            // Keep the redirect URL raw so WooCommerce can preserve it correctly.
            $target = add_query_arg([
                'redirect_to' => $continue_url,
            ], $myaccount);

            wp_safe_redirect($target);
            exit;
        }

        // Logged-in users can continue directly to checkout.
        self::do_enroll_and_redirect_to_checkout(get_current_user_id(), $course_id);
    }

    /**
     * Resume the enrollment flow after WooCommerce login or registration.
     */
    public static function handle_enroll_continue()
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

        if (!$course_id || get_post_type($course_id) !== 'press_course') {
            self::redirect_to_enrollment_notice('enroll_invalid_request');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string) wp_unslash($_GET['_wpnonce']), 'press_lms_enroll_continue_' . $course_id)) {
            self::redirect_to_enrollment_notice('enroll_invalid_request', $course_id);
        }

        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
            self::redirect_to_enrollment_notice('enroll_course_paused', $course_id);
        }

        if (!is_user_logged_in()) {
            // If the customer is still unauthenticated, return to My Account.
            if (class_exists('WooCommerce') && function_exists('wc_get_page_permalink')) {
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }
            self::redirect_to_enrollment_notice('enroll_login_required', $course_id);
        }

        self::do_enroll_and_redirect_to_checkout(get_current_user_id(), $course_id);
    }

    /**
     * Bootstrap the WooCommerce cart/session inside admin-post requests when needed.
     */
    private static function ensure_woo_cart_ready()
    {
        if (!class_exists('WooCommerce') || !function_exists('WC')) return;

        $wc = WC();

        // Load WooCommerce frontend helpers when the request bypasses the normal frontend flow.
        if (method_exists($wc, 'frontend_includes')) {
            $wc->frontend_includes();
        }

        // Initialize the WooCommerce session and cart.
        if (method_exists($wc, 'initialize_session')) {
            $wc->initialize_session();
        }
        if (method_exists($wc, 'initialize_cart')) {
            $wc->initialize_cart();
        }

        // Some installations require an explicit cart bootstrap call.
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    private static function do_enroll_and_redirect_to_checkout($user_id, $course_id)
    {
        if (!class_exists('WooCommerce') || !function_exists('WC') || !function_exists('wc_get_checkout_url')) {
            self::redirect_to_enrollment_notice('enroll_woo_required', (int) $course_id);
        }

        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused((int) $course_id)) {
            self::redirect_to_enrollment_notice('enroll_course_paused', (int) $course_id);
        }

        // Create the pending enrollment before checkout.
        PRESS_LMS_Enrollments::get_or_create_pending((int)$user_id, (int)$course_id, 'woocommerce');

        $product_id = PRESS_LMS_Enrollments::get_course_product_id((int)$course_id);
        if (!$product_id || !get_post($product_id)) {
            self::redirect_to_enrollment_notice('enroll_product_missing', (int) $course_id);
        }

        self::ensure_woo_cart_ready();

        if (!WC()->cart) {
            self::redirect_to_enrollment_notice('enroll_cart_unavailable', (int) $course_id);
        }

        // Replace cart contents so checkout contains only the selected course.
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart((int)$product_id, 1);

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    /**
     * Make WooCommerce honor redirect_to during login.
     */
    public static function woo_login_redirect($redirect, $user)
    {
        if (!empty($_REQUEST['redirect_to'])) {
            $requested = wp_unslash($_REQUEST['redirect_to']);
            $safe = wp_validate_redirect($requested, $redirect);
            return $safe;
        }

        return self::default_dashboard_redirect_for_user($user, $redirect);
    }

    /**
     * Make WooCommerce honor redirect_to during registration.
     */
    public static function woo_registration_redirect($redirect)
    {
        if (!empty($_REQUEST['redirect_to'])) {
            $requested = wp_unslash($_REQUEST['redirect_to']);
            $safe = wp_validate_redirect($requested, $redirect);
            return $safe;
        }

        $user = wp_get_current_user();
        return self::default_dashboard_redirect_for_user($user, $redirect);
    }

    public static function default_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!empty($requested_redirect_to)) {
            return $redirect_to;
        }

        return self::default_dashboard_redirect_for_user($user, $redirect_to);
    }

    private static function default_dashboard_redirect_for_user($user, string $fallback): string
    {
        if (is_wp_error($user) || !$user instanceof WP_User) {
            return $fallback;
        }

        if (user_can($user, 'manage_options')) {
            return $fallback;
        }

        $roles = (array) $user->roles;
        if (in_array('press_student', $roles, true)) {
            return self::get_student_dashboard_url('courses');
        }

        if (
            class_exists('PRESS_LMS_Enrollments') &&
            method_exists('PRESS_LMS_Enrollments', 'has_any_enrollment') &&
            PRESS_LMS_Enrollments::has_any_enrollment((int) $user->ID)
        ) {
            return self::get_student_dashboard_url('courses');
        }

        return $fallback;
    }
}
