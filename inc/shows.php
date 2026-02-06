<?php
/**
 * Class: Shows Manager
 * Handles the 'Show' Custom Post Type, Tracklists, Cross-Post Transfer functionality and Passive migration to generic row inner fields.
 * 
 * REFACTORED: Uses generic field names and terminology for all row types
 * - Old: track_title, track_url, duration
 * - New: title, url, duration
 * - Migration: Automatically converts old format to new on save
 */
class ChrisLowles_Shows {

	public function __construct() {
		// CPT
		add_action('init', [$this, 'register_post_type'], 0);
		
		// Meta Boxes
		add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
		
		// Admin Notice
		add_action('admin_notices', [$this, 'show_admin_notice']);
		
		// Save Handlers
		add_action('save_post_show', [$this, 'save_tracklist']);
		
		// AJAX Handlers for cross-post transfer
		add_action('wp_ajax_get_show_posts', [$this, 'ajax_get_show_posts']);
		add_action('wp_ajax_get_show_tracklist', [$this, 'ajax_get_show_tracklist']);
		add_action('wp_ajax_copy_items_to_show', [$this, 'ajax_copy_items_to_show']);
		add_action('wp_ajax_add_single_item_to_show', [$this, 'ajax_add_single_item_to_show']);
		
		// Assets & Template Button
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets'], 20);
		
		// Frontend Formatting for "Linked" Spacer Rows (Auto IDs)
		add_filter('the_content', [$this, 'auto_id_headings'], 10);
	}

	public function register_post_type() {
		register_post_type('show', [
			'label' => 'Shows',
			'labels' => [
				'menu_name' => 'Shows',
				'name_admin_bar' => 'Show',
				'add_new' => 'Add Show',
				'add_new_item' => 'Add New Show',
				'new_item' => 'New Show',
				'edit_item' => 'Edit Show',
				'view_item' => 'View Show',
				'update_item' => 'View Show',
				'all_items' => 'All Shows',
				'search_items' => 'Search Shows',
				'parent_item_colon' => 'Parent Show',
				'not_found' => 'No shows found.',
				'not_found_in_trash' => 'No shows found in Trash',
				'name' => 'Shows',
				'singular_name' => 'Show',
			],
			'public' => true,
			'exclude_from_search' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_nav_menus' => true,
			'show_in_admin_bar' => true,
			'show_in_rest' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'has_archive' => true,
			'query_var' => true,
			'can_export' => true,
			'rewrite_no_front' => false,
			'show_in_menu' => true,
			'menu_position' => 10,
			'menu_icon' => 'dashicons-playlist-audio',
			'supports' => [
				'title',
				'editor',
				'markup_markdown',
				'thumbnail'
			],
			'rewrite' => true
		]);
	}

	// =========================================================================
	// DATA MIGRATION HELPER
	// =========================================================================

	/**
	 * Migrate old field names to new generic names
	 * Old: track_title, track_url
	 * New: title, url
	 * 
	 * This preserves backwards compatibility while transitioning to generic names
	 */
	private function migrate_tracklist_data($items) {
		if (!is_array($items)) return [];
		
		$migrated = [];
		foreach ($items as $item) {
			// Start with a clean item
			$new_item = [];
			
			// Type is already generic
			$new_item['type'] = $item['type'] ?? 'track';
			
			// Migrate title field (track_title -> title)
			if (isset($item['title'])) {
				$new_item['title'] = $item['title'];
			} elseif (isset($item['track_title'])) {
				$new_item['title'] = $item['track_title'];
			} else {
				$new_item['title'] = '';
			}
			
			// Migrate URL field (track_url -> url)
			if (isset($item['url'])) {
				$new_item['url'] = $item['url'];
			} elseif (isset($item['track_url'])) {
				$new_item['url'] = $item['track_url'];
			} else {
				$new_item['url'] = '';
			}
			
			// Duration is already generic
			$new_item['duration'] = $item['duration'] ?? '';
			
			// Link flag is already generic
			$new_item['link_to_section'] = isset($item['link_to_section']) && $item['link_to_section'];
			
			$migrated[] = $new_item;
		}
		
		return $migrated;
	}

