<?php
/**
 * Class: Cleanup & Restrictions
 * Consolidates all logic for disabling unused WordPress features and plugin bloat.
 */
class ChrisLowles_Cleanup {

    public function __construct() {
        // Bloat Removal
        add_action('admin_init', [$this, 'nuke_hostinger']);
        
        // Feature Disabling
        $this->disable_categories();
        $this->disable_comments();
        $this->disable_pages();
        $this->disable_widgets();
        $this->disable_appearance();
        
        // Gutenberg (Currently disabled/commented out as per original file)
        // $this->disable_gutenberg();
    }

    /**
     * Nuke Hostinger Plugin
     */
    public function nuke_hostinger() {
        $plugin_path = 'hostinger/hostinger.php';
        if (is_plugin_active($plugin_path)) {
            deactivate_plugins($plugin_path);
        }
        add_filter('all_plugins', function($plugins) use ($plugin_path) {
            if (isset($plugins[$plugin_path])) unset($plugins[$plugin_path]);
            return $plugins;
        });
    }

    /**
     * Disable Categories (Taxonomy & UI)
     */
    private function disable_categories() {
        add_action('init', function() {
            unregister_taxonomy_for_object_type('category', 'post');
        }, 20);

        add_action('admin_menu', function() {
            remove_menu_page('edit-tags.php?taxonomy=category');
            remove_meta_box('categorydiv', 'post', 'side');
        }, 999);
    }

    /**
     * Disable Comments, Trackbacks, Pingbacks
     */
    private function disable_comments() {
        // Post type support
        add_action('admin_init', function() {
            foreach (get_post_types() as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        });

        // Front-end closing
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', '__return_empty_array', 10, 2);

        // Admin Menu & Bar
        add_action('admin_menu', function() { remove_menu_page('edit-comments.php'); });
        add_action('admin_init', function() {
            global $pagenow;
            if ($pagenow === 'edit-comments.php') { wp_redirect(admin_url()); exit; }
        });
        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
        });

        // Header cleanup
        add_filter('wp_headers', function($headers) { unset($headers['X-Pingback']); return $headers; });
        add_action('wp_head', function() { echo '<style>.comment-respond, .comments-area, #comments { display: none !important; }</style>'; });
    }

    /**
     * Disable Pages Post Type
     */
    private function disable_pages() {
        add_filter('register_post_type_args', function($args, $post_type) {
            if ($post_type === 'page') {
                $args['public'] = false;
                $args['show_ui'] = false;
                $args['show_in_menu'] = false;
                $args['show_in_admin_bar'] = false;
                $args['show_in_nav_menus'] = false;
                $args['exclude_from_search'] = true;
                $args['publicly_queryable'] = false;
                $args['show_in_rest'] = false; 
            }
            return $args;
        }, 10, 2);

        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('new-page');
        });

        add_action('admin_init', function() {
            global $pagenow;
            if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'page') {
                wp_redirect(admin_url()); exit;
            }
        });
    }

    /**
     * Disable Widgets
     */
    private function disable_widgets() {
        add_action('wp_dashboard_setup', function() {
            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
            remove_meta_box('dashboard_primary', 'dashboard', 'side');
            remove_action('welcome_panel', 'wp_welcome_panel');
        });
        
        // Remove from Appearance menu
        add_action('widgets_init', function() {
            global $wp_registered_sidebars;
            $wp_registered_sidebars = array();
            unregister_widget('WP_Widget_Recent_Comments');
        }, 999);

        // Disable auto-embeds for URLs on their own line
        remove_filter( 'the_content', array( $GLOBALS['wp_embed'], 'autoembed' ), 999 );
    }

    /**
     * Restrict Appearance Menu (Keep Customizer)
     */
    private function disable_appearance() {
        // Remove specific submenus
        add_action('admin_menu', function() {
            remove_submenu_page('themes.php', 'themes.php');
            remove_submenu_page('themes.php', 'theme-install.php');
            remove_submenu_page('themes.php', 'theme-editor.php');
            remove_submenu_page('themes.php', 'nav-menus.php');
            remove_submenu_page('themes.php', 'widgets.php');
            remove_submenu_page('themes.php', 'custom-header');
            remove_submenu_page('themes.php', 'custom-background');
        }, 999);

        // Redirect blocked pages
        add_action('admin_init', function() {
            global $pagenow;
            $blocked = ['themes.php', 'nav-menus.php', 'widgets.php', 'theme-editor.php', 'theme-install.php'];
            if (in_array($pagenow, $blocked)) { wp_safe_redirect(admin_url()); exit; }
        });

        // Cap changes
        add_filter('user_has_cap', function($caps) {
            $caps['switch_themes'] = false;
            $caps['install_themes'] = false;
            $caps['update_themes'] = false;
            $caps['delete_themes'] = false;
            $caps['edit_themes'] = false;
            return $caps;
        });

        // Clean Customizer
        add_action('customize_register', function($wp_customize) {
            $wp_customize->remove_section('nav');
        }, 999);

        // Disable file editing constant
        if (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);

        // Hide updates
        add_filter('pre_site_transient_update_themes', '__return_null');
    }

    /**
     * Disable Gutenberg (Placeholder)
     */
    private function disable_gutenberg() {
         add_filter('use_widgets_block_editor', '__return_false');
         // add_filter('use_block_editor_for_post', '__return_false');
    }
}