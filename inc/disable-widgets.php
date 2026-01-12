<?php

// HIDE DEFAULT WIDGETS
add_action('wp_dashboard_setup', function() {
	// 1. Remove "At a Glance" (shows post/page counts)
	// remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
	
	// 2. Remove "Activity" (shows recent posts and comments)
	// remove_meta_box('dashboard_activity', 'dashboard', 'normal');

	// 3. Remove "Quick Draft" (the quick post box)
	remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
	
	// 4. Remove "WordPress Events and News"
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
	
	// 5. Remove "Site Health Status"
	// remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

	// Special Case: The Welcome Panel (the big "Welcome to WordPress" box)
	remove_action('welcome_panel', 'wp_welcome_panel');
});