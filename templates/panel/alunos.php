<?php
if (!defined('ABSPATH')) exit;

$students = is_array($students ?? null) ? $students : [];
$courses = is_array($courses ?? null) ? $courses : [];

$filter_course = (int) ($filter_course ?? 0);
$filter_status = (string) ($filter_status ?? '');
$filter_search = (string) ($filter_search ?? '');
$filter_sort   = (string) ($filter_sort ?? 'date_desc');
$panel_page_title = (string) ($panel_page_title ?? 'Alunos');
$panel_page_subtitle = (string) ($panel_page_subtitle ?? 'Gerencie alunos, matrículas e progresso por curso.');
$panel_page_slug = (string) ($panel_page_slug ?? 'press-lms-students');
$panel_notice = is_array($panel_notice ?? null) ? $panel_notice : null;

function presslms_admin_student_progress_percent($completed, $total)
{
    $completed = (int) $completed;
    $total = (int) $total;

    if ($total <= 0) return 0;
    return (int) round(($completed / $total) * 100);
}
?>

<div class="wrap presslms-admin-page presslms-admin-page--students">
    <div class="presslms-panel">
        <div class="presslms-panel__header">
            <div>
                <h1 class="title is-3 mb-2"><?php echo esc_html($panel_page_title); ?></h1>
                <p class="subtitle is-6 mb-0"><?php echo esc_html($panel_page_subtitle); ?></p>
            </div>
        </div>

        <?php if ($panel_notice): ?>
            <?php
            $notice_type = (string) ($panel_notice['type'] ?? 'info');
            $notice_class_map = [
                'success' => 'is-success',
                'warning' => 'is-warning',
                'error' => 'is-danger',
                'info' => 'is-info',
            ];
            $notice_class = $notice_class_map[$notice_type] ?? $notice_class_map['info'];
            ?>
            <div class="notification <?php echo esc_attr($notice_class); ?> is-light mb-5">
                <?php echo esc_html((string) ($panel_notice['message'] ?? '')); ?>
            </div>
        <?php endif; ?>

        <div class="box presslms-box presslms-box--filters">
            <form method="get" class="presslms-filters-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($panel_page_slug); ?>">

                <div class="columns is-multiline">
                    <div class="column is-12">
                        <h2 class="title is-6 mb-4">Filtros</h2>
                    </div>

                    <div class="column is-3">
                        <div class="field presslms-field">
                            <label class="label">Curso</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="course">
                                        <option value="0">Todos os cursos</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($filter_course, (int) $course->ID); ?>>
                                                <?php echo esc_html($course->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field presslms-field">
                            <label class="label">Status</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="status">
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
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field presslms-field">
                            <label class="label">Buscar</label>
                            <div class="control">
                                <input class="input" type="text" name="s" value="<?php echo esc_attr($filter_search); ?>" placeholder="Nome, email ou curso...">
                            </div>
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field presslms-field">
                            <label class="label">Ordenar por</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="sort">
                                        <option value="date_desc" <?php selected($filter_sort, 'date_desc'); ?>>Data mais recente</option>
                                        <option value="date_asc" <?php selected($filter_sort, 'date_asc'); ?>>Data mais antiga</option>
                                        <option value="name_asc" <?php selected($filter_sort, 'name_asc'); ?>>Nome (A-Z)</option>
                                        <option value="name_desc" <?php selected($filter_sort, 'name_desc'); ?>>Nome (Z-A)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-12">
                        <div class="buttons">
                            <button type="submit" class="button is-link presslms-btn">Aplicar filtros</button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $panel_page_slug)); ?>" class="button is-light presslms-btn">Limpar</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="box presslms-box">
            <div class="presslms-table-head">
                <h2 class="title is-6 mb-0">Lista de alunos</h2>
                <span class="tag is-light"><?php echo esc_html(count($students)); ?> registro(s)</span>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-hoverable presslms-table presslms-table--students">
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
                                        Nenhum aluno encontrado com os filtros atuais.
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
                                $status_class = class_exists('PRESS_LMS_Enrollments')
                                    ? PRESS_LMS_Enrollments::get_enrollment_status_class($student)
                                    : 'is-light';
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
                                        <span class="tag <?php echo esc_attr($status_class); ?>">
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
                                        <progress class="progress is-link is-small mb-0" value="<?php echo esc_attr($progress); ?>" max="100">
                                            <?php echo esc_html($progress); ?>%
                                        </progress>
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
                                        <div class="buttons are-small mb-0 presslms-table-actions">
                                            <button
                                                type="button"
                                                class="button is-light presslms-btn-action js-presslms-change-password"
                                                data-user-id="<?php echo esc_attr($student->user_id); ?>"
                                                data-student-name="<?php echo esc_attr($student->full_name ?: 'Aluno'); ?>">Alterar Senha
                                            </button>
                                            <?php if ($status_key === 'active'): ?>
                                                <a class="button is-danger is-light presslms-btn-action" href="<?php echo esc_url($block_url); ?>">Bloquear Acesso</a>
                                            <?php else: ?>
                                                <a class="button is-link is-light presslms-btn-action" href="<?php echo esc_url($reactivate_url); ?>">Reativar</a>
                                            <?php endif; ?>
                                            <?php if ($can_extend): ?>
                                                <a class="button is-info is-light presslms-btn-action" href="<?php echo esc_url($extend_30_url); ?>">+30 dias</a>
                                                <a class="button is-info is-light presslms-btn-action" href="<?php echo esc_url($extend_90_url); ?>">+90 dias</a>
                                                <a class="button is-info is-light presslms-btn-action" href="<?php echo esc_url($extend_year_url); ?>">+1 ano</a>
                                            <?php endif; ?>
                                            <a
  class="button is-success is-light presslms-btn-action"
  target="_blank"
  href="<?php echo esc_url(
    admin_url(
      'admin-post.php?action=press_lms_preview_certificate&course_id=' . (int)$student->course_id . '&user_id=' . (int)$student->user_id
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