	// =========================================================================
	// COMMON ADMIN NOTICE FOR EDITORS
	// =========================================================================

	public function show_admin_notice() {
		$screen = get_current_screen();
		// Only show on Show post edit and archive view screens
		if (!$screen || $screen->post_type !== 'show' || !in_array($screen->base, ['post', 'post-new', 'edit'])) { return; }
		?>
		<div class="notice nagging is-dismissible">
			<p>
				<b>Show Post Nagging:</b>
				<div></div>
				<ul>
					<li><a title="Click 'Load Template'">Title Formatting Example:</a> Chris & Jesse: [full-length-month] [non-zero-leading-day-of-the-month] [four-digit-year] ([optional-show-theme])</li>
					<li>When accessing the Show Posts Dashboard at the station it is recommended to head directly to the <b>search function</b> located below this notice so you can find the Show Post most relevant to you, avoid scrolling through the archive if you know you can just search it.</li>
					<li>If you find that you need to push news items into next week, open the archive "All Shows" view in a new tab and use the <b>search function</b> to see if the Show Post has already been made and add to that instead.</li>
					<li>There are <b>(in development)</b> controls in the tracklist metabox that as of right now allow you to add rows all at once or individually into already made Show Posts within the new/edit screen, there is also a link in these modals to create a new Show Post if one doesn't come up, again search from the archive view in another tab just in case.</li>
					<li>When publishing aired shows, if you find that you're not sure of what to do inform me (Chris) on the relevant channels.</li>
					<li>Show Posts are meant to be a centralized format to organize, you can include unlinked subheaders under linked segments in Show Posts as we don't have character limits, go crazy (but keep it organized at least, for my sanity)</li>
					<li>If you find any gaps in the flow of managing Show Posts, inform me (Chris)</li>
				</ul>
			</p>
		</div>
		<?php
	}

	// =========================================================================
	// META BOXES
	// =========================================================================

	public function add_meta_boxes() {
		// Local Tracklist (Main Editor)
		add_meta_box('tracklist_meta_box', 'Show Tracklist', [$this, 'render_tracklist_metabox'], 'show', 'normal', 'high');
	}

	public function render_tracklist_metabox($post) {
		$tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
		// Migrate old data on load
		$tracklist = $this->migrate_tracklist_data($tracklist);
		wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
		$this->render_editor_html($tracklist, $post->ID);
	}

