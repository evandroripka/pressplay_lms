<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Course_Meta
{
    public static function init()
    {
        add_action('add_meta_boxes_press_course', [__CLASS__, 'add_boxes']);
        add_action('save_post_press_course', [__CLASS__, 'save'], 10, 2);
    }


    private static function get_default_certificate_html(): string
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

    public static function render($post)
    {
        wp_nonce_field('press_course_meta_save', 'press_course_meta_nonce');

        $trailer    = get_post_meta($post->ID, '_press_course_trailer', true);
        $product_id = (int) get_post_meta($post->ID, '_press_course_product_id', true);
        $price      = get_post_meta($post->ID, '_press_course_price', true);

        // Carrega todos os professores cadastrados
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

        $certificate_html = (string) get_post_meta($post->ID, '_press_course_certificate_html', true);

        if ($certificate_html === '') {
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
            @media (max-width: 960px) {
                .press-course-tab__grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>';

        echo '<div class="press-course-tabs" id="press-course-tabs">';
        echo '<div class="press-course-tabs__nav" role="tablist" aria-label="Seções do curso">';
        echo '<button type="button" class="press-course-tabs__btn is-active" data-tab-target="details" role="tab" aria-selected="true">Detalhes</button>';
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
        echo '</div>';
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
            })(jQuery);
        </script>
<?php
    }

    public static function save($post_id, $post)
    {
        // ✅ Segurança primeiro
        if (!isset($_POST['press_course_meta_nonce']) || !wp_verify_nonce($_POST['press_course_meta_nonce'], 'press_course_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['press_course_certificate_html'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_html',
                wp_kses_post($_POST['press_course_certificate_html'])
            );
        }
        // Salva preço
        if (isset($_POST['press_course_price'])) {
            $raw = str_replace(',', '.', sanitize_text_field($_POST['press_course_price']));
            $raw = preg_replace('/[^0-9.]/', '', $raw);
            update_post_meta($post_id, '_press_course_price', $raw);
        }
        if (isset($_POST['press_course_certificate_html'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_html',
                wp_kses_post($_POST['press_course_certificate_html'])
            );
        }
        // Salva trailer
        if (isset($_POST['press_course_trailer'])) {
            update_post_meta($post_id, '_press_course_trailer', esc_url_raw($_POST['press_course_trailer']));
        }
        if (isset($_POST['press_course_teacher'])) {
            update_post_meta($post_id, '_press_course_teacher', (int) $_POST['press_course_teacher']);
        }
        // ❌ Removido: salvar product_id manualmente
        // Isso agora é responsabilidade do PRESS_LMS_Woo (criação automática).
        if (isset($_POST['press_course_certificate_description'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_description',
                wp_kses_post($_POST['press_course_certificate_description'])
            );
        }

        if (isset($_POST['press_course_certificate_logo_id'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_logo_id',
                (int) $_POST['press_course_certificate_logo_id']
            );
        }

        if (isset($_POST['press_course_certificate_signature_id'])) {
            update_post_meta(
                $post_id,
                '_press_course_certificate_signature_id',
                (int) $_POST['press_course_certificate_signature_id']
            );
        }
    }
}
