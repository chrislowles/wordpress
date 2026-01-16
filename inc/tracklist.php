<?php

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
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
				<div style="font-size: 13px; color: #666;">
					<strong>Total Duration:</strong> <span id="total-duration">0:00</span>
				</div>
			</div>
			<div id="tracklist-container">
				<?php 
				// 2. Loop through saved tracks/spacers
				foreach ($tracklist as $i => $item): 
					$type = $item['type'] ?? 'track'; 
					$duration = $item['duration'] ?? '';
				?>
					<div class="track-row <?= $type === 'spacer' ? 'is-spacer' : '' ?>">
						<span class="drag-handle" title="Drag to reorder">|||</span>
						<input type="hidden" name="tracklist[<?= $i ?>][type]" value="<?= esc_attr($type) ?>" />
						<input type="text"
							   name="tracklist[<?= $i ?>][track_title]"
							   placeholder="<?= $type === 'spacer' ? '[In The Cinema/The Pin Drop/Walking On Thin Ice/One Up]' : 'Artist/Group - Track Title' ?>"
							   value="<?= esc_attr($item['track_title']) ?>"
							   class="track-title-input" />
						<input type="text"
							   name="tracklist[<?= $i ?>][duration]"
							   placeholder="3:45"
							   value="<?= esc_attr($duration) ?>"
							   class="track-duration-input"
							   style="width: 60px; <?= $type === 'spacer' ? 'display:none;' : '' ?>" />
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
				'duration' => sanitize_text_field($track['duration'] ?? ''),
				'track_url' => esc_url_raw($track['track_url'] ?? ''),
			];
		}
		update_post_meta($post_id, 'tracklist', $sanitized);
	} else {
		delete_post_meta($post_id, 'tracklist');
	}
});