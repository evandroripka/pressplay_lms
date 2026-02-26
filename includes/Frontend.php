<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Frontend
{
    public static function init()
    {
        add_shortcode('press_register', [__CLASS__, 'shortcode_register']);
    }

    public static function header($title = 'Pressplay')
    {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head><body class="press-body">';
    }

    public static function footer()
    {
        wp_footer();
        echo '</body></html>';
    }

    public static function render_register()
    {
        self::header('Cadastro - Pressplay');
        echo '<div class="press-container">';
        echo '<div class="press-card">';
        echo '<h1 class="press-title">Criar conta</h1>';
        echo do_shortcode('[press_register]');
        echo '</div></div>';
        self::footer();
    }

    public static function shortcode_register()
    {
        if (is_user_logged_in()) {
            return '<div class="press-alert press-alert--info">Você já está logado. <a class="press-link" href="' . esc_url(home_url('/meus-cursos')) . '">Ir para Meus Cursos</a></div>';
        }

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['press_register_nonce']) && wp_verify_nonce($_POST['press_register_nonce'], 'press_register')) {
            $full_name = sanitize_text_field($_POST['full_name'] ?? '');
            $phone     = sanitize_text_field($_POST['phone'] ?? '');
            $email     = sanitize_email($_POST['email'] ?? '');

            if (strlen($full_name) < 5) $errors[] = 'Informe seu nome completo.';
            if (!PRESS_LMS_Helpers::is_valid_phone_br($phone)) $errors[] = 'Telefone inválido. Use DDD + número.';
            if (!is_email($email)) $errors[] = 'E-mail inválido.';

            if (empty($errors)) {
                if (email_exists($email)) {
                    $errors[] = 'Este e-mail já está cadastrado. Faça login.';
                } else {
                    $username = PRESS_LMS_Helpers::username_from_email($email);
                    $password = wp_generate_password(24, true, true);

                    $user_id = wp_create_user($username, $password, $email);
                    if (is_wp_error($user_id)) {
                        $errors[] = 'Não foi possível criar o usuário. Tente outro e-mail.';
                    } else {
                        wp_update_user([
                            'ID' => $user_id,
                            'display_name' => $full_name,
                            'first_name' => $full_name,
                            'role' => 'press_student',
                        ]);

                        PRESS_LMS_Helpers::upsert_student_profile($user_id, $full_name, $phone);

                        // envia e-mail "defina sua senha"
                        PRESS_LMS_Mailer::send_set_password_email($user_id);

                        $success = true;
                    }
                }
            }
        }

        ob_start();

        if ($success) {
            echo '<div class="press-alert press-alert--success">Conta criada! Enviamos um e-mail para você definir sua senha.</div>';
            return ob_get_clean();
        }

        if (!empty($errors)) {
            echo '<div class="press-alert press-alert--error"><ul class="press-list">';
            foreach ($errors as $e) echo '<li>' . esc_html($e) . '</li>';
            echo '</ul></div>';
        }

        echo '<form method="post" class="press-form">';
        wp_nonce_field('press_register', 'press_register_nonce');

        echo '<label class="press-label">Nome completo</label>';
        echo '<input class="press-input" name="full_name" required placeholder="Seu nome completo" />';

        echo '<label class="press-label">Telefone (DDD)</label>';
        echo '<input class="press-input" name="phone" required placeholder="(11) 91234-5678" />';

        echo '<label class="press-label">E-mail</label>';
        echo '<input class="press-input" type="email" name="email" required placeholder="seuemail@exemplo.com" />';

        echo '<button class="press-btn press-btn--primary" type="submit">Criar conta</button>';
        echo '</form>';

        return ob_get_clean();
    }

    public static function render_my_courses()
    {
        self::header('Meus Cursos - Pressplay');

        if (!is_user_logged_in()) {
            echo '<div class="press-container"><div class="press-card">';
            echo '<div class="press-alert press-alert--info">Faça login para acessar seus cursos.</div>';
            echo '</div></div>';
            self::footer();
            return;
        }

        echo '<div class="press-container"><div class="press-card">';
        echo '<h1 class="press-title">Meus Cursos</h1>';
        echo '<p class="press-muted">Em seguida vamos listar as matrículas ativas aqui.</p>';
        echo '</div></div>';

        self::footer();
    }

    private static function render_enroll_cta($course_id)
    {
        $product_id = PRESS_LMS_Enrollments::get_course_product_id($course_id);

        echo '<div class="press-card" style="margin-top:16px;padding:16px;border:1px solid #ddd;border-radius:10px;">';
        echo '<p><strong>Conteúdo restrito.</strong> Você precisa estar matriculado para ver as aulas.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="press_lms_enroll">';
        echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
        echo wp_nonce_field('press_lms_enroll_' . $course_id, '_wpnonce', true, false);

        $disabled = (!$product_id) ? 'disabled' : '';
        $label = $product_id ? 'Matricular' : 'Produto ainda não gerado';

        echo '<button type="submit" class="button button-primary" ' . $disabled . '>' . esc_html($label) . '</button>';
        echo '</form>';

        echo '</div>';
    }

    public static function render_course_by_slug($slug)
    {
        $course = get_page_by_path($slug, OBJECT, 'press_course');

        self::header('Curso - Pressplay');
        echo '<div class="press-container"><div class="press-card">';

        if (!$course) {
            echo '<h1 class="press-title">Curso não encontrado</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        $user_id = get_current_user_id();
        $can_access = PRESS_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);

        $trailer = get_post_meta($course->ID, '_press_course_trailer', true);

        echo '<h1 class="press-title">' . esc_html($course->post_title) . '</h1>';

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
        echo '<div class="press-content">';
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
            'post_type' => 'press_lesson',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key' => '_press_lesson_course_id',
            'meta_value' => $course->ID,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        // Só imprime a seção Aulas se fizer sentido
        if ($lessons && count($lessons) > 0) {
            echo '<hr>';
            echo '<h2 style="margin-top:10px;">Aulas</h2>';
            echo '<ul class="press-list">';
            foreach ($lessons as $lesson) {
                $url = home_url('/curso/' . $slug . '/aula/' . $lesson->post_name);
                echo '<li><a class="press-link" href="' . esc_url($url) . '">' . esc_html($lesson->post_title) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<hr>';
            echo '<p class="press-muted">Nenhuma aula cadastrada ainda.</p>';
        }

        echo '</div></div>';
        self::footer();
    }

    public static function render_lesson_by_slug($course_slug, $lesson_slug)
    {
        $course = get_page_by_path($course_slug, OBJECT, 'press_course');
        $lesson = get_page_by_path($lesson_slug, OBJECT, 'press_lesson');

        // Valida existence primeiro
        if (!$course || !$lesson) {
            self::header('Aula - Pressplay');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Aula não encontrada</h1>';
            echo '</div></div>';
            self::footer();
            return;
        }

        // garante que a aula pertence ao curso
        $lesson_course_id = (int) get_post_meta($lesson->ID, '_press_lesson_course_id', true);
        if ($lesson_course_id !== (int) $course->ID) {
            self::header('Aula - Pressplay');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Aula não pertence a este curso</h1>';
            echo '<p><a class="press-link" href="' . esc_url(home_url('/curso/' . $course_slug)) . '">← Voltar para o curso</a></p>';
            echo '</div></div>';
            self::footer();
            return;
        }

        // Checa acesso antes de renderizar conteúdo
        $user_id = get_current_user_id();
        $can_access = PRESS_LMS_Enrollments::can_access_course($user_id, (int)$course->ID);

        if (!$can_access) {
            self::header('Aula - Restrita');
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Conteúdo restrito</h1>';
            echo '<p>Você precisa estar matriculado para acessar esta aula.</p>';
            self::render_enroll_cta((int)$course->ID);
            echo '</div></div>';
            self::footer();
            return;
        }

        // Render normal (matriculado/admin)
        // Render normal (matriculado/admin)
        self::header('Aula - Pressplay');

        $video_url = get_post_meta($lesson->ID, '_press_lesson_video_url', true);
        $vimeo_id  = (int) get_post_meta($lesson->ID, '_press_lesson_vimeo_id', true);

        // ✅ Materiais v2
        $materials = get_post_meta($lesson->ID, '_press_lesson_materials_v2', true);
        if (!is_array($materials)) $materials = [];

        if (class_exists('PRESS_LMS_Materials')) {
            $materials = PRESS_LMS_Materials::normalize_items($materials);
        }

        // Deixa variáveis disponíveis pro template
        $course_slug_var = $course_slug;
        $lesson_slug_var = $lesson_slug;
        $course_var      = $course;
        $lesson_var      = $lesson;

        // ✅ inclui template
        $template = trailingslashit(PRESS_LMS_PATH) . 'templates/frontend/single-press_lesson.php';

        // se sua pasta estiver como "fronted", usa isso:
        // $template = trailingslashit(PRESS_LMS_PATH) . 'templates/fronted/single-press_lesson.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="press-container"><div class="press-card">';
            echo '<h1 class="press-title">Template não encontrado</h1>';
            echo '<p class="press-muted">Esperado em: ' . esc_html($template) . '</p>';
            echo '</div></div>';
        }

        self::footer();
    }
}
