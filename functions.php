<?php

// Nuke Hostinger Bullshit Plugin :D
add_action('admin_init', function() {
    $plugin_path = 'hostinger/hostinger.php';
    // 1. Force Deactivate
    if ( is_plugin_active( $plugin_path ) ) {
        deactivate_plugins( $plugin_path );
    }
    // 2. Hide from Plugin List
    add_filter( 'all_plugins', function( $plugins ) use ( $plugin_path ) {
        if ( isset( $plugins[ $plugin_path ] ) ) {
            unset( $plugins[ $plugin_path ] );
        }
        return $plugins;
    });
});

// HIDE DEFAULT WIDGETS
function custom_remove_dashboard_widgets() {
    // 1. Remove "At a Glance" (shows post/page counts)
    // remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    
    // 2. Remove "Activity" (shows recent posts and comments)
    // remove_meta_box('dashboard_activity', 'dashboard', 'normal');

    // 3. Remove "Quick Draft" (the quick post box)
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    
    // 4. Remove "WordPress Events and News"
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    
    // 5. Remove "Site Health Status"
    // remove_meta_box('dashboard_site_health', 'dashboard', 'normal');

    // Special Case: The Welcome Panel (the big "Welcome to WordPress" box)
    remove_action('welcome_panel', 'wp_welcome_panel');
}
// This line tells WordPress to run our function when setting up the dashboard
add_action('wp_dashboard_setup', 'custom_remove_dashboard_widgets');

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

// Comma-seperated parameter for prefilling tags on new posts: /wp-admin/post-new.php?prefill_tags=technology,news
add_action('save_post', function($post_id, $post, $update) {
    // 1. Check if we are in the admin area and the parameters exist
    if (!is_admin() || !isset($_GET['prefill_tags'])) {
        return;
    }
    // 2. Ensure this is a new 'auto-draft' being created. We do not want to overwrite tags on existing posts being updated.
    if ($post->post_status !== 'auto-draft') {
        return;
    }
    // 3. Check permissions (optional but recommended)
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    // 4. Sanitize and Process the tags. We expect a comma-separated list like ?prefill_tags=News,Events
    $tags_input = sanitize_text_field($_GET['prefill_tags']);
    if (!empty($tags_input)) {
        // Convert the string into an array
        $tags_array = explode(',', $tags_input);
        // 5. Set the tags (This works for the 'post_tag' taxonomy)
        // This will verify the tags exist, or create them if they don't.
        wp_set_object_terms($post_id, $tags_array, 'post_tag');
    }
}, 20, 3);

// Add Presets to the Standard "+ New" Admin Bar Menu
add_action('admin_bar_menu', function($wp_admin_bar) {
    // Format: 'Label' => 'tag1,tag2'
    $presets = array(
        'Show Post' => 'cnj'
        // Copy/Paste to add more...
    );
    foreach ( $presets as $label => $tags ) {
        $safe_id = sanitize_title($label);
        $wp_admin_bar->add_node(array(
            'parent' => 'new-content', // This targets the standard "+ New" dropdown
            'id'     => 'quick-post-' . $safe_id,
            'title'  => $label,
            'href'   => admin_url('post-new.php?prefill_tags=' . $tags),
        ));
    }
}, 90); // Priority 90 ensures they appear at the bottom of the list

// Disable Gutenberg
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

// ADMIN ONLY CSS
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('admin-css', get_stylesheet_directory_uri() . '/admin.css');
});

/// DISABLE CATEGORIES
// TAXONOMY
add_action('init', function() {
    unregister_taxonomy_for_object_type('category', 'post');
}, 20);
// MENU ITEM
add_action('admin_menu', function() {
    remove_menu_page('edit-tags.php?taxonomy=category');
    remove_meta_box('categorydiv', 'post', 'side');
}, 999);
// QUICK/BULK EDIT OPTIONS
add_action('bulk_edit_custom_box', function($column_name, $post_type) {
    if ($column_name === 'categories') {
        remove_meta_box('categorydiv', $post_type, 'side');
    }
}, 10, 2);
add_filter('quick_edit_show_taxonomy', function($show, $taxonomy, $post_type) {
    if ($taxonomy === 'category') {
        return false;
    }
    return $show;
}, 10, 3);

