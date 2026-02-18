<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Frontend
{

    public static function init()
    {
        add_shortcode('mlb_register', [__CLASS__, 'shortcode_register']);
    }

    public static function header($title = 'Malibu')
    {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head><body class="mlb-body">';
    }

    public static function footer()
    {
        wp_footer();
        echo '</body></html>';
    }

    public static function render_register()
    {
        self::header('Cadastro - Malibu');
        echo '<div class="mlb-container">';
        echo '<div class="mlb-card">';
        echo '<h1 class="mlb-title">Criar conta</h1>';
        echo do_shortcode('[mlb_register]');
        echo '</div></div>';
        self::footer();
    }

    public static function shortcode_register()
    {
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
                            'first_name' => $full_name,
                            'role' => 'malibu_student',
                        ]);

                        MLB_LMS_Helpers::upsert_student_profile($user_id, $full_name, $phone);
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

    public static function render_my_courses()
    {
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

    private static function render_access_gate($course_id)
    {
        if (!is_user_logged_in()) {
            echo '<div class="mlb-alert mlb-alert--info" style="margin-top:16px;">';
            echo 'Faça login para acessar este conteúdo. ';
            echo '<a class="mlb-link" href="' . esc_url(wp_login_url(home_url('/curso/' . get_post_field('post_name', $course_id)))) . '">Entrar</a>';
            echo '</div>';
            return;
        }

        $product_id = (int) get_post_meta($course_id, '_mlb_course_product_id', true);

        echo '<div class="mlb-alert mlb-alert--info" style="margin-top:16px;">';
        echo '<strong>Este conteúdo é exclusivo para alunos matriculados.</strong><br>';

        if ($product_id > 0 && class_exists('WooCommerce') && get_post($product_id)) {
            echo '<a class="mlb-btn mlb-btn--primary" style="display:inline-block;text-decoration:none;margin-top:10px;" href="' . esc_url(get_permalink($product_id)) . '">Comprar curso</a>';
        } else {
            echo '<span style="display:block;margin-top:8px;">Produto do curso não encontrado. Verifique o curso no admin.</span>';
        }

        echo '</div>';
    }

    private static function user_can_access_course($course_id)
    {
        if (!is_user_logged_in()) return false;
        return MLB_LMS_Enrollments::has_active_enrollment(get_current_user_id(), $course_id);
    }

    public static function render_course_by_slug($slug)
    {
        $course = get_page_by_path($slug, OBJECT, 'mlb_course');

        self::header('Curso - Malibu');
        echo '<div class="mlb-container"><div class="mlb-card">';

        if (!$course) {
            echo '<h1 class="mlb-title">Curso não encontrado</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $trailer = get_post_meta($course->ID, '_mlb_course_trailer', true);
        $has_access = self::user_can_access_course($course->ID);

        echo '<h1 class="mlb-title">' . esc_html($course->post_title) . '</h1>';

        if (has_post_thumbnail($course->ID)) {
            echo get_the_post_thumbnail($course->ID, 'large', ['style' => 'width:100%;height:auto;border-radius:12px;margin:12px 0;']);
        }

        if ($trailer) {
            $embed = wp_oembed_get($trailer);
            if ($embed) {
                echo '<div style="margin:16px 0;">' . $embed . '</div>';
            }
        }

        echo '<div class="mlb-content">';
        echo apply_filters('the_content', $course->post_content);
        echo '</div>';

        echo '<hr><h2 style="margin-top:10px;">Aulas</h2>';

        if (!$has_access) {
            self::render_access_gate($course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        $lessons = get_posts([
            'post_type' => 'mlb_lesson',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key' => '_mlb_lesson_course_id',
            'meta_value' => $course->ID,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (!$lessons) {
            echo '<p class="mlb-muted">Nenhuma aula cadastrada ainda.</p>';
        } else {
            echo '<ul class="mlb-list">';
            foreach ($lessons as $lesson) {
                $url = home_url('/curso/' . $slug . '/aula/' . $lesson->post_name);
                echo '<li><a class="mlb-link" href="' . esc_url($url) . '">' . esc_html($lesson->post_title) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '</div></div>';
        self::footer();
    }

    public static function render_lesson_by_slug($course_slug, $lesson_slug)
    {
        $course = get_page_by_path($course_slug, OBJECT, 'mlb_course');
        $lesson = get_page_by_path($lesson_slug, OBJECT, 'mlb_lesson');

        self::header('Aula - Malibu');
        echo '<div class="mlb-container"><div class="mlb-card">';

        if (!$course || !$lesson) {
            echo '<h1 class="mlb-title">Aula não encontrada</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $lesson_course_id = (int) get_post_meta($lesson->ID, '_mlb_lesson_course_id', true);
        if ($lesson_course_id !== (int)$course->ID) {
            echo '<h1 class="mlb-title">Aula não pertence a este curso</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        echo '<a class="mlb-link" href="' . esc_url(home_url('/curso/' . $course_slug)) . '">← Voltar para o curso</a>';
        echo '<h1 class="mlb-title" style="margin-top:10px;">' . esc_html($lesson->post_title) . '</h1>';

        if (!self::user_can_access_course($course->ID)) {
            self::render_access_gate($course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        $video = get_post_meta($lesson->ID, '_mlb_lesson_video_url', true);
        $materials = get_post_meta($lesson->ID, '_mlb_lesson_materials', true);
        if (!is_array($materials)) $materials = [];

        if ($video) {
            $embed = wp_oembed_get($video);
            if ($embed) {
                echo '<div style="margin:16px 0;">' . $embed . '</div>';
            } else {
                echo '<p class="mlb-muted">Vídeo informado, mas não foi possível gerar o player.</p>';
            }
        }

        echo '<div class="mlb-content">';
        echo apply_filters('the_content', $lesson->post_content);
        echo '</div>';

        echo '<hr><h2>Materiais</h2>';
        if (!$materials) {
            echo '<p class="mlb-muted">Sem materiais nesta aula.</p>';
        } else {
            echo '<ul class="mlb-list">';
            foreach ($materials as $m) {
                $url = esc_url($m);
                echo '<li><a class="mlb-link" href="' . $url . '" target="_blank" rel="noopener">' . esc_html($m) . '</a></li>';
            }
            echo '</ul>';
        }

        echo '</div></div>';
        self::footer();
    }
}
