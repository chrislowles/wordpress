<?php
/**
 * Agenda Scratchpad
 * Adds a global scratchpad to Dashboard and Post Edit screens with locking to prevent conflicts.
 */

// 1. Register the Widgets (Dashboard & Meta Box)
add_action('admin_init', function() {
	// Add to Dashboard
	add_action('wp_dashboard_setup', function() {
		wp_add_dashboard_widget(
			'agenda_scratchpad_widget',
			'Agenda Scratchpad',
			'render_agenda_scratchpad'
		);
	});

	// Add to Show Edit Screens ONLY
	add_action('add_meta_boxes', function() {
		$screens = ['show']; 
		foreach ($screens as $screen) {
			add_meta_box(
				'agenda_scratchpad_metabox',
				'Agenda Scratchpad',
				'render_agenda_scratchpad',
				$screen,
				'side', // Position in the sidebar
				'high'
			);
		}
	});
});

// 2. Render the Widget HTML (The visual part)
function render_agenda_scratchpad() {
	$content = get_option('agenda_scratchpad_content', '');
	$current_user = wp_get_current_user();
	?>
	<div id="agenda-wrapper" style="position: relative;">
		<div id="agenda-lock-overlay" class="hidden">
			<div class="lock-message">
				<span class="dashicons dashicons-lock"></span>
				<span id="lock-owner-name">Another user</span> is editing this.
			</div>
		</div>

		<textarea id="agenda-content" class="widefat" rows="10" placeholder="Type notes here..."><?php echo esc_textarea($content); ?></textarea>
		
		<div class="agenda-controls">
			<button type="button" id="agenda-save" class="button button-primary">Save Agenda</button>
			<span id="agenda-status" class="spinner-inline"></span>
		</div>
	</div>
	<?php
}

// 3. Enqueue JavaScript & Pass Data
add_action('admin_enqueue_scripts', function($hook) {
	// Only load on dashboard or edit screens
	if ('index.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}

	wp_enqueue_script(
		'agenda-js',
		get_stylesheet_directory_uri() . '/js/agenda.js',
		['jquery', 'heartbeat'], // Dependency on Heartbeat is crucial
		'1.0.0',
		true
	);

	// Pass data to JS (Current User ID)
	wp_localize_script('agenda-js', 'agendaSettings', [
		'user_id' => get_current_user_id(),
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('agenda_save_nonce')
	]);
});

// 4. Handle Heartbeat (The Locking System)
add_filter('heartbeat_received', function($response, $data) {
	// If we aren't sending agenda data, just return
	if (empty($data['agenda_check'])) {
		return $response;
	}

	$user_id = get_current_user_id();
	$lock = get_transient('agenda_scratchpad_lock'); // Returns array('user_id' => 123, 'time' => 123456) or false
	$is_editing = !empty($data['agenda_is_editing']); // JS tells us if the user is actively typing

	// LOGIC:
	// 1. If Locked by someone else: Tell JS "Locked"
	// 2. If Locked by me (or free) AND I am editing: Renew Lock, Tell JS "Owned"
	// 3. If Free AND I am NOT editing: Tell JS "Free"

	if ($lock && $lock['user_id'] != $user_id) {
		// It is locked by someone else
		$user_info = get_userdata($lock['user_id']);
		$response['agenda_status'] = 'locked';
		$response['agenda_owner'] = $user_info ? $user_info->display_name : 'Another user';
		
		// Optional: Send the latest content so the viewer sees updates
		$response['agenda_content'] = get_option('agenda_scratchpad_content', '');
	} else {
		// It is free, or locked by me
		if ($is_editing) {
			// I am editing, so set/renew the lock for 30 seconds (Heartbeat usually runs every 15s)
			set_transient('agenda_scratchpad_lock', ['user_id' => $user_id, 'time' => time()], 30);
			$response['agenda_status'] = 'owned';
		} else {
			// I am idle. If I had the lock, let it expire naturally (or we could clear it here).
			$response['agenda_status'] = 'free';
		}
	}

	return $response;
}, 10, 2);

// 5. Handle Save (AJAX)
add_action('wp_ajax_save_agenda', function() {
	check_ajax_referer('agenda_save_nonce', 'nonce');
	
	$user_id = get_current_user_id();
	$lock = get_transient('agenda_scratchpad_lock');

	// Double check lock before saving
	if ($lock && $lock['user_id'] != $user_id) {
		wp_send_json_error(['message' => 'Locked by another user.']);
	}

	if (isset($_POST['content'])) {
		update_option('agenda_scratchpad_content', wp_kses_post($_POST['content']));
		wp_send_json_success(['message' => 'Saved!']);
	}
});