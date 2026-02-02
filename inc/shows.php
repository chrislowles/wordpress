<?php
/**
 * Shows Custom Post Type & Global Tracklist Manager
 * * Handles:
 * 1. 'Show' CPT Registration
 * 2. Shared Tracklist Rendering Logic (Refactored)
 * 3. Local Tracklist (Post Meta)
 * 4. Global Tracklist (Options API + Dashboard/Sidebar Widgets)
 * 5. Heartbeat Locking & AJAX Saving for Global List
 */

// =============================================================================
// 1. REGISTER THE SHOWS POST TYPE
// =============================================================================

add_action('init', function() {
	register_post_type('show', array(
		'labels' => array(
			'name' => 'Shows',
			'singular_name' => 'Show',
			'menu_name' => 'Shows',
			'all_items' => 'All Shows',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New Show',
			'edit_item' => 'Edit Show',
			'view_item' => 'View Show',
			'search_items' => 'Search Shows',
			'not_found' => 'No shows found.',
		),
		'public' => true,
		'publicly_queryable' => true,
		'exclude_from_search' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_icon' => 'dashicons-playlist-audio',
		'supports' => array('title', 'editor', 'markup_markdown', 'thumbnail'),
		'show_in_rest' => true,
		'menu_position' => 21,
	));
}, 0);

// =============================================================================
// 2. SHARED RENDER LOGIC (REFACTORED)
// =============================================================================

/**
 * Renders a tracklist editor interface.
 * * @param array  $tracks    The array of track data.
 * @param string $scope     'post' (saves with post) or 'global' (saves via AJAX).
 * @param bool   $locked    Whether the widget is locked by another user (Global only).
 * @param string $owner     Name of the user holding the lock.
 * @param bool   $show_transfer_buttons  Whether to show transfer buttons (only on show edit screen)
 */
