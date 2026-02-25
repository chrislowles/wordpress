<?php
/**
 * Class: Shows Manager
 * Handles the 'Show' Custom Post Type, Tracklists, and Cross-Post Transfer functionality.
 *
 * Field names: All data uses the canonical names 'title', 'url', 'duration'.
 * Legacy names ('track_title', 'track_url') are converted once by
 * migrate_tracklist_data() at every read boundary; they never reach sanitize_items().
 *
 * Note: the old '_show_airing_date' post meta is no longer written.  Any
 * existing rows can be left in place (they are harmless) or cleaned up with:
 *   DELETE FROM wp_postmeta WHERE meta_key = '_show_airing_date';
 */
class ChrisLowles_Shows {

	public function __construct() {
		// CPT
		add_action('init', [$this, 'register_post_type'], 0);

		// Meta Boxes
		add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

		// Save Handlers
		add_action('save_post_show', [$this, 'save_tracklist']);
		add_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20, 2);

		// AJAX Handlers for cross-post transfer
		add_action('wp_ajax_get_show_posts',          [$this, 'ajax_get_show_posts']);
		add_action('wp_ajax_get_show_tracklist',       [$this, 'ajax_get_show_tracklist']);
		add_action('wp_ajax_copy_items_to_show',       [$this, 'ajax_copy_items_to_show']);
		add_action('wp_ajax_add_single_item_to_show',  [$this, 'ajax_add_single_item_to_show']);

		// Assets & Template Button
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets'], 20);

		// Admin Columns
		// register_columns() inserts the editing-status dot before the
		// checkbox column; the native 'date' column is left in place unchanged.
		add_filter('manage_show_posts_columns',       [$this, 'register_columns']);
		add_action('manage_show_posts_custom_column', [$this, 'render_column'], 10, 2);

