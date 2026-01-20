<?php
/**
 * Redirects Custom Post Type
 * 
 * This file handles everything for managing URL redirects:
 * - Registering the custom post type
 * - Creating the edit form fields
 * - Displaying redirects in the admin list
 * - Actually performing the redirects on the frontend
 */


// =============================================================================
// REGISTER THE REDIRECTS POST TYPE
// =============================================================================

function register_redirects_post_type() {
	// These are the labels that appear in the admin menu
	$labels = array(
		'name'                  => 'Redirects',
		'singular_name'         => 'Redirect',
		'menu_name'             => 'Redirects',
		'name_admin_bar'        => 'Redirect',
		'add_new'               => 'Add New',
		'add_new_item'          => 'Add New Redirect',
		'new_item'              => 'New Redirect',
		'edit_item'             => 'Edit Redirect',
		'view_item'             => 'View Redirect',
		'all_items'             => 'All Redirects',
		'search_items'          => 'Search Redirects',
		'not_found'             => 'No redirects found.',
		'not_found_in_trash'    => 'No redirects found in Trash.',
	);

	// Configuration for the post type
	$args = array(
		'labels'             => $labels,
		'public'             => false,             // Changed to false - redirects shouldn't be public
		'publicly_queryable' => false,             // Changed to false - we handle queries ourselves
		'show_ui'            => true,              // Show admin interface
		'show_in_menu'       => true,              // Show in admin menu
		'menu_icon'          => 'dashicons-admin-links',
		'query_var'          => false,             // Changed to false - no query var needed
		'rewrite'            => false,             // We handle URLs ourselves
		'capability_type'    => 'post',            // Use post permissions
		'has_archive'        => false,             // No archive page needed
		'hierarchical'       => false,             // Not hierarchical like pages
		'menu_position'      => 20,                // Position in admin menu
		'supports'           => array('title'),    // Only support title field
		'taxonomies'         => array('post_tag'), // Enable tags
	);
	register_post_type('redirect', $args);
}
add_action('init', 'register_redirects_post_type');

// =============================================================================
// CREATE THE EDIT FORM (META BOXES)
// =============================================================================

/**
 * Add the meta box to the redirect edit screen
 */
