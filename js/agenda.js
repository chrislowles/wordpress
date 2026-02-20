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

		// Admin Notice
		add_action('admin_notices', [$this, 'show_admin_notice']);

		// Save Handlers
		add_action('save_post_show', [$this, 'validate_publish_date'], 5);
		add_action('save_post_show', [$this, 'save_tracklist']);
		add_action('save_post_show', [$this, 'auto_fetch_link_titles'], 20, 2);

		// AJAX Handlers for cross-post transfer
		add_action('wp_ajax_get_show_posts',          [$this, 'ajax_get_show_posts']);
		add_action('wp_ajax_get_show_tracklist',       [$this, 'ajax_get_show_tracklist']);
		add_action('wp_ajax_copy_items_to_show',       [$this, 'ajax_copy_items_to_show']);
		add_action('wp_ajax_add_single_item_to_show',  [$this, 'ajax_add_single_item_to_show']);

		// Assets & Template Button
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets'], 20);

		// Admin Columns — Publish Date with airing status
		// Replaces the built-in 'date' column with our status-aware version.
		add_filter('manage_show_posts_columns',         [$this, 'airing_date_column_header']);
		add_action('manage_show_posts_custom_column',   [$this, 'airing_date_column_content'], 10, 2);
		add_filter('manage_edit-show_sortable_columns', [$this, 'airing_date_sortable_column']);
		add_action('pre_get_posts',                     [$this, 'airing_date_orderby']);

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
				'update_item'        => 'View Show',
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
	// PUBLISH DATE VALIDATION
	// Runs early on every show save (priority 5).  If the post is being saved
	// as a draft with a publish date that is already in the past, stash a flag
	// in a short-lived transient so show_admin_notice() can surface it on the
	// next page load (after the redirect back to the edit screen).
	// =========================================================================

	public function validate_publish_date($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;

		// Only nag for drafts — scheduled/published posts are fine by definition.
		$new_status = $_POST['post_status'] ?? get_post_status($post_id);
		if ($new_status !== 'draft') return;

		// WordPress sends the chosen date as separate components (aa, mm, jj, hh, mn, ss).
		// If aa is absent we cannot judge, so bail silently.
		if (empty($_POST['aa'])) return;

		$chosen_ts = mktime(
			(int) ($_POST['hh'] ?? 0),
			(int) ($_POST['mn'] ?? 0),
			(int) ($_POST['ss'] ?? 0),
			(int) ($_POST['mm'] ?? date('n')),
			(int) ($_POST['jj'] ?? date('j')),
			(int) ($_POST['aa'] ?? date('Y'))
		);

		// Set a transient if the chosen publish date is already in the past.
		if ($chosen_ts <= current_time('timestamp')) {
			set_transient('show_overdue_notice_' . get_current_user_id(), $post_id, 60);
		}
	}

	// =========================================================================
	// ADMIN NOTICE
	// =========================================================================

	public function show_admin_notice() {
		$screen = get_current_screen();
		if (!$screen || $screen->post_type !== 'show' || !in_array($screen->base, ['post', 'post-new', 'edit'])) return;

		// ── Overdue / no-date notices on the edit / new-post screens ─────────
		if (in_array($screen->base, ['post', 'post-new'])) {
			$post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;

			// Check transient left by validate_publish_date() after a just-completed save.
			$flagged_id = get_transient('show_overdue_notice_' . get_current_user_id());
			if ($flagged_id) {
				delete_transient('show_overdue_notice_' . get_current_user_id());
				echo '<div class="notice notice-error is-dismissible"><p>'
					. '<strong>⚠️ Publish date is in the past.</strong> '
					. 'This show is still a draft but its publish date has already lapsed — '
					. 'update the date in the <em>Publish</em> panel to when the episode is expected to finish airing.</p></div>';

			} elseif ($post_id) {
				// Persistent overdue banner while the editor is open.
				$post = get_post($post_id);
				if ($post && $post->post_status === 'draft') {
					$ts = strtotime($post->post_date);
					if ($ts && $ts <= current_time('timestamp')) {
						echo '<div class="notice notice-error"><p>'
							. '<strong>⚠️ Overdue:</strong> '
							. 'This draft\'s publish date (<strong>'
							. esc_html(date_i18n('F j, Y \a\t g:i a', $ts))
							. '</strong>) has already passed. '
							. 'Update it in the <em>Publish</em> panel before going live.</p></div>';
					}
				}
			}
		}

		// ── General show workflow nagging ─────────────────────────────────────
		?>
		<div class="notice nagging is-dismissible">
			<details>
				<summary>Show Post Nagging:</summary>
				<ul>
					<li><a title="Click 'Load Template'">Title formatting example:</a> Chris &amp; Jesse: [full-length-month] [non-zero-leading-day-of-the-month] [four-digit-year] ([optional-show-theme])</li>
					<li><strong>Set the Publish date</strong> (in the Publish panel, top-right) to when the episode is expected to finish airing — this is how the archive column tracks overdue and upcoming shows. Show posts must have an explicit future date even while drafts.</li>
					<li>Regarding nixing a show: If you intend to shift a show up to a new date, remember to adjust the slug, title, publish date <em>and</em> the post date if it's already been set.</li>
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
		add_meta_box('tracklist_meta_box', 'Show Tracklist', [$this, 'render_tracklist_metabox'], 'show', 'normal', 'high');
		// The separate "Expected Airing Date" meta box has been removed.
		// Use the built-in Publish panel date/time fields instead.
	}

	// =========================================================================
	// ADMIN COLUMNS — Publish Date with airing status
	//
	// Swaps the built-in 'date' column for our status-aware 'show_airing_date'
	// column in the same position, reading directly from post_date.
	// =========================================================================

	public function airing_date_column_header($columns) {
		$new = [];
		foreach ($columns as $key => $label) {
			if ($key === 'date') {
				$new['show_airing_date'] = 'Publish Date';
			} else {
				$new[$key] = $label;
			}
		}
		return $new;
	}

	public function airing_date_column_content($column, $post_id) {
		if ($column !== 'show_airing_date') return;

		$post   = get_post($post_id);
		$status = $post->post_status ?? 'draft';

		// Published posts: plain date, no status label needed.
		if ($status === 'publish') {
			echo '<span style="color:#1d2327;">' . esc_html(get_the_date('Y/m/d \a\t g:i a', $post_id)) . '</span>';
			return;
		}

		$ts          = strtotime($post->post_date);
		$modified_ts = strtotime($post->post_modified);

		// Draft where post_date is within 60 s of post_modified almost certainly
		// means WordPress auto-filled "right now" and the editor never set a real
		// date — surface a prompt rather than a misleading timestamp.
		if (!$ts || abs($ts - $modified_ts) < 60) {
			echo '<span style="color:#D63638; font-weight:600;">⚠ No date set</span>';
			return;
		}

		$diff      = $ts - current_time('timestamp');
		$formatted = date_i18n('Y/m/d \a\t g:i a', $ts);

		if ($diff < 0) {
			$label  = 'Overdue';
			$colour = '#D63638';
			$weight = '600';
		} elseif ($diff < DAY_IN_SECONDS) {
			$label  = 'Airing soon';
			$colour = '#DBA617';
			$weight = '600';
		} else {
			$label  = 'Confirmed';
			$colour = '#646970';
			$weight = 'normal';
		}

		printf(
			'<span style="display:block; color:%1$s; font-size:13px; font-weight:%4$s; margin-bottom:1px;">%2$s</span>'
			. '<span style="display:block; color:#1d2327; white-space:nowrap;">%3$s</span>',
			esc_attr($colour),
			esc_html($label),
			esc_html($formatted),
			esc_attr($weight)
		);
	}

	public function airing_date_sortable_column($columns) {
		// Map our custom column key back to the native 'date' orderby so WP
		// handles the SQL sorting without any extra pre_get_posts logic.
		$columns['show_airing_date'] = 'date';
		return $columns;
	}

	public function airing_date_orderby($query) {
		// No custom meta_key logic needed — 'date' is a native WP orderby value.
		// This method is retained as a hook placeholder for future use.
		if (!is_admin() || !$query->is_main_query()) return;
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

		$skip_domains = [
			'reddit.com', 'www.reddit.com', 'old.reddit.com',
			'twitter.com', 'x.com', 'instagram.com', 'facebook.com',
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
	 * Priority: og:title → twitter:title → og:title (reversed attrs)
	 *           → twitter:title (reversed attrs) → <title> tag → null
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
			'body'    => "### In The Cinema\n[*What's On at Huski Pics?*](https://huskipics.com.au/movies/now-showing/)\n[*Global box office top 10 (replace placeholder link with latest headline)*](https://www.screendaily.com/box-office/box-office-reports/international)\n\n### The Pin Drop\n[*YouTube global music top 10*](https://charts.youtube.com/charts/TopSongs/global/weekly)\n*Chris' personal picks last week*\n\n### Walking On Thin Ice\n\n### One Up",
			'spacers' => ['In The Cinema', 'The Pin Drop', 'Walking On Thin Ice', 'One Up'],
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