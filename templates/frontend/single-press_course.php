<?php
if (!defined('ABSPATH')) exit;

/** @var WP_Post $course_var */
$course      = $course_var;
$course_slug = (string) ($course_slug_var ?? '');
$can_access  = (bool) ($can_access_var ?? false);

$trailer     = (string) ($trailer_var ?? '');
$course_access_label = (string) ($course_access_label_var ?? '');
$course_notice = is_array($course_notice_var ?? null) ? $course_notice_var : null;
$lessons = PRESS_LMS_Helpers::get_course_lessons((int) $course->ID, ['publish']);
$course_thumbnail_url = get_the_post_thumbnail_url($course->ID, 'medium_large') ?: '';
$course_description = PRESS_LMS_Helpers::render_post_content($course);
$trailer_id = PRESS_LMS_Vimeo::parse_video_id($trailer);
$trailer_embed = $trailer_id ? PRESS_LMS_Vimeo::get_embed_html($trailer_id, 960, $trailer, true) : ($trailer !== '' ? wp_oembed_get($trailer) : '');
$course_duration_seconds = PRESSLMS_Duration::get_course_total_duration((int) $course->ID);
$course_features = class_exists('PRESS_LMS_Course_Meta')
  ? PRESS_LMS_Course_Meta::get_selected_features((int) $course->ID)
  : [];

$first_lesson_url = (string) ($first_lesson_url_var ?? '');
$product_id       = (int) ($product_id_var ?? 0);
$is_paused        = class_exists('PRESS_LMS_Enrollments')
  ? PRESS_LMS_Enrollments::is_course_paused((int) $course->ID)
  : false;
$product = $product_id > 0 && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
$can_start_enrollment = !$can_access && !$is_paused && $product && $product->is_purchasable() && $product->is_in_stock();

