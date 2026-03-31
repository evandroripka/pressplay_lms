<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Course_Meta
{
    const META_FEATURES = '_press_course_features';
    private static $certificate_css_editor_settings = null;

    public static function init()
    {
        add_action('add_meta_boxes_press_course', [__CLASS__, 'add_boxes']);
        add_action('save_post_press_course', [__CLASS__, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function enqueue_admin_assets($hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'press_course') {
            return;
        }

        if (function_exists('wp_enqueue_code_editor')) {
            self::$certificate_css_editor_settings = wp_enqueue_code_editor(['type' => 'text/css']);
        }
    }

    public static function get_feature_catalog(): array
    {
        return [
            'video_on_demand' => [
                'label' => 'Vídeo sob demanda',
                'icon'  => 'fa-light fa-video',
                'admin_icon' => 'dashicons dashicons-video-alt3',
                'default' => true,
            ],
            'download_materials' => [
                'label' => 'Materiais para download',
                'icon'  => 'fa-light fa-file-arrow-down',
                'admin_icon' => 'dashicons dashicons-download',
                'default' => true,
            ],
            'certificate_online' => [
                'label' => 'Certificado online',
                'icon'  => 'fa-light fa-certificate',
                'admin_icon' => 'dashicons dashicons-awards',
                'default' => false,
            ],
            'mobile_desktop_access' => [
                'label' => 'Acesso no celular e PC',
                'icon'  => 'fa-light fa-mobile-screen',
                'admin_icon' => 'dashicons dashicons-smartphone',
                'default' => true,
            ],
            'captions' => [
                'label' => 'Legendas (se houver)',
                'icon'  => 'fa-light fa-closed-captioning',
                'admin_icon' => 'dashicons dashicons-editor-help',
                'default' => true,
            ],
            'community_access' => [
                'label' => 'Comunidade exclusiva',
                'icon'  => 'fa-light fa-user-group',
                'admin_icon' => 'dashicons dashicons-groups',
                'default' => false,
            ],
            'support' => [
                'label' => 'Suporte para dúvidas',
                'icon'  => 'fa-light fa-headset',
                'admin_icon' => 'dashicons dashicons-sos',
                'default' => false,
            ],
            'live_classes' => [
                'label' => 'Aulas ao vivo',
                'icon'  => 'fa-light fa-chalkboard-user',
                'admin_icon' => 'dashicons dashicons-welcome-learn-more',
                'default' => false,
            ],
            'future_updates' => [
                'label' => 'Atualizações futuras',
                'icon'  => 'fa-light fa-arrows-rotate',
                'admin_icon' => 'dashicons dashicons-update',
                'default' => false,
            ],
            'quizzes' => [
                'label' => 'Exercícios e checklists',
                'icon'  => 'fa-light fa-list-check',
                'admin_icon' => 'dashicons dashicons-yes-alt',
                'default' => false,
            ],
        ];
    }

    private static function get_default_feature_keys(): array
    {
        $default_keys = [];

        foreach (self::get_feature_catalog() as $key => $feature) {
            if (!empty($feature['default'])) {
                $default_keys[] = $key;
            }
        }

        return $default_keys;
    }

    public static function get_selected_feature_keys(int $course_id): array
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) {
            return [];
        }

        $catalog = self::get_feature_catalog();
        $raw = get_post_meta($course_id, self::META_FEATURES, true);

        if (metadata_exists('post', $course_id, self::META_FEATURES)) {
            $selected_keys = is_array($raw) ? array_map('sanitize_key', $raw) : [];
        } else {
            $selected_keys = self::get_default_feature_keys();
        }

        return array_values(array_filter($selected_keys, static function ($key) use ($catalog) {
            return isset($catalog[$key]);
        }));
    }

    public static function get_selected_features(int $course_id): array
    {
        $selected_keys = self::get_selected_feature_keys($course_id);
        $catalog = self::get_feature_catalog();
        $selected = [];

        foreach ($catalog as $key => $feature) {
            if (!in_array($key, $selected_keys, true)) {
                continue;
            }

            $selected[] = [
                'key'   => $key,
                'label' => (string) ($feature['label'] ?? ''),
                'icon'  => (string) ($feature['icon'] ?? 'fa-light fa-check'),
            ];
        }

        return $selected;
    }

    private static function get_legacy_default_certificate_html(): string
    {
        return '
<div class="presslms-cert">
  <div class="presslms-cert__logo">
    <img src="{{logo_url}}" alt="Logo">
  </div>

  <div class="presslms-cert__title">Certificado de Conclusão</div>
  <div class="presslms-cert__subtitle">Certificamos que</div>

  <div class="presslms-cert__student">{{student_name}}</div>

  <div class="presslms-cert__text">
    concluiu com êxito o curso
  </div>

  <div class="presslms-cert__course">{{course_name}}</div>

  <div class="presslms-cert__text">
    {{certificate_description}}
  </div>

  <div class="presslms-cert__meta">
    <strong>Duração:</strong> {{course_duration}} &nbsp;&nbsp;|&nbsp;&nbsp;
    <strong>Concluído em:</strong> {{completion_date}}
  </div>

  <div class="presslms-cert__footer">
    <div class="presslms-cert__signature">
      <img src="{{signature_url}}" alt="Assinatura">
      <div class="presslms-cert__line"></div>
      <div class="presslms-cert__label">Assinatura</div>
    </div>
  </div>
</div>';
    }

    private static function normalize_certificate_template_html(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('/\s+/', ' ', $html);
        return is_string($html) ? trim($html) : '';
    }

    public static function is_legacy_default_certificate_html(string $html): bool
    {
        $html = self::normalize_certificate_template_html($html);
        if ($html === '') {
            return false;
        }

        return $html === self::normalize_certificate_template_html(self::get_legacy_default_certificate_html());
    }

    public static function get_default_certificate_html(): string
    {
        $template = PRESS_LMS_PATH . 'templates/certificado/certificado.php';

        if (file_exists($template)) {
            $contents = (string) file_get_contents($template);
            if (trim($contents) !== '') {
                return $contents;
            }
        }

        return '
<div class="presslms-cert">
  <div class="presslms-cert__topbar"></div>

  <div class="presslms-cert__inner">
    <div class="presslms-cert__logo">
      <img src="{{logo_url}}" alt="Logo">
    </div>

    <div style="text-align:center;">
      <div class="presslms-cert__badge">Certificado Oficial</div>
    </div>

    <h1 class="presslms-cert__title">Certificado de Conclusão</h1>
    <p class="presslms-cert__subtitle">Certificamos que</p>

    <div class="presslms-cert__student">{{student_name}}</div>

    <div class="presslms-cert__text">
      concluiu com êxito o curso online
    </div>

    <div class="presslms-cert__course">{{course_name}}</div>

    <div class="presslms-cert__description">
      {{certificate_description}}
    </div>

    <div class="presslms-cert__meta-grid">
      <div class="presslms-cert__meta-card">
        <div class="presslms-cert__meta-label">Duração do curso</div>
        <div class="presslms-cert__meta-value">{{course_duration}}</div>
      </div>

      <div class="presslms-cert__meta-card">
        <div class="presslms-cert__meta-label">Data de conclusão</div>
        <div class="presslms-cert__meta-value">{{completion_date}}</div>
      </div>
    </div>

    <div class="presslms-cert__footer">
      <div class="presslms-cert__signature-block">
        <img src="{{signature_url}}" alt="Assinatura">
        <div class="presslms-cert__line"></div>
        <div class="presslms-cert__label">Assinatura autorizada</div>
      </div>

      <div class="presslms-cert__seal">
        Verificado
        <strong>LMS</strong>
      </div>
    </div>
  </div>
</div>';
    }

    public static function get_default_certificate_css(): string
    {
        $template = PRESS_LMS_PATH . 'templates/certificado/certificado.css';

        if (file_exists($template)) {
            $contents = (string) file_get_contents($template);
            if (trim($contents) !== '') {
                return trim($contents);
            }
        }

        return '';
    }

    private static function sanitize_certificate_css(string $css): string
    {
        $css = wp_unslash($css);
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        $css = wp_kses_no_null($css, ['slash_zero' => 'keep']);
        $css = str_replace(['<?', '?>'], '', $css);
        $css = preg_replace('#</style#i', '<\\/style', $css);

        return is_string($css) ? trim($css) : '';
    }

    private static function get_certificate_preview_placeholder_data_uri(
        string $label,
        int $width = 420,
        int $height = 130,
        string $background = '#f8fafc',
        string $foreground = '#475569',
        string $border = '#cbd5e1'
    ): string {
        $label = trim($label);
        if ($label === '') {
            $label = 'Preview';
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" fill="none"><rect width="%1$d" height="%2$d" rx="18" fill="%3$s"/><rect x="1.5" y="1.5" width="%4$d" height="%5$d" rx="16.5" stroke="%6$s" stroke-width="3" stroke-dasharray="10 10"/><text x="50%%" y="50%%" fill="%7$s" font-family="Arial, sans-serif" font-size="24" font-weight="700" text-anchor="middle" dominant-baseline="middle">%8$s</text></svg>',
            $width,
            $height,
            $background,
            max(0, $width - 3),
            max(0, $height - 3),
            $border,
            $foreground,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    private static function get_certificate_preview_data(
        WP_Post $post,
        string $description,
        string $logo_url,
        string $signature_url
    ): array {
        $course_title = trim((string) $post->post_title);
        $duration_seconds = (int) get_post_meta($post->ID, '_press_course_total_duration', true);
        $course_duration = class_exists('PRESS_LMS_Certificate')
            ? PRESS_LMS_Certificate::format_seconds($duration_seconds)
            : '';

        if ($course_duration === '' || $course_duration === '0min') {
            $course_duration = '8h 00min';
        }

        if (trim($description) === '') {
            $description = 'Certificamos que a aluna concluiu esta formação com excelente desempenho técnico, domínio de acabamento e aplicação prática dos protocolos ensinados em aula.';
        }

        return [
            'student_name'            => 'Ana Beatriz Souza',
            'course_name'             => $course_title !== '' ? $course_title : 'Masterclass de Corte Feminino',
            'course_duration'         => $course_duration,
            'completion_date'         => date_i18n('d/m/Y'),
            'certificate_description' => $description,
            'logo_url'                => $logo_url !== '' ? $logo_url : self::get_certificate_preview_placeholder_data_uri('Logo da marca', 420, 120),
            'signature_url'           => $signature_url !== '' ? $signature_url : self::get_certificate_preview_placeholder_data_uri('Assinatura', 360, 110, '#f8fafc', '#334155', '#94a3b8'),
        ];
    }

    public static function add_boxes()
    {
        add_meta_box(
            'press_course_details',
            'Configurações do Curso',
            [__CLASS__, 'render'],
            'press_course',
            'normal',
            'high'
        );
    }

    private static function get_course_lessons(int $course_id): array
    {
        return PRESS_LMS_Helpers::get_course_lessons($course_id, ['publish', 'draft', 'pending', 'private']);
    }

    private static function render_lessons_section($post)
    {
        $course_id = (int) $post->ID;
        $lessons = self::get_course_lessons($course_id);
        $new_lesson_url = admin_url('post-new.php?post_type=press_lesson&course_id=' . $course_id);

        echo '<div class="press-course-tab__section">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">';
        echo '<div>';
        echo '<h3 style="margin:0 0 4px;">Aulas do Curso</h3>';
        echo '<p style="margin:0;color:#646970;">Crie novas aulas por aqui e edite as já vinculadas a este curso.</p>';
        echo '</div>';
        echo '<a href="' . esc_url($new_lesson_url) . '" class="button button-primary">Adicionar nova aula</a>';
        echo '</div>';

        if (!$lessons) {
            echo '<p>Nenhuma aula cadastrada ainda para este curso.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width:80px;">Ordem</th>';
        echo '<th>Título</th>';
        echo '<th style="width:120px;">Status</th>';
        echo '<th style="width:180px;">Ações</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($lessons as $lesson) {
            $edit_url = get_edit_post_link($lesson->ID);
            $view_url = home_url('/curso/' . $post->post_name . '/aula/' . $lesson->post_name . '/');

            echo '<tr>';
            echo '<td>' . (int) $lesson->menu_order . '</td>';
            echo '<td><strong>' . esc_html($lesson->post_title ?: '(Sem título)') . '</strong></td>';
            echo '<td>' . esc_html($lesson->post_status) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Editar</a> ';
            echo '<a class="button button-small" href="' . esc_url($view_url) . '" target="_blank">Ver</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private static function render_features_section($post): void
    {
        $selected_keys = self::get_selected_feature_keys((int) $post->ID);
        $catalog = self::get_feature_catalog();

        echo '<div class="press-course-tab__section">';
        echo '<h3>O que o curso inclui</h3>';
        echo '<p style="margin-top:0;color:#646970;">Marque os benefícios que devem aparecer na lateral da página do curso no frontend.</p>';
        echo '<div class="press-course-features-grid">';

        foreach ($catalog as $key => $feature) {
            $checked = in_array($key, $selected_keys, true);
            $admin_icon = !empty($feature['admin_icon']) ? (string) $feature['admin_icon'] : 'dashicons dashicons-yes-alt';

            echo '<label class="press-course-feature">';
            echo '<input type="checkbox" name="press_course_features[]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . '>';
            echo '<span class="press-course-feature__icon"><span class="' . esc_attr($admin_icon) . '" aria-hidden="true"></span></span>';
            echo '<span class="press-course-feature__content">';
            echo '<strong>' . esc_html($feature['label']) . '</strong>';
            echo '<small>Exibir este item na sidebar do curso.</small>';
            echo '</span>';
            echo '</label>';
        }

        echo '</div>';
        echo '</div>';
    }

    public static function render($post)
    {
        wp_nonce_field('press_course_meta_save', 'press_course_meta_nonce');

        $trailer    = get_post_meta($post->ID, '_press_course_trailer', true);
        $product_id = (int) get_post_meta($post->ID, '_press_course_product_id', true);
        $price      = get_post_meta($post->ID, '_press_course_price', true);
        $is_paused  = get_post_meta($post->ID, '_press_course_paused', true) === 'yes';
        $access_settings = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_course_access_settings((int) $post->ID)
            : ['type' => 'years', 'value' => 1];
        $access_type = (string) ($access_settings['type'] ?? 'years');
        $access_value = (int) ($access_settings['value'] ?? 1);
        $access_value_input = $access_type === 'lifetime' ? 1 : max(1, $access_value);
        $access_summary = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::format_access_settings($access_settings)
            : '1 ano de acesso';

        // Load all published teachers for the course owner selector.
        $teachers = get_posts([
            'post_type' => 'press_teacher',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $selected_teacher = (int) get_post_meta($post->ID, '_press_course_teacher', true);

        $certificate_description = (string) get_post_meta($post->ID, '_press_course_certificate_description', true);
        $certificate_logo_id     = (int) get_post_meta($post->ID, '_press_course_certificate_logo_id', true);
        $certificate_sign_id     = (int) get_post_meta($post->ID, '_press_course_certificate_signature_id', true);

        $certificate_logo_url = $certificate_logo_id ? wp_get_attachment_url($certificate_logo_id) : '';
        $certificate_sign_url = $certificate_sign_id ? wp_get_attachment_url($certificate_sign_id) : '';
        $certificate_preview_data = self::get_certificate_preview_data(
            $post,
            $certificate_description,
            $certificate_logo_url,
            $certificate_sign_url
        );
        $lessons_count = count(self::get_course_lessons((int) $post->ID));
        $selected_features = self::get_selected_features((int) $post->ID);
        $features_count = count($selected_features);

        $certificate_html = (string) get_post_meta($post->ID, '_press_course_certificate_html', true);
        $certificate_css = (string) get_post_meta($post->ID, '_press_course_certificate_css', true);

        if ($certificate_html === '' || self::is_legacy_default_certificate_html($certificate_html)) {
            $certificate_html = self::get_default_certificate_html();
        }

        if ($certificate_css === '') {
            $certificate_css = self::get_default_certificate_css();
        }

        echo '<style>
            .press-course-tabs {
                margin-top: 8px;
            }
            .press-course-tabs__nav {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 16px;
                padding-bottom: 12px;
                border-bottom: 1px solid #dcdcde;
            }
            .press-course-tabs__btn {
                appearance: none;
                border: 1px solid #dcdcde;
                background: #f6f7f7;
                border-radius: 999px;
                padding: 8px 14px;
                font-weight: 600;
                color: #1d2327;
                cursor: pointer;
            }
            .press-course-tabs__btn.is-active {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
            }
            .press-course-tabs__count {
                display: inline-block;
                margin-left: 6px;
                padding: 1px 7px;
                border-radius: 999px;
                background: rgba(255,255,255,0.24);
                font-size: 12px;
            }
            .press-course-tabs__btn:not(.is-active) .press-course-tabs__count {
                background: #e7f0f7;
                color: #0a4b78;
            }
            .press-course-tab {
                display: block;
            }
            .press-course-tabs.is-enhanced .press-course-tab {
                display: none;
            }
            .press-course-tabs.is-enhanced .press-course-tab.is-active {
                display: block;
            }
            .press-course-tab__section {
                padding: 16px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 12px;
                margin-bottom: 16px;
            }
            .press-course-tab__section:last-child {
                margin-bottom: 0;
            }
            .press-course-tab__section h3 {
                margin-top: 0;
            }
            .press-course-code-field {
                display: grid;
                gap: 12px;
            }
            .press-course-code-field__intro {
                margin: 0;
                color: #646970;
            }
            .press-course-code-field__hints {
                padding: 12px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                margin-bottom: 12px;
            }
            .press-course-code-field__hints code {
                display: inline-block;
                margin: 0 6px 6px 0;
            }
            .press-course-code-field__textarea {
                width: 100%;
                min-height: 360px;
            }
            .press-course-code-field--css .press-course-code-field__textarea {
                min-height: 380px;
                border: 1px solid #111827;
                border-radius: 18px;
                background: #030712;
                color: #e5edf7;
                font-family: Consolas, Monaco, "Courier New", monospace;
                line-height: 1.6;
                box-shadow: 0 18px 40px rgba(2, 6, 23, 0.18);
            }
            .press-course-code-field--css .press-course-code-field__textarea::placeholder {
                color: #7c89a0;
            }
            .press-course-code-field--css .CodeMirror {
                height: auto;
                min-height: 380px;
                border: 1px solid #111827;
                border-radius: 18px;
                background: #030712;
                color: #e5edf7;
                box-shadow: 0 18px 40px rgba(2, 6, 23, 0.24);
            }
            .press-course-code-field--css .CodeMirror-gutters {
                background: #020617;
                border-right: 1px solid #111827;
            }
            .press-course-code-field--css .CodeMirror-linenumber {
                color: #61708a;
            }
            .press-course-code-field--css .CodeMirror-cursor {
                border-left: 1px solid #f8fafc;
            }
            .press-course-code-field--css .CodeMirror-activeline-background,
            .press-course-code-field--css .CodeMirror-activeline .CodeMirror-linebackground,
            .press-course-code-field--css .CodeMirror-activeline-gutter {
                background: transparent !important;
            }
            .press-course-code-field--css .CodeMirror-lines {
                padding: 16px 0;
            }
            .press-course-code-field--css .CodeMirror pre.CodeMirror-line,
            .press-course-code-field--css .CodeMirror pre.CodeMirror-line-like {
                padding: 0 16px;
            }
            .press-course-code-field--css .cm-s-default .cm-comment {
                color: #64748b;
            }
            .press-course-code-field--css .cm-s-default .cm-atom,
            .press-course-code-field--css .cm-s-default .cm-number {
                color: #f9a8d4;
            }
            .press-course-code-field--css .cm-s-default .cm-def,
            .press-course-code-field--css .cm-s-default .cm-variable-2,
            .press-course-code-field--css .cm-s-default .cm-variable-3 {
                color: #c4b5fd;
            }
            .press-course-code-field--css .cm-s-default .cm-property,
            .press-course-code-field--css .cm-s-default .cm-attribute,
            .press-course-code-field--css .cm-s-default .cm-tag {
                color: #7dd3fc;
            }
            .press-course-code-field--css .cm-s-default .cm-string,
            .press-course-code-field--css .cm-s-default .cm-string-2 {
                color: #86efac;
            }
            .press-course-code-field--css .cm-s-default .cm-keyword,
            .press-course-code-field--css .cm-s-default .cm-qualifier {
                color: #fbbf24;
            }
            .press-course-code-field--css .cm-s-default .cm-operator,
            .press-course-code-field--css .cm-s-default .cm-bracket {
                color: #e2e8f0;
            }
            .press-course-code-preview {
                margin-top: 18px;
                border: 1px solid #dcdcde;
                border-radius: 18px;
                overflow: hidden;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            }
            .press-course-code-preview__header {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: flex-start;
                padding: 18px 20px 16px;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.03), rgba(59, 130, 246, 0.08));
                border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            }
            .press-course-code-preview__header h4 {
                margin: 0 0 4px;
                font-size: 15px;
            }
            .press-course-code-preview__header p {
                margin: 0;
                color: #475569;
            }
            .press-course-code-preview__badges {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }
            .press-course-code-preview__badges span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 10px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.8);
                border: 1px solid rgba(148, 163, 184, 0.25);
                color: #0f172a;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            .press-course-code-preview__viewport {
                padding: 16px;
                background:
                    radial-gradient(circle at top left, rgba(59, 130, 246, 0.08), transparent 24%),
                    radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.08), transparent 18%),
                    #e2e8f0;
            }
            .press-course-code-preview__frame {
                display: block;
                width: 100%;
                min-height: 760px;
                border: 0;
                border-radius: 14px;
                background: #f8fafc;
                box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.28);
            }
            .press-course-tab__grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }
            .press-course-features-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            .press-course-feature {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 14px;
                border: 1px solid #dcdcde;
                border-radius: 12px;
                background: #fff;
            }
            .press-course-feature input {
                margin-top: 2px;
            }
            .press-course-feature__icon {
                width: 36px;
                height: 36px;
                flex: 0 0 36px;
                display: grid;
                place-items: center;
                border-radius: 10px;
                background: #f0f6fc;
                color: #2271b1;
                border: 1px solid #d0e3f2;
            }
            .press-course-feature__icon .dashicons {
                width: 18px;
                height: 18px;
                font-size: 18px;
            }
            .press-course-feature__content {
                display: grid;
                gap: 3px;
            }
            .press-course-feature__content small {
                color: #646970;
            }
            .press-course-access-config {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .press-course-access-config input[type="number"] {
                width: 90px;
            }
            @media (max-width: 960px) {
                .press-course-tab__grid {
                    grid-template-columns: 1fr;
                }
                .press-course-features-grid {
                    grid-template-columns: 1fr;
                }
                .press-course-code-preview__header {
                    flex-direction: column;
                }
                .press-course-code-preview__badges {
                    justify-content: flex-start;
                }
            }
        </style>';

        echo '<div class="press-course-tabs" id="press-course-tabs">';
        echo '<div class="press-course-tabs__nav" role="tablist" aria-label="Seções do curso">';
        echo '<button type="button" class="press-course-tabs__btn is-active" data-tab-target="details" role="tab" aria-selected="true">Detalhes</button>';
        echo '<button type="button" class="press-course-tabs__btn" data-tab-target="includes" role="tab" aria-selected="false">Inclui <span class="press-course-tabs__count">' . esc_html($features_count) . '</span></button>';
        echo '<button type="button" class="press-course-tabs__btn" data-tab-target="certificate" role="tab" aria-selected="false">Certificado</button>';
        echo '<button type="button" class="press-course-tabs__btn" data-tab-target="lessons" role="tab" aria-selected="false">Aulas <span class="press-course-tabs__count">' . esc_html($lessons_count) . '</span></button>';
        echo '</div>';

        echo '<div class="press-course-tab is-active" data-tab-panel="details">';
        echo '<div class="press-course-tab__section">';
        echo '<h3>Detalhes do Curso</h3>';
        echo '<p style="margin-top:0;color:#646970;">Configure venda, trailer e professor principal do curso.</p>';

        echo '<p><label><strong>Valor do curso (R$)</strong></label><br>';
        echo '<input type="text" name="press_course_price" value="' . esc_attr($price) . '" class="small-text" placeholder="99,90"> ';
        echo '<span style="color:#666">Ao publicar o curso, o produto WooCommerce será criado/atualizado automaticamente.</span></p>';

        echo '<p><label><strong>Validade do acesso</strong></label><br>';
        echo '<span class="press-course-access-config">';
        echo '<span>Libera acesso por</span>';
        echo '<input type="number" min="1" step="1" name="press_course_access_value" id="press_course_access_value" value="' . esc_attr((string) $access_value_input) . '" class="small-text">';
        echo '<select name="press_course_access_type" id="press_course_access_type">';
        if (class_exists('PRESS_LMS_Enrollments')) {
            foreach (PRESS_LMS_Enrollments::get_supported_access_types() as $type_key => $type_label) {
                echo '<option value="' . esc_attr($type_key) . '"' . selected($access_type, $type_key, false) . '>' . esc_html($type_label) . '</option>';
            }
        } else {
            echo '<option value="years" selected>Anos</option>';
        }
        echo '</select>';
        echo '</span>';
        echo '<br><span id="press_course_access_help" style="color:#666">Configuração atual: ' . esc_html($access_summary) . '.</span></p>';

        echo '<p><label><strong>Trailer (YouTube/Vimeo URL)</strong></label><br>';
        echo '<input type="url" name="press_course_trailer" value="' . esc_attr($trailer) . '" class="widefat" placeholder="https://vimeo.com/... ou https://youtu.be/..."></p>';

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
        echo '<hr>';
        echo '<p style="color:#666">MVP: Galeria de imagens podemos fazer depois (Media Uploader). Primeiro vamos fechar curso/aulas/materiais.</p>';

        echo '<p><label for="press_course_teacher"><strong>Professor do curso</strong></label><br>';
        echo '<select name="press_course_teacher" id="press_course_teacher" class="widefat">';
        echo '<option value="">— Selecionar —</option>';
        foreach ($teachers as $teacher) {
            $selected = selected($selected_teacher, $teacher->ID, false);
            echo '<option value="' . esc_attr($teacher->ID) . '"' . $selected . '>' . esc_html($teacher->post_title) . '</option>';
        }
        echo '</select></p>';

        echo '<hr>';
        echo '<input type="hidden" name="press_course_paused" value="no">';
        echo '<label style="display:flex;align-items:flex-start;gap:10px;">';
        echo '<input type="checkbox" name="press_course_paused" value="yes" ' . checked($is_paused, true, false) . '>';
        echo '<span><strong>Curso pausado</strong><br><span style="color:#646970;">Quando pausado, o curso continua acessível para quem já está matriculado, mas bloqueia novas matrículas e oculta o produto da vitrine do WooCommerce.</span></span>';
        echo '</label>';
        echo '</div>';
        echo '</div>';

        echo '<div class="press-course-tab" data-tab-panel="includes">';
        self::render_features_section($post);
        echo '</div>';

        echo '<div class="press-course-tab" data-tab-panel="certificate">';
        echo '<div class="press-course-tab__section">';
        echo '<h3>Configurações do Certificado</h3>';
        echo '<p style="margin-top:0;color:#646970;">Personalize textos, imagens e o layout entregue ao aluno.</p>';

        echo '<p><label for="press_course_certificate_description"><strong>Descrição do certificado</strong></label><br>';
        echo '<textarea id="press_course_certificate_description" name="press_course_certificate_description" class="widefat" rows="4" placeholder="Ex.: Certificamos que o aluno concluiu com êxito este curso e demonstrou domínio dos conteúdos propostos.">' . esc_textarea($certificate_description) . '</textarea></p>';

        echo '<div class="press-course-tab__grid">';

        echo '<div>';
        echo '<label><strong>Logo do certificado</strong></label>';
        echo '<input type="hidden" name="press_course_certificate_logo_id" id="press_course_certificate_logo_id" value="' . esc_attr($certificate_logo_id) . '">';
        echo '<input type="text" class="widefat" id="press_course_certificate_logo_url" value="' . esc_attr($certificate_logo_url) . '" readonly placeholder="Nenhuma logo selecionada">';
        echo '<p style="margin-top:8px;">';
        echo '<button type="button" class="button" id="press_pick_certificate_logo">Selecionar logo</button> ';
        echo '<button type="button" class="button" id="press_clear_certificate_logo">Limpar</button>';
        echo '</p>';
        echo '</div>';

        echo '<div>';
        echo '<label><strong>Assinatura do certificado</strong></label>';
        echo '<input type="hidden" name="press_course_certificate_signature_id" id="press_course_certificate_signature_id" value="' . esc_attr($certificate_sign_id) . '">';
        echo '<input type="text" class="widefat" id="press_course_certificate_signature_url" value="' . esc_attr($certificate_sign_url) . '" readonly placeholder="Nenhuma assinatura selecionada">';
        echo '<p style="margin-top:8px;">';
        echo '<button type="button" class="button" id="press_pick_certificate_signature">Selecionar assinatura</button> ';
        echo '<button type="button" class="button" id="press_clear_certificate_signature">Limpar</button>';
        echo '</p>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<div class="press-course-tab__section">';
        echo '<h3>Layout do Certificado</h3>';
        echo '<div class="press-course-code-field">';
        echo '<p class="press-course-code-field__intro">Personalize a estrutura do certificado em HTML e controle o visual em um campo separado de CSS.</p>';
        echo '<div class="press-course-code-field__hints">';
        echo '<code>{{student_name}}</code> ';
        echo '<code>{{course_name}}</code> ';
        echo '<code>{{course_duration}}</code> ';
        echo '<code>{{completion_date}}</code> ';
        echo '<code>{{certificate_description}}</code> ';
        echo '<code>{{logo_url}}</code> ';
        echo '<code>{{signature_url}}</code>';
        echo '</div>';

        wp_editor(
            $certificate_html,
            'press_course_certificate_html',
            [
                'textarea_name' => 'press_course_certificate_html',
                'textarea_rows' => 18,
                'media_buttons' => false,
                'teeny'         => false,
                'quicktags'     => true,
            ]
        );
        echo '</div>';

        echo '<div class="press-course-code-field press-course-code-field--css" style="margin-top:16px;">';
        echo '<p class="press-course-code-field__intro">CSS do certificado. O editor já carrega o estilo padrão como base para você ajustar o layout sem misturar estrutura e visual.</p>';
        echo '<textarea id="press_course_certificate_css" name="press_course_certificate_css" class="large-text code press-course-code-field__textarea" rows="20" spellcheck="false" placeholder=".presslms-cert {&#10;  background: #fff;&#10;}">' . esc_textarea($certificate_css) . '</textarea>';
        echo '</div>';

        echo '<div class="press-course-code-preview">';
        echo '<div class="press-course-code-preview__header">';
        echo '<div>';
        echo '<h4>Pré-visualização em tempo real</h4>';
        echo '<p>Mostra o certificado com dados fictícios enquanto você altera HTML, CSS, descrição, logo, assinatura e o nome do curso.</p>';
        echo '</div>';
        echo '<div class="press-course-code-preview__badges">';
        echo '<span>Aluno demo: ' . esc_html($certificate_preview_data['student_name']) . '</span>';
        echo '<span>Conclusão: ' . esc_html($certificate_preview_data['completion_date']) . '</span>';
        echo '<span>Duração: ' . esc_html($certificate_preview_data['course_duration']) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="press-course-code-preview__viewport">';
        echo '<iframe id="press_course_certificate_preview" class="press-course-code-preview__frame" title="Pré-visualização do certificado" sandbox="allow-same-origin"></iframe>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="press-course-tab" data-tab-panel="lessons">';
        self::render_lessons_section($post);
        echo '</div>';
        echo '</div>';
?>
        <script>
            (function($) {
                const $tabsRoot = $('#press-course-tabs');
                const certificatePreviewFrame = document.getElementById('press_course_certificate_preview');
                const certificateHtmlField = document.getElementById('press_course_certificate_html');
                const certificateCssEditorField = document.getElementById('press_course_certificate_css');
                const certificateDescriptionField = document.getElementById('press_course_certificate_description');
                const courseTitleField = document.getElementById('title');
                const certificateLogoUrlField = document.getElementById('press_course_certificate_logo_url');
                const certificateSignatureUrlField = document.getElementById('press_course_certificate_signature_url');
                const certificatePreviewSeed = <?php echo wp_json_encode($certificate_preview_data); ?>;
                const certificateCssEditorSettings = <?php echo wp_json_encode(self::$certificate_css_editor_settings); ?>;
                let certificateCssCodeMirror = null;
                let certificatePreviewTimer = null;

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, function(character) {
                        const entities = {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        };

                        return entities[character] || character;
                    });
                }

                function formatDescription(value) {
                    return escapeHtml(value).replace(/\n/g, '<br>');
                }

                function replaceCertificatePlaceholders(template, data) {
                    const replacements = {
                        '{{student_name}}': escapeHtml(data.student_name || ''),
                        '{{course_name}}': escapeHtml(data.course_name || ''),
                        '{{course_duration}}': escapeHtml(data.course_duration || ''),
                        '{{completion_date}}': escapeHtml(data.completion_date || ''),
                        '{{certificate_description}}': formatDescription(data.certificate_description || ''),
                        '{{logo_url}}': escapeHtml(data.logo_url || ''),
                        '{{signature_url}}': escapeHtml(data.signature_url || '')
                    };

                    let output = String(template || '');

                    Object.keys(replacements).forEach(function(placeholder) {
                        output = output.split(placeholder).join(replacements[placeholder]);
                    });

                    return output;
                }

                function getPreviewFieldValue(field, fallbackValue) {
                    if (!field) {
                        return fallbackValue;
                    }

                    const value = String(field.value || '').trim();

                    return value !== '' ? value : fallbackValue;
                }

                function getCertificateHtmlValue() {
                    const visualEditor = window.tinymce && typeof window.tinymce.get === 'function'
                        ? window.tinymce.get('press_course_certificate_html')
                        : null;

                    if (visualEditor && !visualEditor.isHidden()) {
                        return visualEditor.getContent();
                    }

                    return certificateHtmlField ? certificateHtmlField.value : '';
                }

                function getCertificateCssValue() {
                    if (certificateCssCodeMirror) {
                        return certificateCssCodeMirror.getValue();
                    }

                    return certificateCssEditorField ? certificateCssEditorField.value : '';
                }

                function getCertificatePreviewData() {
                    return {
                        student_name: certificatePreviewSeed.student_name || '',
                        course_name: getPreviewFieldValue(courseTitleField, certificatePreviewSeed.course_name || ''),
                        course_duration: certificatePreviewSeed.course_duration || '',
                        completion_date: certificatePreviewSeed.completion_date || '',
                        certificate_description: getPreviewFieldValue(certificateDescriptionField, certificatePreviewSeed.certificate_description || ''),
                        logo_url: getPreviewFieldValue(certificateLogoUrlField, certificatePreviewSeed.logo_url || ''),
                        signature_url: getPreviewFieldValue(certificateSignatureUrlField, certificatePreviewSeed.signature_url || '')
                    };
                }

                function buildCertificatePreviewDocument(html, css, data) {
                    const renderedHtml = replaceCertificatePlaceholders(html, data);

                    return [
                        '<!DOCTYPE html>',
                        '<html lang="pt-BR">',
                        '<head>',
                        '<meta charset="UTF-8">',
                        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
                        '<title>Prévia do certificado</title>',
                        '<style>html{background:#e2e8f0;}body{overflow:auto !important;}</style>',
                        css ? '<style>\n' + css + '\n</style>' : '',
                        '</head>',
                        '<body>',
                        renderedHtml,
                        '</body>',
                        '</html>'
                    ].join('');
                }

                function renderCertificatePreview() {
                    if (!certificatePreviewFrame) {
                        return;
                    }

                    const previewDocument = buildCertificatePreviewDocument(
                        getCertificateHtmlValue(),
                        getCertificateCssValue(),
                        getCertificatePreviewData()
                    );

                    if ('srcdoc' in certificatePreviewFrame) {
                        certificatePreviewFrame.srcdoc = previewDocument;
                        return;
                    }

                    const frameDocument = certificatePreviewFrame.contentWindow
                        ? certificatePreviewFrame.contentWindow.document
                        : null;

                    if (!frameDocument) {
                        return;
                    }

                    frameDocument.open();
                    frameDocument.write(previewDocument);
                    frameDocument.close();
                }

                function scheduleCertificatePreviewRender() {
                    window.clearTimeout(certificatePreviewTimer);
                    certificatePreviewTimer = window.setTimeout(renderCertificatePreview, 120);
                }

                function bindCertificateTinyMcePreview() {
                    if (!(window.tinymce && typeof window.tinymce.get === 'function')) {
                        return;
                    }

                    const editor = window.tinymce.get('press_course_certificate_html');

                    if (!editor || editor.__pressLmsPreviewBound) {
                        return;
                    }

                    editor.__pressLmsPreviewBound = true;
                    editor.on('keyup change input SetContent Undo Redo NodeChange', scheduleCertificatePreviewRender);
                }

                if ($tabsRoot.length) {
                    const $buttons = $tabsRoot.find('.press-course-tabs__btn');
                    const $panels = $tabsRoot.find('.press-course-tab');

                    $tabsRoot.addClass('is-enhanced');

                    function activateTab(tabId) {
                        $buttons.each(function() {
                            const $button = $(this);
                            const isActive = $button.data('tab-target') === tabId;
                            $button.toggleClass('is-active', isActive);
                            $button.attr('aria-selected', isActive ? 'true' : 'false');
                        });

                        $panels.each(function() {
                            const $panel = $(this);
                            $panel.toggleClass('is-active', $panel.data('tab-panel') === tabId);
                        });

                        if (tabId === 'certificate' && typeof window.tinymce !== 'undefined') {
                            window.setTimeout(function() {
                                const editor = window.tinymce.get('press_course_certificate_html');
                                if (editor) {
                                    editor.execCommand('mceRepaint');
                                }

                                bindCertificateTinyMcePreview();
                                scheduleCertificatePreviewRender();
                            }, 50);
                        }
                    }

                    $buttons.on('click', function() {
                        activateTab($(this).data('tab-target'));
                    });

                    activateTab('details');
                }

                function openMedia(targetId, targetUrlId, title) {
                    const frame = wp.media({
                        title: title,
                        button: {
                            text: 'Usar imagem'
                        },
                        multiple: false
                    });

                    frame.on('select', function() {
                        const attachment = frame.state().get('selection').first().toJSON();
                        $('#' + targetId).val(attachment.id);
                        $('#' + targetUrlId).val(attachment.url);
                        scheduleCertificatePreviewRender();
                    });

                    frame.open();
                }

                function updateAccessDurationField() {
                    const type = $('#press_course_access_type').val();
                    const $valueField = $('#press_course_access_value');
                    const $help = $('#press_course_access_help');

                    if (type === 'lifetime') {
                        $valueField.prop('disabled', true);
                        $help.text('Configuração atual: acesso vitalício. O aluno não perde acesso após a compra.');
                        return;
                    }

                    $valueField.prop('disabled', false);

                    const rawValue = parseInt($valueField.val(), 10);
                    const value = Number.isFinite(rawValue) && rawValue > 0 ? rawValue : 1;
                    const unitMap = {
                        days: value === 1 ? 'dia' : 'dias',
                        months: value === 1 ? 'mês' : 'meses',
                        years: value === 1 ? 'ano' : 'anos'
                    };
                    const unit = unitMap[type] || 'dias';

                    $help.text('Configuração atual: ' + value + ' ' + unit + ' de acesso.');
                }

                $('#press_pick_certificate_logo').on('click', function(e) {
                    e.preventDefault();
                    openMedia('press_course_certificate_logo_id', 'press_course_certificate_logo_url', 'Selecionar logo do certificado');
                });

                $('#press_pick_certificate_signature').on('click', function(e) {
                    e.preventDefault();
                    openMedia('press_course_certificate_signature_id', 'press_course_certificate_signature_url', 'Selecionar assinatura do certificado');
                });

                $('#press_clear_certificate_logo').on('click', function(e) {
                    e.preventDefault();
                    $('#press_course_certificate_logo_id').val('');
                    $('#press_course_certificate_logo_url').val('');
                    scheduleCertificatePreviewRender();
                });

                $('#press_clear_certificate_signature').on('click', function(e) {
                    e.preventDefault();
                    $('#press_course_certificate_signature_id').val('');
                    $('#press_course_certificate_signature_url').val('');
                    scheduleCertificatePreviewRender();
                });

                $('#press_course_access_type').on('change', updateAccessDurationField);
                $('#press_course_access_value').on('input', updateAccessDurationField);
                updateAccessDurationField();

                if (
                    certificateCssEditorField &&
                    certificateCssEditorSettings &&
                    window.wp &&
                    wp.codeEditor &&
                    typeof wp.codeEditor.initialize === 'function'
                ) {
                    const certificateCssEditorInstance = wp.codeEditor.initialize(certificateCssEditorField, certificateCssEditorSettings);

                    if (certificateCssEditorInstance && certificateCssEditorInstance.codemirror) {
                        certificateCssCodeMirror = certificateCssEditorInstance.codemirror;
                        certificateCssCodeMirror.on('change', scheduleCertificatePreviewRender);
                    }
                } else if (certificateCssEditorField) {
                    $(certificateCssEditorField).on('input change', scheduleCertificatePreviewRender);
                }

                if (certificateHtmlField) {
                    $(certificateHtmlField).on('input change', scheduleCertificatePreviewRender);
                }

                if (certificateDescriptionField) {
                    $(certificateDescriptionField).on('input change', scheduleCertificatePreviewRender);
                }

                if (courseTitleField) {
                    $(courseTitleField).on('input change', scheduleCertificatePreviewRender);
                }

                if (certificateLogoUrlField) {
                    $(certificateLogoUrlField).on('change', scheduleCertificatePreviewRender);
                }

                if (certificateSignatureUrlField) {
                    $(certificateSignatureUrlField).on('change', scheduleCertificatePreviewRender);
                }

                if (window.tinymce) {
                    bindCertificateTinyMcePreview();

                    $(document).on('tinymce-editor-init', function(event, editor) {
                        if (editor && editor.id === 'press_course_certificate_html') {
                            bindCertificateTinyMcePreview();
                            scheduleCertificatePreviewRender();
                        }
                    });
                }

                scheduleCertificatePreviewRender();
            })(jQuery);
        </script>
