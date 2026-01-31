<?php
/**
 * DISABLE PAGES
 * Unregisters the 'page' post type UI and related configuration elements.
 */

// 1. Modify the Post Type arguments to hide it completely
add_filter('register_post_type_args', function($args, $post_type) {
	if ($post_type === 'page') {
		$args['public']              = false; // Hides from frontend and admin
		$args['show_ui']             = false; // Hides Admin UI
		$args['show_in_menu']        = false; // Hides from Admin Menu
		$args['show_in_admin_bar']   = false; // Hides from Admin Bar
		$args['show_in_nav_menus']   = false; // Hides from Appearance > Menus
		$args['can_export']          = false;
		$args['has_archive']         = false;
		$args['exclude_from_search'] = true;
		$args['publicly_queryable']  = false;
		$args['show_in_rest']        = false; // Disables Gutenberg/REST API access
	}
	return $args;
}, 10, 2);

// 2. Remove "New Page" from the Admin Bar (Double tap to be sure)
add_action('wp_before_admin_bar_render', function() {
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu('new-page');
});

// 3. Redirect any accidental visits to 'edit.php?post_type=page' to Dashboard
add_action('admin_init', function() {
	global $pagenow;
	if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'page') {
		wp_redirect(admin_url());
		exit;
	}
});