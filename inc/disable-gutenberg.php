<?php

// Disable Gutenberg completely - Markup Markdown works with Classic Editor

// BACKEND
add_filter('use_block_editor_for_post', '__return_false');

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