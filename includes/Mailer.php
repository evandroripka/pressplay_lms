<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Mailer {

    public static function send_set_password_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;

        // gera link de reset padrão do WP
        $key = get_password_reset_key($user);
        if (is_wp_error($key)) return false;

        $reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');

        $opts = get_option('press_lms_settings', []);
        $logo_url = isset($opts['email_logo_url']) ? esc_url($opts['email_logo_url']) : '';
        $brand = isset($opts['brand_name']) ? sanitize_text_field($opts['brand_name']) : 'Pressplay';

        $subject = "Defina sua senha - {$brand}";

        $html = self::build_email_html([
            'brand' => $brand,
            'logo_url' => $logo_url,
            'reset_url' => $reset_url,
            'user_email' => $user->user_email,
        ]);

        add_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);
        $sent = wp_mail($user->user_email, $subject, $html);
        remove_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);

        return $sent;
    }

    public static function mail_content_type() {
        return 'text/html; charset=UTF-8';
    }

    private static function build_email_html($data) {
        $brand = esc_html($data['brand']);
        $reset = esc_url($data['reset_url']);
        $logo  = $data['logo_url'];

        // Você disse que vai fazer o CSS do corpo do e-mail: então deixei bem “templateável”
        $logo_html = $logo ? '<img src="'.esc_url($logo).'" alt="'.$brand.'" style="max-width:160px;height:auto;display:block;margin:0 auto 16px;">' : '';

        return '
        <div style="background:#0b0f17;padding:24px 0;">
          <div style="max-width:560px;margin:0 auto;background:#111827;border:1px solid #1f2937;border-radius:14px;overflow:hidden;">
            <div style="padding:26px 24px;text-align:center;">
              '.$logo_html.'
              <h1 style="margin:0 0 8px;color:#fff;font-size:20px;font-family:Arial,sans-serif;">Defina sua senha</h1>
              <p style="margin:0 0 18px;color:#cbd5e1;font-size:14px;font-family:Arial,sans-serif;line-height:1.5;">
                Para acessar sua área do aluno, clique no botão abaixo e defina sua senha.
              </p>
              <a href="'.$reset.'" style="display:inline-block;background:#22c55e;color:#0b0f17;text-decoration:none;
                 padding:12px 16px;border-radius:10px;font-weight:bold;font-family:Arial,sans-serif;">
                 Definir senha
              </a>
              <p style="margin:18px 0 0;color:#94a3b8;font-size:12px;font-family:Arial,sans-serif;line-height:1.5;">
                Se você não solicitou esse cadastro, ignore este e-mail.
              </p>
            </div>
          </div>
        </div>';
    }
}
