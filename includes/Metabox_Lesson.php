<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Lesson_Meta
{
    const META_COURSE_ID = '_press_lesson_course_id';
    const META_VIDEO_URL = '_press_lesson_video_url';
    const META_VIMEO_THUMBNAIL = '_press_lesson_vimeo_thumbnail_url';

    // Store lesson materials in the structured v2 array format.
    const META_MATERIALS = '_press_lesson_materials_v2';

    public static function init()
    {
        add_action('add_meta_boxes_press_lesson', [__CLASS__, 'add_boxes']);
        add_action('save_post_press_lesson', [__CLASS__, 'save'], 10, 2);

        // Enable the WordPress media uploader for lesson attachments.
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function enqueue_admin_assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'press_lesson') return;

        wp_enqueue_media();
    }

    public static function add_boxes()
    {
        add_meta_box(
            'press_lesson_details',
            'Detalhes da Aula',
            [__CLASS__, 'render'],
            'press_lesson',
            'normal',
            'high'
        );
    }

    private static function generate_item_id()
    {
        // Generate a stable short identifier that does not depend on the row index.
        return 'mt_' . substr(wp_generate_uuid4(), 0, 12);
    }

    /**
     * Migrate legacy lesson materials into the structured v2 format when needed.
     */
    private static function load_materials($post_id)
    {
        $v2 = get_post_meta($post_id, self::META_MATERIALS, true);
        if (is_array($v2) && !empty($v2)) {
            // Normalize stored rows defensively before using them.
            if (class_exists('PRESS_LMS_Materials')) {
                return PRESS_LMS_Materials::normalize_items($v2);
            }
            return $v2;
        }

        // Fall back to the legacy URL-only meta key when present.
        $legacy = get_post_meta($post_id, '_press_lesson_materials', true);
        if (is_array($legacy) && !empty($legacy)) {
            $items = [];
            foreach ($legacy as $url) {
                $url = trim((string)$url);
                if (!$url) continue;
                $items[] = [
                    'id' => self::generate_item_id(),
                    'type' => 'link',
                    'name' => self::default_name_from_url($url),
                    'url' => $url,
                    'attachment_id' => 0,
                ];
            }
            if (class_exists('PRESS_LMS_Materials')) {
                return PRESS_LMS_Materials::normalize_items($items);
            }
            return $items;
        }

        return [];
    }

    private static function default_name_from_url($url)
    {
        $url = (string)$url;
        $path = parse_url($url, PHP_URL_PATH);
        $base = $path ? basename($path) : $url;
        $base = urldecode($base);
        if (!$base || $base === '/' || $base === '.') return 'Material';
        return $base;
    }

    private static function admin_icon($kind)
    {
        // Reuse the SVG icons already shipped with the plugin.
        if (class_exists('PRESS_LMS_Materials')) {
            $img = PRESS_LMS_Materials::get_icon_img_html($kind, 18);
            return '<span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;background:#f6f7f7;border:1px solid #e5e5e5;">' . $img . '</span>';
        }

        // Fall back to a generic attachment icon when the material helper is unavailable.
        return '<span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;background:#f6f7f7;border:1px solid #e5e5e5;font-size:16px;">📎</span>';
    }

    public static function render($post)
    {
        wp_nonce_field('press_lesson_meta_save', 'press_lesson_meta_nonce');

        $course_id = (int) get_post_meta($post->ID, self::META_COURSE_ID, true);

        if ($course_id <= 0 && !empty($_GET['course_id'])) {
            $course_id = (int) $_GET['course_id'];
        }
        $video_url = get_post_meta($post->ID, self::META_VIDEO_URL, true);

        $materials = self::load_materials($post->ID);

        // Current Vimeo validation status for this lesson.
        $vimeo_id    = (int) get_post_meta($post->ID, '_press_lesson_vimeo_id', true);
        $vimeo_title = (string) get_post_meta($post->ID, '_press_lesson_vimeo_title', true);
        $vimeo_error = (string) get_post_meta($post->ID, '_press_lesson_vimeo_error', true);

        // Courses available for linking this lesson.
        $courses = get_posts([
            'post_type' => 'press_course',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $teachers = get_posts([
            'post_type'      => 'press_teacher',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        echo '<p><label><strong>Curso</strong></label><br>';
        echo '<select name="press_lesson_course_id" class="widefat">';
        echo '<option value="">-- Selecione --</option>';
        foreach ($courses as $c) {
            $selected = ((int)$course_id === (int)$c->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($c->ID) . '" ' . $selected . '>' . esc_html($c->post_title) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label><strong>Vídeo (Vimeo/YouTube URL)</strong></label><br>';
        echo '<input type="url" name="press_lesson_video_url" value="' . esc_attr($video_url) . '" class="widefat" placeholder="https://vimeo.com/... ou https://youtu.be/..."></p>';

        // Vimeo validation and preview block.
        echo '<div style="padding:12px;border:1px solid #e5e5e5;border-radius:10px;background:#fff;margin:10px 0;">';
        echo '<strong>Vimeo (validação via API)</strong><br>';

        if ($vimeo_id && !$vimeo_error) {
            echo '<p style="margin:6px 0;color:#0a7b34;"><strong>OK:</strong> Vimeo ID #' . esc_html($vimeo_id) . ' — ' . esc_html($vimeo_title ?: 'Vídeo validado') . '</p>';
            echo '<div style="max-width:860px;margin-top:10px;">';
            if (class_exists('PRESS_LMS_Vimeo')) {
                echo PRESS_LMS_Vimeo::get_embed_html($vimeo_id);
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

        $default_teacher = $course_id > 0 ? (int) get_post_meta($course_id, '_press_course_teacher', true) : 0;
        $lesson_teacher = (int) get_post_meta($post->ID, '_press_lesson_teacher', true);

        echo '<label for="press_lesson_teacher">Professor da aula</label>';
        echo '<select name="press_lesson_teacher" id="press_lesson_teacher" class="widefat">';
        $default_teacher_label = $default_teacher > 0 ? get_the_title($default_teacher) : 'nenhum professor definido no curso';
        echo '<option value="">Usar professor do curso (' . esc_html($default_teacher_label) . ')</option>';
        foreach ($teachers as $teacher) {
            $selected = selected($lesson_teacher, $teacher->ID, false);
            echo '<option value="' . esc_attr($teacher->ID) . '"' . $selected . '>' . esc_html($teacher->post_title) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Lesson materials editor.
        echo '<hr>';
        echo '<div style="margin-top:10px;">';
        echo '<p style="margin:0 0 6px 0;"><strong>Materiais</strong></p>';
        echo '<p style="margin:0 0 10px 0;color:#666;">Adicione anexos (upload/biblioteca) e links com nome (texto exibido).</p>';

        echo '<div style="display:flex;gap:8px;margin-bottom:10px;">';
        echo '<button type="button" class="button" id="pressAddFile">+ Adicionar anexo</button>';
        echo '<button type="button" class="button" id="pressAddLink">+ Adicionar link</button>';
        echo '</div>';

        echo '<div id="pressMaterialsList">';

        if (!$materials) {
            echo '<div class="press-material-empty" style="padding:10px;border:1px dashed #ccd0d4;border-radius:10px;color:#666;">Nenhum material adicionado ainda.</div>';
        } else {
            foreach ($materials as $idx => $m) {
                self::render_material_row($idx, $m);
            }
        }

        echo '</div>';
        echo '</div>';

        // Hidden row template used by the inline materials editor.
        echo '<script type="text/template" id="pressMaterialRowTpl">';
        self::render_material_row('__INDEX__', [
            'id' => '__ID__',
            'type' => 'link',
            'url' => '',
            'name' => '',
            'attachment_id' => 0,
        ], true);
        echo '</script>';

?>
        <script>
            (function() {
                function qs(sel, root) {
                    return (root || document).querySelector(sel);
                }

                function qsa(sel, root) {
                    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
                }

                function uid() {
                    // Generate a stable client-side identifier for each row.
                    return 'mt_' + Math.random().toString(16).slice(2) + Date.now().toString(16);
                }

                function updateEmptyState() {
                    var list = qs('#pressMaterialsList');
                    if (!list) return;
                    var rows = qsa('.press-material-row', list);
                    var empty = qs('.press-material-empty', list);
                    if (rows.length === 0) {
                        if (!empty) {
                            var div = document.createElement('div');
                            div.className = 'press-material-empty';
                            div.style.cssText = 'padding:10px;border:1px dashed #ccd0d4;border-radius:10px;color:#666;';
                            div.textContent = 'Nenhum material adicionado ainda.';
                            list.appendChild(div);
                        }
                    } else {
                        if (empty) empty.remove();
                    }
                }

                function toggleRowUI(row) {
                    var typeEl = qs('.press-material-type', row);
                    var isFile = typeEl && typeEl.value === 'file';

                    var fileBox = qs('.press-material-file', row);
                    var linkBox = qs('.press-material-link', row);

                    if (fileBox) fileBox.style.display = isFile ? 'block' : 'none';
                    if (linkBox) linkBox.style.display = isFile ? 'none' : 'block';
                }

                function bindRow(row) {
                    var typeEl = qs('.press-material-type', row);
                    var removeBtn = qs('.press-material-remove', row);
                    var upBtn = qs('.press-material-up', row);
                    var downBtn = qs('.press-material-down', row);
                    var pickBtn = qs('.press-material-pick', row);
                    var clearBtn = qs('.press-material-clear', row);

                    if (typeEl) {
                        typeEl.addEventListener('change', function() {
                            toggleRowUI(row);
                        });
                    }

                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            row.remove();
                            updateEmptyState();
                        });
                    }

                    function moveRow(dir) {
                        var parent = row.parentNode;
                        if (!parent) return;
                        if (dir < 0) {
                            var prev = row.previousElementSibling;
                            if (prev) parent.insertBefore(row, prev);
                        } else {
                            var next = row.nextElementSibling;
                            if (next) parent.insertBefore(next, row);
                        }
                    }

                    if (upBtn) upBtn.addEventListener('click', function() {
                        moveRow(-1);
                    });
                    if (downBtn) downBtn.addEventListener('click', function() {
                        moveRow(1);
                    });

                    if (pickBtn) {
                        pickBtn.addEventListener('click', function(e) {
                            e.preventDefault();

                            if (typeof wp === 'undefined' || !wp.media) {
                                alert('WP Media não disponível.');
                                return;
                            }

                            var frame = wp.media({
                                title: 'Selecionar arquivo(s)',
                                button: {
                                    text: 'Usar'
                                },
                                multiple: true
                            });

                            frame.on('select', function() {
                                var selection = frame.state().get('selection');
                                if (!selection) return;

                                var items = selection.toArray().map(function(model) {
                                    return model.toJSON();
                                });
                                if (!items.length) return;

                                // Apply the first file to the current row.
                                var first = items.shift();
                                applyAttachmentToRow(row, first);

                                // Create extra rows for the remaining selected files.
                                items.forEach(function(att) {
                                    addRow({
                                        type: 'file',
                                        attachment_id: att.id,
                                        url: att.url,
                                        name: att.title || att.filename || 'Arquivo'
                                    });
                                });
                            });

                            frame.open();
                        });
                    }

                    function applyAttachmentToRow(r, att) {
                        var attIdEl = qs('.press-material-attachment-id', r);
                        var urlEl = qs('.press-material-file-url', r);
                        var nameEl = qs('.press-material-name', r);
                        var typeEl = qs('.press-material-type', r);

                        if (typeEl) typeEl.value = 'file';
                        if (attIdEl) attIdEl.value = att.id || '';
                        if (urlEl) urlEl.value = att.url || '';
                        if (nameEl && !nameEl.value) nameEl.value = att.title || att.filename || 'Arquivo';

                        toggleRowUI(r);
                    }

                    if (clearBtn) {
                        clearBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            var attIdEl = qs('.press-material-attachment-id', row);
                            var urlEl = qs('.press-material-file-url', row);
                            if (attIdEl) attIdEl.value = '';
                            if (urlEl) urlEl.value = '';
                        });
                    }

                    toggleRowUI(row);
                }

                function addRow(data) {
                    var tpl = qs('#pressMaterialRowTpl');
                    var list = qs('#pressMaterialsList');
                    if (!tpl || !list) return;

                    var index = String(Date.now() + Math.floor(Math.random() * 1000));
                    var id = uid();

                    var html = tpl.innerHTML
                        .replaceAll('__INDEX__', index)
                        .replaceAll('__ID__', id);

                    var wrap = document.createElement('div');
                    wrap.innerHTML = html;
                    var row = wrap.firstElementChild;
                    if (!row) return;

                    // Apply the initial row payload.
                    if (data) {
                        var typeEl = qs('select.press-material-type', row);
                        var urlLinkEl = qs('.press-material-link-url', row);
                        var urlFileEl = qs('.press-material-file-url', row);
                        var nameEl = qs('.press-material-name', row);
                        var attEl = qs('.press-material-attachment-id', row);

                        if (typeEl && data.type) typeEl.value = data.type;
                        if (nameEl && data.name) nameEl.value = data.name;
                        if (attEl && data.attachment_id) attEl.value = data.attachment_id;

                        // Mirror the URL into both inputs because the row can switch types.
                        if (urlFileEl && data.url) urlFileEl.value = data.url;
                        if (urlLinkEl && data.url) urlLinkEl.value = data.url;

                        toggleRowUI(row);
                    }

                    list.appendChild(row);
                    bindRow(row);
                    updateEmptyState();
                }

                // Bind rows already rendered by PHP.
                qsa('.press-material-row').forEach(bindRow);

                // Bind toolbar buttons.
                var addFile = qs('#pressAddFile');
                var addLink = qs('#pressAddLink');

                if (addFile) {
                    addFile.addEventListener('click', function() {
                        addRow({
                            type: 'file',
                            url: '',
                            name: '',
                            attachment_id: 0
                        });
                    });
                }

                if (addLink) {
                    addLink.addEventListener('click', function() {
                        addRow({
                            type: 'link',
                            url: '',
                            name: '',
                            attachment_id: 0
                        });
                    });
                }

                updateEmptyState();
            })();
        </script>
<?php
    }

    private static function render_material_row($idx, $m, $is_template = false)
    {
        $id   = isset($m['id']) ? (string)$m['id'] : '';
        $type = isset($m['type']) ? (string)$m['type'] : 'link';
        $url  = isset($m['url']) ? (string)$m['url'] : '';
        $name = isset($m['name']) ? (string)$m['name'] : '';
        $name = is_scalar($name) ? (string)$name : '';
        $url  = is_scalar($url)  ? (string)$url  : '';
        $id   = is_scalar($id)   ? (string)$id   : '';
        $attachment_id = isset($m['attachment_id']) ? (int)$m['attachment_id'] : 0;

        if ($id === '') $id = self::generate_item_id();

        $kind = 'others';
        if (class_exists('PRESS_LMS_Materials')) {
            $kind = PRESS_LMS_Materials::detect_kind($url, $attachment_id);
        }

        $idx_attr = $is_template ? '__INDEX__' : $idx;
        $id_attr  = $is_template ? '__ID__' : $id;

        $row_style = 'padding:12px;border:1px solid #e5e5e5;border-radius:12px;background:#fff;margin-bottom:10px;';
        $header_style = 'display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;';
        $left_style = 'display:flex;align-items:center;gap:10px;';

        echo '<div class="press-material-row" style="' . esc_attr($row_style) . '">';

        echo '<div style="' . esc_attr($header_style) . '">';
        echo '<div style="' . esc_attr($left_style) . '">';
        echo self::admin_icon($kind);
        echo '<strong style="font-size:13px;">Material</strong>';
        echo '</div>';

        echo '<div style="display:flex;gap:6px;">';
        echo '<button type="button" class="button press-material-up" title="Subir">↑</button>';
        echo '<button type="button" class="button press-material-down" title="Descer">↓</button>';
        echo '<button type="button" class="button press-material-remove" title="Remover" style="color:#b32d2e;">Remover</button>';
        echo '</div>';
        echo '</div>';

        // Persist the stable item identifier.
        echo '<input type="hidden" name="press_material_id[' . esc_attr($idx_attr) . ']" value="' . esc_attr($id_attr) . '">';

        // Material type selector.
        echo '<p style="margin:0 0 8px 0;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:4px;">Tipo</label>';
        echo '<select class="widefat press-material-type" name="press_material_type[' . esc_attr($idx_attr) . ']">';
        echo '<option value="file" ' . selected($type, 'file', false) . '>Anexo</option>';
        echo '<option value="link" ' . selected($type, 'link', false) . '>Link</option>';
        echo '</select>';
        echo '</p>';

        // Display name shown to the student.
        echo '<p style="margin:0 0 8px 0;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:4px;">Nome (texto exibido)</label>';
        echo '<input class="widefat press-material-name" name="press_material_name[' . esc_attr($idx_attr) . ']" value="' . esc_attr($name) . '" placeholder="Ex.: Apostila em PDF / Slides / Link do Drive">';
        echo '</p>';

        // File picker fields.
        echo '<div class="press-material-file">';
        echo '<p style="margin:0 0 8px 0;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:4px;">Arquivo</label>';
        echo '<input type="hidden" class="press-material-attachment-id" name="press_material_attachment_id[' . esc_attr($idx_attr) . ']" value="' . esc_attr($attachment_id) . '">';
        echo '<input class="widefat press-material-file-url" name="press_material_url_file[' . esc_attr($idx_attr) . ']" value="' . esc_attr($url) . '" placeholder="Selecione um arquivo ou cole uma URL">';
        echo '<div style="display:flex;gap:8px;margin-top:8px;">';
        echo '<button type="button" class="button press-material-pick">Selecionar arquivo</button>';
        echo '<button type="button" class="button press-material-clear">Limpar</button>';
        echo '</div>';
        echo '</p>';
        echo '</div>';

        // External link fields.
        echo '<div class="press-material-link">';
        echo '<p style="margin:0 0 8px 0;">';
        echo '<label style="display:block;font-weight:600;margin-bottom:4px;">URL</label>';
        echo '<input class="widefat press-material-link-url" name="press_material_url_link[' . esc_attr($idx_attr) . ']" value="' . esc_attr($url) . '" placeholder="https://...">';
        echo '</p>';
        echo '</div>';

        echo '</div>';
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['press_lesson_meta_nonce']) || !wp_verify_nonce($_POST['press_lesson_meta_nonce'], 'press_lesson_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $course_id = intval($_POST['press_lesson_course_id'] ?? 0);
        $video_url = esc_url_raw($_POST['press_lesson_video_url'] ?? '');

        update_post_meta($post_id, self::META_COURSE_ID, $course_id);
        global $wpdb;

        $wpdb->update(
            $wpdb->posts,
            ['post_parent' => $course_id],
            ['ID' => $post_id]
        );

        clean_post_cache($post_id);
        update_post_meta($post_id, self::META_VIDEO_URL, $video_url);

        // Rebuild the structured v2 materials array from the submitted fields.
        $items = [];

        $ids       = $_POST['press_material_id'] ?? [];
        $types     = $_POST['press_material_type'] ?? [];
        $names     = $_POST['press_material_name'] ?? [];
        $urls_file = $_POST['press_material_url_file'] ?? [];
        $urls_link = $_POST['press_material_url_link'] ?? [];
        $atts      = $_POST['press_material_attachment_id'] ?? [];

        if (is_array($types)) {
            foreach ($types as $k => $t) {
                $type = is_string($t) ? sanitize_key($t) : 'link';
                $type = in_array($type, ['file', 'link'], true) ? $type : 'link';

                $id = isset($ids[$k]) ? sanitize_text_field((string)$ids[$k]) : '';
                if ($id === '') $id = self::generate_item_id();

                $name = isset($names[$k]) ? sanitize_text_field((string)$names[$k]) : '';

                $att_id = isset($atts[$k]) ? intval($atts[$k]) : 0;

                // Read the URL from the field set that matches the current type.
                if ($type === 'file') {
                    $url = isset($urls_file[$k]) ? (string)$urls_file[$k] : '';
                } else {
                    $url = isset($urls_link[$k]) ? (string)$urls_link[$k] : '';
                }

                $url = trim((string) $url);

                // Validate the row based on the selected material type.
                if ($type === 'file') {
                    // Prefer the official attachment URL whenever an attachment ID is available.
                    if ($att_id > 0) {
                        $att_url = wp_get_attachment_url($att_id);
                        if ($att_url) {
                            $url = esc_url_raw($att_url);
                        }
                    } else {
                        // Accept a manual file URL when no attachment ID is present.
                        $url = $url ? esc_url_raw($url) : '';
                    }

                    if ($att_id <= 0 && $url === '') {
                        continue;
                    }
                } else {
                    $url = $url ? esc_url_raw($url) : '';
                    if ($url === '') continue;

                    // External links never keep an attachment reference.
                    $att_id = 0;
                }

                if ($name === '') {
                    $name = self::default_name_from_url($url ?: 'Material');
                }

                $items[] = [
                    'id'            => $id,
                    'type'          => $type,
                    'name'          => $name,
                    'url'           => $url,
                    'attachment_id' => (int) $att_id,
                ];
            }
        }

        // De-duplicate rows by ID in case the DOM duplicated submitted fields.
        $dedup = [];
        foreach ($items as $it) {
            if (!is_array($it) || empty($it['id'])) continue;
            $dedup[(string)$it['id']] = $it;
        }
        $items = array_values($dedup);

        // Normalize the stored payload before saving it.
        if (class_exists('PRESS_LMS_Materials')) {
            $items = PRESS_LMS_Materials::normalize_items($items);
            if (is_array($items)) $items = array_values($items);
        }
        if (isset($_POST['press_lesson_teacher'])) {
            update_post_meta($post_id, '_press_lesson_teacher', (int) $_POST['press_lesson_teacher']);
        }
        update_post_meta($post_id, self::META_MATERIALS, $items);

        // Validate Vimeo metadata and cache the current video payload.
        if ($video_url === '' || stripos($video_url, 'vimeo.com') === false) {
            delete_post_meta($post_id, '_press_lesson_vimeo_id');
            delete_post_meta($post_id, '_press_lesson_vimeo_title');
            delete_post_meta($post_id, '_press_lesson_vimeo_link');
            delete_post_meta($post_id, '_press_lesson_vimeo_embed_html');
            delete_post_meta($post_id, '_press_lesson_vimeo_error');
            delete_post_meta($post_id, self::META_VIMEO_THUMBNAIL);
            return;
        }

        if (!class_exists('PRESS_LMS_Vimeo')) {
            update_post_meta($post_id, '_press_lesson_vimeo_error', 'Classe Vimeo não carregada.');
            return;
        }

        $video_id = PRESS_LMS_Vimeo::parse_video_id($video_url);
        if (!$video_id) {
            update_post_meta($post_id, '_press_lesson_vimeo_error', 'Não foi possível extrair o ID do vídeo do Vimeo.');
            delete_post_meta($post_id, self::META_VIMEO_THUMBNAIL);
            return;
        }

        if (!PRESS_LMS_Vimeo::has_token()) {
            update_post_meta($post_id, '_press_lesson_vimeo_id', (int)$video_id);
            update_post_meta($post_id, '_press_lesson_vimeo_title', '');
            update_post_meta($post_id, '_press_lesson_vimeo_link', $video_url);
            update_post_meta($post_id, '_press_lesson_vimeo_embed_html', PRESS_LMS_Vimeo::get_embed_html($video_id));
            update_post_meta($post_id, '_press_lesson_vimeo_error', 'Token Vimeo não configurado. Configure em Pressplay LMS → Configurações.');
            delete_post_meta($post_id, self::META_VIMEO_THUMBNAIL);
            return;
        }

        $data = PRESS_LMS_Vimeo::get_video_data($video_id);

        if (is_wp_error($data)) {
            update_post_meta($post_id, '_press_lesson_vimeo_id', (int)$video_id);
            update_post_meta($post_id, '_press_lesson_vimeo_title', '');
            update_post_meta($post_id, '_press_lesson_vimeo_link', $video_url);
            update_post_meta($post_id, '_press_lesson_vimeo_embed_html', PRESS_LMS_Vimeo::get_embed_html($video_id));
            update_post_meta($post_id, '_press_lesson_vimeo_error', $data->get_error_message());
            delete_post_meta($post_id, self::META_VIMEO_THUMBNAIL);
            return;
        }

        $title = '';
        if (is_array($data) && !empty($data['name'])) {
            $title = (string)$data['name'];
        }

        $thumbnail_url = PRESS_LMS_Vimeo::extract_thumbnail_url($data);

        update_post_meta($post_id, '_press_lesson_vimeo_id', (int)$video_id);
        update_post_meta($post_id, '_press_lesson_vimeo_title', $title);
        update_post_meta($post_id, '_press_lesson_vimeo_link', $video_url);
        update_post_meta($post_id, '_press_lesson_vimeo_embed_html', PRESS_LMS_Vimeo::get_embed_html($video_id));
        if ($thumbnail_url !== '') {
            update_post_meta($post_id, self::META_VIMEO_THUMBNAIL, esc_url_raw($thumbnail_url));
        } else {
            delete_post_meta($post_id, self::META_VIMEO_THUMBNAIL);
        }
        delete_post_meta($post_id, '_press_lesson_vimeo_error');
    }
}
