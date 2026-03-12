<?php
if (!defined('ABSPATH')) exit;

$students = is_array($students ?? null) ? $students : [];
$courses = is_array($courses ?? null) ? $courses : [];

$filter_course = (int) ($filter_course ?? 0);
$filter_status = (string) ($filter_status ?? '');
$filter_search = (string) ($filter_search ?? '');
$filter_sort   = (string) ($filter_sort ?? 'date_desc');
$panel_page_title = (string) ($panel_page_title ?? 'Matrículas');
$panel_page_subtitle = (string) ($panel_page_subtitle ?? 'Gerencie acessos, validade, pedidos e progresso por matrícula.');
$panel_page_slug = (string) ($panel_page_slug ?? 'press-lms');
$panel_notice = is_array($panel_notice ?? null) ? $panel_notice : null;

function presslms_admin_student_progress_percent($completed, $total)
{
    $completed = (int) $completed;
    $total = (int) $total;

    if ($total <= 0) return 0;
    return (int) round(($completed / $total) * 100);
}

if (!function_exists('presslms_admin_student_initials')) {
    function presslms_admin_student_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= function_exists('mb_substr')
                ? mb_substr($part, 0, 1)
                : substr($part, 0, 1);
        }

        if ($initials === '') {
            $initials = function_exists('mb_substr')
                ? mb_substr($name, 0, 2)
                : substr($name, 0, 2);
        }

        return strtoupper((string) $initials);
    }
}
?>

