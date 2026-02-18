<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_CPT
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'register_course']);
        add_action('init', [__CLASS__, 'register_lesson']);
    }

    public static function register_course()
    {
        register_post_type('mlb_course', [
            'labels' => [
                'name' => 'Cursos',
                'singular_name' => 'Curso',
                'add_new_item' => 'Adicionar Curso',
                'edit_item' => 'Editar Curso',
                'all_items' => 'Cursos',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'mlb-lms', // aparece dentro do menu Pressplay LMS
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => ['slug' => 'curso', 'with_front' => false],
            'has_archive' => false,
        ]);
    }

    public static function register_lesson()
    {
        register_post_type('mlb_lesson', [
            'labels' => [
                'name' => 'Aulas',
                'singular_name' => 'Aula',
                'add_new_item' => 'Adicionar Aula',
                'edit_item' => 'Editar Aula',
                'all_items' => 'Aulas',
            ],
            'public' => false,     // não expõe publicamente por padrão
            'show_ui' => true,     // mas aparece no admin
            'show_in_menu' => 'mlb-lms',
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => false,    // rota vai ser custom via Rewrite do plugin
        ]);
    }
}
