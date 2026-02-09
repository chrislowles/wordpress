<?php
/**
 * Class: Post Update Notifier
 * Detects when posts are updated and notifies users viewing them in real-time.
 * Works across all post types, on edit screens, previews, and published posts.
 * Only active for logged-in users with edit permissions.
 */
class ChrisLowles_PostUpdateNotifier {

	public function __construct() {
		// Heartbeat handler
		add_filter('heartbeat_received', [$this, 'heartbeat_post_check'], 10, 2);
		
		// Enqueue scripts for both admin and frontend
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
	}

	/**
	 * Heartbeat handler for post update detection
	 * Checks if a post has been modified since the page was loaded
	 */
	public function heartbeat_post_check($response, $data) {
		if (empty($data['post_update_check'])) {
			return $response;
		}

		$check = $data['post_update_check'];
		$post_id = intval($check['post_id']);
		$last_modified = $check['last_modified'];

		if (!$post_id) {
			return $response;
		}

		$post = get_post($post_id);
		if (!$post) {
			return $response;
		}

		// Check if user has permission to edit this post
		if (!current_user_can('edit_post', $post_id)) {
			return $response;
		}

		// Compare the modified times
		$current_modified = $post->post_modified;
		
		if ($current_modified !== $last_modified) {
			// Post has been updated!
			$response['post_updated'] = true;
			$response['new_modified_time'] = $current_modified;
		}

		return $response;
	}

	/**
	 * Enqueue assets on admin edit screens
	 */
	public function enqueue_admin_assets($hook) {
		// Only on post edit screens
		if (!in_array($hook, ['post.php', 'post-new.php'])) {
			return;
		}

		global $post;
		if (!$post) {
			return;
		}

		// User must be able to edit this post
		if (!current_user_can('edit_post', $post->ID)) {
			return;
		}

		$this->enqueue_notifier_script(true, $post);
	}

	/**
	 * Enqueue assets on frontend (published posts and previews)
	 */
	public function enqueue_frontend_assets() {
		// Only on singular post pages
		if (!is_singular()) {
			return;
		}

		global $post;
		if (!$post) {
			return;
		}

		// User must be logged in and able to edit this post
		if (!is_user_logged_in() || !current_user_can('edit_post', $post->ID)) {
			return;
		}

		$this->enqueue_notifier_script(false, $post);
	}

	/**
	 * Enqueue the notifier script with appropriate settings
	 */
	private function enqueue_notifier_script($is_edit_screen, $post) {
		// Enqueue heartbeat
		wp_enqueue_script('heartbeat');

		// Enqueue our notifier script
		wp_enqueue_script(
			'post-update-notifier',
			get_stylesheet_directory_uri() . '/js/post-update-notifier.js',
			['jquery', 'heartbeat'],
			'1.0.0',
			true
		);

		// Pass data to the script
		wp_localize_script('post-update-notifier', 'postUpdateNotifier', [
			'postId' => $post->ID,
			'lastModified' => $post->post_modified,
			'isEditScreen' => $is_edit_screen,
			'postType' => get_post_type($post),
			'postTitle' => get_the_title($post)
		]);
	}
}