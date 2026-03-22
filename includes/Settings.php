<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Settings
{
    const OPTION_KEY = 'press_lms_settings';
    const GROUP_KEY  = 'press_lms_settings_group';
    const PAGE_SLUG  = 'press-lms-settings';
    const CUSTOM_CSS_KEY = 'frontend_custom_css';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_init', [__CLASS__, 'maybe_redirect_legacy_admin_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_panel_assets']);
        add_action('admin_post_press_lms_manage_enrollment', [__CLASS__, 'handle_manage_enrollment']);
        add_action('template_redirect', [__CLASS__, 'maybe_render_css_preview_probe']);
    }

    public static function menu()
    {
        // Use the bundled plugin icon when available.
        $icon_url = PRESS_LMS_URL . 'assets/pressplay_lms_logo.png';

        // Fall back to a Dashicon if the custom icon cannot be resolved.
        if (empty($icon_url)) {
            $icon_url = 'dashicons-welcome-learn-more';
        }

        add_menu_page(
            'Pressplay LMS',
            'Pressplay LMS',
            'manage_options',
            'press-lms',
            [__CLASS__, 'page_enrollments'],
            $icon_url,
            6
        );
        add_submenu_page(
            'press-lms',
            'Matrículas',
            'Matrículas',
            'manage_options',
            'press-lms',
            [__CLASS__, 'page_enrollments']
        );
        add_submenu_page(
            'press-lms', // Parent LMS menu slug.
            'Professores', // Page title.
            'Professores', // Menu label.
            'edit_posts', // Required capability.
            'edit.php?post_type=press_teacher' // Native teacher post type screen.
        );
        add_submenu_page(
            'press-lms',
            'Configurações',
            'Configurações',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'page_settings']
        );

    }

    public static function register()
    {
        register_setting(self::GROUP_KEY, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);

        // Settings sections.
        add_settings_section(
            'press_lms_section_brand',
            'Marca e E-mails',
            function () {
                echo '<p>Configure informações básicas da marca usadas no fluxo do LMS e e-mails.</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_section(
            'press_lms_section_vimeo',
            'Vimeo API',
            function () {
                echo '<p>Configure um <strong>Vimeo Access Token</strong> para permitir validação de vídeos e exibição de player com acesso via conta do criador.</p>';
                echo '<p class="description">Observação: para o Vimeo permitir embed, o vídeo precisa estar configurado como embeddable (mesmo que seja “não listado” ou privado com embed liberado).</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_section(
            'press_lms_section_styling',
            'CSS Personalizado',
            function () {
                echo '<p>Escreva somente as regras que deseja sobrescrever. O CSS padrão do plugin continua ativo e o seu CSS entra por último nas páginas do LMS.</p>';
            },
            self::PAGE_SLUG
        );

        // Settings fields.
        add_settings_field(
            'brand_name',
            'Nome da marca',
            [__CLASS__, 'field_brand_name'],
            self::PAGE_SLUG,
            'press_lms_section_brand'
        );

        add_settings_field(
            'email_logo_url',
            'Logo para e-mails (URL)',
            [__CLASS__, 'field_email_logo_url'],
            self::PAGE_SLUG,
            'press_lms_section_brand'
        );

        add_settings_field(
            'vimeo_token',
            'Vimeo Access Token',
            [__CLASS__, 'field_vimeo_token'],
            self::PAGE_SLUG,
            'press_lms_section_vimeo'
        );
        add_settings_field(
            'delete_data_on_uninstall',
            'Apagar dados ao desinstalar',
            [__CLASS__, 'field_delete_data_on_uninstall'],
            self::PAGE_SLUG,
            'press_lms_section_brand'
        );

        add_settings_field(
            self::CUSTOM_CSS_KEY,
            'CSS das páginas do LMS',
            [__CLASS__, 'field_frontend_custom_css'],
            self::PAGE_SLUG,
            'press_lms_section_styling'
        );
    }

    public static function sanitize($input)
    {
        $output = [];

        $output['brand_name'] = sanitize_text_field($input['brand_name'] ?? 'Pressplay');
        $output['email_logo_url'] = esc_url_raw($input['email_logo_url'] ?? '');

        // Store the Vimeo token as plain text and mask it only at render time.
        $output['vimeo_token'] = trim(sanitize_text_field($input['vimeo_token'] ?? ''));
        $output['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']) ? 'yes' : 'no';
        $output[self::CUSTOM_CSS_KEY] = self::sanitize_custom_css((string) ($input[self::CUSTOM_CSS_KEY] ?? ''));
        return $output;
    }

    // Shared settings accessor used across the plugin.
    public static function get($key, $default = null)
    {
        $all = get_option(self::OPTION_KEY, []);
        if (!is_array($all)) $all = [];
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function field_brand_name()
    {
        $val = esc_attr(self::get('brand_name', 'Pressplay'));
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[brand_name]" value="' . $val . '" />';
        echo '<p class="description">Ex.: Cursos Espaço Pressplay</p>';
    }

    public static function field_email_logo_url()
    {
        $val = esc_attr(self::get('email_logo_url', ''));
        echo '<input type="url" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[email_logo_url]" value="' . $val . '" placeholder="https://..." />';
        echo '<p class="description">URL de uma imagem pública (PNG/JPG). Usada nos e-mails do plugin.</p>';
    }

    public static function field_vimeo_token()
    {
        $val = esc_attr(self::get('vimeo_token', ''));
        echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_KEY) . '[vimeo_token]" value="' . $val . '" autocomplete="new-password" />';
        echo '<p class="description">Crie no Vimeo: Developer → Apps → Personal access tokens. Permissões comuns: <code>public</code> e <code>private</code> (dependendo da privacidade dos seus vídeos).</p>';
    }
    public static function field_delete_data_on_uninstall()
    {
        $val = self::get('delete_data_on_uninstall', 'no');
        $checked = ($val === 'yes') ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[delete_data_on_uninstall]" value="yes" ' . $checked . '> Sim, apagar tudo (tabelas, conteúdos e configs) ao excluir o plugin</label>';
        echo '<p class="description" style="color:#b32d2e;">Atenção: isso é irreversível. Use apenas se tiver certeza.</p>';
    }

    public static function get_frontend_custom_css(): string
    {
        $css = self::get(self::CUSTOM_CSS_KEY, '');
        return is_string($css) ? trim($css) : '';
    }

    private static function sanitize_custom_css(string $css): string
    {
        $css = wp_unslash($css);
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        $css = wp_kses_no_null($css, ['slash_zero' => 'keep']);
        $css = str_replace(['<?', '?>'], '', $css);
        $css = preg_replace('#</style#i', '<\\/style', $css);

        return is_string($css) ? trim($css) : '';
    }

    public static function get_css_variable_suggestion_tabs(): array
    {
        $tabs = [
            'elementor' => [
                'key' => 'elementor',
                'label' => 'Elementor',
                'description' => 'Cores globais vindas do kit ativo do Elementor.',
                'empty_message' => 'Nenhuma cor global do Elementor foi encontrada nesta instalação.',
                'groups' => self::get_elementor_css_variable_suggestion_groups(),
            ],
            'wordpress' => [
                'key' => 'wordpress',
                'label' => 'WordPress',
                'description' => 'Paletas globais registradas pelo WordPress e pelo theme.json.',
                'empty_message' => 'Nenhuma paleta global do WordPress foi encontrada nesta instalação.',
                'groups' => self::get_wordpress_css_variable_suggestion_groups(),
            ],
            'theme' => [
                'key' => 'theme',
                'label' => 'Tema',
                'description' => 'Variáveis e cores expostas pelo tema ativo e pelo Customizer, quando disponíveis.',
                'empty_message' => 'Nenhuma variável adicional do tema ativo foi encontrada.',
                'groups' => self::get_theme_css_variable_suggestion_groups(),
            ],
        ];

        foreach ($tabs as $tab_key => $tab) {
            $tabs[$tab_key]['groups'] = array_values(array_filter(
                is_array($tab['groups'] ?? null) ? $tab['groups'] : [],
                static function ($group): bool {
                    return !empty($group['items']) && !empty($group['label']);
                }
            ));
            $tabs[$tab_key]['count'] = self::count_css_suggestion_items($tabs[$tab_key]['groups']);
        }

        return array_values($tabs);
    }

    private static function get_elementor_css_variable_suggestion_groups(): array
    {
        if (!class_exists('Elementor\\Plugin')) {
            return [];
        }

        $plugin = \Elementor\Plugin::$instance ?? null;
        if (!$plugin || empty($plugin->kits_manager) || !method_exists($plugin->kits_manager, 'get_active_kit_for_frontend')) {
            return [];
        }

        $kit = $plugin->kits_manager->get_active_kit_for_frontend();
        if (!$kit || !method_exists($kit, 'get_settings_for_display')) {
            return [];
        }

        $groups = [];
        $definitions = [
            'system_colors' => 'Variáveis do Elementor',
            'custom_colors' => 'Cores customizadas do Elementor',
        ];

        foreach ($definitions as $setting_key => $group_label) {
            $items = $kit->get_settings_for_display($setting_key);
            if (!is_array($items) || empty($items)) {
                continue;
            }

            $group_items = [];
            $seen = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = sanitize_key((string) ($item['_id'] ?? ''));
                if ($id === '') {
                    continue;
                }

                $insert = 'var(--e-global-color-' . $id . ')';
                if (isset($seen[$insert])) {
                    continue;
                }

                $label = (string) ($item['title'] ?? $id);
                $group_items[] = self::build_css_suggestion_item(
                    $label,
                    $insert,
                    (string) ($item['color'] ?? '')
                );

                $seen[$insert] = true;
            }

            if (!empty($group_items)) {
                $groups[] = [
                    'key' => sanitize_key($setting_key),
                    'label' => $group_label,
                    'items' => $group_items,
                ];
            }
        }

        return $groups;
    }

    private static function get_wordpress_css_variable_suggestion_groups(): array
    {
        $groups = [];
        $group_labels = [
            'default' => 'Paleta do WordPress',
            'custom' => 'Paleta personalizada',
            'user' => 'Paleta do site',
        ];

        if (function_exists('wp_get_global_settings')) {
            $palette_groups = wp_get_global_settings(['color', 'palette']);

            if (is_array($palette_groups)) {
                foreach ($palette_groups as $group_key => $items) {
                    if ($group_key === 'theme' || !is_array($items) || empty($items)) {
                        continue;
                    }

                    $group_items = [];
                    $seen = [];

                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $label = (string) ($item['name'] ?? $item['slug'] ?? '');
                        $slug = sanitize_title((string) ($item['slug'] ?? ''));
                        $color = trim((string) ($item['color'] ?? ''));
                        $insert = self::resolve_theme_css_insert_token($slug, $color);

                        if ($label === '' || $insert === '' || isset($seen[$insert])) {
                            continue;
                        }

                        $group_items[] = self::build_css_suggestion_item($label, $insert, $color);

                        $seen[$insert] = true;
                    }

                    if (!empty($group_items)) {
                        $groups[] = [
                            'key' => sanitize_key((string) $group_key),
                            'label' => $group_labels[$group_key] ?? ('Paleta ' . self::format_suggestion_label((string) $group_key)),
                            'items' => $group_items,
                        ];
                    }
                }
            }
        }

        return $groups;
    }

    private static function get_theme_css_variable_suggestion_groups(): array
    {
        $groups = [];

        if (function_exists('wp_get_global_settings')) {
            $palette_groups = wp_get_global_settings(['color', 'palette']);

            if (is_array($palette_groups)) {
                $items = $palette_groups['theme'] ?? [];

                if (is_array($items) && !empty($items)) {
                    $group_items = [];
                    $seen = [];

                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $label = (string) ($item['name'] ?? $item['slug'] ?? '');
                        $slug = sanitize_title((string) ($item['slug'] ?? ''));
                        $color = trim((string) ($item['color'] ?? ''));
                        $insert = self::resolve_theme_css_insert_token($slug, $color);

                        if ($label === '' || $insert === '' || isset($seen[$insert])) {
                            continue;
                        }

                        $group_items[] = self::build_css_suggestion_item($label, $insert, $color);
                        $seen[$insert] = true;
                    }

                    if (!empty($group_items)) {
                        $groups[] = [
                            'key' => 'theme',
                            'label' => 'Variáveis do tema',
                            'items' => $group_items,
                        ];
                    }
                }
            }
        }

        $theme_mod_items = [];
        $theme_mods = get_theme_mods();
        if (is_array($theme_mods)) {
            $seen = [];

            foreach ($theme_mods as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }

                $preview = self::normalize_preview_color($value);
                if ($preview === '' || isset($seen[$preview])) {
                    continue;
                }

                $theme_mod_items[] = self::build_css_suggestion_item(
                    self::format_suggestion_label((string) $key),
                    $preview,
                    $preview
                );

                $seen[$preview] = true;
            }
        }

        if (!empty($theme_mod_items)) {
            $groups[] = [
                'key' => 'theme-mods',
                'label' => 'Cores do Customizer',
                'items' => $theme_mod_items,
            ];
        }

        return $groups;
    }

    private static function resolve_theme_css_insert_token(string $slug, string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '';
        }

        if (preg_match('/^var\(/i', $color)) {
            return $color;
        }

        if ($slug !== '') {
            return 'var(--wp--preset--color--' . sanitize_title($slug) . ')';
        }

        return $color;
    }

    private static function normalize_preview_color(string $value): string
    {
        $value = trim($value);

        return preg_match('/^(#|rgba?\(|hsla?\()/i', $value) ? $value : '';
    }

    private static function build_css_suggestion_item(string $label, string $insert, string $color = '', string $meta = ''): array
    {
        $preview = self::normalize_preview_color($color);
        $preview_token = self::get_css_preview_resolution_token($color, $insert);

        return [
            'label' => $label,
            'insert' => $insert,
            'preview' => $preview,
            'preview_token' => $preview_token,
            'meta' => $meta !== '' ? $meta : $insert,
        ];
    }

    private static function get_css_preview_resolution_token(string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $preview = self::normalize_preview_color($candidate);
            if ($preview !== '') {
                return $preview;
            }

            if (preg_match('/^var\(\s*(--[A-Za-z0-9\-_]+)\s*\)$/', $candidate, $matches)) {
                return 'var(' . $matches[1] . ')';
            }
        }

        return '';
    }

    private static function format_suggestion_label(string $label): string
    {
        $label = trim(str_replace(['_', '-'], ' ', $label));
        if ($label === '') {
            return 'Variável';
        }

        return ucwords($label);
    }

    private static function count_css_suggestion_items(array $groups): int
    {
        $count = 0;

        foreach ($groups as $group) {
            $count += count(is_array($group['items'] ?? null) ? $group['items'] : []);
        }

        return $count;
    }

    private static function get_css_selector_hints(): array
    {
        return [
            '.presslms-btn',
            '.presslms-card',
            '.presslms-chip',
            '.presslms-h1',
            '.presslms-h2',
            '.presslms-course-side__btn',
            '.presslms-student-nav__link',
            '.presslms-materials__link',
        ];
    }

    public static function field_frontend_custom_css()
    {
        $css = self::get_frontend_custom_css();
        $tabs = self::get_css_variable_suggestion_tabs();
        $selectors = self::get_css_selector_hints();
        $field_name = self::OPTION_KEY . '[' . self::CUSTOM_CSS_KEY . ']';
        $active_tab = 'elementor';

        foreach ($tabs as $tab) {
            if (!empty($tab['count'])) {
                $active_tab = (string) ($tab['key'] ?? $active_tab);
                break;
            }
        }

        echo '<div class="presslms-css-field">';

        echo '<div class="presslms-css-suggestions">';
        echo '<div class="presslms-css-suggestions__header">';
        echo '<strong>Sugestões de variáveis e cores do site</strong>';
        echo '<p class="description">As categorias são montadas dinamicamente com base no Elementor, no WordPress e no tema ativo. Clique em uma sugestão para copiar a variável e colar no editor CSS.</p>';
        echo '</div>';

        if (!empty($tabs)) {
            echo '<div class="presslms-css-tabs" role="tablist" aria-label="Categorias de variáveis CSS">';

            foreach ($tabs as $tab) {
                $tab_key = sanitize_key((string) ($tab['key'] ?? ''));
                $tab_label = (string) ($tab['label'] ?? '');
                $tab_count = (int) ($tab['count'] ?? 0);
                $is_active = ($tab_key === $active_tab);

                if ($tab_key === '' || $tab_label === '') {
                    continue;
                }

                echo '<button type="button" class="presslms-css-tab js-presslms-css-tab' . ($is_active ? ' is-active' : '') . '" data-presslms-tab="' . esc_attr($tab_key) . '" id="presslms-css-tab-' . esc_attr($tab_key) . '" role="tab" aria-controls="presslms-css-pane-' . esc_attr($tab_key) . '" aria-selected="' . ($is_active ? 'true' : 'false') . '">';
                echo '<span class="presslms-css-tab__label">' . esc_html($tab_label) . '</span>';
                echo '<span class="presslms-css-tab__count">' . esc_html((string) $tab_count) . '</span>';
                echo '</button>';
            }

            echo '</div>';
            echo '<div class="presslms-css-tab-panels">';

            foreach ($tabs as $tab) {
                $tab_key = sanitize_key((string) ($tab['key'] ?? ''));
                $tab_description = (string) ($tab['description'] ?? '');
                $empty_message = (string) ($tab['empty_message'] ?? '');
                $groups = is_array($tab['groups'] ?? null) ? $tab['groups'] : [];
                $is_active = ($tab_key === $active_tab);

                if ($tab_key === '') {
                    continue;
                }

                echo '<section class="presslms-css-pane js-presslms-css-pane' . ($is_active ? ' is-active' : '') . '" id="presslms-css-pane-' . esc_attr($tab_key) . '" data-presslms-pane="' . esc_attr($tab_key) . '" role="tabpanel" aria-labelledby="presslms-css-tab-' . esc_attr($tab_key) . '"' . ($is_active ? '' : ' hidden') . '>';

                if ($tab_description !== '') {
                    echo '<p class="description presslms-css-pane__description">' . esc_html($tab_description) . '</p>';
                }

                if (!empty($groups)) {
                    foreach ($groups as $group) {
                        $group_label = (string) ($group['label'] ?? '');
                        $items = is_array($group['items'] ?? null) ? $group['items'] : [];

                        if ($group_label === '' || empty($items)) {
                            continue;
                        }

                        echo '<div class="presslms-css-suggestion-group">';
                        echo '<div class="presslms-css-suggestion-group__title">' . esc_html($group_label) . '</div>';
                        echo '<div class="presslms-css-suggestion-list">';

                        foreach ($items as $item) {
                            $label = (string) ($item['label'] ?? '');
                            $insert = (string) ($item['insert'] ?? '');
                            $preview = (string) ($item['preview'] ?? '');
                            $preview_token = (string) ($item['preview_token'] ?? '');
                            $meta = (string) ($item['meta'] ?? $insert);

                            if ($label === '' || $insert === '') {
                                continue;
                            }

                            $swatch_classes = 'presslms-css-suggestion__swatch js-presslms-css-swatch';
                            if ($preview === '') {
                                $swatch_classes .= ' presslms-css-suggestion__swatch--empty';
                            }

                            echo '<button type="button" class="presslms-css-suggestion js-presslms-css-copy" data-presslms-insert="' . esc_attr($insert) . '" data-presslms-label-default="Copiar" data-presslms-label-success="Copiado">';
                            echo '<span class="' . esc_attr($swatch_classes) . '"' . ($preview !== '' ? ' style="background:' . esc_attr($preview) . ';"' : '') . ($preview_token !== '' ? ' data-presslms-preview-token="' . esc_attr($preview_token) . '"' : '') . '></span>';
                            echo '<span class="presslms-css-suggestion__content">';
                            echo '<strong>' . esc_html($label) . '</strong>';
                            echo '<code>' . esc_html($meta) . '</code>';
                            echo '</span>';
                            echo '<span class="presslms-css-suggestion__action">Copiar</span>';
                            echo '</button>';
                        }

                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="presslms-css-empty-state">';
                    echo '<strong>Nada encontrado nesta categoria.</strong>';
                    echo '<p class="description">' . esc_html($empty_message !== '' ? $empty_message : 'Você ainda pode escrever seu CSS manualmente abaixo.') . '</p>';
                    echo '</div>';
                }

                echo '</section>';
            }

            echo '</div>';
            echo '<iframe class="presslms-css-preview-frame js-presslms-css-preview-frame" src="' . esc_url(self::get_css_preview_probe_url()) . '" title="Pré-visualização oculta de variáveis CSS" tabindex="-1" aria-hidden="true"></iframe>';
        } else {
            echo '<p class="description">Nenhuma variável global do Elementor, WordPress ou tema foi encontrada. Você ainda pode escrever seu CSS manualmente abaixo.</p>';
        }

        echo '</div>';

        echo '<textarea id="press_lms_frontend_custom_css" name="' . esc_attr($field_name) . '" class="large-text code presslms-css-editor" rows="18" spellcheck="false" placeholder=".presslms-btn {&#10;  background: var(--e-global-color-primary);&#10;  border-radius: 999px;&#10;}">' . esc_textarea($css) . '</textarea>';
        echo '<p class="description">Se deixar vazio, o plugin continua usando apenas o CSS padrão. Se preencher, as regras abaixo entram por último e sobrescrevem somente os seletores que você definir.</p>';
        echo '<p class="description"><strong>Exemplo:</strong> <code>.presslms-btn { background: var(--e-global-color-primary); color: #fff; }</code></p>';

        if (!empty($selectors)) {
            echo '<div class="presslms-css-selectors">';
            echo '<div class="presslms-css-selectors__title">Seletores úteis do LMS</div>';
            echo '<div class="presslms-css-selector-list">';

            foreach ($selectors as $selector) {
                echo '<code class="presslms-css-selector">' . esc_html($selector) . '</code>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private static function get_css_preview_probe_url(): string
    {
        return add_query_arg('presslms_css_preview', '1', home_url('/'));
    }

    public static function maybe_render_css_preview_probe(): void
    {
        if (is_admin()) {
            return;
        }

        $probe = isset($_GET['presslms_css_preview']) ? sanitize_text_field(wp_unslash($_GET['presslms_css_preview'])) : '';
        if ($probe !== '1') {
            return;
        }

        nocache_headers();
        status_header(200);

        echo '<!doctype html>';
        echo '<html ' . get_language_attributes() . '>';
        echo '<head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        wp_head();
        echo '</head>';
        echo '<body class="presslms-css-preview-probe">';
        if (function_exists('wp_body_open')) {
            wp_body_open();
        }
        echo '<div id="presslms-css-preview-probe" aria-hidden="true"></div>';
        wp_footer();
        echo '</body>';
        echo '</html>';
        exit;
    }
    public static function page_dashboard()
    {
        echo '<div class="wrap"><h1>Pressplay LMS</h1><p>Dashboard geral (atalhos, métricas, status).</p></div>';
    }

    public static function page_settings()
    {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap presslms-admin-page presslms-admin-page--settings">';
        echo '<div class="presslms-panel">';
        echo '<div class="presslms-page-header">';
        echo '<div>';
        echo '<h1 class="presslms-page-title">Configurações</h1>';
        echo '<p class="presslms-page-subtitle">Defina marca, e-mails, Vimeo, CSS customizado e preferências operacionais do Pressplay LMS.</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="presslms-admin-card">';
        settings_errors(self::OPTION_KEY);

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP_KEY);
        do_settings_sections(self::PAGE_SLUG);
        submit_button('Salvar configurações');
        echo '</form>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private static function get_admin_panel_url(string $page_slug, array $args = []): string
    {
        $url = admin_url('admin.php?page=' . $page_slug);
        return !empty($args) ? add_query_arg($args, $url) : $url;
    }

    public static function maybe_redirect_legacy_admin_pages(): void
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if (!in_array($page, ['press-lms-students', 'press-lms-enrollments'], true)) {
            return;
        }

        $query_args = wp_unslash($_GET);
        unset($query_args['page']);

        wp_safe_redirect(add_query_arg($query_args, self::get_admin_panel_url('press-lms')));
        exit;
    }

    private static function get_admin_panel_notice(?string $notice): ?array
    {
        $notice = sanitize_key((string) $notice);
        if ($notice === '') {
            return null;
        }

        $map = [
            'enrollment_blocked' => [
                'type' => 'warning',
                'message' => 'O acesso da matrícula foi bloqueado.',
            ],
            'enrollment_reactivated' => [
                'type' => 'success',
                'message' => 'A matrícula foi reativada com sucesso.',
            ],
            'enrollment_extended' => [
                'type' => 'success',
                'message' => 'A validade da matrícula foi prorrogada.',
            ],
            'enrollment_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível localizar a matrícula solicitada.',
            ],
            'enrollment_action_invalid' => [
                'type' => 'error',
                'message' => 'A ação solicitada para a matrícula é inválida.',
            ],
            'enrollment_permission_denied' => [
                'type' => 'error',
                'message' => 'Você não tem permissão para gerenciar esta matrícula.',
            ],
            'enrollment_nonce_invalid' => [
                'type' => 'error',
                'message' => 'Não foi possível validar a ação. Tente novamente.',
            ],
            'enrollment_update_failed' => [
                'type' => 'error',
                'message' => 'Não foi possível atualizar a matrícula.',
            ],
        ];

        return $map[$notice] ?? null;
    }

    private static function get_admin_enrollment_panel_data(): array
    {
        global $wpdb;

        $table_students    = PRESS_LMS_Database::table('students');
        $table_enrollments = PRESS_LMS_Database::table('enrollments');
        $table_progress    = PRESS_LMS_Database::table('progress');
        $posts_table       = $wpdb->posts;
        $postmeta_table    = $wpdb->postmeta;
        $users_table       = $wpdb->users;

        // Admin filters.
        $filter_course = isset($_GET['course']) ? (int) $_GET['course'] : 0;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_sort   = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date_desc';
        $now = current_time('mysql');

        $where = [];
        $params = [];

        $where[] = "1=1";

        if ($filter_course > 0) {
            $where[] = "e.course_id = %d";
            $params[] = $filter_course;
        }

        if ($filter_status === 'active') {
            $where[] = "e.status = %s AND (e.expires_at IS NULL OR e.expires_at > %s)";
            $params[] = 'active';
            $params[] = $now;
        } elseif ($filter_status === 'expired') {
            $where[] = "e.status = %s AND e.expires_at IS NOT NULL AND e.expires_at <= %s";
            $params[] = 'active';
            $params[] = $now;
        } elseif ($filter_status !== '') {
            $where[] = "e.status = %s";
            $params[] = $filter_status;
        }

        if ($filter_search !== '') {
            $like = '%' . $wpdb->esc_like($filter_search) . '%';

            $where[] = "(
        st.full_name LIKE %s
        OR u.display_name LIKE %s
        OR u.user_nicename LIKE %s
        OR u.user_login LIKE %s
        OR u.user_email LIKE %s
        OR c.post_title LIKE %s
    )";

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $order_by = "e.created_at DESC";

        switch ($filter_sort) {
            case 'name_asc':
                $order_by = "st.full_name ASC";
                break;
            case 'name_desc':
                $order_by = "st.full_name DESC";
                break;
            case 'date_asc':
                $order_by = "e.created_at ASC";
                break;
            case 'date_desc':
            default:
                $order_by = "e.created_at DESC";
                break;
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
    SELECT
        e.id,
        e.user_id,
        e.course_id,
        e.status,
        e.order_ref,
        e.payment_provider,
        e.created_at,
        e.updated_at,
        e.purchased_at,
        e.expires_at,

        COALESCE(NULLIF(st.full_name, ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), 'Sem nome') AS full_name,
        st.phone_raw,
        st.phone_e164,

        u.user_email,
        u.display_name,

        c.post_title AS course_title,

        COALESCE(pr.completed_lessons, 0) AS completed_lessons,
        COALESCE(tl.total_lessons, 0) AS total_lessons

    FROM {$table_enrollments} e

    LEFT JOIN {$table_students} st
        ON st.user_id = e.user_id

    LEFT JOIN {$users_table} u
        ON u.ID = e.user_id

    LEFT JOIN {$posts_table} c
        ON c.ID = e.course_id

    LEFT JOIN (
        SELECT
            user_id,
            course_id,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) AS completed_lessons
        FROM {$table_progress}
        GROUP BY user_id, course_id
    ) pr
        ON pr.user_id = e.user_id
        AND pr.course_id = e.course_id

    LEFT JOIN (
        SELECT
            pm.meta_value AS course_id,
            COUNT(p.ID) AS total_lessons
        FROM {$posts_table} p
        INNER JOIN {$postmeta_table} pm
            ON pm.post_id = p.ID
            AND pm.meta_key = '_press_lesson_course_id'
        WHERE p.post_type = 'press_lesson'
          AND p.post_status = 'publish'
        GROUP BY pm.meta_value
    ) tl
        ON tl.course_id = e.course_id

    WHERE {$where_sql}
    ORDER BY {$order_by}
";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $students = $wpdb->get_results($sql);

        $courses = get_posts([
            'post_type'      => 'press_course',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        return [
            'students' => $students,
            'courses' => $courses,
            'filter_course' => $filter_course,
            'filter_status' => $filter_status,
            'filter_search' => $filter_search,
            'filter_sort' => $filter_sort,
        ];
    }

    private static function render_enrollment_panel(string $page_slug, string $title, string $subtitle): void
    {
        if (!current_user_can('manage_options')) return;

        $data = self::get_admin_enrollment_panel_data();

        self::render_panel_template('alunos.php', [
            'students' => $data['students'],
            'courses' => $data['courses'],
            'filter_course' => $data['filter_course'],
            'filter_status' => $data['filter_status'],
            'filter_search' => $data['filter_search'],
            'filter_sort' => $data['filter_sort'],
            'panel_page_title' => $title,
            'panel_page_subtitle' => $subtitle,
            'panel_page_slug' => $page_slug,
            'panel_notice' => self::get_admin_panel_notice($_GET['press_lms_notice'] ?? ''),
        ]);
    }

    public static function page_students()
    {
        self::render_enrollment_panel(
            'press-lms',
            'Matrículas',
            'Gerencie acessos, validade, pedidos e progresso por matrícula.'
        );
    }

    public static function page_enrollments()
    {
        self::render_enrollment_panel(
            'press-lms',
            'Matrículas',
            'Gerencie acessos, validade, pedidos e progresso por matrícula.'
        );
    }

    public static function handle_manage_enrollment(): void
    {
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(self::get_admin_panel_url('press-lms', ['press_lms_notice' => 'enrollment_permission_denied']));
            exit;
        }

        $enrollment_id = isset($_GET['enrollment_id']) ? (int) $_GET['enrollment_id'] : 0;
        $action_type = isset($_GET['enrollment_action']) ? sanitize_key((string) $_GET['enrollment_action']) : '';
        $page_slug = isset($_GET['page_slug']) ? sanitize_key((string) $_GET['page_slug']) : 'press-lms';
        $allowed_pages = ['press-lms', 'press-lms-students', 'press-lms-enrollments'];

        if (!in_array($page_slug, $allowed_pages, true)) {
            $page_slug = 'press-lms';
        }

        if ($enrollment_id <= 0 || $action_type === '') {
            wp_safe_redirect(self::get_admin_panel_url($page_slug, ['press_lms_notice' => 'enrollment_invalid']));
            exit;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string) $_GET['_wpnonce'], 'press_lms_manage_enrollment_' . $enrollment_id)) {
            wp_safe_redirect(self::get_admin_panel_url($page_slug, ['press_lms_notice' => 'enrollment_nonce_invalid']));
            exit;
        }

        $enrollment = class_exists('PRESS_LMS_Enrollments')
            ? PRESS_LMS_Enrollments::get_enrollment_by_id($enrollment_id)
            : null;

        if (!$enrollment) {
            wp_safe_redirect(self::get_admin_panel_url($page_slug, ['press_lms_notice' => 'enrollment_invalid']));
            exit;
        }

        $updated = false;
        $notice = 'enrollment_update_failed';

        switch ($action_type) {
            case 'block':
                $updated = PRESS_LMS_Enrollments::deactivate_enrollment(
                    (int) $enrollment->user_id,
                    (int) $enrollment->course_id,
                    'blocked',
                    (int) ($enrollment->order_ref ?? 0)
                );
                $notice = $updated ? 'enrollment_blocked' : 'enrollment_update_failed';
                break;

            case 'reactivate':
                $updated = PRESS_LMS_Enrollments::reactivate_enrollment_by_id($enrollment_id);
                $notice = $updated ? 'enrollment_reactivated' : 'enrollment_update_failed';
                break;

            case 'extend_30_days':
                $updated = PRESS_LMS_Enrollments::extend_enrollment_by_id($enrollment_id, 30, 'days');
                $notice = $updated ? 'enrollment_extended' : 'enrollment_update_failed';
                break;

            case 'extend_90_days':
                $updated = PRESS_LMS_Enrollments::extend_enrollment_by_id($enrollment_id, 90, 'days');
                $notice = $updated ? 'enrollment_extended' : 'enrollment_update_failed';
                break;

            case 'extend_1_year':
                $updated = PRESS_LMS_Enrollments::extend_enrollment_by_id($enrollment_id, 1, 'years');
                $notice = $updated ? 'enrollment_extended' : 'enrollment_update_failed';
                break;

            default:
                $notice = 'enrollment_action_invalid';
                break;
        }

        wp_safe_redirect(self::get_admin_panel_url($page_slug, ['press_lms_notice' => $notice]));
        exit;
    }

    public static function page_progress()
    {
        echo '<div class="wrap"><h1>Progresso</h1><p>Relatórios de progresso por aluno/curso.</p></div>';
    }

    public static function enqueue_panel_assets($hook)
    {
        if (!is_admin()) return;

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        $allowed_pages = [
            'press-lms',
            self::PAGE_SLUG,
            'press-lms-enrollments',
            'press-lms-progress',
        ];

        if (!in_array($page, $allowed_pages, true)) {
            return;
        }

        wp_enqueue_style(
            'presslms-admin-panels',
            PRESS_LMS_URL . 'assets/css/admin-panels.css',
            ['press-lms-admin'],
            PRESS_LMS_VERSION
        );

        $script_dependencies = ['jquery'];
        $editor_config = null;

        if ($page === self::PAGE_SLUG && function_exists('wp_enqueue_code_editor')) {
            $editor_config = wp_enqueue_code_editor(['type' => 'text/css']);

            if ($editor_config) {
                $script_dependencies[] = 'code-editor';
            }
        }

        wp_enqueue_script(
            'presslms-admin-panels-js',
            PRESS_LMS_URL . 'assets/js/admin-panels.js',
            $script_dependencies,
            PRESS_LMS_VERSION,
            true
        );

        $script_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('presslms_admin_nonce'),
        ];

        if ($editor_config) {
            $script_data['cssEditor'] = [
                'fieldId' => 'press_lms_frontend_custom_css',
                'settings' => $editor_config,
            ];
        }

        wp_localize_script('presslms-admin-panels-js', 'presslmsAdmin', $script_data);
    }
    private static function render_panel_template($template_file, array $vars = [])
    {
        $template = PRESS_LMS_PATH . 'templates/panel/' . $template_file;

        if (!file_exists($template)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Template não encontrado: ' . esc_html($template) . '</p></div></div>';
            return;
        }

        extract($vars, EXTR_SKIP);
        include $template;
    }
}
