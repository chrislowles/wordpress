<?php

// Disabling Shit (and nuking Hostinger bloat, this is for me mostly)
require get_stylesheet_directory() . '/inc/disable-categories.php';
require get_stylesheet_directory() . '/inc/disable-comments.php';
require get_stylesheet_directory() . '/inc/disable-gutenberg.php';
require get_stylesheet_directory() . '/inc/disable-widgets.php';
require get_stylesheet_directory() . '/inc/nuke-hostinger.php';

// Tag Utils (tag prefill parameters, menu presets)
require get_stylesheet_directory() . '/inc/tag-utils.php';

// Co-Authors
require get_stylesheet_directory() . '/inc/co-authors.php';

// Tracklist Logic
require get_stylesheet_directory() . '/inc/tracklist.php';

// Enqueue Scripts & Styles (as yet decoupled from this file, will do soon)
add_action('admin_enqueue_scripts', function($hook) {
    wp_enqueue_style('admin-css', get_stylesheet_directory_uri() . '/css/admin.css');
    global $post;
    if ($hook === 'post-new.php' || $hook === 'post.php') {
        if ($post && $post->post_type === 'post') {
            // Enqueue admin.css (Create this file if it doesn't exist)
            wp_enqueue_style(
                'tracklist-css',
                get_theme_file_uri() . '/css/admin.css'
            );
            // Enqueue tracklist.js
            // Note: We added 'jquery-ui-sortable' to the dependency array
            wp_enqueue_script(
                'tracklist-js',
                get_theme_file_uri() . '/js/tracklist.js',
                ['jquery', 'jquery-ui-sortable'],
                '2.0',
                true
            );
        }
    }
});

add_action('admin_head', function() {
    // setting baseline for system-set light/dark mode in dashboard
    echo '<meta name="color-scheme" content="light dark" />';
});