	/**
	 * Render Tracklist Editor HTML
	 * Now uses generic field names: title, url, duration
	 */
	private function render_editor_html($items, $post_id) {
		$items = is_array($items) ? $items : [];
		?>
		<div class="tracklist-wrapper" data-post-id="<?php echo esc_attr($post_id); ?>" style="position: relative;">
			<div class="tracklist-items">
				<?php foreach ($items as $i => $item): 
					 $type = $item['type'] ?? 'track';
					 $title = $item['title'] ?? '';
					 $url = $item['url'] ?? '';
					 $dur = $item['duration'] ?? '';
					 $link = $item['link_to_section'] ?? false;
				?>
				<div class="tracklist-row <?php echo $type === 'spacer' ? 'is-spacer' : ''; ?>">
					<span class="drag-handle">|||</span>
					<input type="hidden" name="tracklist[<?php echo $i; ?>][type]" value="<?php echo esc_attr($type); ?>" class="item-type">
					<input type="text" name="tracklist[<?php echo $i; ?>][title]" value="<?php echo esc_attr($title); ?>" class="item-title-input" placeholder="<?php echo $type === 'spacer' ? 'Segment Title...' : 'Artist - Title'; ?>">
					<input type="url" name="tracklist[<?php echo $i; ?>][url]" value="<?php echo esc_attr($url); ?>" class="item-url-input" style="<?php echo $type === 'spacer' ? 'display:none' : ''; ?>" placeholder="URL">
					<input type="text" name="tracklist[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($dur); ?>" class="item-duration-input" style="<?php echo $type === 'spacer' ? 'display:none' : ''; ?>" placeholder="0:00">
					<label class="link-checkbox-label" style="<?php echo $type === 'spacer' ? '' : 'display:none'; ?>">
						<input type="checkbox" name="tracklist[<?php echo $i; ?>][link_to_section]" value="1" <?php checked($link); ?> class="link-to-section-checkbox">Link
					</label>
					<button type="button" class="fetch-duration button" style="<?php echo $type === 'spacer' ? 'display:none' : ''; ?>">Fetch</button>
					<button type="button" class="add-to-show-btn button">Add to Show</button>
					<button type="button" class="remove-item button">Delete</button>
				</div>
				<?php endforeach; ?>
			</div>
			
			<div class="tracklist-controls">
				<span class="total-duration-display">0:00</span>
				<button type="button" class="add-track button">+ Track</button>
				<button type="button" class="add-spacer button">+ Spacer</button>
				<button type="button" class="copy-all-to-show-btn button">Copy All to Show</button>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// SAVING & AJAX
	// =========================================================================

	public function save_tracklist($post_id) {
		if (!isset($_POST['tracklist_meta_nonce']) || !wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		if (isset($_POST['tracklist']) && is_array($_POST['tracklist'])) {
			$sanitized = $this->sanitize_items($_POST['tracklist']);
			update_post_meta($post_id, 'tracklist', $sanitized);
		} else {
			delete_post_meta($post_id, 'tracklist');
		}
	}

	/**
	 * AJAX: Get list of all show posts for the dropdown
	 */
	public function ajax_get_show_posts() {
		check_ajax_referer('tracklist_nonce', 'nonce');
		
		$current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
		
		$args = array(
			'post_type' => 'show',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'post_status' => array('publish', 'draft'),
			'post__not_in' => array($current_post_id) // Exclude current post
		);
		
		$posts = get_posts($args);
		$result = array();
		
		foreach ($posts as $post) {
			$result[] = array(
				'id' => $post->ID,
				'title' => $post->post_title,
				'status' => $post->post_status,
				'date' => get_the_date('Y-m-d', $post->ID)
			);
		}
		
		wp_send_json_success($result);
	}

	/**
	 * AJAX: Get tracklist for a specific show
	 * Migrates data on retrieval
	 */
	public function ajax_get_show_tracklist() {
		check_ajax_referer('tracklist_nonce', 'nonce');
		
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		
		if (!$post_id || get_post_type($post_id) !== 'show') {
			wp_send_json_error(array('message' => 'Invalid post ID'));
		}
		
		$tracklist = get_post_meta($post_id, 'tracklist', true);
		$tracklist = is_array($tracklist) ? $tracklist : array();
		
		// Migrate before sending
		$tracklist = $this->migrate_tracklist_data($tracklist);
		
		wp_send_json_success($tracklist);
	}

	/**
	 * AJAX: Copy all items to another show
	 */
	public function ajax_copy_items_to_show() {
		check_ajax_referer('tracklist_nonce', 'nonce');
		
		$target_post_id = isset($_POST['target_post_id']) ? intval($_POST['target_post_id']) : 0;
		$items = isset($_POST['items']) ? $_POST['items'] : array();
		
		if (!$target_post_id || get_post_type($target_post_id) !== 'show') {
			wp_send_json_error(array('message' => 'Invalid target post'));
		}
		
		if (!current_user_can('edit_post', $target_post_id)) {
			wp_send_json_error(array('message' => 'You do not have permission to edit this show'));
		}
		
		// Get existing tracklist and migrate it
		$existing_tracklist = get_post_meta($target_post_id, 'tracklist', true);
		$existing_tracklist = is_array($existing_tracklist) ? $existing_tracklist : array();
		$existing_tracklist = $this->migrate_tracklist_data($existing_tracklist);
		
		// Sanitize and append new items
		$new_items = $this->sanitize_items($items);
		$updated_tracklist = array_merge($existing_tracklist, $new_items);
		
		// Save
		update_post_meta($target_post_id, 'tracklist', $updated_tracklist);
		
		wp_send_json_success(array(
			'message' => 'Items copied successfully',
			'count' => count($new_items)
		));
	}

	/**
	 * AJAX: Add a single item to another show
	 */
	public function ajax_add_single_item_to_show() {
		check_ajax_referer('tracklist_nonce', 'nonce');

		$target_post_id = isset($_POST['target_post_id']) ? intval($_POST['target_post_id']) : 0;
		$item = isset($_POST['item']) ? $_POST['item'] : array();

		if (!$target_post_id || get_post_type($target_post_id) !== 'show') {
			wp_send_json_error(array('message' => 'Invalid target post'));
		}

		if (!current_user_can('edit_post', $target_post_id)) {
			wp_send_json_error(array('message' => 'You do not have permission to edit this show'));
		}

		// Get existing tracklist and migrate it
		$existing_tracklist = get_post_meta($target_post_id, 'tracklist', true);
		$existing_tracklist = is_array($existing_tracklist) ? $existing_tracklist : array();
		$existing_tracklist = $this->migrate_tracklist_data($existing_tracklist);

		// Sanitize and append single item
		$sanitized_items = $this->sanitize_items(array($item));
		if (empty($sanitized_items)) {
			wp_send_json_error(array('message' => 'Invalid item data'));
		}

		$existing_tracklist[] = $sanitized_items[0];
		
		// Save
		update_post_meta($target_post_id, 'tracklist', $existing_tracklist);

		wp_send_json_success(array(
			'message' => 'Item added successfully'
		));
	}

	/**
	 * Sanitize items with new generic field names
	 */
	private function sanitize_items($items) {
		$clean = [];
		foreach ($items as $item) {
			// Get title from either old or new field name
			$title = '';
			if (isset($item['title'])) {
				$title = $item['title'];
			} elseif (isset($item['track_title'])) {
				$title = $item['track_title'];
			}
			
			// Skip empty rows (except spacers can be empty)
			if (empty($title) && ($item['type'] ?? 'track') !== 'spacer') continue;
			
			// Get URL from either old or new field name
			$url = '';
			if (isset($item['url'])) {
				$url = $item['url'];
			} elseif (isset($item['track_url'])) {
				$url = $item['track_url'];
			}
			
			$clean[] = [
				'type' => sanitize_text_field($item['type'] ?? 'track'),
				'title' => sanitize_text_field($title),
				'duration' => sanitize_text_field($item['duration'] ?? ''),
				'url' => esc_url_raw($url),
				'link_to_section' => isset($item['link_to_section']) && $item['link_to_section'] == '1',
			];
		}
		return $clean;
	}

	// =========================================================================
	// ASSETS & HELPERS
	// =========================================================================

	public function enqueue_assets($hook) {
		$is_show_edit = ($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'show';

		// 1. Tracklist JS
		if ($is_show_edit) {
			wp_enqueue_script('tracklist-js', get_theme_file_uri() . '/js/tracklist.js', ['jquery', 'jquery-ui-sortable'], '7.0.0', true);
			wp_localize_script('tracklist-js', 'tracklistSettings', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('tracklist_nonce'),
				'user_id' => get_current_user_id()
			]);
		}

		// 2. Template Button JS
		if ($is_show_edit) {
			// adds the button
			wp_enqueue_script('show-template-button', get_stylesheet_directory_uri() . '/js/show-template-button.js', ['jquery'], '3.0.0', true);
			// body template contents
			wp_localize_script('show-template-button', 'showTemplate', [
				'title' => "Chris & Jesse: " . date('F j Y'),
				'body' => "### In The Cinema\n[*What's On at Huski Pics?*](https://huskipics.com.au/movies/now-showing/)\n[*Global box office top 10 (replace placeholder link with latest headline)*](https://www.screendaily.com/box-office/box-office-reports/international)\n\n### The Pin Drop\n[*YouTube global music top 10*](https://charts.youtube.com/charts/TopSongs/global/weekly)\n*Chris' personal picks last week*\n\n### Walking On Thin Ice\n\n### One Up",
				'spacers' => [
					'In The Cinema',
					'The Pin Drop',
					'Walking On Thin Ice',
					'One Up'
				]
			]);
		}
	}

	public function auto_id_headings($content) {
		if (!is_singular('show') || is_admin()) return $content;
		
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		for ($i = 1; $i <= 6; $i++) {
			foreach ($dom->getElementsByTagName('h' . $i) as $tag) {
				if (!$tag->hasAttribute('id')) {
					$tag->setAttribute('id', sanitize_title($tag->textContent));
				}
			}
		}
		return $dom->saveHTML();
	}
}