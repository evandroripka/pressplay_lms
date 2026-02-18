<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Woo
{
    public static function init()
    {
        add_action('save_post_mlb_course', [__CLASS__, 'maybe_sync_product'], 20, 2);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_order_completed'], 10, 1);
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
        if ($post->post_status !== 'publish') return;
        if (!self::woo_active()) return;

        $existing_product_id = (int) get_post_meta($post_id, '_mlb_course_product_id', true);
        $price = get_post_meta($post_id, '_mlb_course_price', true);
        $price = $price !== '' ? $price : '0';

        if ((float)$price <= 0) return;

        if ($existing_product_id > 0 && get_post($existing_product_id)) {
            self::update_product($existing_product_id, $post, $price, $post_id);
            return;
        }

        $new_product_id = self::create_product($post, $price, $post_id);
        if ($new_product_id) {
            update_post_meta($post_id, '_mlb_course_product_id', $new_product_id);
        }
    }

    public static function handle_order_completed($order_id)
    {
        if (!self::woo_active()) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) return;

        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            if ($product_id <= 0) continue;

            $course_id = (int) get_post_meta($product_id, '_mlb_course_id', true);
            if ($course_id <= 0) continue;

            MLB_LMS_Enrollments::upsert_active_enrollment($user_id, $course_id, $order_id, 'woocommerce');
        }
    }

    private static function create_product($course_post, $price, $course_id)
    {
        $product = new WC_Product_Simple();

        $product->set_name($course_post->post_title);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_regular_price($price);
        $product->set_sku('MLB-COURSE-' . $course_id);
        $product->update_meta_data('_mlb_course_id', $course_id);

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
        $product->set_regular_price($price);

        $thumb_id = get_post_thumbnail_id($course_id);
        if ($thumb_id) {
            $product->set_image_id($thumb_id);
        }

        $product->update_meta_data('_mlb_course_id', $course_id);
        $product->save();
    }
}
