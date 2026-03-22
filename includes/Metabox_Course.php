<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Course_Meta
{
    const META_FEATURES = '_press_course_features';

    public static function init()
    {
        add_action('add_meta_boxes_press_course', [__CLASS__, 'add_boxes']);
        add_action('save_post_press_course', [__CLASS__, 'save'], 10, 2);
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
        $lessons_count = count(self::get_course_lessons((int) $post->ID));
        $selected_features = self::get_selected_features((int) $post->ID);
        $features_count = count($selected_features);

        $certificate_html = (string) get_post_meta($post->ID, '_press_course_certificate_html', true);

        if ($certificate_html === '' || self::is_legacy_default_certificate_html($certificate_html)) {
            $certificate_html = self::get_default_certificate_html();
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

        echo '<p><label><strong>Descrição do certificado</strong></label><br>';
        echo '<textarea name="press_course_certificate_description" class="widefat" rows="4" placeholder="Ex.: Certificamos que o aluno concluiu com êxito este curso e demonstrou domínio dos conteúdos propostos.">' . esc_textarea($certificate_description) . '</textarea></p>';

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
        echo '<p style="color:#666;margin-bottom:8px;">Você pode personalizar o HTML do certificado usando os placeholders abaixo:</p>';
        echo '<div style="padding:12px;border:1px solid #dcdcde;border-radius:8px;background:#fff;margin-bottom:12px;">';
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
        echo '</div>';

        echo '<div class="press-course-tab" data-tab-panel="lessons">';
        self::render_lessons_section($post);
        echo '</div>';
        echo '</div>';
?>
        <script>
            (function($) {
                const $tabsRoot = $('#press-course-tabs');

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
                });

                $('#press_clear_certificate_signature').on('click', function(e) {
                    e.preventDefault();
                    $('#press_course_certificate_signature_id').val('');
                    $('#press_course_certificate_signature_url').val('');
                });

                $('#press_course_access_type').on('change', updateAccessDurationField);
                $('#press_course_access_value').on('input', updateAccessDurationField);
                updateAccessDurationField();
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
