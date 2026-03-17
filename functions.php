<?php
/**
 * Theme Functions
 * Organized into class-based modules for readability and scope isolation.
 */

// 1. General Site Cleanup & restrictions
require get_stylesheet_directory() . '/inc/cleanup.php';
new ChrisLowles_Cleanup();

// 2. Show CPT, Tracklists & Template Button
require get_stylesheet_directory() . '/inc/shows.php';
new ChrisLowles_Shows();

// 3. Page Redirect Manager
require get_stylesheet_directory() . '/inc/redirects.php';
new ChrisLowles_PageRedirects();

// 4. Scratchpad
require get_stylesheet_directory() . '/inc/scratchpad.php';
new ChrisLowles_Scratchpad();

// 5. General Theme Setup
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'gallery', 'caption'));
    unregister_nav_menu("main-menu"); 
    register_nav_menus(
        array(
            'logged-in-menu'  => __( 'Main Menu (Logged In)', 'child' ),
            'logged-out-menu' => __( 'Main Menu (Logged Out)', 'child' ),
        )
    );
}, 20);

// 6. Admin Styles & Dark Mode
add_action('admin_head', function () {
    echo '<meta name="color-scheme" content="light dark" />';
});

// 7. Import Bootstrap for blog styles/functs
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css', array(), null);
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js', array(), null, true);
});

// 8. Import styles for dashboard and fonts
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('dashboard-css', get_stylesheet_directory_uri() . '/css/dashboard.css', array(), null);
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap', false);
});

// 9. Convert single Markdown newlines to hard breaks before parsing
add_filter('the_content', function($content) {
    // Two trailing spaces before a single newline = Markdown hard break.
    // Only targets lines sandwiched between non-blank lines (not paragraph gaps).
    return preg_replace('/([^\n])\n([^\n])/', "$1  \n$2", $content);
}, 1);