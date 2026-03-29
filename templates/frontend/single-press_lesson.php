<?php
if (!defined('ABSPATH')) exit;

/** @var WP_Post $course */
/** @var WP_Post $lesson */

$course_slug = isset($course_slug_var) ? (string)$course_slug_var : '';
$lesson_slug = isset($lesson_slug_var) ? (string)$lesson_slug_var : '';
$course = (isset($course_var) && $course_var instanceof WP_Post) ? $course_var : null;
$lesson = (isset($lesson_var) && $lesson_var instanceof WP_Post) ? $lesson_var : null;
if (!$course || !$lesson) {
  echo '<div class="presslms presslms-lesson"><div class="presslms__container"><div class="presslms-card"><h1 class="presslms-h1">Aula não encontrada</h1></div></div></div>';
  return;
}
$video_url = (string) get_post_meta($lesson->ID, '_press_lesson_video_url', true);
$vimeo_id  = (int) get_post_meta($lesson->ID, '_press_lesson_vimeo_id', true);
$materials_items = (array) get_post_meta($lesson->ID, '_press_lesson_materials_v2', true);
if (class_exists('PRESS_LMS_Materials')) {
  $materials_items = PRESS_LMS_Materials::normalize_items($materials_items);
}
$course_thumbnail_url = get_the_post_thumbnail_url($course->ID, 'medium_large') ?: '';
// Read the stored lesson duration from post meta.
$lesson_duration = (int) get_post_meta($lesson->ID, '_press_lesson_duration', true);
$course_duration = (int) get_post_meta($course->ID, '_press_course_total_duration', true);
$course_url = home_url('/curso/' . $course_slug . '/');
$product_id = class_exists('PRESS_LMS_Enrollments')
  ? PRESS_LMS_Enrollments::get_course_product_id((int) $course->ID)
  : 0;
$can_access_course = class_exists('PRESS_LMS_Enrollments')
  ? PRESS_LMS_Enrollments::can_access_course(get_current_user_id(), (int) $course->ID)
  : false;
$is_paused = class_exists('PRESS_LMS_Enrollments')
  ? PRESS_LMS_Enrollments::is_course_paused((int) $course->ID)
  : false;
$can_start_enrollment = !$can_access_course && !$is_paused && $product_id > 0;

$lessons_list = PRESS_LMS_Helpers::get_course_lessons((int) $course->ID, ['publish']);
$current_lesson_number = 0;

foreach ($lessons_list as $index => $listed_lesson) {
  if ((int) $listed_lesson->ID === (int) $lesson->ID) {
    $current_lesson_number = $index + 1;
    break;
  }
}

$lesson_breadcrumb_label = $current_lesson_number > 0
  ? sprintf('Aula %02d', $current_lesson_number)
  : wp_html_excerpt($lesson->post_title, 26, '...');

// Resolve the lesson instructor.
// Priority:
// 1. teacher assigned directly to the lesson
// 2. teacher assigned to the parent course
$course_teacher_id = (int) get_post_meta($course->ID, '_press_course_teacher', true);
$lesson_teacher_id = (int) get_post_meta($lesson->ID, '_press_lesson_teacher', true);

$teacher_id = $lesson_teacher_id > 0 ? $lesson_teacher_id : $course_teacher_id;

$teacher_name       = '';
$teacher_profession = '';
$teacher_bio        = '';
$teacher_photo      = '';

$teacher_instagram  = '';
$teacher_facebook   = '';
$teacher_x          = '';
$teacher_linkedin   = '';
$teacher_website    = '';
$teacher_behance    = '';
$teacher_pinterest  = '';
$teacher_email      = '';

