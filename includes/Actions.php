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

        // Faz o WooCommerce respeitar redirect_to no login/registro
        add_filter('woocommerce_login_redirect', [__CLASS__, 'woo_login_redirect'], 10, 2);
        add_filter('woocommerce_registration_redirect', [__CLASS__, 'woo_registration_redirect'], 10, 1);
    }

    /**
     * Clique no botão "Matricular"
     */
    public static function handle_enroll()
    {
        $course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

        if (!$course_id || get_post_type($course_id) !== 'press_course') {
            wp_die('Curso inválido.');
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'press_lms_enroll_' . $course_id)) {
            wp_die('Nonce inválido.');
        }

        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_permalink')) {
            wp_die('WooCommerce é obrigatório para matrícula.');
        }

        // Se não logado: manda pro My Account do Woo + redirect_to para continuar matrícula
        if (!is_user_logged_in()) {
            $myaccount = wc_get_page_permalink('myaccount');

            $continue_url = add_query_arg([
                'action'    => 'press_lms_enroll_continue',
                'course_id' => $course_id,
                '_wpnonce'  => wp_create_nonce('press_lms_enroll_continue_' . $course_id),
            ], admin_url('admin-post.php'));

            // IMPORTANTÍSSIMO: não duplo-encode. Só valida depois.
            $target = add_query_arg([
                'redirect_to' => $continue_url,
            ], $myaccount);

            wp_safe_redirect($target);
            exit;
        }

        // Se já logado, vai direto pro checkout
        self::do_enroll_and_redirect_to_checkout(get_current_user_id(), $course_id);
    }

    /**
     * Endpoint chamado após login/registro no Woo (via redirect_to)
     */
    public static function handle_enroll_continue()
    {
        $course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

        if (!$course_id || get_post_type($course_id) !== 'press_course') {
            wp_die('Curso inválido.');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'press_lms_enroll_continue_' . $course_id)) {
            wp_die('Nonce inválido.');
        }

        if (!is_user_logged_in()) {
            // ainda não logou, manda pra minha conta
            if (class_exists('WooCommerce') && function_exists('wc_get_page_permalink')) {
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }
            wp_die('Você precisa estar logado.');
        }

        self::do_enroll_and_redirect_to_checkout(get_current_user_id(), $course_id);
    }

    /**
     * Garante que Woo cart/session existe mesmo no admin-post.php
     */
    private static function ensure_woo_cart_ready()
    {
        if (!class_exists('WooCommerce') || !function_exists('WC')) return;

        $wc = WC();

        // Carrega includes de frontend quando estamos fora do fluxo normal do Woo
        if (method_exists($wc, 'frontend_includes')) {
            $wc->frontend_includes();
        }

        // Inicializa sessão/carrinho
        if (method_exists($wc, 'initialize_session')) {
            $wc->initialize_session();
        }
        if (method_exists($wc, 'initialize_cart')) {
            $wc->initialize_cart();
        }

        // Fallback extra (algumas instalações precisam disso)
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    private static function do_enroll_and_redirect_to_checkout($user_id, $course_id)
    {
        if (!class_exists('WooCommerce') || !function_exists('WC') || !function_exists('wc_get_checkout_url')) {
            wp_die('WooCommerce é obrigatório para matrícula.');
        }

        // matrícula pending
        PRESS_LMS_Enrollments::get_or_create_pending((int)$user_id, (int)$course_id, 'woocommerce');

        $product_id = PRESS_LMS_Enrollments::get_course_product_id((int)$course_id);
        if (!$product_id || !get_post($product_id)) {
            wp_die('Produto do curso não encontrado. Verifique se o curso tem preço e se o Woo gerou o produto.');
        }

        self::ensure_woo_cart_ready();

        if (!WC()->cart) {
            wp_die('Carrinho WooCommerce não inicializado.');
        }

        // Evita checkout com itens aleatórios
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart((int)$product_id, 1);

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    /**
     * Faz o Woo respeitar redirect_to no login.
     */
    public static function woo_login_redirect($redirect, $user)
    {
        if (!empty($_REQUEST['redirect_to'])) {
            $requested = wp_unslash($_REQUEST['redirect_to']);
            $safe = wp_validate_redirect($requested, $redirect);
            return $safe;
        }
        return $redirect;
    }

    /**
     * Faz o Woo respeitar redirect_to no registro.
     */
    public static function woo_registration_redirect($redirect)
    {
        if (!empty($_REQUEST['redirect_to'])) {
            $requested = wp_unslash($_REQUEST['redirect_to']);
            $safe = wp_validate_redirect($requested, $redirect);
            return $safe;
        }
        return $redirect;
    }
    
}
