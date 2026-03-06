<?php
// inclui este arquivo no plugin principal
class PRESSLMS_Teacher_CPT {
    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
    }

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
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => ['slug' => 'professor'],
        ];

        register_post_type('press_teacher', $args);
    }
}