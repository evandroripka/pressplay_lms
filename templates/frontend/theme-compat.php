<?php
if (!defined('ABSPATH')) exit;

status_header(PRESS_LMS_Frontend::get_route_status());
nocache_headers();

get_header();
?>
<main id="primary" class="site-main presslms-theme-main">
  <?php if (class_exists('PRESS_LMS_Frontend')) : ?>
    <?php PRESS_LMS_Frontend::render_theme_compat_content(); ?>
  <?php endif; ?>
</main>
<?php
get_footer();
