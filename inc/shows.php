<?php
/**
 * Class: Shows Manager
 * Handles the 'Show' Custom Post Type, Tracklists, and Cross-Post Transfer functionality.
 *
 * Field names: All data uses the canonical names 'title', 'url', 'duration'.
 * Legacy names ('track_title', 'track_url') are converted once by
 * migrate_tracklist_data() at every read boundary; they never reach sanitize_items().
 *
 * Date Enforcement Policy
 * -----------------------
 * Every show post MUST have an explicit publish date set before it can be saved.
 * The rules are:
 *
 *  - 'draft' status is blocked for new show posts. Saving without a date is
 *    prevented both server-side (save_post_show bail) and client-side (publish
 *    button disabled until a date is chosen).
 *
 *  - 'pending' (Pending Review) is permanently disabled for show posts. The
 *    scheduled date IS the interim review state — a future-dated show sits as
 *    'future' (Scheduled) until it publishes automatically.
 *
 *  - Legacy draft show posts (created before this enforcement was added) are
 *    allowed to open and be edited, but cannot be re-saved without a date.
 *    An admin notice explains this on every edit of a legacy draft.
 *
 * Note: the old '_show_airing_date' post meta is no longer written. Any
 * existing rows can be left in place (they are harmless) or cleaned up with:
 *   DELETE FROM wp_postmeta WHERE meta_key = '_show_airing_date';
 */
class ChrisLowles_Shows {

	// -------------------------------------------------------------------------
	// The date WordPress uses for a "not yet set" post is this sentinel value.
	// Any post_date matching it should be treated as "no date chosen".
	// -------------------------------------------------------------------------
	const WP_UNSET_DATE = '0000-00-00 00:00:00';

	public function __construct() {
		// CPT
		add_action( 'init', [ $this, 'register_post_type' ], 0 );

		// Meta Boxes
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

		// Save Handlers
		add_action( 'save_post_show', [ $this, 'save_tracklist' ] );
		add_action( 'save_post_show', [ $this, 'auto_fetch_link_titles' ], 20, 2 );

		// ---- Date Enforcement -----------------------------------------------
		// Block saves that lack a real date (server-side guard).
		add_action( 'save_post_show', [ $this, 'enforce_show_date' ], 1 );

		// Strip 'draft' and 'pending' from the status dropdown in the editor.
		add_filter( 'wp_insert_post_data', [ $this, 'block_draft_and_pending_status' ], 10, 2 );

		// Show a persistent notice on legacy draft show posts.
		add_action( 'admin_notices', [ $this, 'legacy_draft_notice' ] );
		// ---------------------------------------------------------------------

		// AJAX Handlers for cross-post transfer
		add_action( 'wp_ajax_get_show_posts',         [ $this, 'ajax_get_show_posts' ] );
		add_action( 'wp_ajax_get_show_tracklist',      [ $this, 'ajax_get_show_tracklist' ] );
		add_action( 'wp_ajax_copy_items_to_show',      [ $this, 'ajax_copy_items_to_show' ] );
		add_action( 'wp_ajax_add_single_item_to_show', [ $this, 'ajax_add_single_item_to_show' ] );

		// AJAX Handler: release _edit_lock immediately on edit-screen unload.
		add_action( 'wp_ajax_release_show_edit_lock', [ $this, 'ajax_release_edit_lock' ] );

		// Assets & Template Button
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );

		// Admin Columns
		add_filter( 'manage_show_posts_columns',       [ $this, 'register_columns' ] );
		add_action( 'manage_show_posts_custom_column', [ $this, 'render_column' ], 10, 2 );

