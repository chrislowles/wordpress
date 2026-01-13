<?php
/**
 * Custom Redirect Manager
 * Allows reservation of URL paths (e.g., /merch) to redirect to internal or external URLs.
 */

// 1. Register the "Redirect" Post Type
add_action('init', function() {
    register_post_type('redirect_link', [
        'labels' => [
            'name'          => 'Redirects',
            'singular_name' => 'Redirect',
            'add_new_item'  => 'Add New Redirect',
            'edit_item'     => 'Edit Redirect',
            'search_items'  => 'Search Redirects',
        ],
        'public'      => false, // Not publicly queryable via ?post_type=redirect_link
        'show_ui'     => true,  // Show in Admin
        'menu_icon'   => 'dashicons-randomize', // A shuffle/redirect icon
        'supports'    => ['title'], // Title acts as an internal Label
        'taxonomies'  => ['post_tag'], // Use standard Tags
        'menu_position' => 20,
    ]);
});

// 2. Add Meta Boxes for Input Fields
add_action('add_meta_boxes', function() {
    add_meta_box(
        'redirect_details',
        'Redirect Configuration',
        function($post) {
            $path   = get_post_meta($post->ID, '_redirect_path', true);
            $target = get_post_meta($post->ID, '_redirect_target', true);
            $desc   = get_post_meta($post->ID, '_redirect_desc', true);
            wp_nonce_field('save_redirects', 'redirect_nonce');
            ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Redirect Path</label>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <code><?php echo home_url('/'); ?></code>
                        <input type="text" name="redirect_path" value="<?php echo esc_attr($path); ?>" placeholder="example-path" style="width: 100%; max-width: 400px;" />
                    </div>
                    <p class="description">Enter the path without a leading slash.</p>
                </div>

                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Target URL</label>
                    <input type="url" name="redirect_target" value="<?php echo esc_attr($target); ?>" placeholder="https://..." class="widefat" />
                </div>

                <div>
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Internal Description</label>
                    <textarea name="redirect_desc" class="widefat" rows="3"><?php echo esc_textarea($desc); ?></textarea>
                </div>
            </div>
            <?php
        },
        'redirect_link',
        'normal',
        'high'
    );
});

// 3. Save Data
add_action('save_post', function($post_id) {
    if (!isset($_POST['redirect_nonce']) || !wp_verify_nonce($_POST['redirect_nonce'], 'save_redirects')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    // Sanitize and Save Path (Remove slashes just in case)
    if (isset($_POST['redirect_path'])) {
        $clean_path = trim(sanitize_text_field($_POST['redirect_path']), '/');
        update_post_meta($post_id, '_redirect_path', $clean_path);
    }
    
    // Save Target
    if (isset($_POST['redirect_target'])) {
        update_post_meta($post_id, '_redirect_target', esc_url_raw($_POST['redirect_target']));
    }

    // Save Description
    if (isset($_POST['redirect_desc'])) {
        update_post_meta($post_id, '_redirect_desc', sanitize_textarea_field($_POST['redirect_desc']));
    }
});

// 4. Custom Admin Columns (Viewable Archive)
add_filter('manage_redirect_link_posts_columns', function($columns) {
    // Reorder columns
    $new_columns = [
        'cb'              => $columns['cb'],
        'title'           => 'Label',
        'redirect_path'   => 'Reserved Path',
        'redirect_target' => 'Target URL',
        'redirect_desc'   => 'Description',
        'tags'            => 'Tags', // Use 'tags' key for standard tag column
    ];
    return $new_columns;
});

add_action('manage_redirect_link_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'redirect_path':
            $path = get_post_meta($post_id, '_redirect_path', true);
            echo '<code>/' . esc_html($path) . '</code>';
            break;
        case 'redirect_target':
            $target = get_post_meta($post_id, '_redirect_target', true);
            echo '<a href="' . esc_url($target) . '" target="_blank">' . esc_html($target) . '</a>';
            break;
        case 'redirect_desc':
            echo esc_html(get_post_meta($post_id, '_redirect_desc', true));
            break;
    }
}, 10, 2);

// 5. Make Meta Fields Searchable in Dashboard
add_filter('posts_join', function($join, $query) {
    global $wpdb;
    if (is_admin() && $query->is_main_query() && $query->is_search() && $query->get('post_type') === 'redirect_link') {
        $join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ";
    }
    return $join;
});

add_filter('posts_where', function($where, $query) {
    global $wpdb;
    if (is_admin() && $query->is_main_query() && $query->is_search() && $query->get('post_type') === 'redirect_link') {
        $search = esc_sql($query->get('s'));
        // Replace the standard Title search with a broader Title OR Meta search
        $where = preg_replace(
            "/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "({$wpdb->posts}.post_title LIKE $1) OR ({$wpdb->postmeta}.meta_value LIKE '%{$search}%')",
            $where
        );
    }
    return $where;
});

add_filter('posts_distinct', function($distinct, $query) {
    if (is_admin() && $query->is_main_query() && $query->is_search() && $query->get('post_type') === 'redirect_link') {
        return "DISTINCT";
    }
    return $distinct;
});

// 6. Execute the Redirect (Frontend)
add_action('template_redirect', function() {
    // Parse the current requested URI
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Safety: Don't hijack the homepage
    if (empty($request_path)) {
        return;
    }

    // Query for a redirect that matches this path
    $args = [
        'post_type'   => 'redirect_link',
        'meta_key'    => '_redirect_path',
        'meta_value'  => $request_path,
        'numberposts' => 1,
        'fields'      => 'ids',
    ];

    $redirects = get_posts($args);

    if (!empty($redirects)) {
        $target = get_post_meta($redirects[0], '_redirect_target', true);
        if ($target) {
            wp_redirect($target, 301); // 301 Permanent Redirect
            exit;
        }
    }
});