<?php
if (!defined('ABSPATH')) exit;

$students = is_array($students ?? null) ? $students : [];
$courses = is_array($courses ?? null) ? $courses : [];

$filter_course = (int) ($filter_course ?? 0);
$filter_status = (string) ($filter_status ?? '');
$filter_search = (string) ($filter_search ?? '');
$filter_sort   = (string) ($filter_sort ?? 'date_desc');
$now_ts = current_time('timestamp');

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
                <h1 class="title is-3 mb-2">Alunos</h1>
                <p class="subtitle is-6 mb-0">Gerencie alunos, matrículas e progresso por curso.</p>
            </div>
        </div>

        <div class="box presslms-box presslms-box--filters">
            <form method="get" class="presslms-filters-form">
                <input type="hidden" name="page" value="press-lms-students">

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
                            <a href="<?php echo esc_url(admin_url('admin.php?page=press-lms-students')); ?>" class="button is-light presslms-btn">Limpar</a>
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

                                $status_class = 'is-light';
                                $status_label = 'Desconhecido';
                                $is_expired = !empty($student->expires_at) && strtotime((string) $student->expires_at) <= $now_ts;

                                if ($student->status === 'active' && $is_expired) {
                                    $status_class = 'is-danger is-light';
                                    $status_label = 'Expirado';
                                } elseif ($student->status === 'active') {
                                    $status_class = 'is-success is-light';
                                    $status_label = 'Ativo';
                                } elseif ($student->status === 'pending') {
                                    $status_class = 'is-warning is-light';
                                    $status_label = 'Pendente';
                                }
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
                                            <?php
                                            if ($student->status === 'active' && empty($student->expires_at)) {
                                                echo 'Vitalício';
                                            } elseif (!empty($student->expires_at)) {
                                                echo ($is_expired ? 'Expirou em ' : 'Válido até ')
                                                    . esc_html(date_i18n('d/m/Y', strtotime((string) $student->expires_at)));
                                            } else {
                                                echo '—';
                                            }
                                            ?>
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
                                            <button type="button" class="button is-danger is-light presslms-btn-action">Bloquear Acesso</button>
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
