<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Templates
{
    public static function init()
    {
        add_filter('template_include', [__CLASS__, 'template_include']);
    }

    public static function template_include($template)
    {
        // Se for um single do CPT press_course, força template do plugin
        if (is_singular('press_course')) {
            $plugin_template = PRESS_LMS_PATH . 'templates/single-press_course.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        // Se for um single do CPT press_lesson
        if (is_singular('press_lesson')) {
            $plugin_template = PRESS_LMS_PATH . 'templates/single-press_lesson.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        return $template;
    }
}