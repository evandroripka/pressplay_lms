<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Lesson_Meta
{
    public static function init()
    {
        add_action('add_meta_boxes_mlb_lesson', [__CLASS__, 'add_boxes']);
        add_action('save_post_mlb_lesson', [__CLASS__, 'save'], 10, 2);
    }

    public static function add_boxes()
    {
        add_meta_box(
            'mlb_lesson_details',
            'Detalhes da Aula',
            [__CLASS__, 'render'],
            'mlb_lesson',
            'normal',
            'high'
        );
    }

    public static function render($post)
    {
        wp_nonce_field('mlb_lesson_meta_save', 'mlb_lesson_meta_nonce');

        $course_id = get_post_meta($post->ID, '_mlb_lesson_course_id', true);
        $video_url = get_post_meta($post->ID, '_mlb_lesson_video_url', true);
        $materials = get_post_meta($post->ID, '_mlb_lesson_materials', true);

        if (!is_array($materials)) $materials = [];

        // Lista cursos para selecionar
        $courses = get_posts([
            'post_type' => 'mlb_course',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<p><label><strong>Curso</strong></label><br>';
        echo '<select name="mlb_lesson_course_id" class="widefat">';
        echo '<option value="">-- Selecione --</option>';
        foreach ($courses as $c) {
            $selected = ((int)$course_id === (int)$c->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($c->ID) . '" ' . $selected . '>' . esc_html($c->post_title) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label><strong>Vídeo (Vimeo/YouTube URL)</strong></label><br>';
        echo '<input type="url" name="mlb_lesson_video_url" value="' . esc_attr($video_url) . '" class="widefat" placeholder="https://vimeo.com/..."></p>';

        echo '<hr>';
        echo '<p><strong>Materiais (um por linha)</strong><br><span style="color:#666">Pode ser URL de PDF, link, drive, etc.</span></p>';

        $text = implode("\n", array_map('strval', $materials));
        echo '<textarea name="mlb_lesson_materials_text" class="widefat" rows="6" placeholder="https://...pdf&#10;https://...link">' . esc_textarea($text) . '</textarea>';
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['mlb_lesson_meta_nonce']) || !wp_verify_nonce($_POST['mlb_lesson_meta_nonce'], 'mlb_lesson_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_mlb_lesson_course_id', intval($_POST['mlb_lesson_course_id'] ?? 0));
        update_post_meta($post_id, '_mlb_lesson_video_url', esc_url_raw($_POST['mlb_lesson_video_url'] ?? ''));

        $lines = preg_split("/\r\n|\n|\r/", (string)($_POST['mlb_lesson_materials_text'] ?? ''));
        $lines = array_values(array_filter(array_map('trim', $lines)));

        // salva como array
        update_post_meta($post_id, '_mlb_lesson_materials', $lines);
    }
}
