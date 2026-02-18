<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Templates
{
    public static function init()
    {
        add_filter('template_include', [__CLASS__, 'template_include']);
    }

    public static function template_include($template)
    {
        // Se for um single do CPT mlb_course, força template do plugin
        if (is_singular('mlb_course')) {
            $plugin_template = MLB_LMS_PATH . 'templates/single-mlb_course.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        // Se for um single do CPT mlb_lesson
        if (is_singular('mlb_lesson')) {
            $plugin_template = MLB_LMS_PATH . 'templates/single-mlb_lesson.php';
            if (file_exists($plugin_template)) return $plugin_template;
        }

        return $template;
    }
}