		// Frontend: Auto-add IDs to headings for in-page anchor links
		add_filter( 'the_content', [ $this, 'auto_id_headings' ], 10 );
	}

	public function register_post_type() {
		register_post_type( 'show', [
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
			'supports'            => [ 'title', 'editor', 'markup_markdown', 'thumbnail' ],
			'rewrite'             => true,
		] );
	}

	// =========================================================================
	// DATE ENFORCEMENT
	// =========================================================================

	/**
	 * Returns true when the supplied date string represents a real, set date.
	 * WordPress stores '0000-00-00 00:00:00' for posts that have never had a
	 * date chosen; anything else is treated as intentionally set.
	 */
	private function date_is_set( string $date ): bool {
		return ! empty( $date ) && $date !== self::WP_UNSET_DATE;
	}

	/**
	 * Returns true when the given post is a legacy draft show — i.e. it was
	 * created before date enforcement was introduced and has no real date.
	 *
	 * "Legacy" means it already exists in the database (has an ID > 0) AND its
	 * current saved status is 'draft' AND it has no real post_date set.
	 */
	private function is_legacy_draft( int $post_id ): bool {
		if ( $post_id <= 0 ) return false;
		$post = get_post( $post_id );
		if ( ! $post ) return false;
		return $post->post_status === 'draft' && ! $this->date_is_set( $post->post_date );
	}

	/**
	 * save_post_show — priority 1 (runs before tracklist save).
	 *
	 * Bails the save and redirects back to the edit screen with an error query
	 * arg when a show post is being saved without a real date.
	 *
	 * Exemptions:
	 *  - Autosaves are always allowed through (they don't change the date).
	 *  - REST API saves are blocked like normal saves.
	 *  - Trash / restore transitions are allowed through (the date is irrelevant).
	 *  - Legacy drafts being opened for the first time: the save is blocked but
	 *    the notice explains what to do.
	 */
	public function enforce_show_date( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Allow trash / restore without a date requirement.
		$new_status = $_POST['post_status'] ?? '';
		if ( in_array( $new_status, [ 'trash', 'inherit' ], true ) ) return;

		// Determine the date being submitted.
		// WordPress assembles the post_date from individual date/time fields.
		$submitted_date = $this->build_submitted_date();

		if ( $this->date_is_set( $submitted_date ) ) return; // All good — date present.

		// Also allow if the post already has a real date saved (editing without
		// touching the date picker should not cause a block).
		$saved_post = get_post( $post_id );
		if ( $saved_post && $this->date_is_set( $saved_post->post_date ) ) return;

		// No date — prevent the save by unhooking subsequent save_post_show
		// callbacks so nothing is written, then redirect.
		remove_action( 'save_post_show', [ $this, 'save_tracklist' ] );
		remove_action( 'save_post_show', [ $this, 'auto_fetch_link_titles' ], 20 );

		// Redirect back with an error flag so the admin notice can fire.
		$redirect = add_query_arg(
			[ 'show_date_error' => '1', 'message' => '99' ],
			get_edit_post_link( $post_id, 'url' ) ?: admin_url( 'post.php?post=' . $post_id . '&action=edit' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Reconstruct the submitted post_date from the individual form fields that
	 * WordPress sends, matching what wp_insert_post() assembles internally.
	 * Returns the sentinel value when the fields are absent or unset.
	 */
	private function build_submitted_date(): string {
		$aa = isset( $_POST['aa'] ) ? (int) $_POST['aa'] : 0;
		$mm = isset( $_POST['mm'] ) ? (int) $_POST['mm'] : 0;
		$jj = isset( $_POST['jj'] ) ? (int) $_POST['jj'] : 0;
		$hh = isset( $_POST['hh'] ) ? (int) $_POST['hh'] : 0;
		$mn = isset( $_POST['mn'] ) ? (int) $_POST['mn'] : 0;
		$ss = isset( $_POST['ss'] ) ? (int) $_POST['ss'] : 0;

		if ( ! $aa || ! $mm || ! $jj ) return self::WP_UNSET_DATE;

		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $aa, $mm, $jj, $hh, $mn, $ss );
	}

	/**
	 * wp_insert_post_data filter — strips 'draft' and 'pending' statuses from
	 * show posts before they are written to the database.
	 *
	 * If a new show arrives as 'draft' (e.g. the default for a brand-new post
	 * before the user has set a date), we leave it as-is so the post can be
	 * created in the DB — the enforce_show_date hook above will catch the case
	 * where no date is set and redirect before any tracklist data is saved.
	 *
	 * 'pending' is always converted to 'draft' as a fallback; client-side JS
	 * removes the option from the dropdown so this should rarely fire.
	 *
	 * Legacy draft posts (existing rows without a date) pass through unchanged
	 * so they remain editable; enforce_show_date handles the save-time block.
	 */
	public function block_draft_and_pending_status( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'show' ) return $data;

		// Convert 'pending' → 'draft' (pending is not a valid show state).
		if ( $data['post_status'] === 'pending' ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Admin notice — shown on show edit screens in two situations:
	 *
	 *  1. The save was blocked because no date was set (?show_date_error=1).
	 *  2. A legacy draft show (no date, pre-enforcement) is being edited.
	 *
	 * In both cases the message explains what the user needs to do.
	 */
	public function legacy_draft_notice(): void {
		global $pagenow, $post;

		$is_show_edit = in_array( $pagenow, [ 'post.php', 'post-new.php' ], true )
			&& isset( $post )
			&& $post->post_type === 'show';

		if ( ! $is_show_edit ) return;

		// Case 1 — save was blocked.
		if ( ! empty( $_GET['show_date_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible nagging">';
			echo '<p><strong>Show not saved.</strong> Every show post requires a publish date. ';
			echo 'Use the <strong>Publish</strong> date picker on the right to set one, then save again.</p>';
			echo '</div>';
			return;
		}

		// Case 2 — legacy draft with no date.
		if ( isset( $post->ID ) && $this->is_legacy_draft( $post->ID ) ) {
			echo '<div class="notice notice-warning nagging">';
			echo '<p><strong>This show has no publish date.</strong> ';
			echo 'It was created before date enforcement was introduced. ';
			echo 'Set a date using the <strong>Publish</strong> date picker on the right before saving — ';
			echo 'the save button will remain disabled until a date is chosen.</p>';
			echo '</div>';
		}
	}

	// =========================================================================
	// DATA MIGRATION
	// Single place that converts legacy field names (track_title / track_url)
	// to the canonical names (title / url). Called on every DB read boundary.
	// =========================================================================

	private function migrate_tracklist_data( $items ) {
		if ( ! is_array( $items ) ) return [];

		return array_map( function ( $item ) {
			return [
				'type'            => $item['type'] ?? 'track',
				'title'           => $item['title'] ?? $item['track_title'] ?? '',
				'url'             => $item['url']   ?? $item['track_url']   ?? '',
				'duration'        => $item['duration'] ?? '',
				'link_to_section' => isset( $item['link_to_section'] ) && $item['link_to_section'],
			];
		}, $items );
	}

	// =========================================================================
	// META BOXES
	// =========================================================================

	public function add_meta_boxes() {
		add_meta_box( 'tracklist_meta_box', 'Show Tracklist', [ $this, 'render_tracklist_metabox' ], 'show', 'normal', 'high' );
	}

	// =========================================================================
	// ADMIN COLUMNS
	// =========================================================================

	public function register_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( $key === 'cb' ) {
				$new['cb']             = $label;
				$new['editing_status'] = '<span class="screen-reader-text">Editing Status</span>';
			} else {
				$new[ $key ] = $label;
			}
		}
		return $new;
	}

	public function render_column( $column, $post_id ) {
		if ( $column === 'editing_status' ) {
			$this->render_editing_status( $post_id );
		}
	}

	// -------------------------------------------------------------------------
	// Editing Status Dot
	// -------------------------------------------------------------------------

	const EDIT_LOCK_STALE_SECONDS = 20;

	private function render_editing_status( $post_id ) {
		$lock       = get_post_meta( $post_id, '_edit_lock', true );
		$is_editing = false;
		$label      = 'No active editor';

		if ( $lock ) {
			$parts = explode( ':', $lock, 2 );
			if ( count( $parts ) === 2 && ( time() - (int) $parts[0] ) < self::EDIT_LOCK_STALE_SECONDS ) {
				$is_editing = true;
				$user       = get_userdata( (int) $parts[1] );
				$label      = $user ? esc_attr( $user->display_name ) . ' is editing' : 'Someone is editing';
			}
		}

		printf(
			'<span class="show-edit-dot %s" title="%s"></span>',
			$is_editing ? 'is-editing' : 'is-free',
			esc_attr( $label )
		);
	}

	// =========================================================================
	// TRACKLIST METABOX
	// =========================================================================

	public function render_tracklist_metabox( $post ) {
		$tracklist = get_post_meta( $post->ID, 'tracklist', true ) ?: [];
		$tracklist = $this->migrate_tracklist_data( $tracklist );
		wp_nonce_field( 'save_tracklist_meta', 'tracklist_meta_nonce' );
		$this->render_editor_html( $tracklist, $post->ID );
	}

	/**
	 * Render the tracklist editor HTML.
	 *
	 * Visibility of track-only vs spacer-only elements is handled entirely by
	 * CSS in dashboard.css via the .is-spacer class — no inline styles needed.
	 */
	private function render_editor_html( $items, $post_id ) {
		$items = is_array( $items ) ? $items : [];
		?>
		<div class="tracklist-wrapper" data-post-id="<?php echo esc_attr( $post_id ); ?>">
			<div class="tracklist-items">
				<?php foreach ( $items as $i => $item ):
					$type  = $item['type']  ?? 'track';
					$title = $item['title'] ?? '';
					$url   = $item['url']   ?? '';
					$dur   = $item['duration'] ?? '';
					$link  = $item['link_to_section'] ?? false;
				?>
				<div class="tracklist-row <?php echo $type === 'spacer' ? 'is-spacer' : ''; ?>">
					<span class="drag-handle">|||</span>
					<input type="hidden"  name="tracklist[<?php echo $i; ?>][type]"     value="<?php echo esc_attr( $type );  ?>" class="item-type">
					<input type="text"    name="tracklist[<?php echo $i; ?>][title]"    value="<?php echo esc_attr( $title ); ?>" class="item-title-input"    placeholder="<?php echo $type === 'spacer' ? 'Segment Title...' : 'Artist - Title'; ?>">
					<input type="url"     name="tracklist[<?php echo $i; ?>][url]"      value="<?php echo esc_attr( $url );   ?>" class="item-url-input"      placeholder="URL">
					<input type="text"    name="tracklist[<?php echo $i; ?>][duration]" value="<?php echo esc_attr( $dur );   ?>" class="item-duration-input" placeholder="0:00">
					<label class="link-checkbox-label">
						<input type="checkbox" name="tracklist[<?php echo $i; ?>][link_to_section]" value="1" <?php checked( $link ); ?> class="link-to-section-checkbox">Link
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

	public function save_tracklist( $post_id ) {
		if ( ! isset( $_POST['tracklist_meta_nonce'] ) || ! wp_verify_nonce( $_POST['tracklist_meta_nonce'], 'save_tracklist_meta' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( isset( $_POST['tracklist'] ) && is_array( $_POST['tracklist'] ) ) {
			update_post_meta( $post_id, 'tracklist', $this->sanitize_items( $_POST['tracklist'] ) );
		} else {
			delete_post_meta( $post_id, 'tracklist' );
		}
	}

	/**
	 * Auto-fetch titles for bare URLs in post content on save.
	 * Only runs if the user confirmed via the JavaScript dialog.
	 */
	public function auto_fetch_link_titles( $post_id, $post ) {
		if ( empty( $_POST['fetch_link_titles'] ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$content = $post->post_content;
		if ( empty( $content ) ) return;

		preg_match_all( '/(?<!\]\()\b(https?:\/\/[^\s\)\]<>"\']+)/i', $content, $matches );
		if ( empty( $matches[1] ) ) return;

		$skip_domains = [
			'reddit.com', 'www.reddit.com', 'old.reddit.com',
			'twitter.com', 'x.com',
			'instagram.com', 'facebook.com',
			'tiktok.com', 'linkedin.com', 'pinterest.com',
		];

		$replacements    = [];
		$updated_content = $content;

		foreach ( array_unique( $matches[1] ) as $url ) {
			$url    = rtrim( $url, '.,;:!?)' );
			$parsed = parse_url( $url );
			if ( ! isset( $parsed['host'] ) ) continue;
			if ( in_array( strtolower( $parsed['host'] ), $skip_domains ) ) continue;

			$response = wp_remote_get( $url, [
				'timeout'     => 10,
				'sslverify'   => true,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . '; +' . home_url() . ')',
			] );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) continue;

			$title = $this->extract_title_from_html( wp_remote_retrieve_body( $response ) );
			if ( ! empty( $title ) ) {
				$replacements[ $url ] = '[' . $title . '](' . $url . ')';
			}
		}

		if ( empty( $replacements ) ) return;

		foreach ( $replacements as $url => $link ) {
			$updated_content = str_replace( $url, $link, $updated_content );
		}

		if ( $updated_content === $content ) return;

		remove_action( 'save_post_show', [ $this, 'auto_fetch_link_titles' ], 20 );
		wp_update_post( [ 'ID' => $post_id, 'post_content' => $updated_content ], true );
		add_action( 'save_post_show', [ $this, 'auto_fetch_link_titles' ], 20, 2 );
	}

	/**
	 * Extract page title from HTML.
	 */
	private function extract_title_from_html( $html ) {
		if ( empty( $html ) ) return null;

		$patterns = [
			'/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i',
			'/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i',
			'/<meta\s+content=["\'](.*?)["\']\s+property=["\']og:title["\']/i',
			'/<meta\s+content=["\'](.*?)["\']\s+name=["\']twitter:title["\']/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $m ) ) {
				return html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		if ( preg_match( '/<title>(.*?)<\/title>/is', $html, $m ) ) {
			return html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return null;
	}

	// =========================================================================
	// AJAX HANDLERS
	// =========================================================================

	public function ajax_get_show_posts() {
		check_ajax_referer( 'tracklist_nonce', 'nonce' );

		$current_post_id = isset( $_POST['current_post_id'] ) ? intval( $_POST['current_post_id'] ) : 0;
		$posts           = get_posts( [
			'post_type'      => 'show',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => [ 'publish', 'draft' ],
			'post__not_in'   => [ $current_post_id ],
		] );

		wp_send_json_success( array_map( function ( $post ) {
			return [
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'date'   => get_the_date( 'Y-m-d', $post->ID ),
			];
		}, $posts ) );
	}

	public function ajax_get_show_tracklist() {
		check_ajax_referer( 'tracklist_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id || get_post_type( $post_id ) !== 'show' ) {
			wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
		}

		$tracklist = get_post_meta( $post_id, 'tracklist', true ) ?: [];
		wp_send_json_success( $this->migrate_tracklist_data( $tracklist ) );
	}

	public function ajax_copy_items_to_show() {
		check_ajax_referer( 'tracklist_nonce', 'nonce' );

		$target_id = isset( $_POST['target_post_id'] ) ? intval( $_POST['target_post_id'] ) : 0;
		$items     = $_POST['items'] ?? [];

		if ( ! $target_id || get_post_type( $target_id ) !== 'show' ) {
			wp_send_json_error( [ 'message' => 'Invalid target post' ] );
		}
		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to edit this show' ] );
		}

		$existing  = $this->migrate_tracklist_data( get_post_meta( $target_id, 'tracklist', true ) ?: [] );
		$new_items = $this->sanitize_items( $items );

		update_post_meta( $target_id, 'tracklist', array_merge( $existing, $new_items ) );
		wp_send_json_success( [ 'message' => 'Items copied successfully', 'count' => count( $new_items ) ] );
	}

	public function ajax_add_single_item_to_show() {
		check_ajax_referer( 'tracklist_nonce', 'nonce' );

		$target_id = isset( $_POST['target_post_id'] ) ? intval( $_POST['target_post_id'] ) : 0;
		$item      = $_POST['item'] ?? [];

		if ( ! $target_id || get_post_type( $target_id ) !== 'show' ) {
			wp_send_json_error( [ 'message' => 'Invalid target post' ] );
		}
		if ( ! current_user_can( 'edit_post', $target_id ) ) {
			wp_send_json_error( [ 'message' => 'You do not have permission to edit this show' ] );
		}

		$sanitized = $this->sanitize_items( [ $item ] );
		if ( empty( $sanitized ) ) wp_send_json_error( [ 'message' => 'Invalid item data' ] );

		$existing   = $this->migrate_tracklist_data( get_post_meta( $target_id, 'tracklist', true ) ?: [] );
		$existing[] = $sanitized[0];

		update_post_meta( $target_id, 'tracklist', $existing );
		wp_send_json_success( [ 'message' => 'Item added successfully' ] );
	}

	/**
	 * Release _edit_lock for a show post immediately.
	 */
	public function ajax_release_edit_lock() {
		check_ajax_referer( 'tracklist_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id || get_post_type( $post_id ) !== 'show' || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		$lock  = get_post_meta( $post_id, '_edit_lock', true );
		$parts = $lock ? explode( ':', $lock, 2 ) : [];

		if ( isset( $parts[1] ) && (int) $parts[1] === get_current_user_id() ) {
			delete_post_meta( $post_id, '_edit_lock' );
		}

		wp_send_json_success();
	}

	// =========================================================================
	// SANITIZATION
	// =========================================================================

	private function sanitize_items( $items ) {
		$clean = [];
		foreach ( $items as $item ) {
			$type  = sanitize_text_field( $item['type']  ?? 'track' );
			$title = sanitize_text_field( $item['title'] ?? '' );

			if ( empty( $title ) && $type !== 'spacer' ) continue;

			$clean[] = [
				'type'            => $type,
				'title'           => $title,
				'duration'        => sanitize_text_field( $item['duration'] ?? '' ),
				'url'             => esc_url_raw( $item['url'] ?? '' ),
				'link_to_section' => isset( $item['link_to_section'] ) && $item['link_to_section'] === '1',
			];
		}
		return $clean;
	}

	// =========================================================================
	// ASSETS
	// =========================================================================

	public function enqueue_assets( $hook ) {
		$is_show_edit = ( $hook === 'post.php' || $hook === 'post-new.php' ) && get_post_type() === 'show';
		if ( ! $is_show_edit ) return;

		// Shared utilities
		wp_enqueue_script( 'theme-utils', get_stylesheet_directory_uri() . '/js/utils.js', [ 'jquery' ], '1.0.0', true );

		// Tracklist editor
		wp_enqueue_script( 'tracklist-js', get_theme_file_uri() . '/js/tracklist.js', [ 'jquery', 'jquery-ui-sortable', 'theme-utils' ], '8.0.0', true );
		wp_localize_script( 'tracklist-js', 'tracklistSettings', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'tracklist_nonce' ),
			'user_id'  => get_current_user_id(),
		] );

		// Release _edit_lock on navigate-away via sendBeacon.
		wp_add_inline_script( 'tracklist-js', sprintf(
			'window.addEventListener("beforeunload", function() {
				var postId = document.getElementById("post_ID");
				if (!postId || !postId.value) return;
				var data = new FormData();
				data.append("action", "release_show_edit_lock");
				data.append("post_id", postId.value);
				data.append("nonce", tracklistSettings.nonce);
				navigator.sendBeacon(%s, data);
			});',
			wp_json_encode( admin_url( 'admin-ajax.php' ) )
		) );

		// Template loader button
		wp_enqueue_script( 'show-template-button', get_stylesheet_directory_uri() . '/js/show-template-button.js', [ 'jquery', 'theme-utils' ], '3.0.0', true );
		wp_localize_script( 'show-template-button', 'showTemplate', [
			'title'   => "Chris & Jesse: " . date( 'F j Y' ),
			'body'    => "### In The Cinema\n[*What's On at Huski Pics?*](https://huskipics.com.au/movies/now-showing/)\n\n[*Global box office top 10 (replace placeholder link with latest headline)*](https://www.screendaily.com/box-office/box-office-reports/international)\n\n### The Pin Drop\n[*YouTube global music top 10*](https://charts.youtube.com/charts/TopSongs/global/weekly)\n\n*Chris' personal picks last week*\n\n### Walking On Thin Ice\n\n### One Up\n\n### One Up (More)",
			'spacers' => [
				'In The Cinema',
				'The Pin Drop',
				'Walking On Thin Ice',
				'One Up',
				'One Up (More)'
			]
		] );

		// Auto-link-title fetcher
		wp_enqueue_script( 'fetch-link-titles', get_stylesheet_directory_uri() . '/js/fetch-link-titles.js', [ 'jquery', 'theme-utils' ], '1.0.0', true );

		// Date enforcement — disable publish/submit until a date is set.
		wp_enqueue_script(
			'show-date-enforcement',
			get_stylesheet_directory_uri() . '/js/show-date-enforcement.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);

		// Pass through whether this post is a legacy draft so JS can adjust
		// its initial state without having to infer it from the DOM alone.
		$post_id        = get_the_ID();
		$is_legacy      = $this->is_legacy_draft( $post_id ?: 0 );
		$has_date       = $post_id && $this->date_is_set( get_post( $post_id )->post_date ?? '' );

		wp_localize_script( 'show-date-enforcement', 'showDateEnforcement', [
			'isLegacyDraft' => $is_legacy,
			'hasDate'       => $has_date,
			'unsetDate'     => self::WP_UNSET_DATE,
		] );
	}

	// =========================================================================
	// FRONTEND
	// =========================================================================

	public function auto_id_headings( $content ) {
		if ( ! is_singular( 'show' ) || is_admin() ) return $content;

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		for ( $i = 1; $i <= 6; $i++ ) {
			foreach ( $dom->getElementsByTagName( 'h' . $i ) as $tag ) {
				if ( ! $tag->hasAttribute( 'id' ) ) {
					$tag->setAttribute( 'id', sanitize_title( $tag->textContent ) );
				}
			}
		}
		return $dom->saveHTML();
	}
}