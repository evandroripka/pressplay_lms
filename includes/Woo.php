<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Woo
{
    public static function init()
    {
        // Keep the linked WooCommerce product in sync with the course post.
        add_action('save_post_press_course', [__CLASS__, 'maybe_sync_product'], 20, 2);
        // Create pending enrollments when course products enter the cart.
        add_action('woocommerce_add_to_cart', [__CLASS__, 'handle_add_to_cart'], 10, 6);
        // Ensure pending enrollments also exist when the order is created at checkout.
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'handle_order_processed'], 10, 3);
        // Activate enrollments once the order is paid or processed.
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_completed'], 10, 1);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_order_completed'], 10, 1);
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'filter_is_purchasable'], 10, 2);
        add_filter('woocommerce_is_sold_individually', [__CLASS__, 'filter_is_sold_individually'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'normalize_course_cart_quantities'], 20, 1);
        add_filter('woocommerce_loop_add_to_cart_link', [__CLASS__, 'filter_loop_add_to_cart_link'], 10, 3);
        add_action('wp', [__CLASS__, 'maybe_swap_single_product_button']);
    }

    /**
     * Create or refresh a pending enrollment when a linked course product is added to the cart.
     */
    public static function handle_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (!class_exists('WooCommerce')) return;

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            // Guest checkouts are handled later when the order is created.
            return;
        }

        $course_id = self::get_course_id_from_product_id((int)$product_id);
        if ($course_id <= 0) return;
        if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) return;

        PRESS_LMS_Enrollments::get_or_create_pending($user_id, $course_id, 'woocommerce');
    }

    /**
     * Ensure a pending enrollment exists even when the order is created without using the LMS CTA.
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
     * Activate the enrollment when payment is confirmed.
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
     * Resolve the course linked to a WooCommerce product.
     */
    private static function get_course_id_from_product_id(int $product_id): int
    {
        if ($product_id <= 0) return 0;

        // Prefer the product-level course reference when it exists.
        $course_id = (int) get_post_meta($product_id, '_press_course_id', true);
        if ($course_id > 0) {
            return $course_id;
        }

        // Fall back to the course-level product reference.
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

    private static function get_course_url_from_product_id(int $product_id): string
    {
        $course_id = self::get_course_id_from_product_id($product_id);
        if ($course_id <= 0) {
            return '';
        }

        $course_url = get_permalink($course_id);
        return $course_url ? (string) $course_url : '';
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

        // Only sync purchasable products for published courses.
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

        // Skip product creation until a valid price exists.
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
        $product->set_sold_individually(true);

        // Courses behave as virtual, non-downloadable products.
        $product->set_virtual(true);
        $product->set_downloadable(false);

        $product->set_regular_price($price);

        // Use a deterministic SKU so the product can be located easily.
        $product->set_sku('PRESS-COURSE-' . $course_id);

        // Store the course reference on the WooCommerce product.
        $product->update_meta_data('_press_course_id', $course_id);

        // Reuse the course featured image as the product thumbnail.
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
        $product->set_sold_individually(true);
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

    public static function filter_is_sold_individually($sold_individually, $product)
    {
        if (!$product instanceof WC_Product) {
            return $sold_individually;
        }

        return self::get_course_id_from_product_id((int) $product->get_id()) > 0
            ? true
            : $sold_individually;
    }

    public static function validate_add_to_cart($passed, $product_id, $quantity, $variation_id, $variations, $cart_item_data)
    {
        $course_id = self::get_course_id_from_product_id((int) $product_id);
        if ($course_id <= 0) {
            return $passed;
        }

        if ((int) $quantity > 1) {
            wc_add_notice('Cursos só podem ser adicionados uma vez ao carrinho.', 'error');
            return false;
        }

        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if ((int) ($cart_item['product_id'] ?? 0) === (int) $product_id) {
                    wc_add_notice('Este curso já está no carrinho.', 'notice');
                    return false;
                }
            }
        }

        return $passed;
    }

    public static function normalize_course_cart_quantities($cart): void
    {
        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = (int) ($cart_item['product_id'] ?? 0);
            if ($product_id <= 0) {
                continue;
            }

            if (self::get_course_id_from_product_id($product_id) <= 0) {
                continue;
            }

            if ((int) ($cart_item['quantity'] ?? 1) > 1) {
                $cart->set_quantity($cart_item_key, 1, false);
            }
        }
    }

    public static function filter_loop_add_to_cart_link($html, $product, $args)
    {
        if (!$product instanceof WC_Product) {
            return $html;
        }

        $course_url = self::get_course_url_from_product_id((int) $product->get_id());
        if ($course_url === '') {
            return $html;
        }

        $class_name = 'button';
        if (is_array($args) && !empty($args['class'])) {
            $class_name = (string) $args['class'];
        }

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($course_url),
            esc_attr($class_name),
            esc_html__('Ver curso', 'pressplay-lms')
        );
    }

    public static function maybe_swap_single_product_button(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        $product_id = get_the_ID();
        if (!$product_id || self::get_course_id_from_product_id((int) $product_id) <= 0) {
            return;
        }

        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'render_single_view_course_button'], 30);
    }

    public static function render_single_view_course_button(): void
    {
        global $product;

        if (!$product instanceof WC_Product) {
            $product = wc_get_product(get_the_ID());
        }

        if (!$product instanceof WC_Product) {
            return;
        }

        $course_url = self::get_course_url_from_product_id((int) $product->get_id());
        if ($course_url === '') {
            return;
        }

        echo '<p class="presslms-product-course-link">';
        echo '<a class="button alt" href="' . esc_url($course_url) . '">Ver curso</a>';
        echo '</p>';
    }
}
