<?php

// Register Custom Post Type for Redirects
function register_redirects_post_type() {
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

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'menu_icon'          => 'dashicons-admin-links',
		'query_var'          => true,
		'rewrite'            => false, // We'll handle redirects manually
		'capability_type'    => 'post',
		'has_archive'        => false,
		'hierarchical'       => false,
		'menu_position'      => 20,
		'supports'           => array('title'),
		'taxonomies'         => array('post_tag'),
	);

	register_post_type('redirect', $args);
}
add_action('init', 'register_redirects_post_type');

// Add Meta Boxes for Redirect Fields
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

// Render Meta Box
function render_redirect_meta_box($post) {
	wp_nonce_field('save_redirect_meta', 'redirect_meta_nonce');
	
	$path = get_post_meta($post->ID, '_redirect_path', true);
	$url = get_post_meta($post->ID, '_redirect_url', true);
	$description = get_post_meta($post->ID, '_redirect_description', true);
	?>
	<table class="form-table">
		<tr>
			<th><label for="redirect_path">Path (without leading slash)</label></th>
			<td>
				<input type="text" id="redirect_path" name="redirect_path" value="<?php echo esc_attr($path); ?>" class="regular-text" placeholder="example-path" />
				<p class="description">Will redirect from: <?php echo esc_url(home_url('/')); ?><strong id="path-preview"><?php echo esc_html($path ?: 'example-path'); ?></strong></p>
			</td>
		</tr>
		<tr>
			<th><label for="redirect_url">Destination URL</label></th>
			<td>
				<input type="url" id="redirect_url" name="redirect_url" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="https://example.com" required />
				<p class="description">Full URL (internal or external) to redirect to</p>
			</td>
		</tr>
		<tr>
			<th><label for="redirect_description">Internal Description</label></th>
			<td>
				<textarea id="redirect_description" name="redirect_description" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
				<p class="description">Internal notes about this redirect (not visible to visitors)</p>
			</td>
		</tr>
	</table>
	<script>
		document.getElementById('redirect_path').addEventListener('input', function(e) {
			document.getElementById('path-preview').textContent = e.target.value || 'example-path';
		});
	</script>
	<?php
}

// Save Meta Box Data
function save_redirect_meta($post_id) {
	if (!isset($_POST['redirect_meta_nonce']) || !wp_verify_nonce($_POST['redirect_meta_nonce'], 'save_redirect_meta')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	if (isset($_POST['redirect_path'])) {
		$path = sanitize_text_field($_POST['redirect_path']);
		$path = ltrim($path, '/'); // Remove leading slash if present
		update_post_meta($post_id, '_redirect_path', $path);
	}

	if (isset($_POST['redirect_url'])) {
		update_post_meta($post_id, '_redirect_url', esc_url_raw($_POST['redirect_url']));
	}

	if (isset($_POST['redirect_description'])) {
		update_post_meta($post_id, '_redirect_description', sanitize_textarea_field($_POST['redirect_description']));
	}
}
add_action('save_post_redirect', 'save_redirect_meta');

// Custom Columns for Redirect List
function redirect_custom_columns($columns) {
	$new_columns = array(
		'cb' => $columns['cb'],
		'title' => 'Title',
		'redirect_path' => 'Path',
		'redirect_url' => 'Destination URL',
		'redirect_description' => 'Description',
		'tags' => 'Tags',
		'date' => 'Date',
	);
	return $new_columns;
}
add_filter('manage_redirect_posts_columns', 'redirect_custom_columns');

// Populate Custom Columns
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

// Make Custom Columns Sortable
function redirect_sortable_columns($columns) {
	$columns['redirect_path'] = 'redirect_path';
	$columns['redirect_url'] = 'redirect_url';
	return $columns;
}
add_filter('manage_edit-redirect_sortable_columns', 'redirect_sortable_columns');

// Handle Sorting
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

// Add Search Functionality for Meta Fields
function redirect_search_join($join) {
	global $wpdb, $pagenow;

	if (is_admin() && 'edit.php' === $pagenow && isset($_GET['post_type']) && 'redirect' === $_GET['post_type'] && isset($_GET['s']) && !empty($_GET['s'])) {
		$join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ";
	}

	return $join;
}
add_filter('posts_join', 'redirect_search_join');

function redirect_search_where($where) {
	global $wpdb, $pagenow;

	if (is_admin() && 'edit.php' === $pagenow && isset($_GET['post_type']) && 'redirect' === $_GET['post_type'] && isset($_GET['s']) && !empty($_GET['s'])) {
		$search = esc_sql($wpdb->esc_like($_GET['s']));
		$where = preg_replace(
			"/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
			"({$wpdb->posts}.post_title LIKE $1) OR ({$wpdb->postmeta}.meta_key = '_redirect_path' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%') OR ({$wpdb->postmeta}.meta_key = '_redirect_url' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%') OR ({$wpdb->postmeta}.meta_key = '_redirect_description' AND {$wpdb->postmeta}.meta_value LIKE '%{$search}%')",
			$where
		);
	}

	return $where;
}
add_filter('posts_where', 'redirect_search_where');

function redirect_search_distinct($distinct) {
	global $pagenow;

	if (is_admin() && 'edit.php' === $pagenow && isset($_GET['post_type']) && 'redirect' === $_GET['post_type'] && isset($_GET['s']) && !empty($_GET['s'])) {
		return "DISTINCT";
	}

	return $distinct;
}
add_filter('posts_distinct', 'redirect_search_distinct');

// Handle the Actual Redirects
function handle_custom_redirects() {
	// Don't run in admin
	if (is_admin()) {
		return;
	}

	$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
	
	if (empty($request_path)) {
		return;
	}

	// Query for redirect with matching path
	$args = array(
		'post_type' => 'redirect',
		'posts_per_page' => 1,
		'meta_query' => array(
			array(
				'key' => '_redirect_path',
				'value' => $request_path,
				'compare' => '='
			)
		)
	);

	$redirect_query = new WP_Query($args);

	if ($redirect_query->have_posts()) {
		$redirect_query->the_post();
		$redirect_url = get_post_meta(get_the_ID(), '_redirect_url', true);
		
		if ($redirect_url) {
			wp_redirect($redirect_url, 301);
			exit;
		}
	}

	wp_reset_postdata();
}
add_action('template_redirect', 'handle_custom_redirects');

// Flush rewrite rules on theme activation
function redirects_flush_rewrites() {
	register_redirects_post_type();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'redirects_flush_rewrites');