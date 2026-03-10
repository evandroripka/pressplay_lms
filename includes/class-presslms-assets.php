<?php
if (!defined('ABSPATH')) exit;

class PRESSLMS_Assets
{
  public static function init(): void
  {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend'], 20);
  }

  public static function enqueue_frontend(): void
  {
    if (self::is_lesson_route()) {
      self::enqueue_lesson_assets();
      return;
    }

    if (self::is_course_route()) {
      self::enqueue_course_assets();
    }
  }

  /**
   * Detecta:
   * /curso/{course-slug}/aula/{lesson-slug}/
   */
  private static function is_lesson_route(): bool
  {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path = trim($path, '/');

    if ($path === '') return false;

    return (bool) preg_match('#^curso/[^/]+/aula/[^/]+/?$#', $path);
  }

  /**
   * Detecta:
   * /curso/{course-slug}/
   */
  private static function is_course_route(): bool
  {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path = trim($path, '/');

    if ($path === '') return false;

    return (bool) preg_match('#^curso/[^/]+/?$#', $path);
  }

  private static function enqueue_shared_styles(): void
  {
    if (!wp_style_is('presslms-base', 'enqueued')) {
      wp_enqueue_style(
        'presslms-base',
        PRESS_LMS_URL . 'assets/css/presslms-base.css',
        [],
        PRESS_LMS_VERSION
      );
    }

    // FontAwesome 7 Pro
    $fa_base = 'https://site-assets.fontawesome.com/releases/v7.2.0/css/';

    if (!wp_style_is('presslms-fa7-core', 'enqueued')) {
      wp_enqueue_style('presslms-fa7-core', $fa_base . 'fontawesome.css', [], '7.2.0');
      wp_enqueue_style('presslms-fa7-light', $fa_base . 'light.css', ['presslms-fa7-core'], '7.2.0');
      wp_enqueue_style('presslms-fa7-regular', $fa_base . 'regular.css', ['presslms-fa7-core'], '7.2.0');
      wp_enqueue_style('presslms-fa7-solid', $fa_base . 'solid.css', ['presslms-fa7-core'], '7.2.0');
      wp_enqueue_style('presslms-fa7-brands', $fa_base . 'brands.css', ['presslms-fa7-core'], '7.2.0');
    }
  }

  private static function enqueue_course_assets(): void
  {
    self::enqueue_shared_styles();

    if (!wp_style_is('presslms-course', 'enqueued')) {
      wp_enqueue_style(
        'presslms-course',
        PRESS_LMS_URL . 'assets/css/presslms-course.css',
        ['presslms-base'],
        PRESS_LMS_VERSION
      );
    }
  }

  private static function enqueue_lesson_assets(): void
  {
    self::enqueue_shared_styles();

    wp_enqueue_script(
      'vimeo-player-sdk',
      'https://player.vimeo.com/api/player.js',
      [],
      null,
      true
    );
    wp_enqueue_script(
      'presslms-lesson-progress',
      PRESS_LMS_URL . 'assets/js/lesson-progress.js',
      ['vimeo-player-sdk'],
      PRESS_LMS_VERSION,
      true
    );

    if (!wp_style_is('presslms-lesson', 'enqueued')) {
      wp_enqueue_style(
        'presslms-lesson',
        PRESS_LMS_URL . 'assets/css/presslms-lesson.css',
        ['presslms-base'],
        PRESS_LMS_VERSION
      );
    }
  }
}
