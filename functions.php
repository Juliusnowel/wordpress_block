<?php
function my_child_theme_enqueue_styles() {
    wp_enqueue_style('parent-style', get_parent_theme_file_uri('style.css'));
    wp_enqueue_style('child-style', get_stylesheet_uri(), ['parent-style'], wp_get_theme()->get('Version'));
}
add_action( 'wp_enqueue_scripts', 'my_child_theme_enqueue_styles' );

// REGISTER BLOCKS

add_action('init', function () {
    register_block_type( get_stylesheet_directory() . '/blocks/vite-faq' ); // IVAN
    register_block_type( get_stylesheet_directory() . '/blocks/definition-box' ); // IVAN
    register_block_type( get_stylesheet_directory() . '/blocks/key-takeaways' ); // IVAN
});

?>
