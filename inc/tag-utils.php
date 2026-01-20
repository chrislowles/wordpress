<?php

// Working with tags as a central organizer.

// Comma-seperated parameter for prefilling tags on new posts: /wp-admin/post-new.php?prefill_tags=technology,news
add_action('save_post', function( $post_id, $post, $update ) {
	// 1. Check if we are in the admin area and the parameters exist
	if ( !is_admin() || !isset( $_GET['prefill_tags'] ) ) {
		return;
	}
	// 2. Ensure this is a new 'auto-draft' being created. We do not want to overwrite tags on existing posts being updated.
	if ( $post->post_status !== 'auto-draft' ) {
		return;
	}
	// 3. Check permissions (optional but recommended)
	if ( !current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// 4. Sanitize and Process the tags. We expect a comma-separated list like ?prefill_tags=News,Events
	$tags_input = sanitize_text_field( $_GET['prefill_tags'] );
	if ( !empty( $tags_input ) ) {
		// Convert the string into an array
		$tags_array = explode( ',', $tags_input );
		// 5. Set the tags (This works for the 'post_tag' taxonomy)
		// This will verify the tags exist, or create them if they don't.
		wp_set_object_terms( $post_id, $tags_array, 'post_tag' );
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
		$safe_id = sanitize_title( $label );
		$wp_admin_bar->add_node(
			array(
				'parent' => 'new-content', // This targets the standard "+ New" dropdown
				'id' => 'quick-post-' . $safe_id,
				'title' => $label,
				'href' => admin_url( 'post-new.php?prefill_tags=' . $tags )
			)
		);
	}
}, 90); // Priority 90 ensures they appear at the bottom of the list