if ($teacher_id > 0) {
  $teacher_post = get_post($teacher_id);

  if ($teacher_post && $teacher_post->post_type === 'press_teacher') {
    $teacher_name       = get_the_title($teacher_id);
    $teacher_profession = (string) get_post_meta($teacher_id, '_press_teacher_profession', true);
    $teacher_bio        = (string) get_post_field('post_content', $teacher_id);
    $teacher_photo      = get_the_post_thumbnail_url($teacher_id, 'medium');

    $teacher_instagram  = (string) get_post_meta($teacher_id, '_press_teacher_instagram', true);
    $teacher_facebook   = (string) get_post_meta($teacher_id, '_press_teacher_facebook', true);
    $teacher_x          = (string) get_post_meta($teacher_id, '_press_teacher_x', true);
    $teacher_linkedin   = (string) get_post_meta($teacher_id, '_press_teacher_linkedin', true);
    $teacher_website    = (string) get_post_meta($teacher_id, '_press_teacher_website', true);
    $teacher_behance    = (string) get_post_meta($teacher_id, '_press_teacher_behance', true);
    $teacher_pinterest  = (string) get_post_meta($teacher_id, '_press_teacher_pinterest', true);
    $teacher_email      = (string) get_post_meta($teacher_id, '_press_teacher_email', true);
  }
}