function render_tracklist_editor_html($tracks, $scope = 'post', $locked = false, $owner = '', $show_transfer_buttons = false) {
	$tracks = is_array($tracks) ? $tracks : [];
	// Prefix input names to avoid collision between Global widget and Post metabox on the same screen
	$name_prefix = ($scope === 'global') ? 'global_tracklist' : 'tracklist';
	$wrapper_class = ($scope === 'global') ? 'tracklist-wrapper is-global' : 'tracklist-wrapper is-local';
	
	// If locked, disable inputs
	$disabled_attr = $locked ? 'disabled' : '';
	?>
	
	<div class="<?php echo esc_attr($wrapper_class); ?>" data-scope="<?php echo esc_attr($scope); ?>" style="position: relative;">
		
		<?php if ($scope === 'global'): ?>
			<div class="tracklist-lock-overlay <?php echo $locked ? '' : 'hidden'; ?>">
				<div class="lock-message">
					<span class="dashicons dashicons-lock"></span>
					<span class="lock-owner-name"><?php echo esc_html($owner); ?></span> is editing this.
				</div>
			</div>
		<?php endif; ?>

		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
			<div style="font-size: 13px; color: #666;">
				<strong>Total:</strong> <span class="total-duration-display">0:00</span>
			</div>
			<?php if ($scope === 'global'): ?>
				<div class="global-controls">
					<span class="spinner-inline global-spinner" style="float:none; margin: 0 5px 0 0;"></span>
					<button type="button" class="button button-primary global-save-btn" <?php echo $disabled_attr; ?>>Save</button>
				</div>
			<?php endif; ?>
		</div>

		<div class="tracklist-items">
			<?php foreach ($tracks as $i => $item): 
				$type = $item['type'] ?? 'track';
				$duration = $item['duration'] ?? '';
				$title = $item['track_title'] ?? '';
				$url = $item['track_url'] ?? '';
				$link_to_section = $item['link_to_section'] ?? false;
			?>
				<div class="track-row <?php echo $type === 'spacer' ? 'is-spacer' : ''; ?>">
					<span class="drag-handle" title="Drag to reorder">|||</span>
					<input type="hidden" 
						   name="<?php echo $name_prefix; ?>[<?php echo $i; ?>][type]" 
						   value="<?php echo esc_attr($type); ?>" 
						   class="track-type" />
					
					<input type="text"
						   name="<?php echo $name_prefix; ?>[<?php echo $i; ?>][track_title]"
						   placeholder="<?php echo $type === 'spacer' ? 'Segment Title...' : 'Artist - Track'; ?>"
						   value="<?php echo esc_attr($title); ?>"
						   class="track-title-input" 
						   <?php echo $disabled_attr; ?> />
					
					<input type="url"
						   name="<?php echo $name_prefix; ?>[<?php echo $i; ?>][track_url]"
						   placeholder="https://..."
						   value="<?php echo esc_url($url); ?>"
						   class="track-url-input"
						   style="<?php echo $type === 'spacer' ? 'display:none;' : ''; ?>" 
						   <?php echo $disabled_attr; ?> />
					
					<input type="text"
						   name="<?php echo $name_prefix; ?>[<?php echo $i; ?>][duration]"
						   placeholder="3:45"
						   value="<?php echo esc_attr($duration); ?>"
						   class="track-duration-input"
						   style="width: 60px; <?php echo $type === 'spacer' ? 'display:none;' : ''; ?>" 
						   <?php echo $disabled_attr; ?> />

					<label class="link-checkbox-label" 
						   style="<?php echo $type === 'spacer' ? '' : 'display:none;'; ?>" 
						   title="Link this spacer to a section in the body content (Make sure the header content is exact)">
						<input type="checkbox" 
							   name="<?php echo $name_prefix; ?>[<?php echo $i; ?>][link_to_section]"
							   class="link-to-section-checkbox"
							   value="1"
							   <?php checked($link_to_section, true); ?>
							   <?php echo $disabled_attr; ?> />
						Link
					</label>

					<?php if ($show_transfer_buttons): ?>
						<button type="button" 
								class="transfer-track button" 
								title="<?php echo $scope === 'global' ? 'Copy to Local' : 'Copy to Global'; ?>"
								data-target-scope="<?php echo $scope === 'global' ? 'post' : 'global'; ?>"
								style="<?php echo $type === 'spacer' ? 'display:none;' : ''; ?>" 
								<?php echo $disabled_attr; ?>>
							<?php echo $scope === 'global' ? 'Local' : 'Global'; ?>
						</button>
					<?php endif; ?>

					<button type="button" class="fetch-duration button" 
							style="<?php echo $type === 'spacer' ? 'display:none;' : ''; ?>" 
							<?php echo $disabled_attr; ?>>Grab</button>
					
					<button type="button" class="remove-track button" <?php echo $disabled_attr; ?>>Delete</button>
				</div>
			<?php endforeach; ?>
		</div>

		<div style="margin-top: 10px;">
			<button type="button" class="add-track button" <?php echo $disabled_attr; ?>>+ Track</button>
			<button type="button" class="add-spacer button" <?php echo $disabled_attr; ?>>+ Spacer</button>
			
			<?php if ($show_transfer_buttons): ?>
				<?php if ($scope === 'post'): ?>
					<button type="button" class="copy-all-to-global button" title="Copy all items to Global Tracklist/Timeline" <?php echo $disabled_attr; ?>>Global</button>
				<?php else: ?>
					<button type="button" class="copy-all-to-local button" title="Copy all items to Local Tracklist/Timeline" <?php echo $disabled_attr; ?>>Local</button>
				<?php endif; ?>
			<?php endif; ?>
			
			<span class="youtube-playlist-container"></span>
		</div>
	</div>
	<?php
}

// =============================================================================
// 3. REGISTER META BOXES & WIDGETS
// =============================================================================

