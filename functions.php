<?php

// Disabling Shit (and nuking Hostinger bloat, this is for me mostly)
require get_stylesheet_directory() . '/inc/disable-categories.php';
require get_stylesheet_directory() . '/inc/disable-comments.php';
require get_stylesheet_directory() . '/inc/disable-gutenberg.php';
require get_stylesheet_directory() . '/inc/disable-widgets.php';
require get_stylesheet_directory() . '/inc/nuke-hostinger.php';

// Tracklist/Show Post type logic
require get_stylesheet_directory() . '/inc/shows.php';

// Redirect Manager
require get_stylesheet_directory() . '/inc/redirects.php';

// Agenda Scratchpad (Dashboard/Editor Widget)
require get_stylesheet_directory() . '/inc/agenda.php';

// Setting baseline for system-set light/dark mode in dashboard
add_action('admin_head', function() {
	echo '<meta name="color-scheme" content="light dark" />';
});

// Enqueue Scripts & Styles
add_action('admin_enqueue_scripts', function($hook) {
	// Enqueue dashboard CSS for all admin pages
	$css_path = get_stylesheet_directory_uri() . '/css/dashboard.css';
	wp_enqueue_style(
		'dashboard-css',
		$css_path,
		array(),
		'1.0.1'  // Version bump
	);

	// Note: tracklist.js is now handled in inc/shows.php
	// Removed duplicate enqueue logic that was causing conflicts
});

//add_action('admin_notices', function() {
//	if (post_type_exists('show')) {
//		echo '<div class="notice notice-success"><p>Show post type is registered :)</p></div>';
//	} else {
//		echo '<div class="notice notice-error"><p>Show post type is not registered :(</p></div>';
//	}
//});