if (!function_exists('presslms_format_seconds')) {
  function presslms_format_seconds($seconds): string
  {
    $seconds = max(0, (int)$seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
  }
}
?>
<div class="presslms presslms-lesson" data-presslms-page="lesson">
  <div class="presslms__container">
    <header class="presslms-topbar">
      <div class="presslms-topbar__left">
        <div class="presslms-title">
          <h1 class="presslms-h1"><?php echo esc_html($course->post_title); ?></h1>
          <div class="presslms-meta">
            <span class="presslms-chip">
              <i class="fa-light fa-clock"></i>
              <b><?php echo esc_html(presslms_format_seconds($course_duration)); ?></b> total
            </span>
            <span class="presslms-chip">
              <i class="fa-light fa-circle-play"></i>
              Aula: <b><?php echo esc_html(presslms_format_seconds($lesson_duration)); ?></b>
            </span>
          </div>
          <nav class="presslms-breadcrumbs presslms-breadcrumbs--compact" aria-label="Breadcrumb">
            <a href="<?php echo esc_url($course_url); ?>">Curso</a>
            <span>&gt;</span>
            <span class="presslms-breadcrumbs__current" aria-current="page"><?php echo esc_html($lesson_breadcrumb_label); ?></span>
          </nav>
        </div>
      </div>
      <div class="presslms-topbar__right">
        <?php if ($can_access_course): ?>
          <a class="presslms-btn presslms-btn--primary" href="<?php echo esc_url($course_url); ?>">
            <i class="fa-light fa-arrow-left-long"></i>
            Voltar ao Curso
          </a>
        <?php elseif ($is_paused): ?>
          <button class="presslms-btn presslms-btn--primary" type="button" disabled>
            <i class="fa-light fa-pause"></i>
            Curso Pausado
          </button>
        <?php else: ?>
          <button
            class="presslms-btn presslms-btn--primary"
            type="submit"
            form="presslms-lesson-enroll-form"
            <?php echo $can_start_enrollment ? '' : 'disabled'; ?>
          >
            <i class="fa-light fa-bag-shopping"></i>
            Comprar Curso
          </button>
        <?php endif; ?>
      </div>
    </header>
    <div class="presslms-layout">
      <main class="presslms-main">
        <section class="presslms-card presslms-player">
          <div class="presslms-player__ratio">
            <?php
            $rendered_video = false;

            if ($vimeo_id && class_exists('PRESS_LMS_Vimeo')) {
              $html = PRESS_LMS_Vimeo::get_embed_html($vimeo_id);
              if ($html) {
                echo $html;
                $rendered_video = true;
              }
            }

            if (!$rendered_video && $video_url) {
              $embed = wp_oembed_get($video_url);
              if ($embed) {
                echo $embed;
                $rendered_video = true;
              } else {
                echo '<p class="presslms-muted">Vídeo informado, mas não foi possível gerar o player.</p>';
              }
            }
            ?>
          </div>
        </section>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2">
              <i class="fa-light fa-book-open"></i>
              <?php echo esc_html($lesson->post_title); ?>
            </h2>
          </div>
          <div class="presslms-content">
            <?php echo apply_filters('the_content', $lesson->post_content); ?>
          </div>
        </section>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2">
              <i class="fa-light fa-chalkboard-user"></i>
              Instrutor
            </h2>
          </div>
          <?php if ($teacher_id > 0 && $teacher_name !== ''): ?>
            <div class="presslms-instructor">
              <?php if ($teacher_photo): ?>
                <div class="presslms-avatar presslms-avatar--image">
                  <img src="<?php echo esc_url($teacher_photo); ?>" alt="<?php echo esc_attr($teacher_name); ?>">
                </div>
              <?php else: ?>
                <div class="presslms-avatar" aria-hidden="true"></div>
              <?php endif; ?>
              <div class="presslms-instructor__content">
                <div class="presslms-strong"><?php echo esc_html($teacher_name); ?></div>
                <?php if ($teacher_profession): ?>
                  <div class="presslms-muted"><?php echo esc_html($teacher_profession); ?></div>
                <?php endif; ?>
                <?php if ($teacher_bio): ?>
                  <div class="presslms-instructor__bio">
                    <?php echo wp_kses_post(wpautop($teacher_bio)); ?>
                  </div>
                <?php endif; ?>
                <?php
                $has_social =
                  $teacher_instagram ||
                  $teacher_facebook ||
                  $teacher_x ||
                  $teacher_linkedin ||
                  $teacher_website ||
                  $teacher_behance ||
                  $teacher_pinterest ||
                  $teacher_email;
                ?>
                <?php if ($has_social): ?>
                  <div class="presslms-social">
                    <?php if ($teacher_instagram): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_instagram); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_facebook): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_facebook); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_x): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_x); ?>" target="_blank" rel="noopener noreferrer" aria-label="X">
                        <i class="fa-brands fa-x-twitter"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_linkedin): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_linkedin); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_website): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_website); ?>" target="_blank" rel="noopener noreferrer" aria-label="Website">
                        <i class="fa-light fa-globe"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_behance): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_behance); ?>" target="_blank" rel="noopener noreferrer" aria-label="Behance">
                        <i class="fa-brands fa-behance"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_pinterest): ?>
                      <a class="presslms-iconlink" href="<?php echo esc_url($teacher_pinterest); ?>" target="_blank" rel="noopener noreferrer" aria-label="Pinterest">
                        <i class="fa-brands fa-pinterest-p"></i>
                      </a>
                    <?php endif; ?>

                    <?php if ($teacher_email): ?>
                      <a class="presslms-iconlink" href="mailto:<?php echo antispambot(esc_attr($teacher_email)); ?>" aria-label="E-mail">
                        <i class="fa-light fa-envelope"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <p class="presslms-muted">Nenhum instrutor definido para esta aula.</p>
          <?php endif; ?>
        </section>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2">
              <i class="fa-light fa-folder-open"></i>
              Materiais
            </h2>
          </div>
          <?php if (!$materials_items || count($materials_items) === 0): ?>
            <p class="presslms-muted">Sem materiais nesta aula.</p>
          <?php else: ?>
            <ul class="presslms-materials">
              <?php foreach ($materials_items as $it):
                $type = $it['type'] ?? 'link';
                $name = (string)($it['name'] ?? '');
                $url  = (string)($it['url'] ?? '');
                $att  = (int)($it['attachment_id'] ?? 0);
                if ($url === '') continue;

                $download_attr = '';
                if ($type === 'file' && $att > 0) {
                  $file_path = get_attached_file($att);
                  if ($file_path) $download_attr = ' download="' . esc_attr(basename($file_path)) . '"';
                }
              ?>
                <li class="presslms-materials__item">
                  <i class="presslms-materials__icon fa-light fa-file-lines"></i>
                  <a class="presslms-materials__link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" <?php echo $download_attr; ?>>
                    <?php echo esc_html($name !== '' ? $name : $url); ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </main>
      <aside class="presslms-aside">
        <section class="presslms-card presslms-aside__sticky">
          <div class="presslms-card__header">
            <h2 class="presslms-h2">
              <i class="fa-light fa-list-check"></i>
              Aulas
            </h2>
          </div>

          <?php
          // Sidebar: render the real lesson list for the current course.
          $current_lesson_id = (int) $lesson->ID;
          ?>

          <nav class="presslms-lessons">
            <?php if (!$lessons_list): ?>
              <div class="presslms-muted">Nenhuma aula cadastrada ainda.</div>
            <?php else: ?>
              <?php foreach ($lessons_list as $i => $l):
                $url = home_url('/curso/' . $course_slug . '/aula/' . $l->post_name . '/');
                $active = ((int)$l->ID === $current_lesson_id) ? ' is-active' : '';
                $sidebar_thumb = class_exists('PRESS_LMS_Helpers')
                  ? PRESS_LMS_Helpers::get_lesson_thumbnail_url((int) $l->ID, (int) $course->ID, 'medium')
                  : ($course_thumbnail_url ?: '');
                $sidebar_duration = (int) get_post_meta($l->ID, '_press_lesson_duration', true);
              ?>
                <a class="presslms-lessons__item<?php echo esc_attr($active); ?>" href="<?php echo esc_url($url); ?>">
                  <span class="presslms-lessons__thumb" aria-hidden="true">
                    <?php if ($sidebar_thumb): ?>
                      <img src="<?php echo esc_url($sidebar_thumb); ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <span class="presslms-lessons__thumb-placeholder"><?php echo esc_html($i + 1); ?></span>
                    <?php endif; ?>
                    <span class="presslms-lessons__badge"><?php echo esc_html($i + 1); ?></span>
                  </span>
                  <span class="presslms-lessons__body">
                    <span class="presslms-lessons__title"><?php echo esc_html($l->post_title); ?></span>
                    <?php if ($sidebar_duration > 0): ?>
                      <span class="presslms-lessons__meta"><?php echo esc_html(presslms_format_seconds($sidebar_duration)); ?></span>
                    <?php endif; ?>
                  </span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </nav>
        </section>
        <section class="presslms-card">
          <div class="presslms-card__header">
            <h2 class="presslms-h2">
              <i class="fa-light fa-sparkles"></i>
              Cursos Relacionados
            </h2>
          </div>
          <div class="presslms-related">
            <article class="presslms-related__item">
              <div class="presslms-thumb" aria-hidden="true"></div>
              <div class="presslms-related__info">
                <div class="presslms-strong">[Curso 1]</div>
                <div class="presslms-muted">R$ 99,90</div>
              </div>
              <a class="presslms-btn presslms-btn--ghost" href="#"><i class="fa-light fa-bag-shopping"></i></a>
            </article>
          </div>
        </section>
      </aside>
    </div>
  </div>
</div>
<?php if ($can_start_enrollment): ?>
  <form
    id="presslms-lesson-enroll-form"
    method="post"
    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    style="display:none;"
  >
    <input type="hidden" name="action" value="press_lms_enroll">
    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course->ID); ?>">
    <?php echo wp_nonce_field('press_lms_enroll_' . $course->ID, '_wpnonce', true, false); ?>
  </form>
<?php endif; ?>
<script>
window.presslmsLessonData = {
  ajaxUrl: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>",
  nonce: "<?php echo esc_js(wp_create_nonce('presslms_track_progress')); ?>",
  courseId: <?php echo (int) $course->ID; ?>,
  lessonId: <?php echo (int) $lesson->ID; ?>,
  vimeoId: <?php echo (int) $vimeo_id; ?>
};
</script>
