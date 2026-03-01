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

// 3. Redirect Manager
require get_stylesheet_directory() . '/inc/redirects.php';
new ChrisLowles_Redirects();

// 4. Scratchpad
require get_stylesheet_directory() . '/inc/scratchpad.php';
new ChrisLowles_Scratchpad();

// 5. General Theme Setup
add_action('after_setup_theme', function () {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('html5', array('search-form', 'gallery', 'caption'));
	register_nav_menus(array('main-menu' => esc_html__('Main Menu', 'child')));
});

// 6. Admin Styles & Dark Mode
add_action('admin_head', function () {
	echo '<meta name="color-scheme" content="light dark" />';
});

add_action('admin_enqueue_scripts', function () {
	wp_enqueue_style(
		'dashboard-css',
		get_stylesheet_directory_uri() . '/css/dashboard.css',
		array(),
		'1.0.2'
	);
	wp_enqueue_style(
		'google-fonts',
		'https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap',
		false
	);
});