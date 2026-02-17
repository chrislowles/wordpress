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
		add_action('save_post_show', [$this, 'save_airing_date']);
		add_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20, 2);
		
		// AJAX Handlers for cross-post transfer
		add_action('wp_ajax_get_show_posts', [$this, 'ajax_get_show_posts']);
		add_action('wp_ajax_get_show_tracklist', [$this, 'ajax_get_show_tracklist']);
		add_action('wp_ajax_copy_items_to_show', [$this, 'ajax_copy_items_to_show']);
		add_action('wp_ajax_add_single_item_to_show', [$this, 'ajax_add_single_item_to_show']);
		
		// Assets & Template Button
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets'], 20);

		// Admin Columns — Airing Date
		add_filter('manage_show_posts_columns',       [$this, 'airing_date_column_header']);
		add_action('manage_show_posts_custom_column', [$this, 'airing_date_column_content'], 10, 2);
		add_filter('manage_edit-show_sortable_columns', [$this, 'airing_date_sortable_column']);
		add_action('pre_get_posts',                   [$this, 'airing_date_orderby']);
		
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
			<details>
				<summary>Show Post Nagging:</summary>
				<ul>
					<li><a title="Click 'Load Template'">Title formatting example:</a> Chris & Jesse: [full-length-month] [non-zero-leading-day-of-the-month] [four-digit-year] ([optional-show-theme])</li>
					<li>Regarding nixing a show: If you intend to shift a show up to a new date, remember to adjust the slug, title, air date *and* the publish date if it's already been set.</li>
					<li>When accessing the Show Posts Dashboard at the station it is recommended to head directly to the <b>search function</b> located below this notice so you can find the Show Post most relevant to you, avoid scrolling through the archive if you know you can just search it.</li>
					<li>If you find that you need to push news items into next week, open the archive "All Shows" view in a new tab and use the <b>search function</b> to see if the Show Post has already been made and add to that instead.</li>
					<li>There are <b>(in development)</b> controls in the tracklist metabox that as of right now allow you to add rows all at once or individually into already made Show Posts within the new/edit screen, there is also a link in these modals to create a new Show Post if one doesn't come up, again search from the archive view in another tab just in case.</li>
					<li>When publishing aired shows, if you find that you're not sure of what to do inform me (Chris) on the relevant channels.</li>
					<li>Show Posts are meant to be a centralized format to organize, you can include unlinked subheaders under linked segments in Show Posts as we don't have character limits, go crazy (but keep it organized at least, for my sanity)</li>
					<li>If you find any gaps in the flow of managing Show Posts, inform me (Chris)</li>
				</ul>
			</details>
		</div>
		<?php
	}

	// =========================================================================
	// META BOXES
	// =========================================================================

	public function add_meta_boxes() {
		// Local Tracklist (Main Editor)
		add_meta_box('tracklist_meta_box', 'Show Tracklist', [$this, 'render_tracklist_metabox'], 'show', 'normal', 'high');

		// Expected Airing Date (Sidebar)
		add_meta_box('show_airing_date', 'Expected Airing Date', [$this, 'render_airing_date_metabox'], 'show', 'side', 'high');
	}

	// -------------------------------------------------------------------------
	// Airing Date Metabox
	// -------------------------------------------------------------------------

	public function render_airing_date_metabox($post) {
		wp_nonce_field('save_airing_date_meta', 'airing_date_meta_nonce');
		$airing_date = get_post_meta($post->ID, '_show_airing_date', true);
		?>
		<p style="margin: 0 0 8px;">
			<label for="show_airing_date" style="display:block; margin-bottom:4px; font-weight:600;">Date &amp; Time</label>
			<input
				type="datetime-local"
				id="show_airing_date"
				name="show_airing_date"
				value="<?php echo esc_attr($airing_date); ?>"
				style="width:100%;"
			/>
		</p>
		<p style="margin:0; color:#646970; font-size:11px;">
			Set this to when the episode is expected to finish airing so the post goes live at the right time.
		</p>
		<?php
	}

	public function save_airing_date($post_id) {
		if (!isset($_POST['airing_date_meta_nonce']) || !wp_verify_nonce($_POST['airing_date_meta_nonce'], 'save_airing_date_meta')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		if (!empty($_POST['show_airing_date'])) {
			// Sanitize as a datetime string (YYYY-MM-DDTHH:MM from datetime-local input)
			$raw = sanitize_text_field($_POST['show_airing_date']);
			// Validate it looks like a datetime-local value before saving
			if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) {
				update_post_meta($post_id, '_show_airing_date', $raw);
			}
		} else {
			delete_post_meta($post_id, '_show_airing_date');
		}
	}

	// -------------------------------------------------------------------------
	// Airing Date Admin Columns
	// -------------------------------------------------------------------------

	public function airing_date_column_header($columns) {
		// Insert after the title column
		$new = [];
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ($key === 'title') {
				$new['show_airing_date'] = 'Airs';
			}
		}
		return $new;
	}

	public function airing_date_column_content($column, $post_id) {
		if ($column !== 'show_airing_date') return;

		$airing_date = get_post_meta($post_id, '_show_airing_date', true);

		if (empty($airing_date)) {
			echo '<span style="color:#a7aaad;">—</span>';
			return;
		}

		// Parse the stored datetime-local string (YYYY-MM-DDTHH:MM)
		$timestamp = strtotime($airing_date);
		if (!$timestamp) {
			echo '<span style="color:#a7aaad;">—</span>';
			return;
		}

		$now  = current_time('timestamp');
		$diff = $timestamp - $now;

		// Format: 2026/02/18 at 7:26 am  (matches WP built-in date column style)
		$formatted = date_i18n('Y/m/d \a\t g:i a', $timestamp);

		// Determine label and colour
		if ($diff < 0) {
			$label        = 'Overdue';
			$label_colour = '#D63638'; // WP error red
		} elseif ($diff < DAY_IN_SECONDS) {
			$label        = 'Airing soon';
			$label_colour = '#DBA617'; // WP warning amber
		} else {
			$label        = 'Confirmed';
			$label_colour = '#646970'; // WP muted grey — matches built-in label style
		}

		printf(
			'<span style="display:block; color:%1$s; font-size:13px; margin-bottom:1px;">%2$s</span>' .
			'<span style="display:block; color:#1d2327; white-space:nowrap;">%3$s</span>',
			esc_attr($label_colour),
			esc_html($label),
			esc_html($formatted)
		);
	}

	public function airing_date_sortable_column($columns) {
		$columns['show_airing_date'] = 'show_airing_date';
		return $columns;
	}

	public function airing_date_orderby($query) {
		if (!is_admin() || !$query->is_main_query()) return;
		if ($query->get('orderby') === 'show_airing_date') {
			$query->set('meta_key', '_show_airing_date');
			$query->set('orderby', 'meta_value');
		}
	}

	// =========================================================================
	// TRACKLIST METABOX
	// =========================================================================

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
	 * Auto-fetch titles for bare URLs in post content
	 * Runs on save_post_show hook with priority 20 (after main save)
	 * Fetches page HTML and extracts title from meta tags or <title> element
	 * Works for both drafts and published posts
	 * Only runs if user confirmed via the JavaScript dialog
	 * Skips known-problematic domains and fails silently
	 */
	public function auto_fetch_link_titles($post_id, $post) {
		// Check if user wants to fetch titles (set by JavaScript)
		if (empty($_POST['fetch_link_titles'])) {
			return;
		}
		
		// Avoid infinite loops and unnecessary processing
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (wp_is_post_revision($post_id)) return;
		if (wp_is_post_autosave($post_id)) return;
		if (!current_user_can('edit_post', $post_id)) return;
		
		// Only process if content exists
		$content = $post->post_content;
		if (empty($content)) return;
		
		// Find bare URLs (not already in markdown link syntax)
		// Improved pattern to catch more URL variations
		$pattern = '/(?<!\]\()\b(https?:\/\/[^\s\)\]<>"\']+)/i';
		
		preg_match_all($pattern, $content, $matches);
		
		if (empty($matches[1])) return;
		
		$updated_content = $content;
		$replacements = [];
		
		// Domains known to be problematic or that block scraping
		$skip_domains = [
			'reddit.com',
			'www.reddit.com',
			'old.reddit.com',
			'twitter.com',
			'x.com',
			'instagram.com',
			'facebook.com',
			'tiktok.com',
			'linkedin.com',
			'pinterest.com'
		];
		
		// Fetch metadata for each URL
		foreach (array_unique($matches[1]) as $url) {
			// Clean up URL (remove trailing punctuation that might have been caught)
			$url = rtrim($url, '.,;:!?)');
			
			// Check if domain should be skipped
			$parsed_url = parse_url($url);
			if (!isset($parsed_url['host'])) continue;
			
			$host = strtolower($parsed_url['host']);
			if (in_array($host, $skip_domains)) {
				// Silently skip problematic domains
				continue;
			}
			
			// Fetch the page HTML
			$response = wp_remote_get($url, [
				'timeout' => 10,
				'sslverify' => true,
				'redirection' => 5,
				'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; +' . home_url() . ')'
			]);
			
			// Fail silently on error
			if (is_wp_error($response)) continue;
			
			$response_code = wp_remote_retrieve_response_code($response);
			
			// Only process successful responses
			if ($response_code !== 200) continue;
			
			// Get the HTML body
			$html = wp_remote_retrieve_body($response);
			
			// Extract title using meta tags or <title> element
			$title = $this->extract_title_from_html($html);
			
			// Only add replacement if we successfully got a title
			if (!empty($title)) {
				// Create markdown link: [title](url)
				$replacements[$url] = '[' . $title . '](' . $url . ')';
			}
			// Silently skip if no title found
		}
		
		// Apply replacements if we have any
		if (!empty($replacements)) {
			foreach ($replacements as $url => $markdown_link) {
				$updated_content = str_replace($url, $markdown_link, $updated_content);
			}
			
			// Only update if content actually changed
			if ($updated_content !== $content) {
				// Unhook to prevent infinite loop
				remove_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20);
				
				// Update post content
				wp_update_post([
					'ID' => $post_id,
					'post_content' => $updated_content
				], true);
				
				// Re-hook for future saves
				add_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20, 2);
			}
		}
	}

	/**
	 * Extract page title from HTML
	 * Tries Open Graph, Twitter Cards, then <title> tag
	 * 
	 * @param string $html The HTML content
	 * @return string|null The extracted title, or null if not found
	 */
	private function extract_title_from_html($html) {
		if (empty($html)) return null;
		
		// Try Open Graph title first (most reliable for social sharing)
		if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
			return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		// Try Twitter Card title
		if (preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
			return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		// Try reversed attribute order for Open Graph (some sites do this)
		if (preg_match('/<meta\s+content=["\'](.*?)["\']\s+property=["\']og:title["\']/i', $html, $matches)) {
			return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		// Try reversed attribute order for Twitter Card
		if (preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']twitter:title["\']/i', $html, $matches)) {
			return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		// Fallback to <title> tag
		if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
			return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		return null;
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
		
		// 3. Fetch Link Titles confirmation JS
		if ($is_show_edit) {
			wp_enqueue_script('fetch-link-titles', get_stylesheet_directory_uri() . '/js/fetch-link-titles.js', ['jquery'], '1.0.0', true);
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