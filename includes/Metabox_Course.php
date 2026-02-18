<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Course_Meta
{
    public static function init()
    {
        add_action('add_meta_boxes_mlb_course', [__CLASS__, 'add_boxes']);
        add_action('save_post_mlb_course', [__CLASS__, 'save'], 10, 2);
        add_action('admin_notices', [__CLASS__, 'maybe_show_price_notice']);
    }

    public static function add_boxes()
    {
        add_meta_box(
            'mlb_course_details',
            'Detalhes do Curso',
            [__CLASS__, 'render'],
            'mlb_course',
            'normal',
            'high'
        );
    }

    public static function render($post)
    {
        wp_nonce_field('mlb_course_meta_save', 'mlb_course_meta_nonce');

        $trailer    = get_post_meta($post->ID, '_mlb_course_trailer', true);
        $product_id = (int) get_post_meta($post->ID, '_mlb_course_product_id', true);
        $price      = get_post_meta($post->ID, '_mlb_course_price', true);

        echo '<p><label><strong>Preço (USD)</strong></label><br>';
        echo '<input type="text" name="mlb_course_price" value="' . esc_attr($price) . '" class="small-text" placeholder="99.90"> ';
        echo '<span style="color:#666">Aceita 99.90 ou 99,90. Ao publicar, o produto WooCommerce será criado/atualizado automaticamente.</span></p>';

        echo '<p><label><strong>Trailer (YouTube/Vimeo URL)</strong></label><br>';
        echo '<input type="url" name="mlb_course_trailer" value="' . esc_attr($trailer) . '" class="widefat" placeholder="https://vimeo.com/... ou https://youtu.be/...\"></p>';

        echo '<hr>';
        echo '<p><label><strong>Produto WooCommerce</strong></label><br>';

        if ($product_id > 0 && get_post($product_id)) {
            $edit_link = get_edit_post_link($product_id);
            echo '<span style="display:inline-block;padding:6px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;">';
            echo 'ID: <strong>' . esc_html($product_id) . '</strong>';
            echo '</span> ';
            if ($edit_link) {
                echo '<a class="button" href="' . esc_url($edit_link) . '" style="margin-left:8px;">Editar produto</a>';
            }
            echo '<br><span style="color:#666">Esse produto foi gerado automaticamente pelo Pressplay LMS.</span>';
        } else {
            echo '<span style="color:#666">Ainda não criado. Publique o curso com um preço válido e com WooCommerce ativo.</span>';
        }

        echo '</p>';
    }

    private static function normalize_price($raw_price)
    {
        $raw_price = trim((string) $raw_price);
        if ($raw_price === '') return null;

        if (!preg_match('/^\d+(?:[\.,]\d{1,2})?$/', $raw_price)) {
            return false;
        }

        $normalized = str_replace(',', '.', $raw_price);
        if (substr_count($normalized, '.') > 1) {
            return false;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public static function maybe_show_price_notice()
    {
        if (!current_user_can('edit_posts')) return;

        if (!empty($_GET['mlb_course_price_invalid'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Preço inválido. Use apenas formato 99.90 ou 99,90.</p></div>';
        }
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['mlb_course_meta_nonce']) || !wp_verify_nonce($_POST['mlb_course_meta_nonce'], 'mlb_course_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['mlb_course_price'])) {
            $normalized = self::normalize_price(wp_unslash($_POST['mlb_course_price']));
            if ($normalized === false) {
                add_filter('redirect_post_location', function ($location) {
                    return add_query_arg('mlb_course_price_invalid', '1', $location);
                });
            } elseif ($normalized !== null) {
                update_post_meta($post_id, '_mlb_course_price', $normalized);
            }
        }

        if (isset($_POST['mlb_course_trailer'])) {
            update_post_meta($post_id, '_mlb_course_trailer', esc_url_raw($_POST['mlb_course_trailer']));
        }
    }
}
