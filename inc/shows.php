<?php
/**
 * Shows Custom Post Type
 * 
 * Private post type for internal show/episode references with tracklist functionality
 */

// =============================================================================
// REGISTER THE SHOWS POST TYPE
// =============================================================================

function register_shows_post_type() {
	$labels = array(
		'name' => 'Shows',
		'singular_name' => 'Show',
		'menu_name' => 'Shows',
		'name_admin_bar' => 'Show',
		'add_new' => 'Add New',
		'add_new_item' => 'Add New Show',
		'new_item' => 'New Show',
		'edit_item' => 'Edit Show',
		'view_item' => 'View Show',
		'all_items' => 'All Shows',
		'search_items' => 'Search Shows',
		'not_found' => 'No shows found.',
		'not_found_in_trash' => 'No shows found in Trash.',
	);

	$args = array(
		'labels' => $labels,
		'public' => false,
		'publicly_queryable' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_icon' => 'dashicons-playlist-audio',
		'query_var' => false,
		'rewrite' => false,
		'capability_type' => 'post',
		'has_archive' => false,
		'hierarchical' => false,
		'menu_position' => 21,
		'supports' => array('title', 'editor', 'thumbnail'),
		'show_in_rest' => false,  // CHANGED: Disable REST API to prevent Gutenberg
	);
	
	register_post_type('show', $args);
}
add_action('init', 'register_shows_post_type', 0);

// =============================================================================
// TRACKLIST METABOX FOR SHOWS
// =============================================================================

function add_show_tracklist_metabox() {
	add_meta_box(
		'tracklist_meta_box',
		'Tracklist/Timeline',
		'render_show_tracklist_metabox',
		'show',
		'normal',
		'default'
	);
}
add_action('add_meta_boxes', 'add_show_tracklist_metabox');

function render_show_tracklist_metabox($post) {
	// Get existing data
	$tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
	wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
	?>
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
		<div style="font-size: 13px; color: #666;">
			<strong>Total Duration:</strong> <span id="total-duration">0:00</span>
		</div>
	</div>
	<div id="tracklist-container">
		<?php
		// Loop through saved tracks/spacers
		foreach ($tracklist as $i => $item):
			$type = $item['type'] ?? 'track';
			$duration = $item['duration'] ?? '';
		?>
			<div class="track-row <?= $type === 'spacer' ? 'is-spacer' : '' ?>">
				<span class="drag-handle" title="Drag to reorder">|||</span>
				<input type="hidden" name="tracklist[<?= $i ?>][type]" value="<?= esc_attr($type) ?>" />
				<input type="text"
						name="tracklist[<?= $i ?>][track_title]"
						placeholder="<?= $type === 'spacer' ? 'Segment (In The Cinema/The Pin Drop/Walking On Thin Ice/One Up P1-2)' : 'Artist/Group - Track Title' ?>"
						value="<?= esc_attr($item['track_title']) ?>"
						class="track-title-input" />
				<input type="url"
						name="tracklist[<?= $i ?>][track_url]"
						placeholder="https://..."
						value="<?= esc_url($item['track_url'] ?? '') ?>"
						class="track-url-input"
						style="<?= $type === 'spacer' ? 'display:none;' : '' ?>" />
				<input type="text"
						name="tracklist[<?= $i ?>][duration]"
						placeholder="3:45"
						value="<?= esc_attr($duration) ?>"
						class="track-duration-input"
						style="width: 60px; <?= $type === 'spacer' ? 'display:none;' : '' ?>" />
				<button type="button" class="fetch-duration button" style="<?= $type === 'spacer' ? 'display:none;' : '' ?>">Grab Duration</button>
				<button type="button" class="remove-track button">Remove</button>
			</div>
		<?php endforeach; ?>
	</div>
	<div style="margin-top: 10px;">
		<button type="button" class="add-track button button-primary">Track</button>
		<button type="button" class="add-spacer button">Spacer</button>
	</div>
	<?php
}

// =============================================================================
// SAVE TRACKLIST DATA
// =============================================================================

function save_show_tracklist_meta($post_id) {
	// Security checks
	if (!isset($_POST['tracklist_meta_nonce']) || 
		!wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta')) {
		return;
	}

	// Check autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Check permissions
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	// Save tracklist data
	if (!empty($_POST['tracklist']) && is_array($_POST['tracklist'])) {
		$sanitized = [];
		foreach ($_POST['tracklist'] as $track) {
			if (empty(trim($track['track_title'] ?? ''))) continue;
			$sanitized[] = [
				'type' => sanitize_text_field($track['type'] ?? 'track'),
				'track_title' => sanitize_text_field($track['track_title']),
				'duration' => sanitize_text_field($track['duration'] ?? ''),
				'track_url' => esc_url_raw($track['track_url'] ?? ''),
			];
		}
		update_post_meta($post_id, 'tracklist', $sanitized);
	} else {
		delete_post_meta($post_id, 'tracklist');
	}
}
add_action('save_post_show', 'save_show_tracklist_meta');

// =============================================================================
// ENQUEUE SCRIPTS FOR SHOWS
// =============================================================================

function enqueue_show_scripts($hook) {
	global $post;
	
	// Only load on show edit screens
	if (($hook === 'post-new.php' || $hook === 'post.php') && 
		$post && $post->post_type === 'show') {
		
		// Enqueue tracklist.js
		wp_enqueue_script(
			'tracklist-js',
			get_theme_file_uri() . '/js/tracklist.js',
			['jquery', 'jquery-ui-sortable'],
			'2.1',  // Version bump to clear cache
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'enqueue_show_scripts', 20);