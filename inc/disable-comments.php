<?php

// COMPLETELY DISABLE COMMENTS/TRACKBACKS/PINGBACKS.
// Removes UI elements from Admin Menu, Admin Bar, and Front End.
// 1. Disable support for comments and trackbacks in post types
add_action('admin_init', function() {
	$post_types = get_post_types();
	foreach ($post_types as $post_type) {
		if (post_type_supports($post_type, 'comments')) {
			remove_post_type_support($post_type, 'comments');
			remove_post_type_support($post_type, 'trackbacks');
		}
	}
});
// 2. Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
// 3. Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);
// 4. Remove comments page in menu
add_action('admin_menu', function() {
	remove_menu_page('edit-comments.php');
});
// 5. Redirect any user trying to access comments page
add_action('admin_init', function () {
	global $pagenow;
	if ($pagenow === 'edit-comments.php') {
		wp_redirect(admin_url());
		exit;
	}
});
// 6. Remove comments from the Admin Bar
add_action('wp_before_admin_bar_render', function () {
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu('comments');
});
// 7. Remove "Recent Comments" style from head
add_action('widgets_init', function () {
	global $wp_widget_factory;
	if (isset($wp_widget_factory->widgets['WP_Widget_Recent_Comments'])) {
		remove_action('wp_head', array($wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style'));
	}
});
// 8. "Recent Comments" widget
add_action('widgets_init', function() {
	unregister_widget('WP_Widget_Recent_Comments');
});
// 9. Remove X-Pingback to header
add_filter('wp_headers', function ($headers) {
	unset($headers['X-Pingback']);
	return $headers;
});
// 10. Hide the comment form on front end (CSS Fallback)
// This is a safety net for themes that hardcode the comment form without checking if comments are open.
add_action('wp_head', function () {
	echo '<style>.comment-respond, .comments-area, #comments { display: none !important; }</style>';
});