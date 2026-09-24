<?php
if (!defined('ABSPATH')) exit;

/** Administrator-issued access, independent of WooCommerce orders and user roles. */
class PRESS_LMS_Manual_Enrollments
{
    public static function init(): void
    {
        add_action('admin_post_press_lms_grant_access', [__CLASS__, 'handle']);
    }

    public static function render(array $courses): void
    {
        if (!current_user_can('manage_options')) return;
        $search = is_string($_GET['manual_user_search'] ?? null) ? sanitize_text_field(wp_unslash($_GET['manual_user_search'])) : '';
        $users = $search !== '' ? get_users(['search'=>'*'.$search.'*','search_columns'=>['user_login','user_email','display_name'],'fields'=>['ID','display_name','user_email'],'number'=>20,'orderby'=>'display_name','order'=>'ASC']) : [];
        echo '<details class="presslms-admin-card" id="presslms-manual-access"' . ($search !== '' ? ' open' : '') . '><summary><strong>Liberar acesso manual</strong></summary>';
        echo '<p>Selecione uma conta existente, o curso e a validade. Nenhum pedido ou pagamento sera criado, e os cargos do usuario serao preservados.</p>';
        echo '<form method="get"><input type="hidden" name="page" value="press-lms"><label for="presslms-user-search">Buscar usuario por nome, login ou e-mail</label><p><input type="search" id="presslms-user-search" name="manual_user_search" value="' . esc_attr($search) . '" required> <button class="button" type="submit">Buscar usuario</button></p></form>';
        if ($search !== '' && !$users) echo '<p>Nenhum usuario encontrado nesta instalacao.</p>';
        if ($users) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="press_lms_grant_access">';
            wp_nonce_field('press_lms_grant_access', 'press_lms_manual_nonce');
            echo '<p><label>Usuario<br><select name="user_id" required><option value="">Selecione</option>';
            foreach ($users as $user) echo '<option value="' . (int) $user->ID . '">' . esc_html($user->display_name . ' - ' . $user->user_email . ' (#' . $user->ID . ')') . '</option>';
            echo '</select></label></p><p class="description">Exibindo ate 20 resultados; refine a busca se necessario.</p>';
            echo '<p><label>Curso<br><select name="course_id" required><option value="">Selecione</option>';
            foreach ($courses as $course) echo '<option value="' . (int) $course->ID . '">' . esc_html($course->post_title) . '</option>';
            echo '</select></label></p><p><label>Validade<br><select name="access_type"><option value="days">Dias</option><option value="months">Meses</option><option value="years">Anos</option><option value="lifetime">Ilimitado</option></select></label> <label>Quantidade <input type="number" name="access_value" min="1" max="36500" value="30" style="width:90px"></label></p>';
            echo '<p class="description">Prazo contado a partir de agora, no fuso horario do site. A quantidade e ignorada no acesso ilimitado. Se ja existir acesso ativo, gerencie a matricula atual na lista abaixo.</p>';
            echo '<p><label>Motivo (opcional)<br><input type="text" name="reason" maxlength="250" class="regular-text" placeholder="Ex.: cortesia ou turma presencial"></label></p>';
            echo '<p><label><input type="checkbox" name="notify" value="yes"> Enviar e-mail avisando o usuario</label></p>';
            echo '<button type="submit" class="button button-primary">Liberar acesso</button></form>';
        }
        echo '<p class="description">A conta acessara <a href="' . esc_url(home_url('/meus-cursos/')) . '">Meus cursos</a> com seu login habitual. Administradores mantem tambem suas permissoes de previsualizacao do site.</p></details>';
    }

    public static function validate_request(array $input)
    {
        if (!current_user_can('manage_options')) return new WP_Error('manual_denied', 'Voce nao tem permissao para liberar acessos.');
        if (!is_string($input['press_lms_manual_nonce'] ?? null) || !wp_verify_nonce(wp_unslash($input['press_lms_manual_nonce']), 'press_lms_grant_access')) {
            return new WP_Error('manual_nonce', 'A sessao expirou. Recarregue o painel e tente novamente.');
        }
        return true;
    }

    public static function handle(): void
    {
        $valid = self::validate_request($_POST);
        if (is_wp_error($valid)) wp_die(esc_html($valid->get_error_message()), '', ['response'=>403]);
        $result = self::grant(
            absint($_POST['user_id'] ?? 0), absint($_POST['course_id'] ?? 0),
            is_string($_POST['access_type'] ?? null) ? sanitize_key($_POST['access_type']) : '',
            is_scalar($_POST['access_value'] ?? null) ? (int) $_POST['access_value'] : 0,
            is_string($_POST['reason'] ?? null) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '',
            ($_POST['notify'] ?? '') === 'yes'
        );
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), 'Acesso manual', ['response'=>400,'back_link'=>true]);
        wp_safe_redirect(add_query_arg('press_lms_notice','manual_access_granted',admin_url('admin.php?page=press-lms')));
        exit;
    }

    public static function grant(int $user_id, int $course_id, string $type, int $amount, string $reason = '', bool $notify = false)
    {
        global $wpdb;
        if (!current_user_can('manage_options')) return new WP_Error('manual_denied', 'Sem permissao para conceder acesso.');
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        if (!$user || !is_user_member_of_blog($user_id)) return new WP_Error('manual_user', 'Selecione um usuario cadastrado neste site.');
        if (!$course instanceof WP_Post || $course->post_type !== 'press_course' || $course->post_status !== 'publish') {
            return new WP_Error('manual_course', 'Selecione um curso publicado.');
        }
        $limits = ['days'=>36500,'months'=>1200,'years'=>100,'lifetime'=>0];
        if (!isset($limits[$type]) || ($type !== 'lifetime' && ($amount < 1 || $amount > $limits[$type]))) {
            return new WP_Error('manual_period', 'Informe um prazo valido de ate 100 anos ou escolha ilimitado.');
        }

        // Serialize manual grants for the same user/course without changing the schema.
        $lock = 'press_manual_' . md5($wpdb->prefix . ':' . $user_id . ':' . $course_id);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
            return new WP_Error('manual_busy', 'Outra liberacao esta em andamento. Tente novamente.');
        }
        try {
            if (PRESS_LMS_Enrollments::has_active_enrollment($user_id, $course_id)) {
                return new WP_Error('manual_active', 'Este usuario ja possui acesso ativo. Use as acoes da matricula existente para alterar sua validade.');
            }
            $now = new DateTimeImmutable('now', wp_timezone());
            $expires = $type === 'lifetime' ? null : $now->modify('+' . $amount . ' ' . $type)->format('Y-m-d H:i:s');
            $table = PRESS_LMS_Database::table('enrollments');
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND course_id=%d AND payment_provider='manual' ORDER BY id DESC LIMIT 1", $user_id, $course_id));
            $data = ['user_id'=>$user_id,'course_id'=>$course_id,'status'=>'active','purchased_at'=>$now->format('Y-m-d H:i:s'),'expires_at'=>$expires,'payment_provider'=>'manual','order_ref'=>null,'updated_at'=>$now->format('Y-m-d H:i:s')];
            if ($existing) $saved = $wpdb->update($table, $data, ['id'=>$existing]);
            else {
                $data['created_at']=$now->format('Y-m-d H:i:s');
                $saved=$wpdb->insert($table,$data);
            }
            if ($saved === false) return new WP_Error('manual_storage', 'Nao foi possivel salvar a matricula. Nenhum acesso foi confirmado.');
            $id = $existing ?: (int) $wpdb->insert_id;
            add_user_meta($user_id, 'press_lms_manual_grant', ['enrollment_id'=>$id,'course_id'=>$course_id,'actor_id'=>get_current_user_id(),'granted_at_utc'=>gmdate('Y-m-d H:i:s'),'expires_at'=>$expires,'timezone'=>wp_timezone()->getName(),'reason'=>substr(sanitize_text_field($reason),0,250)]);
            // Do not assign roles or overwrite the existing WordPress/student profile.
            if ($notify && class_exists('PRESS_LMS_Mailer')) {
                PRESS_LMS_Mailer::send_enrollment_activated_email($user_id, $course_id, PRESS_LMS_Enrollments::get_enrollment_by_id($id));
            }
            return $id;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
}
