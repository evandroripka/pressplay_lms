<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Course_Meta
{
    public static function init()
    {
        add_action('add_meta_boxes_mlb_course', [__CLASS__, 'add_boxes']);
        add_action('save_post_mlb_course', [__CLASS__, 'save'], 10, 2);
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

        $trailer = get_post_meta($post->ID, '_mlb_course_trailer', true);
        $product_id = get_post_meta($post->ID, '_mlb_course_product_id', true);

        echo '<p><label><strong>Trailer (YouTube/Vimeo URL)</strong></label><br>';
        echo '<input type="url" name="mlb_course_trailer" value="' . esc_attr($trailer) . '" class="widefat" placeholder="https://vimeo.com/... ou https://youtu.be/..."></p>';

        echo '<p><label><strong>ID do Produto WooCommerce</strong></label><br>';
        echo '<input type="number" name="mlb_course_product_id" value="' . esc_attr($product_id) . '" class="small-text"> ';
        echo '<span style="color:#666"> (por enquanto você cria o produto no Woo e cola o ID aqui)</span></p>';

        echo '<hr>';
        echo '<p style="color:#666">MVP: Galeria de imagens podemos fazer depois (Media Uploader). Primeiro vamos fechar curso/aulas/materiais.</p>';
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['mlb_course_meta_nonce']) || !wp_verify_nonce($_POST['mlb_course_meta_nonce'], 'mlb_course_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['mlb_course_trailer'])) {
            update_post_meta($post_id, '_mlb_course_trailer', esc_url_raw($_POST['mlb_course_trailer']));
        }

        if (isset($_POST['mlb_course_product_id'])) {
            update_post_meta($post_id, '_mlb_course_product_id', intval($_POST['mlb_course_product_id']));
        }
    }
}
