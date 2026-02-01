<?php
/**
 * DISABLE APPEARANCE MENU FUNCTIONALITY
 * 
 * This file removes most Appearance submenu items and moves the
 * Appearance menu to the bottom of the admin dashboard.
 */

// Remove Appearance submenu pages
add_action('admin_menu', function() {
	// Remove Theme File Editor
	remove_submenu_page('themes.php', 'theme-editor.php');
	
	// Remove Customize (Design)
	remove_submenu_page('themes.php', 'customize.php');
	
	// Remove Widgets
	remove_submenu_page('themes.php', 'widgets.php');

	// Remove Menus (optional - uncomment if you want to remove it)
	// remove_submenu_page('themes.php', 'nav-menus.php');
	
	// Remove Themes page (optional - uncomment if you want to remove it)
	// remove_submenu_page('themes.php', 'themes.php');
	
	// Remove Background (if it exists)
	remove_submenu_page('themes.php', 'custom-background');
	
	// Remove Header (if it exists)
	remove_submenu_page('themes.php', 'custom-header');
	
}, 999);

// Redirect users away from removed pages if they try to access them directly
add_action('admin_init', function() {
	global $pagenow;
	
	$blocked_pages = array(
		'theme-editor.php',
		'customize.php',
		'widgets.php',
	);
	
	if (in_array($pagenow, $blocked_pages)) {
		wp_redirect(admin_url());
		exit;
	}
});

// Move Appearance menu to the bottom of the dashboard
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', function($menu_order) {
	// Find and remove 'themes.php' from its current position
	$appearance_key = array_search('themes.php', $menu_order);
	if ($appearance_key !== false) {
		unset($menu_order[$appearance_key]);
	}
	
	// Add it to the end
	$menu_order[] = 'themes.php';
	
	return $menu_order;
});

// Remove Customizer from the admin bar
add_action('wp_before_admin_bar_render', function() {
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu('customize');
});

// Disable theme switching capability (optional - uncomment to prevent theme changes)
// add_filter('user_has_cap', function($caps) {
// 	$caps['switch_themes'] = false;
// 	$caps['edit_theme_options'] = false;
// 	return $caps;
// });