function add_redirect_meta_boxes() {
	add_meta_box(
		'redirect_details',
		'Redirect Details',
		'render_redirect_meta_box',
		'redirect',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'add_redirect_meta_boxes');


/**
 * Render the actual form fields inside the meta box
 */
function render_redirect_meta_box($post) {
	// Security field to verify the form submission came from WordPress
	wp_nonce_field('save_redirect_meta', 'redirect_meta_nonce');
	
	// Get existing values from the database
	$path = get_post_meta($post->ID, '_redirect_path', true);
	$url = get_post_meta($post->ID, '_redirect_url', true);
	$description = get_post_meta($post->ID, '_redirect_description', true);
	?>
	<table class="form-table">
		<!-- PATH FIELD -->
		<tr>
			<th><label for="redirect_path">Path (without leading slash)</label></th>
			<td>
				<input
					type="text"
					id="redirect_path"
					name="redirect_path"
					value="<?php echo esc_attr($path); ?>"
					class="widefat"
					placeholder="example-path"
				/>
				<p class="description">
					Will redirect from: <?php echo esc_url(home_url('/')); ?><strong id="path-preview"><?php echo esc_html($path ?: 'example-path'); ?></strong>
				</p>
			</td>
		</tr>

		<!-- DESTINATION URL FIELD -->
		<tr>
			<th><label for="redirect_url">Destination URL</label></th>
			<td>
				<input
					type="url"
					id="redirect_url"
					name="redirect_url"
					value="<?php echo esc_attr($url); ?>"
					class="widefat"
					placeholder="https://youtu.be/dQw4w9WgXcQ"
					required
				/>
				<p class="description">Full URL (internal or external) to redirect to</p>
			</td>
		</tr>

		<!-- INTERNAL DESCRIPTION FIELD -->
		<tr>
			<th><label for="redirect_description">Internal Description</label></th>
			<td>
				<textarea
					id="redirect_description"
					name="redirect_description"
					rows="3"
					class="widefat"
				><?php echo esc_textarea($description); ?></textarea>
				<p class="description">Internal notes about this redirect (not visible to visitors)</p>
			</td>
		</tr>
	</table>

	<!-- LIVE PREVIEW: Update the path preview as you type -->
	<script>
		document.getElementById('redirect_path').addEventListener('input', function(e) {
			document.getElementById('path-preview').textContent = e.target.value || 'example-path';
		});
	</script>
	<?php
}


/**
 * Save the meta box data when the post is saved
 */
function save_redirect_meta($post_id) {
	// Security checks
	if (!isset($_POST['redirect_meta_nonce'])) {
		return;
	}
	
	if (!wp_verify_nonce($_POST['redirect_meta_nonce'], 'save_redirect_meta')) {
		return;
	}

	// Don't save during autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Check user has permission to edit
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	// Save the path (remove leading slash if they added one)
	if (isset($_POST['redirect_path'])) {
		$path = sanitize_text_field($_POST['redirect_path']);
		$path = ltrim($path, '/');
		update_post_meta($post_id, '_redirect_path', $path);
	}

	// Save the destination URL
	if (isset($_POST['redirect_url'])) {
		update_post_meta($post_id, '_redirect_url', esc_url_raw($_POST['redirect_url']));
	}

	// Save the description
	if (isset($_POST['redirect_description'])) {
		update_post_meta($post_id, '_redirect_description', sanitize_textarea_field($_POST['redirect_description']));
	}
}
add_action('save_post_redirect', 'save_redirect_meta');


// =============================================================================
// CUSTOMIZE THE ADMIN LIST VIEW
// =============================================================================

/**
 * Define which columns to show in the redirects list
 */
function redirect_custom_columns($columns) {
	$new_columns = array(
		'cb'                    => $columns['cb'],
		'title'                 => 'Title',
		'redirect_path'         => 'Path',
		'redirect_url'          => 'Destination URL',
		'redirect_description'  => 'Description',
		'tags'                  => 'Tags',
		'date'                  => 'Date',
	);
	return $new_columns;
}
add_filter('manage_redirect_posts_columns', 'redirect_custom_columns');


/**
 * Fill in the custom columns with data
 */
function redirect_custom_column_content($column, $post_id) {
	switch ($column) {
		case 'redirect_path':
			$path = get_post_meta($post_id, '_redirect_path', true);
			if ($path) {
				echo '<code>/' . esc_html($path) . '</code>';
			} else {
				echo '—';
			}
			break;

		case 'redirect_url':
			$url = get_post_meta($post_id, '_redirect_url', true);
			if ($url) {
				echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
			} else {
				echo '—';
			}
			break;

		case 'redirect_description':
			$description = get_post_meta($post_id, '_redirect_description', true);
			if ($description) {
				echo '<span title="' . esc_attr($description) . '">' . esc_html(wp_trim_words($description, 10)) . '</span>';
			} else {
				echo '—';
			}
			break;
	}
}
add_action('manage_redirect_posts_custom_column', 'redirect_custom_column_content', 10, 2);


/**
 * Make certain columns sortable by clicking the header
 */
function redirect_sortable_columns($columns) {
	$columns['redirect_path'] = 'redirect_path';
	$columns['redirect_url'] = 'redirect_url';
	return $columns;
}
add_filter('manage_edit-redirect_sortable_columns', 'redirect_sortable_columns');


/**
 * Handle the actual sorting when columns are clicked
 */
function redirect_orderby($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	$orderby = $query->get('orderby');

	if ('redirect_path' === $orderby) {
		$query->set('meta_key', '_redirect_path');
		$query->set('orderby', 'meta_value');
	}

	if ('redirect_url' === $orderby) {
		$query->set('meta_key', '_redirect_url');
		$query->set('orderby', 'meta_value');
	}
}
add_action('pre_get_posts', 'redirect_orderby');


// =============================================================================
// ENHANCED SEARCH FUNCTIONALITY
// =============================================================================

/**
 * Join the postmeta table so we can search custom fields
 */
function redirect_search_join($join) {
	global $wpdb, $pagenow;

	if (is_admin() && 
	    'edit.php' === $pagenow && 
	    isset($_GET['post_type']) && 
	    'redirect' === $_GET['post_type'] && 
	    isset($_GET['s']) && 
	    !empty($_GET['s'])) {
		$join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ";
	}

	return $join;
}
add_filter('posts_join', 'redirect_search_join');


/**
 * Modify the search query to include custom fields
 */
function redirect_search_where($where) {
	global $wpdb, $pagenow;

	if (is_admin() && 
	    'edit.php' === $pagenow && 
	    isset($_GET['post_type']) && 
	    'redirect' === $_GET['post_type'] && 
	    isset($_GET['s']) && 
	    !empty($_GET['s'])) {
		
		$search = esc_sql($wpdb->esc_like($_GET['s']));
		
		$where = preg_replace(
			"/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
			"({$wpdb->posts}.post_title LIKE $1) " .
			"OR ({$wpdb->postmeta}.meta_key = '_redirect_path' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%') " .
			"OR ({$wpdb->postmeta}.meta_key = '_redirect_url' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%') " .
			"OR ({$wpdb->postmeta}.meta_key = '_redirect_description' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%')",
			$where
		);
	}

	return $where;
}
add_filter('posts_where', 'redirect_search_where');


/**
 * Make sure we don't get duplicate results from the join
 */
function redirect_search_distinct($distinct) {
	global $pagenow;

	if (is_admin() && 
	    'edit.php' === $pagenow && 
	    isset($_GET['post_type']) && 
	    'redirect' === $_GET['post_type'] && 
	    isset($_GET['s']) && 
	    !empty($_GET['s'])) {
		return "DISTINCT";
	}

	return $distinct;
}
add_filter('posts_distinct', 'redirect_search_distinct');


// =============================================================================
// PERFORM THE ACTUAL REDIRECTS ON THE FRONTEND
// =============================================================================

/**
 * Check if the current URL matches a redirect and perform it
 * This runs EARLY to catch requests before WordPress processes them
 */
function handle_custom_redirects() {
	// Don't run in admin area
	if (is_admin()) {
		return;
	}

	// Get the path they're trying to access
	$request_uri = $_SERVER['REQUEST_URI'];
	$request_path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
	
	// If they're on the homepage, do nothing
	if (empty($request_path)) {
		return;
	}

	// DEBUG: Temporarily log what we're looking for
	// Remove this after testing
	error_log('Redirect Debug - Looking for path: ' . $request_path);

	// Look for a redirect with this exact path
	$args = array(
		'post_type'      => 'redirect',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => '_redirect_path',
				'value'   => $request_path,
				'compare' => '='
			)
		),
		'no_found_rows'  => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$redirect_query = new WP_Query($args);

	// DEBUG: Log if we found anything
	error_log('Redirect Debug - Found posts: ' . ($redirect_query->have_posts() ? 'YES' : 'NO'));

	// If we found a matching redirect, perform it
	if ($redirect_query->have_posts()) {
		$redirect_query->the_post();
		$redirect_url = get_post_meta(get_the_ID(), '_redirect_url', true);
		
		// DEBUG: Log the URL we found
		error_log('Redirect Debug - Redirect URL: ' . ($redirect_url ?: 'EMPTY'));
		
		if ($redirect_url && !empty($redirect_url)) {
			wp_reset_postdata();
			// 301 = permanent redirect (good for SEO)
			wp_redirect($redirect_url, 301);
			exit;
		}
	}

	wp_reset_postdata();
}
// Use priority 1 to run very early in the template_redirect process
add_action('template_redirect', 'handle_custom_redirects', 1);


// =============================================================================
// ACTIVATION HELPER
// =============================================================================

/**
 * Flush rewrite rules when the theme is activated
 */
function redirects_flush_rewrites() {
	register_redirects_post_type();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'redirects_flush_rewrites');