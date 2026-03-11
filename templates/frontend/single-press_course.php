<?php
if (!defined('ABSPATH')) exit;

/** @var WP_Post $course_var */
$course      = $course_var;
$course_slug = (string) ($course_slug_var ?? '');
$can_access  = (bool) ($can_access_var ?? false);

$trailer     = (string) ($trailer_var ?? '');
$lessons = PRESS_LMS_Helpers::get_course_lessons((int) $course->ID, ['publish']);
$course_thumbnail_url = get_the_post_thumbnail_url($course->ID, 'medium_large') ?: '';
$course_duration_seconds = (int) get_post_meta($course->ID, '_press_course_total_duration', true);
$course_features = class_exists('PRESS_LMS_Course_Meta')
  ? PRESS_LMS_Course_Meta::get_selected_features((int) $course->ID)
  : [];

$first_lesson_url = (string) ($first_lesson_url_var ?? '');
$product_id       = (int) ($product_id_var ?? 0);
$is_paused        = class_exists('PRESS_LMS_Enrollments')
  ? PRESS_LMS_Enrollments::is_course_paused((int) $course->ID)
  : false;
$can_start_enrollment = !$can_access && !$is_paused && $product_id > 0;

$buy_url = '#';
if (function_exists('wc_get_cart_url') && $product_id > 0) {
  $buy_url = add_query_arg('add-to-cart', $product_id, wc_get_cart_url());
}

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
        <h1 class="presslms-h1"><?php echo esc_html($course->post_title); ?></h1>
        <div class="presslms-course-hero__meta">
          <span class="presslms-chip">
            <i class="fa-light fa-circle-info"></i>
            Última atualização: <b><?php echo esc_html(get_the_modified_date('d/m/Y', $course)); ?></b>
          </span>
          <span class="presslms-chip">
          <?php
          $teacher_id = (int) get_post_meta($course->ID, '_press_course_teacher', true);
          if ($teacher_id) {
            $teacher = get_post($teacher_id);
            echo 'Instrutor: <b>' . esc_html($teacher->post_title) . '</b>';
          }
          ?>
          </span>
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
        </div>
        <div class="presslms-course-hero__about presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-bullseye-arrow"></i> O que você aprenderá</h2>
          </div>
          <div class="presslms-content">
            <?php echo apply_filters('the_content', $course->post_content); ?>
          </div>
        </div>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2"><i class="fa-light fa-list-check"></i> Conteúdo do curso</h2>
          </div>
          <?php if (!$lessons || count($lessons) === 0): ?>
            <p class="presslms-muted">Nenhuma aula cadastrada ainda.</p>
          <?php else: ?>
            <div class="presslms-course-lessons">
              <?php foreach ($lessons as $idx => $lesson):
                $lesson_url = home_url('/curso/' . $course_slug . '/aula/' . $lesson->post_name . '/');
                $lesson_thumbnail_url = get_the_post_thumbnail_url($lesson->ID, 'medium_large') ?: $course_thumbnail_url;
                $lesson_duration = (int) get_post_meta($lesson->ID, '_press_lesson_duration', true);
                $lesson_label = sprintf('Aula %02d', $idx + 1);
              ?>
                <a
                  class="presslms-course-lessons__item"
                  href="<?php echo esc_url($lesson_url); ?>"
                  data-presslms-lesson-link="1"
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
      </div>
      <aside class="presslms-course-hero__right">
        <section class="presslms-card presslms-course-side">
          <div class="presslms-course-side__media">
            <?php
            if ($trailer) {
              $embed = wp_oembed_get($trailer);
              if ($embed) {
                echo '<div class="presslms-course-side__ratio">' . $embed . '</div>';
              } else {
                echo '<div class="presslms-course-side__placeholder">TRAILER DO CURSO</div>';
              }
            } else {
              echo '<div class="presslms-course-side__placeholder">TRAILER DO CURSO</div>';
            }
            ?>
          </div>
          <div class="presslms-course-side__cta">
            <?php if ($can_access && $first_lesson_url): ?>
              <a class="presslms-btn presslms-btn--primary presslms-course-side__btn" href="<?php echo esc_url($first_lesson_url); ?>">
                <i class="fa-light fa-arrow-right-to-bracket"></i>
                Acessar Curso
              </a>
            <?php elseif ($is_paused): ?>
              <button class="presslms-btn presslms-btn--primary presslms-course-side__btn" type="button" disabled>
                <i class="fa-light fa-pause"></i>
                Curso Pausado
              </button>
              <p class="presslms-muted" style="margin:10px 0 0;">Novas matrículas estão temporariamente indisponíveis.</p>
            <?php else: ?>
              <a class="presslms-btn presslms-btn--primary presslms-course-side__btn" href="<?php echo esc_url($buy_url); ?>">
                <i class="fa-light fa-bag-shopping"></i>
                Comprar Curso
              </a>
              <?php if ($product_id <= 0): ?>
                <p class="presslms-muted" style="margin:10px 0 0;">Produto do WooCommerce ainda não gerado.</p>
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