add_action('add_meta_boxes', function() {
	// A. STANDARD META BOX (Per Post) - Scope: 'post'
	add_meta_box(
		'tracklist_meta_box',
		'Show Tracklist (Local)',
		function($post) {
			$tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
			wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
			// Render using shared function - show transfer buttons on edit screen
			render_tracklist_editor_html($tracklist, 'post', false, '', true);
		},
		'show',
		'normal',
		'high'
	);

	// B. GLOBAL REFERENCE WIDGET (Sidebar on Edit Screen) - Scope: 'global'
	add_meta_box(
		'global_tracklist_widget',
		'Global Tracklist / Timeline',
		'render_global_tracklist_widget',
		'show',
		'side',
		'default'
	);
});

// C. DASHBOARD WIDGET - Scope: 'global'
add_action('wp_dashboard_setup', function() {
	wp_add_dashboard_widget(
		'global_tracklist_dashboard',
		'Global Tracklist / Timeline',
		'render_global_tracklist_widget'
	);
});

/**
 * Callback for both Dashboard and Sidebar Global Widgets
 */
function render_global_tracklist_widget() {
	$tracklist = get_option('global_tracklist_data', []);
	
	// Check Locking Status
	$user_id = get_current_user_id();
	$lock = get_transient('global_tracklist_lock'); // ['user_id' => 123, 'time' => 1234]
	
	$is_locked = false;
	$owner_name = '';

	if ($lock && $lock['user_id'] != $user_id) {
		$is_locked = true;
		$user_info = get_userdata($lock['user_id']);
		$owner_name = $user_info ? $user_info->display_name : 'Another user';
	}

	// Only show transfer buttons if we're on a show edit screen
	global $pagenow, $post;
	$show_transfer_buttons = (
		($pagenow === 'post.php' || $pagenow === 'post-new.php') && 
		isset($post) && 
		$post->post_type === 'show'
	);

	render_tracklist_editor_html($tracklist, 'global', $is_locked, $owner_name, $show_transfer_buttons);
}

// =============================================================================
// 4. DATA SAVING HANDLERS
// =============================================================================

