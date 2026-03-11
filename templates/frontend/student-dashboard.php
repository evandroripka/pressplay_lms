<?php
if (!defined('ABSPATH')) exit;

$profile = is_array($student_profile_var ?? null) ? $student_profile_var : [];
$courses = is_array($student_courses_var ?? null) ? $student_courses_var : [];
$certificates = is_array($student_certificates_var ?? null) ? $student_certificates_var : [];
$stats = is_array($student_stats_var ?? null) ? $student_stats_var : [];
$active_tab = (string) ($student_tab_var ?? 'courses');
$notice = is_array($student_notice_var ?? null) ? $student_notice_var : null;
$urls = is_array($student_urls_var ?? null) ? $student_urls_var : [];

$display_name = (string) ($profile['display_name'] ?? 'Aluno');
$avatar_url = (string) ($profile['avatar_url'] ?? '');
$initials = (string) ($profile['initials'] ?? 'A');
$email = (string) ($profile['email'] ?? '');
$phone = (string) ($profile['phone'] ?? '');
$registered_at = (string) ($profile['registered_at'] ?? '');
?>
<div class="presslms presslms-student" data-presslms-page="student">
  <div class="presslms__container">
    <header class="presslms-card presslms-student-hero">
      <div class="presslms-student-hero__main">
        <div class="presslms-student-avatar" aria-hidden="true">
          <?php if ($avatar_url): ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
          <?php else: ?>
            <span><?php echo esc_html($initials); ?></span>
          <?php endif; ?>
        </div>
        <div class="presslms-student-hero__copy">
          <span class="presslms-student-eyebrow">Área do aluno</span>
          <h1 class="presslms-h1">Olá, <?php echo esc_html($display_name); ?></h1>
          <div class="presslms-student-hero__meta">
            <?php if ($email !== ''): ?>
              <span class="presslms-chip">
                <i class="fa-light fa-envelope"></i>
                <?php echo esc_html($email); ?>
              </span>
            <?php endif; ?>
            <?php if ($registered_at !== ''): ?>
              <span class="presslms-chip">
                <i class="fa-light fa-calendar"></i>
                Desde <b><?php echo esc_html($registered_at); ?></b>
              </span>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
              <span class="presslms-chip">
                <i class="fa-light fa-phone"></i>
                <?php echo esc_html($phone); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="presslms-student-hero__actions">
        <a class="presslms-btn" href="<?php echo esc_url($urls['profile'] ?? '#'); ?>">
          <i class="fa-light fa-user-gear"></i>
          Meu perfil
        </a>
        <a class="presslms-btn" href="<?php echo esc_url($urls['password'] ?? '#'); ?>">
          <i class="fa-light fa-key"></i>
          Trocar senha
        </a>
        <a class="presslms-btn" href="<?php echo esc_url($urls['logout'] ?? '#'); ?>">
          <i class="fa-light fa-arrow-right-from-bracket"></i>
          Sair
        </a>
      </div>
    </header>

    <section class="presslms-student-stats">
      <article class="presslms-card presslms-student-stat">
        <span class="presslms-student-stat__icon"><i class="fa-light fa-layer-group"></i></span>
        <div>
          <strong><?php echo esc_html((string) ($stats['active_courses'] ?? 0)); ?></strong>
          <span>Cursos ativos</span>
        </div>
      </article>
      <article class="presslms-card presslms-student-stat">
        <span class="presslms-student-stat__icon"><i class="fa-light fa-certificate"></i></span>
        <div>
          <strong><?php echo esc_html((string) ($stats['available_certificates'] ?? 0)); ?></strong>
          <span>Certificados liberados</span>
        </div>
      </article>
      <article class="presslms-card presslms-student-stat">
        <span class="presslms-student-stat__icon"><i class="fa-light fa-list-check"></i></span>
        <div>
          <strong><?php echo esc_html((string) ($stats['completed_lessons'] ?? 0)); ?>/<?php echo esc_html((string) ($stats['total_lessons'] ?? 0)); ?></strong>
          <span>Aulas concluídas</span>
        </div>
      </article>
      <article class="presslms-card presslms-student-stat">
        <span class="presslms-student-stat__icon"><i class="fa-light fa-clock"></i></span>
        <div>
          <strong><?php echo esc_html((string) ($stats['content_duration_label'] ?? '0min')); ?></strong>
          <span>Carga total disponível</span>
        </div>
      </article>
    </section>

    <nav class="presslms-student-nav" aria-label="Navegação da área do aluno">
      <a class="presslms-student-nav__link<?php echo $active_tab === 'catalog' ? ' is-active' : ''; ?>" href="<?php echo esc_url($urls['catalog'] ?? '#'); ?>">
        <i class="fa-light fa-store"></i>
        Showroom
      </a>
      <a class="presslms-student-nav__link<?php echo $active_tab === 'courses' ? ' is-active' : ''; ?>" href="<?php echo esc_url($urls['courses'] ?? '#'); ?>">
        <i class="fa-light fa-graduation-cap"></i>
        Meus cursos
      </a>
      <a class="presslms-student-nav__link<?php echo $active_tab === 'certificates' ? ' is-active' : ''; ?>" href="<?php echo esc_url($urls['certificates'] ?? '#'); ?>">
        <i class="fa-light fa-certificate"></i>
        Certificados
      </a>
      <a class="presslms-student-nav__link<?php echo $active_tab === 'profile' ? ' is-active' : ''; ?>" href="<?php echo esc_url($urls['profile'] ?? '#'); ?>">
        <i class="fa-light fa-user-pen"></i>
        Perfil
      </a>
      <a class="presslms-student-nav__link<?php echo $active_tab === 'password' ? ' is-active' : ''; ?>" href="<?php echo esc_url($urls['password'] ?? '#'); ?>">
        <i class="fa-light fa-key"></i>
        Trocar senha
      </a>
    </nav>

    <?php if ($notice): ?>
      <div class="presslms-card presslms-student-notice presslms-student-notice--<?php echo esc_attr($notice['type']); ?>">
        <?php echo esc_html((string) $notice['message']); ?>
      </div>
    <?php endif; ?>

    <?php if ($active_tab === 'courses'): ?>
      <?php if (empty($courses)): ?>
        <section class="presslms-card presslms-student-empty">
          <h2 class="presslms-h2"><i class="fa-light fa-sparkles"></i> Sua biblioteca ainda está vazia</h2>
          <p class="presslms-muted">Quando você comprar um curso, ele aparecerá aqui com progresso, acesso rápido e certificado.</p>
          <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url($urls['shop'] ?? '#'); ?>">
            <i class="fa-light fa-bag-shopping"></i>
            Explorar cursos
          </a>
        </section>
      <?php else: ?>
        <section class="presslms-student-course-list">
          <?php foreach ($courses as $course): ?>
            <article class="presslms-card presslms-student-course">
              <div class="presslms-student-course__thumb" aria-hidden="true">
                <?php if (!empty($course['thumbnail_url'])): ?>
                  <img src="<?php echo esc_url((string) $course['thumbnail_url']); ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span><?php echo esc_html(wp_html_excerpt((string) $course['course_title'], 2, '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="presslms-student-course__content">
                <div class="presslms-student-course__top">
                  <div class="presslms-student-course__badges">
                    <span class="presslms-student-pill presslms-student-pill--status"><?php echo esc_html((string) $course['status_label']); ?></span>
                    <span class="presslms-student-pill"><?php echo esc_html((string) $course['progress_percent']); ?>% concluído</span>
                  </div>
                  <?php if (!empty($course['purchased_at'])): ?>
                    <span class="presslms-student-course__date">Matrícula em <?php echo esc_html((string) $course['purchased_at']); ?></span>
                  <?php endif; ?>
                </div>

                <h2 class="presslms-student-course__title">
                  <a href="<?php echo esc_url((string) $course['course_url']); ?>"><?php echo esc_html((string) $course['course_title']); ?></a>
                </h2>

                <div class="presslms-student-course__meta">
                  <?php if (!empty($course['teacher_name'])): ?>
                    <span><i class="fa-light fa-chalkboard-user"></i> <?php echo esc_html((string) $course['teacher_name']); ?></span>
                  <?php endif; ?>
                  <span><i class="fa-light fa-layer-group"></i> <?php echo esc_html((string) $course['total_lessons']); ?> aulas</span>
                  <?php if (!empty($course['duration_label'])): ?>
                    <span><i class="fa-light fa-clock"></i> <?php echo esc_html((string) $course['duration_label']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($course['access_expires_label'])): ?>
                    <span><i class="fa-light fa-calendar"></i> <?php echo esc_html((string) $course['access_expires_label']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="presslms-student-progress">
                  <div class="presslms-student-progress__track">
                    <span class="presslms-student-progress__bar" style="width: <?php echo esc_attr((string) max(0, min(100, (int) $course['progress_percent']))); ?>%;"></span>
                  </div>
                  <div class="presslms-student-progress__legend">
                    <span><?php echo esc_html((string) $course['completed_lessons']); ?> de <?php echo esc_html((string) $course['total_lessons']); ?> aulas concluídas</span>
                    <span><?php echo esc_html((string) $course['progress_percent']); ?>%</span>
                  </div>
                </div>

                <div class="presslms-student-course__actions">
                  <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url((string) $course['resume_url']); ?>">
                    <i class="fa-light fa-play"></i>
                    <?php echo esc_html((string) $course['resume_label']); ?>
                  </a>
                  <a class="presslms-btn" href="<?php echo esc_url((string) $course['course_url']); ?>">
                    <i class="fa-light fa-arrow-up-right-from-square"></i>
                    Ver curso
                  </a>
                  <?php if (!empty($course['certificate_available']) && !empty($course['certificate_url'])): ?>
                    <a class="presslms-btn" href="<?php echo esc_url((string) $course['certificate_url']); ?>" target="_blank" rel="noopener noreferrer">
                      <i class="fa-light fa-certificate"></i>
                      Reemitir certificado
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    <?php elseif ($active_tab === 'certificates'): ?>
      <?php if (empty($certificates)): ?>
        <section class="presslms-card presslms-student-empty">
          <h2 class="presslms-h2"><i class="fa-light fa-certificate"></i> Nenhum certificado liberado ainda</h2>
          <p class="presslms-muted">Os certificados ficam disponíveis automaticamente quando você conclui 100% do curso.</p>
        </section>
      <?php else: ?>
        <section class="presslms-student-certificate-list">
          <?php foreach ($certificates as $certificate): ?>
            <article class="presslms-card presslms-student-certificate">
              <div class="presslms-student-certificate__thumb" aria-hidden="true">
                <?php if (!empty($certificate['thumbnail_url'])): ?>
                  <img src="<?php echo esc_url((string) $certificate['thumbnail_url']); ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span><?php echo esc_html(wp_html_excerpt((string) $certificate['course_title'], 2, '')); ?></span>
                <?php endif; ?>
              </div>
              <div class="presslms-student-certificate__content">
                <span class="presslms-student-pill presslms-student-pill--success">Certificado disponível</span>
                <h2 class="presslms-student-certificate__title"><?php echo esc_html((string) $certificate['course_title']); ?></h2>
                <?php if (!empty($certificate['completed_at'])): ?>
                  <p class="presslms-muted">Concluído em <?php echo esc_html((string) $certificate['completed_at']); ?></p>
                <?php endif; ?>
                <div class="presslms-student-certificate__actions">
                  <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url((string) $certificate['certificate_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-light fa-file-arrow-down"></i>
                    Reemitir certificado
                  </a>
                  <a class="presslms-btn" href="<?php echo esc_url((string) $certificate['course_url']); ?>">
                    <i class="fa-light fa-arrow-up-right-from-square"></i>
                    Ver curso
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    <?php elseif ($active_tab === 'profile'): ?>
      <section class="presslms-student-profile-grid">
        <article class="presslms-card presslms-student-profile-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-id-card"></i> Dados da conta</h2>
          </div>
          <dl class="presslms-student-detail-list">
            <div>
              <dt>Nome</dt>
              <dd><?php echo esc_html($display_name); ?></dd>
            </div>
            <div>
              <dt>E-mail</dt>
              <dd><?php echo esc_html($email); ?></dd>
            </div>
            <div>
              <dt>Telefone</dt>
              <dd><?php echo $phone !== '' ? esc_html($phone) : 'Não informado'; ?></dd>
            </div>
            <div>
              <dt>Membro desde</dt>
              <dd><?php echo $registered_at !== '' ? esc_html($registered_at) : 'Não disponível'; ?></dd>
            </div>
          </dl>
        </article>

        <article class="presslms-card presslms-student-profile-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-shield-keyhole"></i> Segurança da conta</h2>
          </div>
          <p class="presslms-muted">Gerencie sua senha em uma tela dedicada para manter o acesso à área do aluno e aos seus certificados.</p>
          <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url($urls['password'] ?? '#'); ?>">
            <i class="fa-light fa-key"></i>
            Ir para trocar senha
          </a>
        </article>
      </section>
    <?php elseif ($active_tab === 'password'): ?>
      <section class="presslms-student-profile-grid">
        <article class="presslms-card presslms-student-profile-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-key"></i> Alterar senha</h2>
          </div>
          <form class="presslms-student-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="press_lms_update_account_password">
            <input type="hidden" name="redirect_screen" value="password">
            <?php wp_nonce_field('press_lms_update_account_password', 'press_lms_account_password_nonce'); ?>

            <label class="presslms-student-form__label" for="presslms-current-password">Senha atual</label>
            <input class="presslms-student-form__input" id="presslms-current-password" type="password" name="current_password" required>

            <label class="presslms-student-form__label" for="presslms-new-password">Nova senha</label>
            <input class="presslms-student-form__input" id="presslms-new-password" type="password" name="new_password" required minlength="6">

            <label class="presslms-student-form__label" for="presslms-confirm-password">Confirmar nova senha</label>
            <input class="presslms-student-form__input" id="presslms-confirm-password" type="password" name="confirm_password" required minlength="6">

            <p class="presslms-muted">Use uma senha com pelo menos 6 caracteres. Você continuará logado após a atualização.</p>

            <button class="presslms-btn presslms-btn--primary" type="submit">
              <i class="fa-light fa-floppy-disk"></i>
              Salvar nova senha
            </button>
          </form>
        </article>

        <article class="presslms-card presslms-student-profile-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-id-card"></i> Dados da conta</h2>
          </div>
          <dl class="presslms-student-detail-list">
            <div>
              <dt>Nome</dt>
              <dd><?php echo esc_html($display_name); ?></dd>
            </div>
            <div>
              <dt>E-mail</dt>
              <dd><?php echo esc_html($email); ?></dd>
            </div>
            <div>
              <dt>Telefone</dt>
              <dd><?php echo $phone !== '' ? esc_html($phone) : 'Não informado'; ?></dd>
            </div>
          </dl>
          <a class="presslms-btn" href="<?php echo esc_url($urls['profile'] ?? '#'); ?>">
            <i class="fa-light fa-user-pen"></i>
            Voltar para meu perfil
          </a>
        </article>
      </section>
    <?php else: ?>
      <section class="presslms-card presslms-student-empty">
        <h2 class="presslms-h2"><i class="fa-light fa-circle-exclamation"></i> Área do aluno não encontrada</h2>
        <p class="presslms-muted">Use a navegação acima para acessar seus cursos, certificados ou dados da conta.</p>
      </section>
    <?php endif; ?>
  </div>
</div>
