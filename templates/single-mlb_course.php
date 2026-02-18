<?php
if (!defined('ABSPATH')) exit;

get_header();

$course_id = get_the_ID();
$trailer = get_post_meta($course_id, '_mlb_course_trailer', true);

?>
<div class="mlb-container" style="max-width:1000px;margin:40px auto;padding:0 16px;">
  <h1><?php the_title(); ?></h1>

  <?php if (has_post_thumbnail($course_id)) : ?>
    <div style="margin:16px 0;">
      <?php echo get_the_post_thumbnail($course_id, 'large', ['style' => 'width:100%;height:auto;border-radius:12px;']); ?>
    </div>
  <?php endif; ?>

  <?php if ($trailer) :
      $embed = wp_oembed_get($trailer);
      if ($embed) : ?>
        <div style="margin:16px 0;"><?php echo $embed; ?></div>
      <?php endif;
    endif; ?>

  <div class="mlb-content">
    <?php the_content(); ?>
  </div>

  <hr style="margin:24px 0;">

  <h2>Aulas</h2>
  <?php
  $lessons = get_posts([
      'post_type' => 'mlb_lesson',
      'numberposts' => -1,
      'post_status' => 'publish',
      'meta_key' => '_mlb_lesson_course_id',
      'meta_value' => $course_id,
      'orderby' => 'title',
      'order' => 'ASC',
  ]);

  if (!$lessons) {
      echo '<p style="color:#666">Nenhuma aula cadastrada ainda.</p>';
  } else {
      echo '<ul>';
      foreach ($lessons as $lesson) {
          echo '<li><a href="' . esc_url(get_permalink($lesson->ID)) . '">' . esc_html($lesson->post_title) . '</a></li>';
      }
      echo '</ul>';
  }
  ?>
</div>
<?php

get_footer();
