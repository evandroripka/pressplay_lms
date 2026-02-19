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

    private static function render_enroll_cta($course_id)
    {
        $product_id = MLB_LMS_Enrollments::get_course_product_id($course_id);

        echo '<div class="mlb-card" style="margin-top:16px;padding:16px;border:1px solid #ddd;border-radius:10px;">';
        echo '<p><strong>Conteúdo restrito.</strong> Você precisa estar matriculado para ver as aulas.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mlb_lms_enroll">';
        echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
        echo wp_nonce_field('mlb_lms_enroll_' . $course_id, '_wpnonce', true, false);

        $disabled = (!$product_id) ? 'disabled' : '';
        $label = $product_id ? 'Matricular' : 'Produto ainda não gerado';

        echo '<button type="submit" class="button button-primary" ' . $disabled . '>' . esc_html($label) . '</button>';
        echo '</form>';

        echo '</div>';
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

        $user_id = get_current_user_id();
        $can_access = MLB_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);

        $trailer = get_post_meta($course->ID, '_mlb_course_trailer', true);

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

        // Conteúdo/vitrine do curso sempre aparece
        echo '<div class="mlb-content">';
        echo apply_filters('the_content', $course->post_content);
        echo '</div>';

        // Se não tem acesso, mostra CTA e encerra sem mostrar cabeçalhos soltos
        if (!$can_access) {
            echo '<hr>';
            self::render_enroll_cta((int)$course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        // Lista aulas vinculadas (apenas para matriculados/admin)
        $lessons = get_posts([
            'post_type' => 'mlb_lesson',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key' => '_mlb_lesson_course_id',
            'meta_value' => $course->ID,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        // Só imprime a seção Aulas se fizer sentido
        if ($lessons && count($lessons) > 0) {
            echo '<hr>';
            echo '<h2 style="margin-top:10px;">Aulas</h2>';
            echo '<ul class="mlb-list">';
            foreach ($lessons as $lesson) {
                $url = home_url('/curso/' . $slug . '/aula/' . $lesson->post_name);
                echo '<li><a class="mlb-link" href="' . esc_url($url) . '">' . esc_html($lesson->post_title) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<hr>';
            echo '<p class="mlb-muted">Nenhuma aula cadastrada ainda.</p>';
        }

        echo '</div></div>';
        self::footer();
    }

    public static function render_lesson_by_slug($course_slug, $lesson_slug)
    {
        $course = get_page_by_path($course_slug, OBJECT, 'mlb_course');
        $lesson = get_page_by_path($lesson_slug, OBJECT, 'mlb_lesson');

        // Valida existence primeiro
        if (!$course || !$lesson) {
            self::header('Aula - Malibu');
            echo '<div class="mlb-container"><div class="mlb-card">';
            echo '<h1 class="mlb-title">Aula não encontrada</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        // garante que a aula pertence ao curso
        $lesson_course_id = (int) get_post_meta($lesson->ID, '_mlb_lesson_course_id', true);
        if ($lesson_course_id !== (int) $course->ID) {
            self::header('Aula - Malibu');
            echo '<div class="mlb-container"><div class="mlb-card">';
            echo '<h1 class="mlb-title">Aula não pertence a este curso</h1>';
            echo '<p><a class="mlb-link" href="' . esc_url(home_url('/curso/' . $course_slug)) . '">← Voltar para o curso</a></p>';
            echo '</div></div>';
            self::footer();
            return;
        }

        // Checa acesso antes de renderizar conteúdo
        $user_id = get_current_user_id();
        $can_access = MLB_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);

        if (!$can_access) {
            self::header('Aula - Restrita');
            echo '<div class="mlb-container"><div class="mlb-card">';
            echo '<h1 class="mlb-title">Conteúdo restrito</h1>';
            echo '<p>Você precisa estar matriculado para acessar esta aula.</p>';
            self::render_enroll_cta((int)$course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        // Render normal (matriculado/admin)
        self::header('Aula - Malibu');
        echo '<div class="mlb-container"><div class="mlb-card">';

        $video_url = get_post_meta($lesson->ID, '_mlb_lesson_video_url', true);
        $vimeo_id  = (int) get_post_meta($lesson->ID, '_mlb_lesson_vimeo_id', true);

        $materials = get_post_meta($lesson->ID, '_mlb_lesson_materials', true);
        if (!is_array($materials)) $materials = [];

        echo '<a class="mlb-link" href="' . esc_url(home_url('/curso/' . $course_slug)) . '">← Voltar para o curso</a>';
        echo '<h1 class="mlb-title" style="margin-top:10px;">' . esc_html($lesson->post_title) . '</h1>';

        // ============================
        // VIDEO RENDER (Vimeo API first)
        // ============================
        $rendered_video = false;

        if ($vimeo_id && class_exists('MLB_LMS_Vimeo')) {
            $html = MLB_LMS_Vimeo::get_embed_html($vimeo_id);
            if ($html) {
                echo '<div style="margin:16px 0;">' . $html . '</div>';
                $rendered_video = true;
            }
        }

        // fallback: oEmbed (YouTube / Vimeo público)
        if (!$rendered_video && $video_url) {
            $embed = wp_oembed_get($video_url);
            if ($embed) {
                echo '<div style="margin:16px 0;">' . $embed . '</div>';
                $rendered_video = true;
            } else {
                echo '<p class="mlb-muted">Vídeo informado, mas não foi possível gerar o player.</p>';
            }
        }

        echo '<div class="mlb-content">';
        echo apply_filters('the_content', $lesson->post_content);
        echo '</div>';

        // Materiais só aparece se houver algo, senão só uma msg simples sem título solto
        echo '<hr>';
        if (!$materials || count($materials) === 0) {
            echo '<p class="mlb-muted">Sem materiais nesta aula.</p>';
        } else {
            echo '<h2>Materiais</h2>';
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