		// Frontend: Auto-add IDs to headings for in-page anchor links
		add_filter('the_content', [$this, 'auto_id_headings'], 10);
	}

	public function register_post_type() {
		register_post_type('show', [
			'label'  => 'Shows',
			'labels' => [
				'menu_name'          => 'Shows',
				'name_admin_bar'     => 'Show',
				'add_new'            => 'Add Show',
				'add_new_item'       => 'Add New Show',
				'new_item'           => 'New Show',
				'edit_item'          => 'Edit Show',
				'view_item'          => 'View Show',
				'update_item'        => 'Update Show',
				'all_items'          => 'All Shows',
				'search_items'       => 'Search Shows',
				'parent_item_colon'  => 'Parent Show',
				'not_found'          => 'No shows found.',
				'not_found_in_trash' => 'No shows found in Trash',
				'name'               => 'Shows',
				'singular_name'      => 'Show',
			],
			'public'              => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'has_archive'         => true,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite_no_front'    => false,
			'show_in_menu'        => true,
			'menu_position'       => 10,
			'menu_icon'           => 'dashicons-playlist-audio',
			'supports'            => ['title', 'editor', 'markup_markdown', 'thumbnail'],
			'rewrite'             => true,
		]);
	}

	// =========================================================================
	// DATA MIGRATION
	// Single place that converts legacy field names (track_title / track_url)
	// to the canonical names (title / url).  Called on every DB read boundary.
	// =========================================================================

	private function migrate_tracklist_data($items) {
		if (!is_array($items)) return [];

		return array_map(function ($item) {
			return [
				'type'            => $item['type'] ?? 'track',
				'title'           => $item['title'] ?? $item['track_title'] ?? '',
				'url'             => $item['url']   ?? $item['track_url']   ?? '',
				'duration'        => $item['duration'] ?? '',
				'link_to_section' => isset($item['link_to_section']) && $item['link_to_section'],
			];
		}, $items);
	}

	// =========================================================================
	// META BOXES
	// =========================================================================

	public function add_meta_boxes() {
		add_meta_box('tracklist_meta_box', 'Show Tracklist', [$this, 'render_tracklist_metabox'], 'show', 'normal', 'high');
	}

	// =========================================================================
	// ADMIN COLUMNS
	// register_columns() is the single filter callback that defines all custom
	// columns for the show post list.  render_column() dispatches rendering for
	// each custom column key.
	//
	// Columns defined here:
	//   editing_status — dot indicator read from WP's native _edit_lock meta,
	//                    refreshed by Heartbeat while a post is open for editing.
	//                    Green = no active editor, Red = post currently open.
	//
	// The native 'date' column is preserved as-is; no custom date logic applies.
	// =========================================================================

	public function register_columns($columns) {
		$new = [];
		foreach ($columns as $key => $label) {
			if ($key === 'cb') {
				$new['cb']             = $label;
				$new['editing_status'] = '<span class="screen-reader-text">Editing Status</span>';
			} else {
				$new[$key] = $label;
			}
		}
		return $new;
	}

	public function render_column($column, $post_id) {
		if ($column === 'editing_status') {
			$this->render_editing_status($post_id);
		}
	}

	// -------------------------------------------------------------------------
	// Editing Status Dot
	// Reads WP's native _edit_lock meta ({timestamp}:{user_id}).
	// The lock is refreshed every ~15 s via Heartbeat while an editor has the
	// post open and expires naturally when they leave.  WordPress treats it as
	// stale after 150 s, so we use the same threshold.
	// -------------------------------------------------------------------------

	private function render_editing_status($post_id) {
		$lock       = get_post_meta($post_id, '_edit_lock', true);
		$is_editing = false;
		$label      = 'No active editor';

		if ($lock) {
			$parts = explode(':', $lock, 2);
			if (count($parts) === 2 && (time() - (int) $parts[0]) < 150) {
				$is_editing = true;
				$user       = get_userdata((int) $parts[1]);
				$label      = $user ? esc_attr($user->display_name) . ' is editing' : 'Someone is editing';
			}
		}

		printf(
			'<span class="show-edit-dot %s" title="%s"></span>',
			$is_editing ? 'is-editing' : 'is-free',
			esc_attr($label)
		);
	}

	// =========================================================================
	// TRACKLIST METABOX
	// =========================================================================

	public function render_tracklist_metabox($post) {
		$tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
		$tracklist = $this->migrate_tracklist_data($tracklist);
		wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
		$this->render_editor_html($tracklist, $post->ID);
	}

	/**
	 * Render the tracklist editor HTML.
	 *
	 * Visibility of track-only vs spacer-only elements is handled entirely by
	 * CSS in dashboard.css via the .is-spacer class — no inline styles needed.
	 */
	private function render_editor_html($items, $post_id) {
		$items = is_array($items) ? $items : [];
		?>
		<div class="tracklist-wrapper" data-post-id="<?php echo esc_attr($post_id); ?>">
			<div class="tracklist-items">
				<?php foreach ($items as $i => $item):
					$type  = $item['type']  ?? 'track';
					$title = $item['title'] ?? '';
					$url   = $item['url']   ?? '';
					$dur   = $item['duration'] ?? '';
					$link  = $item['link_to_section'] ?? false;
				?>
				<div class="tracklist-row <?php echo $type === 'spacer' ? 'is-spacer' : ''; ?>">
					<span class="drag-handle">|||</span>
					<input type="hidden"  name="tracklist[<?php echo $i; ?>][type]"     value="<?php echo esc_attr($type);  ?>" class="item-type">
					<input type="text"    name="tracklist[<?php echo $i; ?>][title]"    value="<?php echo esc_attr($title); ?>" class="item-title-input"    placeholder="<?php echo $type === 'spacer' ? 'Segment Title...' : 'Artist - Title'; ?>">
					<input type="url"     name="tracklist[<?php echo $i; ?>][url]"      value="<?php echo esc_attr($url);   ?>" class="item-url-input"      placeholder="URL">
					<input type="text"    name="tracklist[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($dur);   ?>" class="item-duration-input" placeholder="0:00">
					<label class="link-checkbox-label">
						<input type="checkbox" name="tracklist[<?php echo $i; ?>][link_to_section]" value="1" <?php checked($link); ?> class="link-to-section-checkbox">Link
					</label>
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
	// SAVE HANDLERS
	// =========================================================================

	public function save_tracklist($post_id) {
		if (!isset($_POST['tracklist_meta_nonce']) || !wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		if (isset($_POST['tracklist']) && is_array($_POST['tracklist'])) {
			update_post_meta($post_id, 'tracklist', $this->sanitize_items($_POST['tracklist']));
		} else {
			delete_post_meta($post_id, 'tracklist');
		}
	}

	/**
	 * Auto-fetch titles for bare URLs in post content on save.
	 * Only runs if the user confirmed via the JavaScript dialog.
	 */
	public function auto_fetch_link_titles($post_id, $post) {
		if (empty($_POST['fetch_link_titles'])) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
		if (!current_user_can('edit_post', $post_id)) return;

		$content = $post->post_content;
		if (empty($content)) return;

		preg_match_all('/(?<!\]\()\b(https?:\/\/[^\s\)\]<>"\']+)/i', $content, $matches);
		if (empty($matches[1])) return;

		# these domains tend to not work anyway so skip when processing them for the bare url detection
		$skip_domains = [
			'reddit.com', 'www.reddit.com', 'old.reddit.com',
			'twitter.com', 'x.com',
			'instagram.com', 'facebook.com',
			'tiktok.com', 'linkedin.com', 'pinterest.com',
		];

		$replacements    = [];
		$updated_content = $content;

		foreach (array_unique($matches[1]) as $url) {
			$url    = rtrim($url, '.,;:!?)');
			$parsed = parse_url($url);
			if (!isset($parsed['host'])) continue;
			if (in_array(strtolower($parsed['host']), $skip_domains)) continue;

			$response = wp_remote_get($url, [
				'timeout'     => 10,
				'sslverify'   => true,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; +' . home_url() . ')',
			]);

			if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) continue;

			$title = $this->extract_title_from_html(wp_remote_retrieve_body($response));
			if (!empty($title)) {
				$replacements[$url] = '[' . $title . '](' . $url . ')';
			}
		}

		if (empty($replacements)) return;

		foreach ($replacements as $url => $link) {
			$updated_content = str_replace($url, $link, $updated_content);
		}

		if ($updated_content === $content) return;

		remove_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20);
		wp_update_post(['ID' => $post_id, 'post_content' => $updated_content], true);
		add_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20, 2);
	}

	/**
	 * Extract page title from HTML.
	 * Priority: og:title -> twitter:title -> og:title (reversed attrs)
	 *           -> twitter:title (reversed attrs) -> <title> tag -> null
	 */
	private function extract_title_from_html($html) {
		if (empty($html)) return null;

		$patterns = [
			'/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i',
			'/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i',
			'/<meta\s+content=["\'](.*?)["\']\s+property=["\']og:title["\']/i',
			'/<meta\s+content=["\'](.*?)["\']\s+name=["\']twitter:title["\']/i',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $html, $m)) {
				return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}
		}

		if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
			return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		return null;
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	public function ajax_get_show_posts() {
		check_ajax_referer('tracklist_nonce', 'nonce');

		$current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
		$posts           = get_posts([
			'post_type'      => 'show',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => ['publish', 'draft'],
			'post__not_in'   => [$current_post_id],
		]);

		wp_send_json_success(array_map(function ($post) {
			return [
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'date'   => get_the_date('Y-m-d', $post->ID),
			];
		}, $posts));
	}

	public function ajax_get_show_tracklist() {
		check_ajax_referer('tracklist_nonce', 'nonce');

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		if (!$post_id || get_post_type($post_id) !== 'show') {
			wp_send_json_error(['message' => 'Invalid post ID']);
		}

		$tracklist = get_post_meta($post_id, 'tracklist', true) ?: [];
		wp_send_json_success($this->migrate_tracklist_data($tracklist));
	}

	public function ajax_copy_items_to_show() {
		check_ajax_referer('tracklist_nonce', 'nonce');

		$target_id = isset($_POST['target_post_id']) ? intval($_POST['target_post_id']) : 0;
		$items     = $_POST['items'] ?? [];

		if (!$target_id || get_post_type($target_id) !== 'show') {
			wp_send_json_error(['message' => 'Invalid target post']);
		}
		if (!current_user_can('edit_post', $target_id)) {
			wp_send_json_error(['message' => 'You do not have permission to edit this show']);
		}

		$existing  = $this->migrate_tracklist_data(get_post_meta($target_id, 'tracklist', true) ?: []);
		$new_items = $this->sanitize_items($items);

		update_post_meta($target_id, 'tracklist', array_merge($existing, $new_items));
		wp_send_json_success(['message' => 'Items copied successfully', 'count' => count($new_items)]);
	}

	public function ajax_add_single_item_to_show() {
		check_ajax_referer('tracklist_nonce', 'nonce');

		$target_id = isset($_POST['target_post_id']) ? intval($_POST['target_post_id']) : 0;
		$item      = $_POST['item'] ?? [];

		if (!$target_id || get_post_type($target_id) !== 'show') {
			wp_send_json_error(['message' => 'Invalid target post']);
		}
		if (!current_user_can('edit_post', $target_id)) {
			wp_send_json_error(['message' => 'You do not have permission to edit this show']);
		}

		$sanitized = $this->sanitize_items([$item]);
		if (empty($sanitized)) wp_send_json_error(['message' => 'Invalid item data']);

		$existing   = $this->migrate_tracklist_data(get_post_meta($target_id, 'tracklist', true) ?: []);
		$existing[] = $sanitized[0];

		update_post_meta($target_id, 'tracklist', $existing);
		wp_send_json_success(['message' => 'Item added successfully']);
	}

	// =========================================================================
	// SANITIZATION
	// migrate_tracklist_data() is always called upstream, so only canonical
	// field names ('title', 'url') are expected here.
	// =========================================================================

	private function sanitize_items($items) {
		$clean = [];
		foreach ($items as $item) {
			$type  = sanitize_text_field($item['type']  ?? 'track');
			$title = sanitize_text_field($item['title'] ?? '');

			// Tracks must have a title; spacers may be empty (used as dividers)
			if (empty($title) && $type !== 'spacer') continue;

			$clean[] = [
				'type'            => $type,
				'title'           => $title,
				'duration'        => sanitize_text_field($item['duration'] ?? ''),
				'url'             => esc_url_raw($item['url'] ?? ''),
				'link_to_section' => isset($item['link_to_section']) && $item['link_to_section'] === '1',
			];
		}
		return $clean;
	}

	// =========================================================================
	// ASSETS
	// =========================================================================

	public function enqueue_assets($hook) {
		$is_show_edit = ($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'show';
		if (!$is_show_edit) return;

		// Shared utilities — must be enqueued before the three consumers below
		wp_enqueue_script('theme-utils', get_stylesheet_directory_uri() . '/js/utils.js', ['jquery'], '1.0.0', true);

		// Tracklist editor
		wp_enqueue_script('tracklist-js', get_theme_file_uri() . '/js/tracklist.js', ['jquery', 'jquery-ui-sortable', 'theme-utils'], '8.0.0', true);
		wp_localize_script('tracklist-js', 'tracklistSettings', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('tracklist_nonce'),
			'user_id'  => get_current_user_id(),
		]);

		// Template loader button
		wp_enqueue_script('show-template-button', get_stylesheet_directory_uri() . '/js/show-template-button.js', ['jquery', 'theme-utils'], '3.0.0', true);
		wp_localize_script('show-template-button', 'showTemplate', [
			'title'   => "Chris & Jesse: " . date('F j Y'),
			'body'    => "### In The Cinema\n[*What's On at Huski Pics?*](https://huskipics.com.au/movies/now-showing/)\n\n[*Global box office top 10 (replace placeholder link with latest headline)*](https://www.screendaily.com/box-office/box-office-reports/international)\n\n### The Pin Drop\n[*YouTube global music top 10*](https://charts.youtube.com/charts/TopSongs/global/weekly)\n\n*Chris' personal picks last week*\n\n### Walking On Thin Ice\n\n### One Up\n\n### One Up (More)",
			'spacers' => [
				'In The Cinema',
				'The Pin Drop',
				'Walking On Thin Ice',
				'One Up',
				'One Up (More)'
			]
		]);

		// Auto-link-title fetcher
		wp_enqueue_script('fetch-link-titles', get_stylesheet_directory_uri() . '/js/fetch-link-titles.js', ['jquery', 'theme-utils'], '1.0.0', true);
	}

	// =========================================================================
	// FRONTEND
	// =========================================================================

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