<?php
/**
 * Show Template Button
 * Adds a "Load Template" button next to the title field on Show edit screens
 * that prefills both the title and body content with a starter template.
 */

// Only load on Show edit screens
add_action('admin_enqueue_scripts', function($hook) {
	// Only run on post edit/new screens for 'show' post type
	if (($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'show') {
		wp_enqueue_script(
			'show-template-button',
			get_stylesheet_directory_uri() . '/js/show-template-button.js',
			['jquery'],
			'1.0.0',
			true
		);
		// Pass the template data to JavaScript
		wp_localize_script('show-template-button', 'showTemplate', [
			'title' => get_show_title_template(),
			'body' => get_show_body_template()
		]);
	}
});

/**
 * Get the default title template
 * Edit this function to change the default title format
 */
function get_show_title_template() {
	$today = date('Y-m-d');
	return "CNJ {$today}";
}

/**
 * Get the default body template
 * Edit this function to change the default body content
 */
function get_show_body_template() {
	return <<<TEMPLATE
### In The Cinema
* []()

### The Pin Drop
* []()

### Walking on Thin Ice
* []()

### One Up P1
* []()

### One Up P2
* []()
TEMPLATE;
}