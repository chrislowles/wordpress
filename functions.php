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

/**
 * Automatically add IDs to headings in post content, transforms "The Main Header Text" into <h2 id="the-main-header-text">...
 * Intended for as yet unfinished inner linking from Spacers in the tracklist metabox to sections in the agenda
 */
add_filter('the_content', function ($content) {
    // Only process if we have content
    if (empty($content)) {
        return $content;
    }

    // Regex to match h1-h6 tags
    // Group 1: Level (1-6)
    // Group 2: Existing Attributes (e.g. class="...")
    // Group 3: Inner HTML (The Heading Text)
    return preg_replace_callback(
        '/<h([1-6])([^>]*)>(.*?)<\/h\1>/i', 
        function ($matches) {
            $level = $matches[1];
            $attrs = $matches[2];
            $inner = $matches[3];
            
            // Safety Check: If an ID already exists, do not overwrite it
            if (preg_match('/\bid\s*=/i', $attrs)) {
                return $matches[0];
            }

            // Generate slug from the inner text (stripping tags ensures we just get text)
            // sanitize_title() handles the lowercase and hyphenation automatically
            $slug = sanitize_title(strip_tags($inner));
            
            // If slug is empty (e.g. heading was just an image), return original
            if (empty($slug)) {
                return $matches[0];
            }

            // Rebuild the tag with the new ID
            return "<h{$level}{$attrs} id=\"{$slug}\">{$inner}</h{$level}>";
        }, 
        $content
    );
});