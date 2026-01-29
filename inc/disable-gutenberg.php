<?php

// Disable Gutenberg completely - Markup Markdown works with Classic Editor

// 1. DISABLE GUTENBERG (BLOCK EDITOR)
// BACKEND
add_filter('use_block_editor_for_post', '__return_false');

// WIDGETS
add_filter('use_widgets_block_editor', '__return_false');

// 2. DISABLE VISUAL EDITOR (TINYMCE)
// This removes the "Visual" tab and enforces "Text" (Code) mode globally.
add_filter('user_can_richedit', '__return_false');

// Force the default editor to always be 'html' (just to be safe)
add_filter('wp_default_editor', function() {
	return 'html';
});

// 3. CLEAN UP FRONTEND ASSETS
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