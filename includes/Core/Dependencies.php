<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Dependencies
{
    public static function init()
    {
        add_action('admin_notices', [__CLASS__, 'check_dependencies']);
    }

    public static function check_dependencies()
    {
        if (!current_user_can('activate_plugins')) return;

        // Load WordPress plugin helpers on demand.
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $missing = [];

        // WooCommerce is required for product, cart, and order handling.
        if (!class_exists('WooCommerce')) {
            $missing[] = [
                'name' => 'WooCommerce',
                'slug' => 'woocommerce',
                'description' => 'WooCommerce é necessário para venda e gerenciamento de pagamentos dos cursos.',
                'required' => true,
            ];
        }

        if (empty($missing)) return;

        // Show an error when a required dependency is missing, otherwise use a warning notice.
        $has_required_missing = false;
        foreach ($missing as $p) {
            if (!empty($p['required'])) {
                $has_required_missing = true;
                break;
            }
        }

        $notice_class = $has_required_missing ? 'notice notice-error' : 'notice notice-warning';

        echo '<div class="' . esc_attr($notice_class) . '">';
        echo '<h2>Pressplay LMS - Dependências</h2>';

        if ($has_required_missing) {
            echo '<p><strong>Atenção:</strong> existem plugins <strong>obrigatórios</strong> faltando. Algumas funções do Pressplay LMS não irão funcionar.</p>';
        } else {
            echo '<p>Existem plugins <strong>recomendados</strong> para melhorar o funcionamento do Pressplay LMS.</p>';
        }

        echo '<ul style="margin-left:18px; list-style:disc;">';

        foreach ($missing as $plugin) {
            $install_url = admin_url('plugin-install.php?s=' . urlencode($plugin['slug']) . '&tab=search&type=term');

            echo '<li style="margin:8px 0;">';
            echo '<strong>' . esc_html($plugin['name']) . '</strong>';

            if (!empty($plugin['required'])) {
                echo ' <span style="color:#b32d2e;font-weight:600;">(obrigatório)</span>';
            } else {
                echo ' <span style="color:#996800;font-weight:600;">(recomendado)</span>';
            }

            echo '<br><span style="color:#555;">' . esc_html($plugin['description']) . '</span> ';
            echo '<a href="' . esc_url($install_url) . '" class="button button-primary" style="margin-left:10px; vertical-align:middle;">Instalar</a>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}
