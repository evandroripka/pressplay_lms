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

        // Vimeo status
        $vimeo_id    = (int) get_post_meta($post->ID, '_mlb_lesson_vimeo_id', true);
        $vimeo_title = (string) get_post_meta($post->ID, '_mlb_lesson_vimeo_title', true);
        $vimeo_error = (string) get_post_meta($post->ID, '_mlb_lesson_vimeo_error', true);

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
        echo '<input type="url" name="mlb_lesson_video_url" value="' . esc_attr($video_url) . '" class="widefat" placeholder="https://vimeo.com/... ou https://youtu.be/..."></p>';

        // Avisos Vimeo
        echo '<div style="padding:12px;border:1px solid #e5e5e5;border-radius:10px;background:#fff;margin:10px 0;">';
        echo '<strong>Vimeo (validação via API)</strong><br>';

        if ($vimeo_id && !$vimeo_error) {
            echo '<p style="margin:6px 0;color:#0a7b34;"><strong>OK:</strong> Vimeo ID #' . esc_html($vimeo_id) . ' — ' . esc_html($vimeo_title ?: 'Vídeo validado') . '</p>';
            echo '<div style="max-width:860px;margin-top:10px;">';
            if (class_exists('MLB_LMS_Vimeo')) {
                echo MLB_LMS_Vimeo::get_embed_html($vimeo_id);
            } else {
                echo '<p style="color:#666">Classe Vimeo não carregada.</p>';
            }
            echo '</div>';
        } elseif ($vimeo_error) {
            echo '<p style="margin:6px 0;color:#b32d2e;"><strong>Erro:</strong> ' . esc_html($vimeo_error) . '</p>';
            echo '<p style="margin:6px 0;color:#666">Dica: verifique se o token Vimeo está configurado nas Configurações do Pressplay LMS e se o vídeo permite incorporação (embed).</p>';
        } else {
            echo '<p style="margin:6px 0;color:#666">Cole uma URL do Vimeo e salve a aula para validar via API (se token estiver configurado).</p>';
        }

        echo '</div>';

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

        $course_id = intval($_POST['mlb_lesson_course_id'] ?? 0);
        $video_url = esc_url_raw($_POST['mlb_lesson_video_url'] ?? '');

        update_post_meta($post_id, '_mlb_lesson_course_id', $course_id);
        update_post_meta($post_id, '_mlb_lesson_video_url', $video_url);

        // Materiais
        $lines = preg_split("/\r\n|\n|\r/", (string)($_POST['mlb_lesson_materials_text'] ?? ''));
        $lines = array_values(array_filter(array_map('trim', $lines)));
        update_post_meta($post_id, '_mlb_lesson_materials', $lines);

        // ============================
        // Vimeo validation (se URL for Vimeo)
        // ============================
        // Limpa dados antigos se URL mudou para não-vimeo
        if ($video_url === '' || stripos($video_url, 'vimeo.com') === false) {
            delete_post_meta($post_id, '_mlb_lesson_vimeo_id');
            delete_post_meta($post_id, '_mlb_lesson_vimeo_title');
            delete_post_meta($post_id, '_mlb_lesson_vimeo_link');
            delete_post_meta($post_id, '_mlb_lesson_vimeo_embed_html');
            delete_post_meta($post_id, '_mlb_lesson_vimeo_error');
            return;
        }

        if (!class_exists('MLB_LMS_Vimeo')) {
            update_post_meta($post_id, '_mlb_lesson_vimeo_error', 'Classe Vimeo não carregada.');
            return;
        }

        $video_id = MLB_LMS_Vimeo::parse_video_id($video_url);
        if (!$video_id) {
            update_post_meta($post_id, '_mlb_lesson_vimeo_error', 'Não foi possível extrair o ID do vídeo do Vimeo.');
            return;
        }

        // Se não tem token, a gente não bloqueia — só avisa e salva ID básico
        if (!MLB_LMS_Vimeo::has_token()) {
            update_post_meta($post_id, '_mlb_lesson_vimeo_id', (int)$video_id);
            update_post_meta($post_id, '_mlb_lesson_vimeo_title', '');
            update_post_meta($post_id, '_mlb_lesson_vimeo_link', $video_url);
            update_post_meta($post_id, '_mlb_lesson_vimeo_embed_html', MLB_LMS_Vimeo::get_embed_html($video_id));
            update_post_meta($post_id, '_mlb_lesson_vimeo_error', 'Token Vimeo não configurado. Configure em Pressplay LMS → Configurações.');
            return;
        }

        $data = MLB_LMS_Vimeo::get_video_data($video_id);

        if (is_wp_error($data)) {
            // Mantém o ID, mas marca erro
            update_post_meta($post_id, '_mlb_lesson_vimeo_id', (int)$video_id);
            update_post_meta($post_id, '_mlb_lesson_vimeo_title', '');
            update_post_meta($post_id, '_mlb_lesson_vimeo_link', $video_url);
            update_post_meta($post_id, '_mlb_lesson_vimeo_embed_html', MLB_LMS_Vimeo::get_embed_html($video_id));
            update_post_meta($post_id, '_mlb_lesson_vimeo_error', $data->get_error_message());
            return;
        }

        // Vimeo OK
        $title = '';
        if (is_array($data) && !empty($data['name'])) {
            $title = (string)$data['name'];
        }

        update_post_meta($post_id, '_mlb_lesson_vimeo_id', (int)$video_id);
        update_post_meta($post_id, '_mlb_lesson_vimeo_title', $title);
        update_post_meta($post_id, '_mlb_lesson_vimeo_link', $video_url);
        update_post_meta($post_id, '_mlb_lesson_vimeo_embed_html', MLB_LMS_Vimeo::get_embed_html($video_id));
        delete_post_meta($post_id, '_mlb_lesson_vimeo_error');
    }
}