<?php
    }

    public static function save($post_id, $post)
    {
        // Verify the metabox nonce and bail out on invalid saves.
        if (!isset($_POST['press_course_meta_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['press_course_meta_nonce']), 'press_course_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['press_course_certificate_html'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_html',
                wp_kses_post((string) wp_unslash($_POST['press_course_certificate_html']))
            );
        }

        if (isset($_POST['press_course_certificate_css'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_css',
                self::sanitize_certificate_css((string) $_POST['press_course_certificate_css'])
            );
        }

        // Persist the course price used to sync WooCommerce products.
        if (isset($_POST['press_course_price'])) {
            $raw = str_replace(',', '.', sanitize_text_field((string) wp_unslash($_POST['press_course_price'])));
            $raw = preg_replace('/[^0-9.]/', '', $raw);
            update_post_meta($post_id, '_press_course_price', $raw);
        }

        // Persist the optional course trailer URL.
        if (isset($_POST['press_course_trailer'])) {
            update_post_meta($post_id, '_press_course_trailer', esc_url_raw((string) wp_unslash($_POST['press_course_trailer'])));
        }
        if (isset($_POST['press_course_teacher'])) {
            update_post_meta($post_id, '_press_course_teacher', (int) wp_unslash($_POST['press_course_teacher']));
        }
        if (isset($_POST['press_course_access_type'])) {
            $access_type = sanitize_key((string) wp_unslash($_POST['press_course_access_type']));
            $access_value = isset($_POST['press_course_access_value']) ? (int) wp_unslash($_POST['press_course_access_value']) : 1;

            if (class_exists('PRESS_LMS_Enrollments') && method_exists('PRESS_LMS_Enrollments', 'normalize_access_settings')) {
                $settings = PRESS_LMS_Enrollments::normalize_access_settings($access_type, $access_value);
                update_post_meta($post_id, '_press_course_access_type', (string) $settings['type']);
                update_post_meta($post_id, '_press_course_access_value', (int) $settings['value']);
            }
        }
        $feature_catalog = self::get_feature_catalog();
        $submitted_features = isset($_POST['press_course_features']) && is_array($_POST['press_course_features'])
            ? array_map('sanitize_key', wp_unslash($_POST['press_course_features']))
            : [];
        $submitted_features = array_values(array_filter($submitted_features, static function ($key) use ($feature_catalog) {
            return isset($feature_catalog[$key]);
        }));
        update_post_meta($post_id, self::META_FEATURES, $submitted_features);

        if (isset($_POST['press_course_paused'])) {
            update_post_meta(
                $post_id,
                '_press_course_paused',
                (string) wp_unslash($_POST['press_course_paused']) === 'yes' ? 'yes' : 'no'
            );

            if (class_exists('PRESS_LMS_Woo') && method_exists('PRESS_LMS_Woo', 'sync_course_product_state')) {
                PRESS_LMS_Woo::sync_course_product_state((int) $post_id);
            }
        }
        // Product synchronization is handled by the WooCommerce integration layer.
        if (isset($_POST['press_course_certificate_description'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_description',
                wp_kses_post((string) wp_unslash($_POST['press_course_certificate_description']))
            );
        }

        if (isset($_POST['press_course_certificate_logo_id'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_logo_id',
                (int) wp_unslash($_POST['press_course_certificate_logo_id'])
            );
        }

        if (isset($_POST['press_course_certificate_signature_id'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_signature_id',
                (int) wp_unslash($_POST['press_course_certificate_signature_id'])
            );
        }
    }
}
