<?php
if (!defined('ABSPATH')) exit;

/** @var WP_Post $course_var */
$course      = $course_var;
$course_slug = (string) ($course_slug_var ?? '');
$can_access  = (bool) ($can_access_var ?? false);

$trailer     = (string) ($trailer_var ?? '');
$lessons     = is_array($lessons_var ?? null) ? $lessons_var : [];

$first_lesson_url = (string) ($first_lesson_url_var ?? '');
$product_id       = (int) ($product_id_var ?? 0);

$buy_url = '#';
if (function_exists('wc_get_cart_url') && $product_id > 0) {
  $buy_url = add_query_arg('add-to-cart', $product_id, wc_get_cart_url());
}
?>

<div class="presslms presslms-course" data-presslms-page="course">
  <div class="presslms__container">

    <header class="presslms-course-hero">
      <div class="presslms-course-hero__left">
        <h1 class="presslms-h1"><?php echo esc_html($course->post_title); ?></h1>

        <div class="presslms-course-hero__meta">
          <span class="presslms-chip">
            <i class="fa-light fa-circle-info"></i>
            Última atualização: <b><?php echo esc_html( get_the_modified_date('d/m/Y', $course) ); ?></b>
          </span>

          <span class="presslms-chip">
            <i class="fa-light fa-layer-group"></i>
            <b><?php echo esc_html( count($lessons) ); ?></b> aulas
          </span>
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
              ?>
                <a class="presslms-course-lessons__item" href="<?php echo esc_url($lesson_url); ?>">
                  <span class="presslms-course-lessons__num"><?php echo esc_html($idx + 1); ?></span>
                  <span class="presslms-course-lessons__title"><?php echo esc_html($lesson->post_title); ?></span>
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

          <div class="presslms-course-side__includes">
            <div class="presslms-course-side__includes-title">Este curso inclui:</div>
            <ul class="presslms-course-side__list">
              <li><i class="fa-light fa-video"></i> Vídeo sob demanda</li>
              <li><i class="fa-light fa-file-arrow-down"></i> Materiais para download</li>
              <li><i class="fa-light fa-mobile-screen"></i> Acesso no celular e PC</li>
              <li><i class="fa-light fa-closed-captioning"></i> Legendas (se houver)</li>
            </ul>
          </div>
        </section>

      </aside>
    </header>

  </div>
</div>