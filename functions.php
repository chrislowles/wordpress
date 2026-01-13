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

// Setting baseline for system-set light/dark mode in dashboard
add_action('admin_head', function() {
	echo '<meta name="color-scheme" content="light dark" />';
});

// Enqueue Scripts & Styles (as yet decoupled from this file, will do soon)
add_action('admin_enqueue_scripts', function($hook) {
	// 1. Define the path to your new CSS file, get_stylesheet_directory_uri() points to your current active theme folder
	$css_path = get_stylesheet_directory_uri() . '/css/dashboard.css';
	// 2. Enqueue the style, 'dashboard-css' is a unique ID (handle) for this file.
	wp_enqueue_style( 
		'dashboard-css',
		$css_path,
		array(),      // Dependencies (none needed here)
		'1.0.0'       // Version number (useful for cache busting)
	);
	global $post;
	if ($hook === 'post-new.php' || $hook === 'post.php') {
		if ($post && $post->post_type === 'post') {
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