if (!function_exists('presslms_course_format_seconds')) {
  function presslms_course_format_seconds($seconds): string
  {
    $seconds = max(0, (int) $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;

    if ($h > 0) {
      return sprintf('%d:%02d:%02d', $h, $m, $s);
    }

    return sprintf('%d:%02d', $m, $s);
  }
}

$course_duration_label = class_exists('PRESS_LMS_Certificate')
  ? PRESS_LMS_Certificate::format_seconds($course_duration_seconds)
  : presslms_course_format_seconds($course_duration_seconds);
?>
<div
  class="presslms presslms-course"
  data-presslms-page="course"
  data-course-locked="<?php echo $can_access ? '0' : '1'; ?>"
  data-course-paused="<?php echo $is_paused ? '1' : '0'; ?>"
  data-course-can-enroll="<?php echo $can_start_enrollment ? '1' : '0'; ?>"
>
  <div class="presslms__container">
    <header class="presslms-course-hero">
      <div class="presslms-course-hero__left">
        <?php if ($course_notice): ?>
          <div class="presslms-card presslms-student-notice presslms-student-notice--<?php echo esc_attr((string) ($course_notice['type'] ?? 'error')); ?>" style="margin-bottom:16px;">
            <?php echo esc_html((string) ($course_notice['message'] ?? '')); ?>
          </div>
        <?php endif; ?>
        <h1 class="presslms-h1"><?php echo esc_html($course->post_title); ?></h1>
        <div class="presslms-course-hero__meta">
          <span class="presslms-chip">
            <i class="fa-light fa-circle-info"></i>
            Última atualização: <b><?php echo esc_html(get_the_modified_date('d/m/Y', $course)); ?></b>
          </span>
          <?php
          $teacher_id = (int) get_post_meta($course->ID, '_press_course_teacher', true);
          $teacher = $teacher_id ? get_post($teacher_id) : null;
          if ($teacher instanceof WP_Post) {
            echo '<span class="presslms-chip">Instrutor: <b>' . esc_html($teacher->post_title) . '</b></span>';
          }
          ?>
          <span class="presslms-chip">
            <i class="fa-light fa-layer-group"></i>
            <b><?php echo esc_html(count($lessons)); ?></b> aulas
          </span>
          <?php if ($course_duration_seconds > 0): ?>
            <span class="presslms-chip">
              <i class="fa-light fa-clock"></i>
              <b><?php echo esc_html($course_duration_label); ?></b> de conteúdo
            </span>
          <?php endif; ?>
          <?php if ($course_access_label !== ''): ?>
            <span class="presslms-chip">
              <i class="fa-light fa-calendar"></i>
              <b><?php echo esc_html($course_access_label); ?></b>
            </span>
          <?php endif; ?>
        </div>
        <?php if (trim($course_description) !== ''): ?>
        <div class="presslms-course-hero__about presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-bullseye-arrow"></i> O que você aprenderá</h2>
          </div>
          <div class="presslms-content">
            <?php echo $course_description; ?>
          </div>
        </div>
        <?php endif; ?>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-list-check"></i> Conteúdo do curso</h2>
          </div>
          <?php if (!$lessons || count($lessons) === 0): ?>
            <p class="presslms-muted">Este curso ainda não possui aulas publicadas. Consulte a equipe sobre a disponibilidade do conteúdo antes de se matricular.</p>
          <?php else: ?>
            <div class="presslms-course-lessons">
              <?php foreach ($lessons as $idx => $lesson):
                $lesson_url = home_url('/curso/' . $course_slug . '/aula/' . $lesson->post_name . '/');
                $lesson_thumbnail_url = class_exists('PRESS_LMS_Helpers')
                  ? PRESS_LMS_Helpers::get_lesson_thumbnail_url((int) $lesson->ID, (int) $course->ID, 'medium_large')
                  : ($course_thumbnail_url ?: '');
                $lesson_duration = (int) get_post_meta($lesson->ID, '_press_lesson_duration', true);
                $lesson_label = sprintf('Aula %02d', $idx + 1);
                $is_sample = PRESS_LMS_Helpers::is_sample_lesson((int) $lesson->ID, (int) $course->ID);
              ?>
                <a
                  class="presslms-course-lessons__item"
                  href="<?php echo esc_url($lesson_url); ?>"
                  data-presslms-lesson-link="1"
                  data-free-preview="<?php echo $is_sample ? '1' : '0'; ?>"
                >
                  <span class="presslms-course-lessons__thumb" aria-hidden="true">
                    <?php if ($lesson_thumbnail_url): ?>
                      <img
                        src="<?php echo esc_url($lesson_thumbnail_url); ?>"
                        alt=""
                        loading="lazy"
                      >
                    <?php else: ?>
                      <span class="presslms-course-lessons__thumb-placeholder"><?php echo esc_html($idx + 1); ?></span>
                    <?php endif; ?>
                    <span class="presslms-course-lessons__num"><?php echo esc_html($idx + 1); ?></span>
                  </span>
                  <span class="presslms-course-lessons__body">
                    <span class="presslms-course-lessons__eyebrow"><?php echo esc_html($lesson_label); ?></span>
                    <?php if ($is_sample): ?><span class="presslms-sample-label">Aula grátis</span><?php endif; ?>
                    <span class="presslms-course-lessons__title"><?php echo esc_html($lesson->post_title); ?></span>
                    <?php if ($lesson_duration > 0): ?>
                      <span class="presslms-course-lessons__meta">
                        <i class="fa-light fa-clock"></i>
                        <?php echo esc_html(presslms_course_format_seconds($lesson_duration)); ?>
                      </span>
                    <?php endif; ?>
                  </span>
                  <span class="presslms-course-lessons__action">
                    <i class="fa-light fa-play"></i>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <?php PRESS_LMS_Materials::render_course_downloads((int) $course->ID); ?>
      </div>
      <aside class="presslms-course-hero__right">
        <section class="presslms-card presslms-course-side">
          <?php if ($trailer_embed || $course_thumbnail_url): ?>
            <div class="presslms-course-side__media">
              <?php if ($trailer_embed): ?>
                <div class="presslms-course-side__ratio" id="presslms-trailer-player"><?php echo $trailer_embed; ?></div>
              <?php else: ?>
                <img class="presslms-course-side__cover" src="<?php echo esc_url($course_thumbnail_url); ?>" alt="<?php echo esc_attr($course->post_title); ?>">
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="presslms-course-side__cta">
            <?php if (!$can_access && $product && $product->get_price() !== ''): ?>
              <div class="presslms-course-price" aria-label="Valor do curso"><?php echo wp_kses_post($product->get_price_html()); ?></div>
            <?php endif; ?>
            <?php if ($can_access && $first_lesson_url): ?>
              <a class="presslms-btn presslms-btn--primary presslms-course-side__btn" href="<?php echo esc_url($first_lesson_url); ?>">
                <i class="fa-light fa-arrow-right-to-bracket"></i>
                Continuar curso
              </a>
            <?php elseif ($can_access): ?>
              <p class="presslms-muted">Seu acesso está ativo. As aulas serão disponibilizadas em breve.</p>
            <?php elseif ($is_paused): ?>
              <button class="presslms-btn presslms-btn--primary presslms-course-side__btn" type="button" disabled>
                <i class="fa-light fa-pause"></i>
                Curso Pausado
              </button>
              <p class="presslms-muted" style="margin:10px 0 0;">Novas matrículas estão temporariamente indisponíveis.</p>
            <?php else: ?>
              <button
                class="presslms-btn presslms-btn--primary presslms-course-side__btn"
                type="submit"
                form="presslms-course-enroll-form"
                <?php echo $can_start_enrollment ? '' : 'disabled'; ?>
              >
                <i class="fa-light fa-bag-shopping"></i>
                Comprar Curso
              </button>
              <?php if (!$can_start_enrollment): ?>
                <p class="presslms-muted" style="margin:10px 0 0;">Matrículas temporariamente indisponíveis.</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($course_features)): ?>
            <div class="presslms-course-side__includes">
              <div class="presslms-course-side__includes-title">Este curso inclui:</div>
              <ul class="presslms-course-side__list">
                <?php foreach ($course_features as $feature): ?>
                  <li>
                    <i class="<?php echo esc_attr($feature['icon']); ?>"></i>
                    <?php echo esc_html($feature['label']); ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <div class="presslms-course-side__includes"><?php PRESS_LMS_Terms::render_course((int) $course->ID); ?></div>
        </section>
      </aside>
    </header>
  </div>
</div>
<?php if ($can_start_enrollment): ?>
  <form
    id="presslms-course-enroll-form"
    method="post"
    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    style="display:none;"
  >
    <input type="hidden" name="action" value="press_lms_enroll">
    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course->ID); ?>">
    <?php echo wp_nonce_field('press_lms_enroll_' . $course->ID, '_wpnonce', true, false); ?>
  </form>
<?php endif; ?>