<div class="wrap presslms-admin-page presslms-admin-page--enrollments">
    <div class="presslms-panel">
        <div class="presslms-page-header">
            <div>
                <h1 class="presslms-page-title"><?php echo esc_html($panel_page_title); ?></h1>
                <p class="presslms-page-subtitle"><?php echo esc_html($panel_page_subtitle); ?></p>
            </div>
        </div>

        <div class="presslms-admin-notice-stack">
        <?php if ($panel_notice): ?>
            <?php
            $notice_type = (string) ($panel_notice['type'] ?? 'info');
            ?>
            <div class="presslms-admin-notice presslms-admin-notice--<?php echo esc_attr($notice_type); ?>">
                <?php echo esc_html((string) ($panel_notice['message'] ?? '')); ?>
            </div>
        <?php endif; ?>
        </div>

        <div class="presslms-admin-card presslms-admin-card--filters">
            <form method="get" class="presslms-filters-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($panel_page_slug); ?>">

                <div class="presslms-filter-grid">
                    <div class="presslms-filter-grid__title">
                        <h2 class="presslms-section-title">Filtros</h2>
                    </div>

                    <div class="presslms-field">
                        <div class="field presslms-field">
                            <label class="presslms-field__label">Curso</label>
                            <select class="presslms-field__control" name="course">
                                <option value="0">Todos os cursos</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($filter_course, (int) $course->ID); ?>>
                                        <?php echo esc_html($course->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="presslms-field">
                        <div class="field presslms-field">
                            <label class="presslms-field__label">Status</label>
                            <select class="presslms-field__control" name="status">
                                <option value="">Todos os status</option>
                                <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pendente</option>
                                <option value="active" <?php selected($filter_status, 'active'); ?>>Ativo</option>
                                <option value="expired" <?php selected($filter_status, 'expired'); ?>>Expirado</option>
                                <option value="blocked" <?php selected($filter_status, 'blocked'); ?>>Bloqueado</option>
                                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelado</option>
                                <option value="failed" <?php selected($filter_status, 'failed'); ?>>Pagamento falhou</option>
                                <option value="refunded" <?php selected($filter_status, 'refunded'); ?>>Reembolsado</option>
                            </select>
                        </div>
                    </div>

                    <div class="presslms-field">
                        <div class="field presslms-field">
                            <label class="presslms-field__label">Buscar</label>
                            <input class="presslms-field__control" type="text" name="s" value="<?php echo esc_attr($filter_search); ?>" placeholder="Nome, email ou curso...">
                        </div>
                    </div>

                    <div class="presslms-field">
                        <div class="field presslms-field">
                            <label class="presslms-field__label">Ordenar por</label>
                            <select class="presslms-field__control" name="sort">
                                <option value="date_desc" <?php selected($filter_sort, 'date_desc'); ?>>Data mais recente</option>
                                <option value="date_asc" <?php selected($filter_sort, 'date_asc'); ?>>Data mais antiga</option>
                                <option value="name_asc" <?php selected($filter_sort, 'name_asc'); ?>>Nome (A-Z)</option>
                                <option value="name_desc" <?php selected($filter_sort, 'name_desc'); ?>>Nome (Z-A)</option>
                            </select>
                        </div>
                    </div>

                    <div class="presslms-filter-actions">
                        <button type="submit" class="button button-primary presslms-button">Aplicar filtros</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $panel_page_slug)); ?>" class="button presslms-button presslms-button--secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="presslms-admin-card">
            <div class="presslms-table-head">
                <h2 class="presslms-section-title">Lista de matrículas</h2>
                <span class="presslms-counter"><?php echo esc_html(count($students)); ?> registro(s)</span>
            </div>

            <div class="presslms-table-wrap">
                <table class="wp-list-table widefat fixed striped presslms-table presslms-table--enrollments">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Curso</th>
                            <th>Progresso</th>
                            <th>Pedido</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="presslms-empty-state">
                                        Nenhuma matrícula encontrada com os filtros atuais.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $progress = presslms_admin_student_progress_percent(
                                    (int) $student->completed_lessons,
                                    (int) $student->total_lessons
                                );
                                $status_key = class_exists('PRESS_LMS_Enrollments')
                                    ? PRESS_LMS_Enrollments::get_enrollment_status_key($student)
                                    : sanitize_key((string) ($student->status ?? ''));
                                $status_label = class_exists('PRESS_LMS_Enrollments')
                                    ? PRESS_LMS_Enrollments::get_enrollment_status_label($student)
                                    : 'Desconhecido';
                                $access_summary = class_exists('PRESS_LMS_Enrollments')
                                    ? PRESS_LMS_Enrollments::get_enrollment_access_summary($student)
                                    : 'Status indisponível';
                                $manage_nonce = 'press_lms_manage_enrollment_' . (int) $student->id;
                                $action_base_args = [
                                    'action' => 'press_lms_manage_enrollment',
                                    'enrollment_id' => (int) $student->id,
                                    'page_slug' => $panel_page_slug,
                                ];
                                $block_url = wp_nonce_url(
                                    add_query_arg($action_base_args + ['enrollment_action' => 'block'], admin_url('admin-post.php')),
                                    $manage_nonce
                                );
                                $reactivate_url = wp_nonce_url(
                                    add_query_arg($action_base_args + ['enrollment_action' => 'reactivate'], admin_url('admin-post.php')),
                                    $manage_nonce
                                );
                                $extend_30_url = wp_nonce_url(
                                    add_query_arg($action_base_args + ['enrollment_action' => 'extend_30_days'], admin_url('admin-post.php')),
                                    $manage_nonce
                                );
                                $extend_90_url = wp_nonce_url(
                                    add_query_arg($action_base_args + ['enrollment_action' => 'extend_90_days'], admin_url('admin-post.php')),
                                    $manage_nonce
                                );
                                $extend_year_url = wp_nonce_url(
                                    add_query_arg($action_base_args + ['enrollment_action' => 'extend_1_year'], admin_url('admin-post.php')),
                                    $manage_nonce
                                );
                                $can_extend = !empty($student->expires_at) && in_array($status_key, ['active', 'expired'], true);
                                ?>
                                <tr>
                                    <td>
                                        <div class="presslms-student-cell">
                                            <span class="presslms-student-avatar" aria-hidden="true">
                                                <?php echo esc_html(presslms_admin_student_initials((string) ($student->full_name ?: 'Aluno'))); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo esc_html($student->full_name ?: 'Sem nome'); ?></strong>
                                                <?php if (!empty($student->phone_raw)): ?>
                                                    <div class="presslms-muted"><?php echo esc_html($student->phone_raw); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td><?php echo esc_html($student->user_email ?: '—'); ?></td>

                                    <td>
                                        <span class="presslms-status presslms-status--<?php echo esc_attr($status_key ?: 'unknown'); ?>">
                                            <?php echo esc_html($status_label); ?>
                                        </span>
                                    </td>

                                    <td><?php echo esc_html($student->course_title ?: 'Curso removido'); ?></td>

                                    <td style="min-width:160px;">
                                        <div class="presslms-progress-meta">
                                            <?php echo esc_html($progress); ?>%
                                            <span class="presslms-muted-inline">
                                                (<?php echo (int) $student->completed_lessons; ?>/<?php echo (int) $student->total_lessons; ?> aulas)
                                            </span>
                                        </div>
                                        <div class="presslms-progress">
                                            <span class="presslms-progress__bar" style="width: <?php echo esc_attr($progress); ?>%;"></span>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!empty($student->order_ref)): ?>
                                            #<?php echo esc_html($student->order_ref); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php
                                        $primary_date = !empty($student->purchased_at) ? $student->purchased_at : $student->created_at;
                                        echo !empty($primary_date)
                                            ? esc_html(date_i18n('d/m/Y H:i', strtotime((string) $primary_date)))
                                            : '—';
                                        ?>
                                        <div class="presslms-muted">
                                            <?php echo esc_html($access_summary); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="presslms-table-actions">
                                            <button
                                                type="button"
                                                class="button presslms-button presslms-button--secondary presslms-btn-action js-presslms-change-password"
                                                data-user-id="<?php echo esc_attr($student->user_id); ?>"
                                                data-student-name="<?php echo esc_attr($student->full_name ?: 'Aluno'); ?>">Alterar Senha
                                            </button>
                                            <?php if ($status_key === 'active'): ?>
                                                <a class="button presslms-button presslms-button--danger presslms-btn-action" href="<?php echo esc_url($block_url); ?>">Bloquear Acesso</a>
                                            <?php else: ?>
                                                <a class="button presslms-button presslms-button--info presslms-btn-action" href="<?php echo esc_url($reactivate_url); ?>">Reativar</a>
                                            <?php endif; ?>
                                            <?php if ($can_extend): ?>
                                                <a class="button presslms-button presslms-button--ghost presslms-btn-action" href="<?php echo esc_url($extend_30_url); ?>">+30 dias</a>
                                                <a class="button presslms-button presslms-button--ghost presslms-btn-action" href="<?php echo esc_url($extend_90_url); ?>">+90 dias</a>
                                                <a class="button presslms-button presslms-button--ghost presslms-btn-action" href="<?php echo esc_url($extend_year_url); ?>">+1 ano</a>
                                            <?php endif; ?>
                                            <a
                                                class="button presslms-button presslms-button--success presslms-btn-action"
                                                target="_blank"
                                                href="<?php echo esc_url(
                                                    admin_url(
                                                        'admin-post.php?action=press_lms_preview_certificate&course_id=' . (int) $student->course_id . '&user_id=' . (int) $student->user_id
                                                    )
                                                ); ?>"
                                            >
                                                Emitir Certificado
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
