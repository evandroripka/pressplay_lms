<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Woo
{
    public static function init()
    {
        // cria/atualiza produto quando salvar curso
        add_action('save_post_press_course', [__CLASS__, 'maybe_sync_product'], 20, 2);
        // cria pending ao adicionar no carrinho pela loja normal
        add_action('woocommerce_add_to_cart', [__CLASS__, 'handle_add_to_cart'], 10, 6);
        // garante pending quando o pedido é criado no checkout
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'handle_order_processed'], 10, 3);
        // ativa matrícula quando pedido for pago/processado
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_completed'], 10, 1);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_order_completed'], 10, 1);
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'filter_is_purchasable'], 10, 2);
    }

    /**
     * Quando um produto é adicionado ao carrinho via loja normal do Woo.
     * Se o produto estiver ligado a um curso e o usuário estiver logado,
     * cria/atualiza matrícula pending.
     */
    public static function handle_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (!class_exists('WooCommerce')) return;

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            // guest ainda não tem user_id, então deixamos o checkout_order_processed cobrir
            return;
        }

        $course_id = self::get_course_id_from_product_id((int)$product_id);
        if ($course_id <= 0) return;
        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) return;

        PRESS_LMS_Enrollments::get_or_create_pending($user_id, $course_id, 'woocommerce');
    }

    /**
     * Quando o pedido é criado no checkout.
     * Garante que exista matrícula pending mesmo se o usuário não passou
     * pelo botão do LMS nem pelo add_to_cart estando logado.
     */
    public static function handle_order_processed($order_id, $posted_data, $order)
    {
        if (!class_exists('WooCommerce')) return;

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) return;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = (int) $product->get_id();
            $course_id = self::get_course_id_from_product_id($product_id);

            if ($course_id > 0) {
                if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
                    continue;
                }
                PRESS_LMS_Enrollments::get_or_create_pending($user_id, $course_id, 'woocommerce');
            }
        }
    }

    /**
     * Ativa a matrícula quando o pedido estiver pago/processado.
     */
    public static function handle_order_completed($order_id)
    {
        if (!class_exists('WooCommerce')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) return;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = (int) $product->get_id();
            $course_id = self::get_course_id_from_product_id($product_id);

            if ($course_id > 0) {
                if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
                    continue;
                }
                PRESS_LMS_Enrollments::activate_enrollment($user_id, $course_id, (int)$order_id, 'woocommerce');
            }
        }
    }

    /**
     * Resolve qual curso pertence a um produto Woo.
     */
    private static function get_course_id_from_product_id(int $product_id): int
    {
        if ($product_id <= 0) return 0;

        // Preferencial: produto guarda course_id
        $course_id = (int) get_post_meta($product_id, '_press_course_id', true);
        if ($course_id > 0) {
            return $course_id;
        }

        // Fallback: achar curso pelo meta _press_course_product_id
        $q = new WP_Query([
            'post_type'      => 'press_course',
            'post_status'    => 'publish',
            'meta_key'       => '_press_course_product_id',
            'meta_value'     => $product_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($q->posts[0])) {
            return (int) $q->posts[0];
        }

        return 0;
    }

    private static function woo_active()
    {
        return class_exists('WooCommerce') && class_exists('WC_Product_Simple');
    }

    public static function maybe_sync_product($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Só quando estiver publicado
        if ($post->post_status !== 'publish') return;

        if (!self::woo_active()) return;

        $existing_product_id = (int) get_post_meta($post_id, '_press_course_product_id', true);
        $price = get_post_meta($post_id, '_press_course_price', true);
        $price = $price !== '' ? $price : '0';
        $is_paused = class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused((int) $post_id);

        if ($is_paused) {
            self::sync_course_product_state((int) $post_id);
            return;
        }

        // Se não tem preço ainda, não cria
        if ((float)$price <= 0) return;

        if ($existing_product_id > 0 && get_post($existing_product_id)) {
            self::update_product($existing_product_id, $post, $price, $post_id);
            return;
        }

        $new_product_id = self::create_product($post, $price, $post_id);
        if ($new_product_id) {
            update_post_meta($post_id, '_press_course_product_id', $new_product_id);
        }
    }

    private static function create_product($course_post, $price, $course_id)
    {
        $product = new WC_Product_Simple();

        $product->set_name($course_post->post_title);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');

        // Curso é serviço digital
        $product->set_virtual(true);
        $product->set_downloadable(false);

        $product->set_regular_price($price);

        // SKU
        $product->set_sku('PRESS-COURSE-' . $course_id);

        // Linkar produto -> curso
        $product->update_meta_data('_press_course_id', $course_id);

        // Thumbnail do curso
        $thumb_id = get_post_thumbnail_id($course_id);
        if ($thumb_id) {
            $product->set_image_id($thumb_id);
        }

        $product_id = $product->save();

        wp_update_post([
            'ID' => $product_id,
            'post_content' => wp_strip_all_tags($course_post->post_content),
        ]);

        return $product_id;
    }

    private static function update_product($product_id, $course_post, $price, $course_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) return;

        $product->set_name($course_post->post_title);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_regular_price($price);

        $thumb_id = get_post_thumbnail_id($course_id);
        if ($thumb_id) {
            $product->set_image_id($thumb_id);
        }

        $product->update_meta_data('_press_course_id', $course_id);
        $product->save();
    }

    public static function sync_course_product_state(int $course_id): void
    {
        if (!self::woo_active()) return;

        $product_id = (int) get_post_meta($course_id, '_press_course_product_id', true);
        if ($product_id <= 0) return;

        $product = wc_get_product($product_id);
        if (!$product) return;

        $is_paused = class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id);

        if ($is_paused) {
            $product->set_catalog_visibility('hidden');
            $product->set_status('draft');
        } else {
            $product->set_catalog_visibility('visible');
            $product->set_status('publish');
        }

        $product->save();
    }

    public static function filter_is_purchasable($is_purchasable, $product)
    {
        if (!$is_purchasable || !$product instanceof WC_Product) {
            return $is_purchasable;
        }

        $course_id = self::get_course_id_from_product_id((int) $product->get_id());
        if ($course_id <= 0) {
            return $is_purchasable;
        }

        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
            return false;
        }

        return $is_purchasable;
    }
}