// Tracklist Metabox
add_action('add_meta_boxes', function() {
    add_meta_box(
        'tracklist_meta_box',
        'Tracklist',
        function ($post) {
            // 1. Get existing data
            $tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
            wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
            ?>
            <div id="tracklist-container">
                <?php 
                // 2. Loop through saved tracks/spacers
                foreach ($tracklist as $i => $item): 
                    $type = $item['type'] ?? 'track'; 
                ?>
                    <div class="track-row <?= $type === 'spacer' ? 'is-spacer' : '' ?>">
                        <span class="drag-handle" title="Drag to reorder">|||</span>
                        <input type="hidden" name="tracklist[<?= $i ?>][type]" value="<?= esc_attr($type) ?>" />
                        <input type="text"
                               name="tracklist[<?= $i ?>][track_title]"
                               placeholder="<?= $type === 'spacer' ? '[In The Cinema/The Pin Drop/Walking On Thin Ice/One Up]' : 'Artist/Group - Track Title' ?>"
                               value="<?= esc_attr($item['track_title']) ?>"
                               class="widefat" />
                        <input type="url"
                               name="tracklist[<?= $i ?>][track_url]"
                               placeholder="https://..."
                               value="<?= esc_url($item['track_url'] ?? '') ?>"
                               class="track-url-input"
                               style="<?= $type === 'spacer' ? 'display:none;' : '' ?>" />
                        <button type="button" class="remove-track button">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 10px;">
                <button type="button" class="add-track button button-primary">Track</button>
                <button type="button" class="add-spacer button">Spacer</button>
            </div>
            <?php
        },
        'post',
        'normal',
        'default'
    );
});

// Save Tracklist Logic
add_action('save_post_post', function($post_id) {
    if (
        !isset($_POST['tracklist_meta_nonce']) ||
        !wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta') ||
        !current_user_can('edit_post', $post_id)
    ) {
        return;
    }
    if (!empty($_POST['tracklist']) && is_array($_POST['tracklist'])) {
        $sanitized = [];
        foreach ($_POST['tracklist'] as $track) {
            if (empty(trim($track['track_title'] ?? ''))) continue;
            $sanitized[] = [
                'type' => sanitize_text_field($track['type'] ?? 'track'),
                'track_title' => sanitize_text_field($track['track_title']),
                'track_url' => esc_url_raw($track['track_url'] ?? ''),
            ];
        }
        update_post_meta($post_id, 'tracklist', $sanitized);
    } else {
        delete_post_meta($post_id, 'tracklist');
    }
});

// Enqueue Scripts & Styles
add_action('admin_enqueue_scripts', function($hook) {
    global $post;
    if ($hook === 'post-new.php' || $hook === 'post.php') {
        if ($post && $post->post_type === 'post') {
            // Enqueue admin.css (Create this file if it doesn't exist)
            wp_enqueue_style(
                'tracklist-css',
                get_theme_file_uri() . '/admin.css'
            );
            // Enqueue tracklist.js
            // Note: We added 'jquery-ui-sortable' to the dependency array
            wp_enqueue_script(
                'tracklist-js',
                get_theme_file_uri() . '/tracklist.js',
                ['jquery', 'jquery-ui-sortable'],
                '2.0',
                true
            );
        }
    }
});