// A. LOCAL SAVE (Hooked to Post Save)
add_action('save_post_show', function($post_id) {
	// Security: Nonce check
	if (!isset($_POST['tracklist_meta_nonce']) || !wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;

	// Save 'tracklist' inputs (ignore 'global_tracklist')
	if (isset($_POST['tracklist']) && is_array($_POST['tracklist'])) {
		$sanitized = [];
		foreach ($_POST['tracklist'] as $track) {
			// Basic filtering
			if (empty($track['track_title']) && $track['type'] !== 'spacer') continue; 
			
			$sanitized[] = [
				'type' => sanitize_text_field($track['type'] ?? 'track'),
				'track_title' => sanitize_text_field($track['track_title']),
				'duration' => sanitize_text_field($track['duration'] ?? ''),
				'track_url' => esc_url_raw($track['track_url'] ?? ''),
				'link_to_section' => isset($track['link_to_section']) && $track['link_to_section'] == '1',
			];
		}
		update_post_meta($post_id, 'tracklist', $sanitized);
	} else {
		delete_post_meta($post_id, 'tracklist');
	}
});

// B. GLOBAL SAVE (AJAX)
add_action('wp_ajax_save_global_tracklist', function() {
	check_ajax_referer('global_tracklist_nonce', 'nonce');
	
	$user_id = get_current_user_id();
	$lock = get_transient('global_tracklist_lock');

	// Enforce Lock
	if ($lock && $lock['user_id'] != $user_id) {
		wp_send_json_error(['message' => 'Locked by another user.']);
	}

	if (isset($_POST['data']) && is_array($_POST['data'])) {
		$sanitized = [];
		foreach ($_POST['data'] as $track) {
			$sanitized[] = [
				'type' => sanitize_text_field($track['type'] ?? 'track'),
				'track_title' => sanitize_text_field($track['track_title']),
				'duration' => sanitize_text_field($track['duration'] ?? ''),
				'track_url' => esc_url_raw($track['track_url'] ?? ''),
				'link_to_section' => isset($track['link_to_section']) && $track['link_to_section'] == '1',
			];
		}
		update_option('global_tracklist_data', $sanitized);
		wp_send_json_success(['message' => 'Global list saved.']);
	} else {
		// Empty list
		update_option('global_tracklist_data', []);
		wp_send_json_success(['message' => 'Global list cleared.']);
	}
});

// =============================================================================
// 5. HEARTBEAT LOCKING LOGIC
// =============================================================================

add_filter('heartbeat_received', function($response, $data) {
	// Check if our specific heartbeat key exists
	if (empty($data['global_tl_check'])) {
		return $response;
	}

	$user_id = get_current_user_id();
	$lock = get_transient('global_tracklist_lock');
	$is_editing = !empty($data['global_tl_editing']); // JS tells us if user is typing

	// 1. Locked by someone else
	if ($lock && $lock['user_id'] != $user_id) {
		$user_info = get_userdata($lock['user_id']);
		$response['global_tl_status'] = 'locked';
		$response['global_tl_owner'] = $user_info ? $user_info->display_name : 'Someone';
		// Send latest data to sync
		$response['global_tl_data'] = get_option('global_tracklist_data', []);
	} 
	// 2. Free or Locked by me
	else {
		if ($is_editing) {
			// Renew lock (30s)
			set_transient('global_tracklist_lock', ['user_id' => $user_id, 'time' => time()], 30);
			$response['global_tl_status'] = 'owned';
		} else {
			$response['global_tl_status'] = 'free';
		}
	}

	return $response;
}, 10, 2);


// =============================================================================
// 6. ENQUEUE SCRIPTS
// =============================================================================

add_action('admin_enqueue_scripts', function($hook) {
	// Load on Dashboard or Show Edit
	$is_dashboard = ($hook === 'index.php');
	$is_show_edit = ($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'show';

	if ($is_dashboard || $is_show_edit) {
		wp_enqueue_script(
			'tracklist-js',
			get_theme_file_uri() . '/js/tracklist.js',
			['jquery', 'jquery-ui-sortable', 'heartbeat'],
			'3.3', // Bumped version
			true
		);

		// Pass Nonce and User ID
		wp_localize_script('tracklist-js', 'tracklistSettings', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('global_tracklist_nonce'),
			'user_id' => get_current_user_id()
		]);
	}
}, 20);

// =============================================================================
// 7. DISPLAY FILTER - AUTO-ADD IDs TO HEADINGS (DISPLAY ONLY, NOT SAVED)
// =============================================================================

/**
 * Automatically add ID attributes to h1-h6 tags when displaying content
 * Only adds IDs if they don't already have one
 * This runs on display only and does NOT modify the saved post content
 */
add_filter('the_content', function($content) {
	// Only run on show posts on the frontend
	if (!is_singular('show') || is_admin()) {
		return $content;
	}

	// Use DOMDocument to parse HTML safely
	$dom = new DOMDocument();
	
	// Suppress errors from malformed HTML
	libxml_use_internal_errors(true);
	
	// Load HTML with UTF-8 encoding
	$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	
	libxml_clear_errors();

	// Find all heading tags (h1-h6)
	$headings = [];
	for ($i = 1; $i <= 6; $i++) {
		$tags = $dom->getElementsByTagName('h' . $i);
		foreach ($tags as $tag) {
			$headings[] = $tag;
		}
	}

	// Process each heading
	foreach ($headings as $heading) {
		// Only add ID if it doesn't already have one
		if (!$heading->hasAttribute('id')) {
			// Get the text content
			$text = $heading->textContent;
			
			// Generate slug-like ID
			$id = sanitize_title($text);
			
			// Set the ID attribute
			$heading->setAttribute('id', $id);
		}
	}

	// Return the modified HTML (for display only, not saved to DB)
	return $dom->saveHTML();
}, 10);