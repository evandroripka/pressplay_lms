<?php

/**
 * Register the teacher post type used by LMS courses and lessons.
 */
class PRESSLMS_Teacher_CPT {
    /**
     * Hook the teacher post type registration into WordPress init.
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
    }

    /**
     * Register the internal teacher post type.
     */
    public static function register_cpt() {
        $labels = [
            'name' => 'Professores',
            'singular_name' => 'Professor',
            'add_new' => 'Adicionar Novo',
            'add_new_item' => 'Adicionar Novo Professor',
            'edit_item' => 'Editar Professor',
            'new_item' => 'Novo Professor',
            'view_item' => 'Ver Professor',
            'search_items' => 'Buscar Professores',
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'thumbnail'],
            'rewrite' => ['slug' => 'professor'],
        ];

        register_post_type('press_teacher', $args);
    }
}
