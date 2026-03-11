<?php
if (!defined('ABSPATH')) exit;

$catalog_courses = is_array($catalog_courses_var ?? null) ? $catalog_courses_var : [];
$catalog_stats = is_array($catalog_stats_var ?? null) ? $catalog_stats_var : [];
$catalog_page_title = (string) ($catalog_page_title_var ?? 'Cursos');
?>
<div class="presslms presslms-catalog" data-presslms-page="catalog">
  <div class="presslms__container">
    <section class="presslms-catalog-hero presslms-card">
      <div class="presslms-catalog-hero__copy">
        <span class="presslms-catalog-hero__eyebrow">Catálogo do aluno</span>
        <h1 class="presslms-h1"><?php echo esc_html($catalog_page_title); ?></h1>
        <p class="presslms-muted">
          Veja os cursos disponíveis, confira o que cada um oferece e entre direto na página completa de cada curso.
        </p>
      </div>
      <div class="presslms-catalog-hero__stats">
        <span class="presslms-chip">
          <i class="fa-light fa-layer-group"></i>
          <b><?php echo esc_html((string) ($catalog_stats['total_courses'] ?? 0)); ?></b> cursos
        </span>
        <span class="presslms-chip">
          <i class="fa-light fa-list-check"></i>
          <b><?php echo esc_html((string) ($catalog_stats['total_lessons'] ?? 0)); ?></b> aulas
        </span>
        <?php if (!empty($catalog_stats['total_duration_label'])): ?>
          <span class="presslms-chip">
            <i class="fa-light fa-clock"></i>
            <b><?php echo esc_html((string) $catalog_stats['total_duration_label']); ?></b> de conteúdo
          </span>
        <?php endif; ?>
      </div>
    </section>

    <?php if (empty($catalog_courses)): ?>
      <section class="presslms-card presslms-catalog-empty">
        <h2 class="presslms-h2"><i class="fa-light fa-sparkles"></i> Nenhum curso publicado ainda</h2>
        <p class="presslms-muted">Assim que novos cursos forem publicados, eles aparecerão aqui com capa, duração e acesso direto à página do curso.</p>
      </section>
    <?php else: ?>
      <section class="presslms-catalog-grid" aria-label="Lista de cursos">
        <?php foreach ($catalog_courses as $course): ?>
          <article class="presslms-card presslms-catalog-card">
            <a class="presslms-catalog-card__media" href="<?php echo esc_url((string) ($course['course_url'] ?? '#')); ?>">
              <?php if (!empty($course['thumbnail_url'])): ?>
                <img src="<?php echo esc_url((string) $course['thumbnail_url']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="presslms-catalog-card__fallback"><?php echo esc_html(wp_html_excerpt((string) ($course['course_title'] ?? ''), 2, '')); ?></span>
              <?php endif; ?>
            </a>

            <div class="presslms-catalog-card__content">
              <div class="presslms-catalog-card__top">
                <span class="presslms-catalog-pill presslms-catalog-pill--<?php echo esc_attr((string) ($course['status_class'] ?? 'available')); ?>">
                  <?php echo esc_html((string) ($course['status_label'] ?? 'Curso online')); ?>
                </span>
                <?php if (!empty($course['updated_at'])): ?>
                  <span class="presslms-catalog-card__updated">Atualizado em <?php echo esc_html((string) $course['updated_at']); ?></span>
                <?php endif; ?>
              </div>

              <h2 class="presslms-catalog-card__title">
                <a href="<?php echo esc_url((string) ($course['course_url'] ?? '#')); ?>">
                  <?php echo esc_html((string) ($course['course_title'] ?? 'Curso')); ?>
                </a>
              </h2>

              <?php if (!empty($course['excerpt'])): ?>
                <p class="presslms-catalog-card__excerpt"><?php echo esc_html((string) $course['excerpt']); ?></p>
              <?php endif; ?>

              <div class="presslms-catalog-card__meta">
                <?php if (!empty($course['teacher_name'])): ?>
                  <span><i class="fa-light fa-chalkboard-user"></i> <?php echo esc_html((string) $course['teacher_name']); ?></span>
                <?php endif; ?>
                <span><i class="fa-light fa-layer-group"></i> <?php echo esc_html((string) ($course['lessons_count'] ?? 0)); ?> aulas</span>
                <?php if (!empty($course['duration_label'])): ?>
                  <span><i class="fa-light fa-clock"></i> <?php echo esc_html((string) $course['duration_label']); ?></span>
                <?php endif; ?>
              </div>

              <?php if (!empty($course['features']) && is_array($course['features'])): ?>
                <ul class="presslms-catalog-card__features">
                  <?php foreach ($course['features'] as $feature): ?>
                    <li>
                      <i class="<?php echo esc_attr((string) ($feature['icon'] ?? 'fa-light fa-check')); ?>"></i>
                      <?php echo esc_html((string) ($feature['label'] ?? '')); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <div class="presslms-catalog-card__actions">
                <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url((string) ($course['primary_url'] ?? '#')); ?>">
                  <i class="fa-light fa-arrow-right"></i>
                  <?php echo esc_html((string) ($course['primary_label'] ?? 'Ver curso')); ?>
                </a>
                <a class="presslms-btn" href="<?php echo esc_url((string) ($course['course_url'] ?? '#')); ?>">
                  <i class="fa-light fa-eye"></i>
                  Detalhes
                </a>
                <?php if (!empty($course['can_access'])): ?>
                  <span class="presslms-catalog-card__progress"><?php echo esc_html((string) ($course['progress_percent'] ?? 0)); ?>% concluído</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
