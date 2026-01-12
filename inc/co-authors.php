<?php

// CO-AUTHORS FOR MULTIPLE USER ATTRIBUTION
// Add the Meta Box to the Post Editor
add_action('add_meta_boxes', function() {
	add_meta_box(
		'multi_author_box',         // Unique ID
		'Multiple Authors',         // Box Title
		function($post) {
			// Get all users
			$users = get_users();
			// Get the authors already saved for this post
			$saved_authors = get_post_meta($post->ID, '_multi_author_ids', true) ?: [];
			foreach ($users as $user) {
				$checked = in_array($user->ID, $saved_authors) ? 'checked' : '';
				echo '<label><input type="checkbox" name="multi_authors[]" value="' . $user->ID . '" ' . $checked . '> ' . esc_html($user->display_name) . '</label><br>';
			}
		}, // The function that draws the box
		'post',                     // Post type
		'side'                      // Where it appears (side bar)
	);
});

// Save the checkbox data when the post is saved
add_action('save_post', function($post_id) {
	if (isset($_POST['multi_authors'])) {
		// Sanitize and save the array of User IDs
		$author_ids = array_map('intval', $_POST['multi_authors']);
		update_post_meta($post_id, '_multi_author_ids', $author_ids);
	} else {
		// If no authors are checked, delete the record
		delete_post_meta($post_id, '_multi_author_ids');
	}
});

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