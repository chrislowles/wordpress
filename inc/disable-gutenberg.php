<?php

// Disable Gutenberg for most post types, but allow it for specific ones like 'show'

// BACKEND
add_filter('use_block_editor_for_post', function($use_block_editor, $post) {
	// Allow block editor for 'show' post type (so Markup Markdown can work)
	if ($post->post_type === 'show') {
		return true;
	}
	// Disable for everything else
	return false;
}, 10, 2);

// WIDGETS
add_filter('use_widgets_block_editor', '__return_false');
add_action('wp_enqueue_scripts', function() {
	// Remove CSS on the front end.
	wp_dequeue_style('wp-block-library');

	// Remove Gutenberg theme.
	wp_dequeue_style('wp-block-library-theme');

	// Remove inline global CSS on the front end.
	wp_dequeue_style('global-styles');

	// Remove classic-themes CSS for backwards compatibility for button blocks.
	wp_dequeue_style('classic-theme-styles');
}, 20);