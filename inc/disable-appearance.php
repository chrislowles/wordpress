<?php
/**
 * COMPLETELY DISABLE APPEARANCE MENU & FUNCTIONALITY
 * 
 * This file removes the entire Appearance menu and all associated functionality:
 * - Removes all Appearance submenu items
 * - Blocks direct access to all Appearance pages
 * - Removes Appearance from admin bar
 * - Disables theme switching and customization
 * - Removes all theme-related capabilities
 */

// =============================================================================
// 1. REMOVE ALL APPEARANCE SUBMENU PAGES
// =============================================================================

add_action('admin_menu', function() {
	// Remove the entire Appearance menu
	remove_menu_page('themes.php');
	
	// Belt and suspenders: Also remove all submenus in case they're added by plugins
	remove_submenu_page('themes.php', 'themes.php');           // Themes
	remove_submenu_page('themes.php', 'customize.php');        // Customize
	remove_submenu_page('themes.php', 'nav-menus.php');        // Menus
	remove_submenu_page('themes.php', 'widgets.php');          // Widgets
	remove_submenu_page('themes.php', 'theme-editor.php');     // Theme File Editor
	remove_submenu_page('themes.php', 'custom-header');        // Header
	remove_submenu_page('themes.php', 'custom-background');    // Background
	remove_submenu_page('themes.php', 'theme-install.php');    // Add New Theme
	
}, 999);

// =============================================================================
// 2. BLOCK DIRECT ACCESS TO ALL APPEARANCE PAGES
// =============================================================================

add_action('admin_init', function() {
	global $pagenow;
	
	// List of all Appearance-related pages to block
	$blocked_pages = array(
		'themes.php',           // Main Themes page
		'customize.php',        // Customizer
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
	
	// Also block customize.php with action parameter
	if ($pagenow === 'customize.php' || (isset($_GET['action']) && $_GET['action'] === 'customize')) {
		wp_safe_redirect(admin_url());
		exit;
	}
});

// =============================================================================
// 3. REMOVE APPEARANCE & CUSTOMIZER FROM ADMIN BAR
// =============================================================================

add_action('wp_before_admin_bar_render', function() {
	global $wp_admin_bar;
	
	// Remove Customize link from admin bar
	$wp_admin_bar->remove_menu('customize');
	
	// Remove Themes link from admin bar
	$wp_admin_bar->remove_menu('themes');
	
	// Remove any appearance-related submenus
	$wp_admin_bar->remove_menu('appearance');
}, 999);

// Also remove from frontend admin bar
add_action('admin_bar_menu', function($wp_admin_bar) {
	$wp_admin_bar->remove_node('customize');
	$wp_admin_bar->remove_node('themes');
	$wp_admin_bar->remove_node('appearance');
}, 999);

// =============================================================================
// 4. DISABLE THEME SWITCHING & CUSTOMIZATION CAPABILITIES
// =============================================================================

add_filter('user_has_cap', function($caps) {
	// Remove all theme-related capabilities
	$caps['switch_themes'] = false;          // Can't switch themes
	$caps['edit_theme_options'] = false;     // Can't edit theme options
	$caps['install_themes'] = false;         // Can't install new themes
	$caps['update_themes'] = false;          // Can't update themes
	$caps['delete_themes'] = false;          // Can't delete themes
	$caps['edit_themes'] = false;            // Can't edit theme files
	
	return $caps;
});

// =============================================================================
// 5. REMOVE THEME CUSTOMIZATION FUNCTIONALITY
// =============================================================================

// Disable Customizer completely
add_action('customize_register', function($wp_customize) {
	// Remove all default sections
	$wp_customize->remove_section('title_tagline');
	$wp_customize->remove_section('colors');
	$wp_customize->remove_section('header_image');
	$wp_customize->remove_section('background_image');
	$wp_customize->remove_section('nav');
	$wp_customize->remove_section('static_front_page');
	$wp_customize->remove_section('custom_css');
}, 999);

// Disable theme preview
add_filter('customize_previewable_devices', '__return_empty_array');

// =============================================================================
// 6. DISABLE WIDGETS FUNCTIONALITY
// =============================================================================

// Remove widgets screen
add_action('widgets_init', function() {
	// Unregister all widget areas/sidebars
	// This prevents the Widgets screen from being functional
	global $wp_registered_sidebars;
	$wp_registered_sidebars = array();
}, 999);

// =============================================================================
// 7. DISABLE MENUS FUNCTIONALITY
// =============================================================================

// Remove menu management capability
add_filter('user_has_cap', function($caps) {
	$caps['edit_theme_options'] = false; // This also controls menu access
	return $caps;
}, 20);

// =============================================================================
// 8. REMOVE THEME SUPPORT FEATURES
// =============================================================================

add_action('after_setup_theme', function() {
	// Remove theme customization features
	remove_theme_support('custom-header');
	remove_theme_support('custom-background');
	remove_theme_support('custom-logo');
	remove_theme_support('customize-selective-refresh-widgets');
}, 999);

// =============================================================================
// 9. DISABLE THEME FILE EDITOR
// =============================================================================

// Disable file editing completely (also affects plugins)
if (!defined('DISALLOW_FILE_EDIT')) {
	define('DISALLOW_FILE_EDIT', true);
}

// =============================================================================
// 10. HIDE THEME-RELATED NOTICES & UPDATES
// =============================================================================

// Remove theme update notifications
add_filter('site_transient_update_themes', function($value) {
	return new stdClass();
});

// Remove theme update checks
remove_action('load-update-core.php', 'wp_update_themes');
add_filter('pre_site_transient_update_themes', '__return_null');

// =============================================================================
// 11. REMOVE APPEARANCE LINKS FROM DASHBOARD WIDGETS
// =============================================================================

add_action('wp_dashboard_setup', function() {
	// Remove any appearance-related dashboard widgets
	remove_meta_box('dashboard_primary', 'dashboard', 'side'); // WordPress News might have theme links
}, 999);

// =============================================================================
// 12. PREVENT THEME SWITCHING VIA URL MANIPULATION
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
// 13. REMOVE THEME SETUP WIZARD & WELCOME SCREENS
// =============================================================================

// Remove theme activation redirect
remove_action('after_switch_theme', 'wp_new_admin_notice');

// Remove customizer redirect after theme activation
add_action('after_switch_theme', function() {
	// Do nothing - prevents auto-redirect to customizer
}, 1);

// =============================================================================
// OPTIONAL: DEBUGGING
// =============================================================================

/**
 * Uncomment the function below to log when someone attempts to access
 * Appearance-related pages. Useful for debugging.
 */
/*
add_action('admin_init', function() {
	global $pagenow;
	$appearance_pages = array('themes.php', 'customize.php', 'nav-menus.php', 'widgets.php', 'theme-editor.php');
	if (in_array($pagenow, $appearance_pages)) {
		error_log('Blocked access to Appearance page: ' . $pagenow . ' by user ID: ' . get_current_user_id());
	}
});
*/