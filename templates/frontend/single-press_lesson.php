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

// duração por meta (vamos preencher via API depois)
$lesson_duration = (int) get_post_meta($lesson->ID, '_press_lesson_duration', true);
$course_duration = (int) get_post_meta($course->ID, '_press_course_total_duration', true);

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
        <!--         <a class="presslms-back" href="<?php echo esc_url(home_url('/curso/' . $course_slug)); ?>">
          <i class="fa-light fa-arrow-left-long"></i>
          <span>Voltar para o curso</span>
        </a>
 -->
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
        </div>
      </div>

      <div class="presslms-topbar__right">
        <a class="presslms-btn presslms-btn--primary" href="#">
          <i class="fa-light fa-bag-shopping"></i>
          Comprar Curso
        </a>
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

          <div class="presslms-instructor">
            <div class="presslms-avatar" aria-hidden="true"></div>
            <div>
              <?php
              $course_teacher = (int) get_post_meta($course->ID, '_press_course_teacher', true);
              $lesson_teacher = (int) get_post_meta($lesson->ID, '_press_lesson_teacher', true);
              $teacher_id = $lesson_teacher ?: $course_teacher;

              if ($teacher_id) {
                $teacher = get_post($teacher_id);
                echo '<h3>Instrutor: ' . esc_html($teacher->post_title) . '</h3>';
              }
              ?>
              <div class="presslms-strong">[Nome do Instrutor]</div>
              <div class="presslms-muted">[Cargo / Bio curta]</div>

              <div class="presslms-social">
                <a class="presslms-iconlink" href="#"><i class="fa-brands fa-instagram"></i></a>
                <a class="presslms-iconlink" href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                <a class="presslms-iconlink" href="#"><i class="fa-brands fa-x-twitter"></i></a>
              </div>
            </div>
          </div>
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
          // ---------------------------------------------------------
          // Sidebar: lista real de aulas do curso
          // ---------------------------------------------------------
          $lessons_list = get_posts([
            'post_type'      => 'press_lesson',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_press_lesson_course_id',
            'meta_value'     => (int) $course->ID,
            'orderby'        => 'menu_order title', // se você usar menu_order, ele respeita
            'order'          => 'ASC',
          ]);

          $current_lesson_id = (int) $lesson->ID;
          ?>

          <nav class="presslms-lessons">
            <?php if (!$lessons_list): ?>
              <div class="presslms-muted">Nenhuma aula cadastrada ainda.</div>
            <?php else: ?>
              <?php foreach ($lessons_list as $i => $l):
                $url = home_url('/curso/' . $course_slug . '/aula/' . $l->post_name . '/');
                $active = ((int)$l->ID === $current_lesson_id) ? ' is-active' : '';
              ?>
                <a class="presslms-lessons__item<?php echo esc_attr($active); ?>" href="<?php echo esc_url($url); ?>">
                  <span><?php echo esc_html($i + 1); ?>.</span>
                  <?php echo esc_html($l->post_title); ?>
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