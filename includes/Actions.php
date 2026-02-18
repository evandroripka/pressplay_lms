<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Actions
{
    public static function init()
    {
        add_action('admin_post_mlb_lms_enroll', [__CLASS__, 'handle_enroll']);
        add_action('admin_post_nopriv_mlb_lms_enroll', [__CLASS__, 'handle_enroll']);
    }

    public static function handle_enroll()
    {
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

        if (!$course_id || get_post_type($course_id) !== 'mlb_course') {
            wp_die('Curso inválido.');
        }

        // Se não logado: manda pro login e depois volta pro curso
        if (!is_user_logged_in()) {
            $redirect = get_permalink($course_id);
            $login_url = wp_login_url($redirect);
            wp_redirect($login_url);
            exit;
        }

        // Nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mlb_lms_enroll_' . $course_id)) {
            wp_die('Nonce inválido.');
        }

        // Precisa de Woo
        if (!class_exists('WooCommerce') || !function_exists('WC')) {
            wp_die('WooCommerce é obrigatório para matrícula.');
        }

        $user_id = get_current_user_id();

        // Cria/atualiza matrícula pending (se já tiver active, isso não atrapalha)
        MLB_LMS_Enrollments::get_or_create_pending($user_id, $course_id, 'woocommerce');

        $product_id = MLB_LMS_Enrollments::get_course_product_id($course_id);

        if (!$product_id || !get_post($product_id)) {
            wp_die('Produto do curso não encontrado. Verifique se o curso tem preço e se o Woo gerou o produto.');
        }

        // Coloca no carrinho e vai pro checkout
        WC()->cart->add_to_cart($product_id, 1);

        wp_redirect(wc_get_checkout_url());
        exit;
    }
}
