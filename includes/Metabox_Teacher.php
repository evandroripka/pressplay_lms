<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Teacher_Meta
{
    public static function init()
    {
        add_action('add_meta_boxes_press_teacher', [__CLASS__, 'add_boxes']);
        add_action('save_post_press_teacher', [__CLASS__, 'save'], 10, 2);

        add_filter('enter_title_here', [__CLASS__, 'title_placeholder'], 10, 2);

        add_action('do_meta_boxes', [__CLASS__, 'rename_featured_image_box']);
    }

    public static function title_placeholder($text, $post)
    {
        if ($post && $post->post_type === 'press_teacher') {
            return 'Nome completo do professor';
        }
        return $text;
    }

    public static function rename_featured_image_box()
    {
        remove_meta_box('postimagediv', 'press_teacher', 'side');

        add_meta_box(
            'postimagediv',
            'Foto de Perfil',
            'post_thumbnail_meta_box',
            'press_teacher',
            'side',
            'low'
        );
    }

    public static function add_boxes()
    {
        add_meta_box(
            'press_teacher_biography',
            'Biografia',
            [__CLASS__, 'render_biography_box'],
            'press_teacher',
            'normal',
            'high'
        );

        add_meta_box(
            'press_teacher_socials',
            'Redes Sociais e Contato',
            [__CLASS__, 'render_socials_box'],
            'press_teacher',
            'normal',
            'default'
        );
    }

   public static function render_biography_box($post)
{
    wp_nonce_field('press_teacher_meta_save', 'press_teacher_meta_nonce');

    $profession = get_post_meta($post->ID, '_press_teacher_profession', true);
    $biography  = get_post_field('post_content', $post->ID);

    echo '<div style="padding-top:4px;">';

    // Profissão separada
    echo '<p style="margin:0 0 16px 0;">';
    echo '<label for="press_teacher_profession" style="display:block;font-weight:600;margin-bottom:6px;">Profissão</label>';
    echo '<input 
            type="text" 
            id="press_teacher_profession" 
            name="press_teacher_profession" 
            value="' . esc_attr($profession) . '" 
            class="widefat" 
            placeholder="Ex.: Desenvolvedor Sênior, Designer, Especialista em WordPress">';
    echo '</p>';

    // Rótulo simples acima do editor
    echo '<p style="margin:0 0 8px 0;font-weight:600;">Biografia resumida</p>';

    wp_editor(
        $biography,
        'press_teacher_biography_editor',
        [
            'textarea_name' => 'press_teacher_biography',
            'textarea_rows' => 10,
            'media_buttons' => false,
            'teeny'         => false,
            'quicktags'     => true,
        ]
    );

    echo '</div>';
}

    public static function render_socials_box($post)
    {
        $fields = [
            'instagram' => 'Instagram',
            'facebook'  => 'Facebook',
            'x'         => 'X / Twitter',
            'linkedin'  => 'LinkedIn',
            'website'   => 'Website',
            'behance'   => 'Behance',
            'pinterest' => 'Pinterest',
            'email'     => 'E-mail',
        ];

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">';

        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_press_teacher_' . $key, true);

            echo '<p style="margin:0;">';
            echo '<label for="press_teacher_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';

            $type = ($key === 'email') ? 'email' : 'url';
            $placeholder = ($key === 'email')
                ? 'exemplo@dominio.com'
                : 'https://...';

            echo '<input type="' . esc_attr($type) . '" id="press_teacher_' . esc_attr($key) . '" name="press_teacher_' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="widefat" placeholder="' . esc_attr($placeholder) . '">';
            echo '</p>';
        }

        echo '</div>';
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['press_teacher_meta_nonce']) || !wp_verify_nonce($_POST['press_teacher_meta_nonce'], 'press_teacher_meta_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!$post || $post->post_type !== 'press_teacher') return;

        // Profissão
        if (isset($_POST['press_teacher_profession'])) {
            update_post_meta(
                $post_id,
                '_press_teacher_profession',
                sanitize_text_field($_POST['press_teacher_profession'])
            );
        }

        // Redes sociais
        $social_fields = ['instagram', 'facebook', 'x', 'linkedin', 'website', 'behance', 'pinterest', 'email'];

        foreach ($social_fields as $field) {
            $meta_key = '_press_teacher_' . $field;

            if (!isset($_POST['press_teacher_' . $field])) {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            $raw = trim((string) $_POST['press_teacher_' . $field]);

            if ($raw === '') {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            if ($field === 'email') {
                update_post_meta($post_id, $meta_key, sanitize_email($raw));
            } else {
                update_post_meta($post_id, $meta_key, esc_url_raw($raw));
            }
        }

        // Biografia -> salva em post_content
        if (isset($_POST['press_teacher_biography'])) {
            remove_action('save_post_press_teacher', [__CLASS__, 'save'], 10);

            wp_update_post([
                'ID'           => $post_id,
                'post_content' => wp_kses_post($_POST['press_teacher_biography']),
            ]);

            add_action('save_post_press_teacher', [__CLASS__, 'save'], 10, 2);
        }
    }
}