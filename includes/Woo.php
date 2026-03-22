<?php
if (!defined('ABSPATH')) exit;

/**
 * WooCommerce bridge for course products, checkout flow, and payment-driven enrollment sync.
 */
class PRESS_LMS_Woo
{
    private const ACCOUNT_ENDPOINT = 'area-do-aluno';
    private const INVALIDATING_ORDER_STATUSES = ['cancelled', 'failed', 'refunded'];

    public static function init()
    {
        add_action('init', [__CLASS__, 'register_account_endpoint']);
        add_action('template_redirect', [__CLASS__, 'maybe_redirect_account_endpoint']);
        // Keep the linked WooCommerce product in sync with the course post.
        add_action('save_post_press_course', [__CLASS__, 'maybe_sync_product'], 20, 2);
        // Create pending enrollments when course products enter the cart.
        add_action('woocommerce_add_to_cart', [__CLASS__, 'handle_add_to_cart'], 10, 6);
        // Ensure pending enrollments also exist when the order is created at checkout.
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'handle_order_processed'], 10, 3);
        // React to payment confirmation through the official WooCommerce payment lifecycle.
        add_action('woocommerce_payment_complete', [__CLASS__, 'handle_payment_complete'], 10, 2);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_changed'], 10, 4);
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'filter_is_purchasable'], 10, 2);
        add_filter('woocommerce_is_sold_individually', [__CLASS__, 'filter_is_sold_individually'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'normalize_course_cart_quantities'], 20, 1);
        add_filter('woocommerce_loop_add_to_cart_link', [__CLASS__, 'filter_loop_add_to_cart_link'], 10, 3);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'filter_account_menu_items']);
        add_filter('woocommerce_get_endpoint_url', [__CLASS__, 'filter_account_endpoint_url'], 10, 4);
        add_action('woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', [__CLASS__, 'render_student_account_endpoint']);
        add_action('wp', [__CLASS__, 'maybe_swap_single_product_button']);
    }

    public static function register_account_endpoint(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_rewrite_endpoint(self::ACCOUNT_ENDPOINT, EP_ROOT | EP_PAGES);
    }

    private static function get_student_profile_url(): string
    {
        if (class_exists('PRESS_LMS_Frontend') && method_exists('PRESS_LMS_Frontend', 'get_student_area_url')) {
            return PRESS_LMS_Frontend::get_student_area_url('profile');
        }

        return home_url('/perfil/');
    }

    public static function maybe_redirect_account_endpoint(): void
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_permalink')) {
            return;
        }

        $myaccount_url = wc_get_page_permalink('myaccount');
        if (!$myaccount_url) {
            return;
        }

        $request_path = class_exists('PRESS_LMS_Rewrite') && method_exists('PRESS_LMS_Rewrite', 'get_request_path')
            ? PRESS_LMS_Rewrite::get_request_path()
            : trim((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $endpoint_path = trim((string) wp_parse_url(trailingslashit($myaccount_url) . self::ACCOUNT_ENDPOINT . '/', PHP_URL_PATH), '/');

        if ($request_path !== '' && $endpoint_path !== '' && $request_path === $endpoint_path) {
            wp_safe_redirect(self::get_student_profile_url());
            exit;
        }
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

        $user_id = self::get_order_user_id($order);
        if ($user_id <= 0) return;

        $provider = self::get_order_payment_provider($order);

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = (int) $product->get_id();
            $course_id = self::get_course_id_from_product_id($product_id);

            if ($course_id > 0) {
                if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
                    continue;
                }
                PRESS_LMS_Enrollments::get_or_create_pending($user_id, $course_id, $provider);
                PRESS_LMS_Enrollments::attach_order_to_pending_enrollment($user_id, $course_id, (int) $order_id, $provider);
            }
        }
    }

    /**
     * Activate enrollments when WooCommerce confirms payment explicitly.
     */
    public static function handle_payment_complete($order_id, $transaction_id = ''): void
    {
        if (!class_exists('WooCommerce')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        self::activate_order_enrollments($order);
    }

    /**
     * React to generic order status changes so gateway-specific webhooks remain compatible.
     */
    public static function handle_order_status_changed($order_id, $from_status, $to_status, $order = null): void
    {
        if (!class_exists('WooCommerce')) return;

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        $to_status = self::normalize_order_status($to_status);

        if (self::is_paid_order_status($to_status)) {
            self::activate_order_enrollments($order);
            return;
        }

        if (self::is_invalidating_order_status($to_status)) {
            self::handle_order_invalidated($order_id, $order);
        }
    }

    /**
     * Preserve the legacy public method name for internal compatibility.
     */
    public static function handle_order_completed($order_id)
    {
        if (!class_exists('WooCommerce')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        self::activate_order_enrollments($order);
    }

    /**
     * Activate every enrollment linked to paid course products in the order.
     */
    private static function activate_order_enrollments(WC_Order $order): void
    {
        $user_id = self::get_order_user_id($order);
        if ($user_id <= 0) return;

        $provider = self::get_order_payment_provider($order);

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = (int) $product->get_id();
            $course_id = self::get_course_id_from_product_id($product_id);

            if ($course_id > 0) {
                if (class_exists('PRESS_LMS_Enrollments') && PRESS_LMS_Enrollments::is_course_paused($course_id)) {
                    continue;
                }
                PRESS_LMS_Enrollments::activate_enrollment($user_id, $course_id, (int) $order->get_id(), $provider);
            }
        }
    }

    /**
     * Revoke access when the linked WooCommerce order becomes invalid.
     */
    public static function handle_order_invalidated($order_id, $order = null): void
    {
        if (!class_exists('WooCommerce')) return;

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) return;

        $user_id = self::get_order_user_id($order);
        if ($user_id <= 0) return;

        $status = self::normalize_order_status((string) $order->get_status());
        $enrollment_status = self::is_invalidating_order_status($status)
            ? $status
            : 'cancelled';

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $product_id = (int) $product->get_id();
            $course_id = self::get_course_id_from_product_id($product_id);
            if ($course_id <= 0) {
                continue;
            }

            PRESS_LMS_Enrollments::deactivate_enrollment($user_id, $course_id, $enrollment_status, (int) $order_id);
        }
    }

    /**
     * Resolve the gateway identifier stored on the WooCommerce order.
     */
    private static function get_order_payment_provider(WC_Order $order): string
    {
        $gateway_id = sanitize_key((string) $order->get_payment_method());
        if ($gateway_id === '') {
            return 'woocommerce';
        }

        return substr($gateway_id, 0, 40);
    }

    /**
     * Prefer the linked customer account, but fall back to billing email when possible.
     */
    private static function get_order_user_id(WC_Order $order): int
    {
        $user_id = (int) $order->get_user_id();
        if ($user_id > 0) {
            return $user_id;
        }

        $email = sanitize_email((string) $order->get_billing_email());
        if ($email === '') {
            return 0;
        }

        $user = get_user_by('email', $email);
        return $user instanceof WP_User ? (int) $user->ID : 0;
    }

    /**
     * Normalize order statuses coming from core hooks or custom extensions.
     */
    private static function normalize_order_status(string $status): string
    {
        $status = sanitize_key($status);

        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }

        return $status;
    }

    /**
     * Respect WooCommerce's paid-status list so gateway plugins and custom statuses stay compatible.
     */
    private static function is_paid_order_status(string $status): bool
    {
        $status = self::normalize_order_status($status);
        if ($status === '') {
            return false;
        }

        if (!function_exists('wc_get_is_paid_statuses')) {
            return in_array($status, ['processing', 'completed'], true);
        }

        $paid_statuses = array_map([__CLASS__, 'normalize_order_status'], (array) wc_get_is_paid_statuses());
        return in_array($status, $paid_statuses, true);
    }

    /**
     * Identify statuses that should revoke LMS access.
     */
    private static function is_invalidating_order_status(string $status): bool
    {
        return in_array(self::normalize_order_status($status), self::INVALIDATING_ORDER_STATUSES, true);
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

        $course = get_post($course_id);
        if (!$course instanceof WP_Post || $course->post_type !== 'press_course') {
            return '';
        }

        return home_url('/curso/' . $course->post_name . '/');
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

        if (
            is_user_logged_in() &&
            class_exists('PRESS_LMS_Enrollments') &&
            PRESS_LMS_Enrollments::has_active_enrollment(get_current_user_id(), $course_id)
        ) {
            wc_add_notice('Você já tem acesso ativo a este curso.', 'notice');
            return false;
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

    public static function filter_account_menu_items(array $items): array
    {
        if (!class_exists('WooCommerce')) {
            return $items;
        }

        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);

        $items[self::ACCOUNT_ENDPOINT] = 'Área do aluno';

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public static function filter_account_endpoint_url($url, $endpoint, $value, $permalink): string
    {
        if ($endpoint !== self::ACCOUNT_ENDPOINT) {
            return (string) $url;
        }

        return self::get_student_profile_url();
    }

    public static function render_student_account_endpoint(): void
    {
        $links = class_exists('PRESS_LMS_Frontend') && method_exists('PRESS_LMS_Frontend', 'get_student_menu_items')
            ? PRESS_LMS_Frontend::get_student_menu_items()
            : [];

        echo '<h3>Área do aluno</h3>';
        echo '<p>Acesse rapidamente o showroom, seus cursos, certificados, perfil e troca de senha.</p>';

        if (empty($links)) {
            echo '<p>Nenhum atalho da área do aluno foi encontrado.</p>';
            return;
        }

        echo '<div class="presslms-woo-student-links">';

        foreach ($links as $link) {
            $label = (string) ($link['label'] ?? '');
            $url = (string) ($link['url'] ?? '');

            if ($label === '' || $url === '') {
                continue;
            }

            echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
        }

        echo '</div>';
    }
}
