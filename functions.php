<?php

// Disabling Shit (and nuking Hostinger bloat, this is for me mostly)
require get_stylesheet_directory() . '/inc/disable-categories.php';
require get_stylesheet_directory() . '/inc/disable-comments.php';
require get_stylesheet_directory() . '/inc/disable-gutenberg.php';
require get_stylesheet_directory() . '/inc/disable-pages.php';
require get_stylesheet_directory() . '/inc/disable-widgets.php';
require get_stylesheet_directory() . '/inc/disable-appearance.php';
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
		'1.0.1'
	);
});