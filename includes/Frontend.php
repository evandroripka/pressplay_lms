<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Frontend {

    public static function init() {
        add_shortcode('mlb_register', [__CLASS__, 'shortcode_register']);
    }

    public static function header($title = 'Malibu') {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head><body class="mlb-body">';
    }

    public static function footer() {
        wp_footer();
        echo '</body></html>';
    }

    public static function render_register() {
        self::header('Cadastro - Malibu');
        echo '<div class="mlb-container">';
        echo '<div class="mlb-card">';
        echo '<h1 class="mlb-title">Criar conta</h1>';
        echo do_shortcode('[mlb_register]');
        echo '</div></div>';
        self::footer();
    }

    public static function shortcode_register() {
        if (is_user_logged_in()) {
            return '<div class="mlb-alert mlb-alert--info">Você já está logado. <a class="mlb-link" href="' . esc_url(home_url('/meus-cursos')) . '">Ir para Meus Cursos</a></div>';
        }

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mlb_register_nonce']) && wp_verify_nonce($_POST['mlb_register_nonce'], 'mlb_register')) {
            $full_name = sanitize_text_field($_POST['full_name'] ?? '');
            $phone     = sanitize_text_field($_POST['phone'] ?? '');
            $email     = sanitize_email($_POST['email'] ?? '');

            if (strlen($full_name) < 5) $errors[] = 'Informe seu nome completo.';
            if (!MLB_LMS_Helpers::is_valid_phone_br($phone)) $errors[] = 'Telefone inválido. Use DDD + número.';
            if (!is_email($email)) $errors[] = 'E-mail inválido.';

            if (empty($errors)) {
                if (email_exists($email)) {
                    $errors[] = 'Este e-mail já está cadastrado. Faça login.';
                } else {
                    $username = MLB_LMS_Helpers::username_from_email($email);
                    $password = wp_generate_password(24, true, true);

                    $user_id = wp_create_user($username, $password, $email);
                    if (is_wp_error($user_id)) {
                        $errors[] = 'Não foi possível criar o usuário. Tente outro e-mail.';
                    } else {
                        wp_update_user([
                            'ID' => $user_id,
                            'display_name' => $full_name,
                            'first_name' => $full_name, // você pode separar depois
                            'role' => 'malibu_student',
                        ]);

                        MLB_LMS_Helpers::upsert_student_profile($user_id, $full_name, $phone);

                        // envia e-mail "defina sua senha"
                        MLB_LMS_Mailer::send_set_password_email($user_id);

                        $success = true;
                    }
                }
            }
        }

        ob_start();

        if ($success) {
            echo '<div class="mlb-alert mlb-alert--success">Conta criada! Enviamos um e-mail para você definir sua senha.</div>';
            return ob_get_clean();
        }

        if (!empty($errors)) {
            echo '<div class="mlb-alert mlb-alert--error"><ul class="mlb-list">';
            foreach ($errors as $e) echo '<li>' . esc_html($e) . '</li>';
            echo '</ul></div>';
        }

        echo '<form method="post" class="mlb-form">';
        wp_nonce_field('mlb_register', 'mlb_register_nonce');

        echo '<label class="mlb-label">Nome completo</label>';
        echo '<input class="mlb-input" name="full_name" required placeholder="Seu nome completo" />';

        echo '<label class="mlb-label">Telefone (DDD)</label>';
        echo '<input class="mlb-input" name="phone" required placeholder="(11) 91234-5678" />';

        echo '<label class="mlb-label">E-mail</label>';
        echo '<input class="mlb-input" type="email" name="email" required placeholder="seuemail@exemplo.com" />';

        echo '<button class="mlb-btn mlb-btn--primary" type="submit">Criar conta</button>';
        echo '</form>';

        return ob_get_clean();
    }

    public static function render_my_courses() {
        self::header('Meus Cursos - Malibu');

        if (!is_user_logged_in()) {
            echo '<div class="mlb-container"><div class="mlb-card">';
            echo '<div class="mlb-alert mlb-alert--info">Faça login para acessar seus cursos.</div>';
            echo '</div></div>';
            self::footer();
            return;
        }

        echo '<div class="mlb-container"><div class="mlb-card">';
        echo '<h1 class="mlb-title">Meus Cursos</h1>';
        echo '<p class="mlb-muted">Em seguida vamos listar as matrículas ativas aqui.</p>';
        echo '</div></div>';

        self::footer();
    }

    public static function render_course_by_slug($slug) {
        self::header('Curso - Malibu');

        echo '<div class="mlb-container"><div class="mlb-card">';
        echo '<h1 class="mlb-title">Curso: ' . esc_html($slug) . '</h1>';
        echo '<p class="mlb-muted">Próximo passo: puxar dados do curso + validar matrícula + listar aulas.</p>';
        echo '</div></div>';

        self::footer();
    }
}