// CO-AUTHORS FOR MULTIPLE USER ATTRIBUTION
// Add the Meta Box to the Post Editor
function add_multi_author_meta_box() {
    add_meta_box(
        'multi_author_box',         // Unique ID
        'Multiple Authors',         // Box Title
        'display_multi_author_box', // The function that draws the box
        'post',                     // Post type
        'side'                      // Where it appears (side bar)
    );
}
add_action('add_meta_boxes', 'add_multi_author_meta_box');
// Draw the list of users with checkboxes
function display_multi_author_box($post) {
    // Get all users
    $users = get_users();
    // Get the authors already saved for this post
    $saved_authors = get_post_meta($post->ID, '_multi_author_ids', true) ?: [];
    foreach ($users as $user) {
        $checked = in_array($user->ID, $saved_authors) ? 'checked' : '';
        echo '<label><input type="checkbox" name="multi_authors[]" value="' . $user->ID . '" ' . $checked . '> ' . esc_html($user->display_name) . '</label><br>';
    }
}
// Save the checkbox data when the post is saved
function save_multi_author_data($post_id) {
    if (isset($_POST['multi_authors'])) {
        // Sanitize and save the array of User IDs
        $author_ids = array_map('intval', $_POST['multi_authors']);
        update_post_meta($post_id, '_multi_author_ids', $author_ids);
    } else {
        // If no authors are checked, delete the record
        delete_post_meta($post_id, '_multi_author_ids');
    }
}
add_action('save_post', 'save_multi_author_data');
function get_multi_authors_list() {
    global $post;
    $author_ids = get_post_meta($post->ID, '_multi_author_ids', true);
    if (!empty($author_ids)) {
        $names = [];
        foreach ($author_ids as $id) {
            $user_info = get_userdata($id);
            $names[] = $user_info->display_name;
        }
        // Turns the array into a string: "Name 1, Name 2, and Name 3"
        return implode(', ', $names);
    }

    // Fallback to the default author if no multiple authors are selected
    return get_the_author();
}

// PAGE REDIRECT METABOX
// 1. Add the Meta Box to the Page Editor
add_action('add_meta_boxes', function() {
    add_meta_box(
        'page_redirect_meta_box',  // Unique ID
        'Page Redirect',           // Box Title
        'render_page_redirect_box',// The function that draws the box
        'page',                    // Post type (Pages only)
        'side',                    // Context (side bar)
        'high'                     // Priority
    );
});
// 2. Render the Metabox Content
function render_page_redirect_box($post) {
    // Retrieve existing values
    $is_active = get_post_meta($post->ID, '_redirect_active', true);
    $redirect_url = get_post_meta($post->ID, '_redirect_url', true);
    // Nonce for security
    wp_nonce_field('save_page_redirect_meta', 'page_redirect_nonce');
    ?>
    <p>
        <label>
            <input type="checkbox" name="redirect_active" value="1" <?php checked($is_active, '1'); ?> />
            <strong>Enable Redirect</strong>
        </label>
    </p>
    <p>
        <label for="redirect_url" style="display:block; margin-bottom:5px;">Redirect URL:</label>
        <input type="url" name="redirect_url" id="redirect_url" value="<?php echo esc_url($redirect_url); ?>" class="widefat" placeholder="https://..." />
        <span class="description" style="display:block; margin-top:5px; font-size:12px; color:#666;">Enter the full URL (including https://).</span>
    </p>
    <?php
}
// 3. Save the Data
add_action('save_post', function($post_id) {
    // Security checks
    if (!isset($_POST['page_redirect_nonce']) || !wp_verify_nonce($_POST['page_redirect_nonce'], 'save_page_redirect_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_page', $post_id)) {
        return;
    }

    // Save "Active" Checkbox (checkboxes are not sent if unchecked, so we check for existence)
    $is_active = isset($_POST['redirect_active']) ? '1' : '0';
    update_post_meta($post_id, '_redirect_active', $is_active);

    // Save URL
    if (isset($_POST['redirect_url'])) {
        update_post_meta($post_id, '_redirect_url', esc_url_raw($_POST['redirect_url']));
    }
});
// 4. Perform the Redirect
add_action('template_redirect', function() {
    // Only run on single pages
    if (!is_page()) {
        return;
    }
    global $post;
    // Check if redirect is active
    $is_active = get_post_meta($post->ID, '_redirect_active', true);
    $redirect_url = get_post_meta($post->ID, '_redirect_url', true);

    if ($is_active === '1' && !empty($redirect_url)) {
        wp_redirect($redirect_url, 301); // 301 = Permanent Redirect
        exit;
    }
});