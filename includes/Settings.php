<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function menu() {
        add_menu_page(
            'Malibu LMS',
            'Malibu LMS',
            'manage_options',
            'mlb-lms',
            [__CLASS__, 'page'],
            'dashicons-welcome-learn-more',
            58
        );
    }

    public static function register() {
        register_setting('mlb_lms_settings_group', 'mlb_lms_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => [],
        ]);
    }

    public static function sanitize($input) {
        return [
            'brand_name' => sanitize_text_field($input['brand_name'] ?? 'Malibu'),
            'email_logo_url' => esc_url_raw($input['email_logo_url'] ?? ''),
        ];
    }

    public static function page() {
        $opts = get_option('mlb_lms_settings', []);
        ?>
        <div class="wrap">
            <h1>Malibu LMS</h1>
            <form method="post" action="options.php">
                <?php settings_fields('mlb_lms_settings_group'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Nome da marca</th>
                        <td><input type="text" name="mlb_lms_settings[brand_name]" value="<?php echo esc_attr($opts['brand_name'] ?? 'Malibu'); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">URL do Logo (e-mail)</th>
                        <td><input type="url" name="mlb_lms_settings[email_logo_url]" value="<?php echo esc_attr($opts['email_logo_url'] ?? ''); ?>" class="regular-text" placeholder="https://.../logo.png"></td>
                    </tr>
                </table>

                <?php submit_button('Salvar'); ?>
            </form>
        </div>
        <?php
    }
}
