<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_CPT
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'register_course']);
        add_action('init', [__CLASS__, 'register_lesson']);
    }

    public static function register_course()
    {
        register_post_type('press_course', [
            'labels' => [
                'name' => 'Cursos',
                'singular_name' => 'Curso',
                'add_new_item' => 'Adicionar Curso',
                'edit_item' => 'Editar Curso',
                'all_items' => 'Cursos',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'press-lms', // Register the post type under the LMS admin menu.
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'rewrite' => ['slug' => 'curso', 'with_front' => false],
            'has_archive' => false,
        ]);
    }

    public static function register_lesson()
    {
        register_post_type('press_lesson', [
            'labels' => [
                'name' => 'Aulas',
                'singular_name' => 'Aula',
                'add_new_item' => 'Adicionar Aula',
                'edit_item' => 'Editar Aula',
                'all_items' => 'Aulas',
            ],
            'public' => false,     // Frontend access is handled by custom routes.
            'show_ui' => true,     // Keep lesson management available in the admin.
            'show_in_menu' => false, // Lesson creation/editing should start from the course editor.
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'rewrite' => false,    // The lesson permalink is resolved by the plugin router.
        ]);
    }
}
