<?php
/**
 * MOSTLY RESTRICT APPEARANCE MENU
 * 
 * This version keeps the Customizer accessible for basic site settings
 * (like site icon, title, tagline) while blocking theme switching and
 * file editing capabilities.
 * 
 * What's BLOCKED:
 * - Theme switching/installation
 * - Theme file editor (site source is managed through gh repo/gh action workflow)
 * - Menu management (nav-menus.php) [As yet implemented custom menu editor]
 * - Widget management (entirely unused so it can go lol)
 * 
 * What's ALLOWED:
 * - Customizer (for site identity, colors, etc.)
 */

// =============================================================================
// 1. REMOVE UNWANTED APPEARANCE SUBMENU PAGES
// =============================================================================
add_action('admin_menu', function() {
	// Remove theme switching page
	remove_submenu_page('themes.php', 'themes.php');
	
	// Remove theme installation
	remove_submenu_page('themes.php', 'theme-install.php');
	
	// Remove theme file editor
	remove_submenu_page('themes.php', 'theme-editor.php');
	
	// Remove menus (you said you don't use them)
	remove_submenu_page('themes.php', 'nav-menus.php');
	
	// Remove widgets
	remove_submenu_page('themes.php', 'widgets.php');
	
	// Remove header/background (legacy)
	remove_submenu_page('themes.php', 'custom-header');
	remove_submenu_page('themes.php', 'custom-background');
	
	// KEEP customize.php accessible for site settings
}, 999);

// =============================================================================
// 2. BLOCK DIRECT ACCESS TO UNWANTED PAGES
// =============================================================================
add_action('admin_init', function() {
	global $pagenow;

	// List of blocked pages (NOTE: customize.php is NOT in this list)
	$blocked_pages = array(
		'themes.php',           // Main Themes page
		'nav-menus.php',        // Menus
		'widgets.php',          // Widgets
		'theme-editor.php',     // Theme File Editor
		'theme-install.php',    // Add New Theme
	);
	
	// If trying to access any blocked page, redirect to dashboard
	if (in_array($pagenow, $blocked_pages)) {
		wp_safe_redirect(admin_url());
		exit;
	}
});

// =============================================================================
// 3. REMOVE THEME SWITCHING CAPABILITIES (BUT NOT CUSTOMIZER ACCESS)
// =============================================================================
add_filter('user_has_cap', function($caps) {
	// Block theme management
	$caps['switch_themes'] = false;          // Can't switch themes
	$caps['install_themes'] = false;         // Can't install new themes
	$caps['update_themes'] = false;          // Can't update themes
	$caps['delete_themes'] = false;          // Can't delete themes
	$caps['edit_themes'] = false;            // Can't edit theme files
	
	// IMPORTANT: We're NOT removing 'edit_theme_options' because that controls
	// access to the customizer, which we want to keep for site settings
	
	return $caps;
});

// =============================================================================
// 4. CUSTOMIZE THE CUSTOMIZER (Keep Only What You Need)
// =============================================================================
add_action('customize_register', function($wp_customize) {
	// Remove sections you don't need
	// Uncomment any you want to hide
	
	// $wp_customize->remove_section('header_image');
	// $wp_customize->remove_section('background_image');
	$wp_customize->remove_section('nav');  // Nav menus
	// $wp_customize->remove_section('static_front_page');
	
	// KEEP: title_tagline (for site icon!)
	// KEEP: colors (if you want them)
	// KEEP: custom_css (if you want it)
}, 999);

// =============================================================================
// 5. DISABLE WIDGETS FUNCTIONALITY
// =============================================================================
add_action('widgets_init', function() {
	global $wp_registered_sidebars;
	$wp_registered_sidebars = array();
}, 999);

// =============================================================================
// 6. DISABLE MENUS FUNCTIONALITY
// =============================================================================
// Menus are blocked via the admin_menu and admin_init hooks above
// No additional code needed

// =============================================================================
// 7. DISABLE THEME FILE EDITOR
// =============================================================================
if (!defined('DISALLOW_FILE_EDIT')) {
	define('DISALLOW_FILE_EDIT', true);
}

// =============================================================================
// 8. HIDE THEME-RELATED NOTICES & UPDATES
// =============================================================================
add_filter('site_transient_update_themes', function($value) {
	return new stdClass();
});

remove_action('load-update-core.php', 'wp_update_themes');
add_filter('pre_site_transient_update_themes', '__return_null');

// =============================================================================
// 9. PREVENT THEME SWITCHING VIA URL MANIPULATION
// =============================================================================
add_action('admin_init', function() {
	// Block theme switching actions
	if (isset($_GET['action']) && in_array($_GET['action'], array('activate', 'delete', 'enable-auto-update', 'disable-auto-update'))) {
		if (isset($_GET['stylesheet']) || isset($_GET['theme'])) {
			wp_safe_redirect(admin_url());
			exit;
		}
	}
});

// =============================================================================
// 10. REMOVE THEME SETUP WIZARD & WELCOME SCREENS
// =============================================================================
remove_action('after_switch_theme', 'wp_new_admin_notice');

add_action('after_switch_theme', function() {
	// Prevent auto-redirect to customizer
}, 1);

// =============================================================================
// WHAT YOU CAN NOW ACCESS
// =============================================================================
/**
 * With this refactored version, you can access:
 * 
 * 1. CUSTOMIZER: Go to Appearance > Customize
 *    - Site Identity (site icon, title, tagline)
 *    - Colors (if your theme supports it)
 *    - Custom CSS
 *    - Any other customizer panels your theme adds
 * 
 * 2. The main "Appearance" menu item will still show in the admin menu
 *    but most sub-items are removed
 * 
 * What's still blocked:
 * - Theme switching
 * - Installing new themes
 * - Theme file editor
 * - Menus
 * - Widgets
 */