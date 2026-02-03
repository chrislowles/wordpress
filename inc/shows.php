<?php
/**
 * Class: Shows Manager
 * Handles the 'Show' Custom Post Type, Tracklists (Global & Local), 
 * Heartbeat Locking, and Template Buttons.
 */
class ChrisLowles_Shows {

    public function __construct() {
        // CPT
        add_action('init', [$this, 'register_post_type'], 0);
        
        // Meta Boxes & Widgets
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Save Handlers
        add_action('save_post_show', [$this, 'save_local_tracklist']);
        add_action('wp_ajax_save_global_tracklist', [$this, 'ajax_save_global_tracklist']);
        
        // Locking Logic
        add_filter('heartbeat_received', [$this, 'handle_heartbeat'], 10, 2);
        
        // Assets & Template Button
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        
        // Frontend Formatting (Auto IDs)
        add_filter('the_content', [$this, 'auto_id_headings'], 10);
    }

    public function register_post_type() {
        register_post_type('show', array(
            'labels' => array(
                'name' => 'Shows',
                'singular_name' => 'Show',
                'menu_name' => 'Shows',
                'all_items' => 'All Shows',
                'add_new' => 'Add New',
                'edit_item' => 'Edit Show',
                'not_found' => 'No shows found.',
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-playlist-audio',
            'supports' => array('title', 'editor', 'markup_markdown', 'thumbnail'),
            'show_in_rest' => true,
            'menu_position' => 21,
            'exclude_from_search' => true,
        ));
    }

    // =========================================================================
    // META BOXES & DASHBOARD
    // =========================================================================

    public function add_meta_boxes() {
        // Local Tracklist (Main Editor)
        add_meta_box('tracklist_meta_box', 'Show Tracklist (Local)', [$this, 'render_local_metabox'], 'show', 'normal', 'high');
        // Global Reference (Sidebar)
        add_meta_box('global_tracklist_widget', 'Show Tracklist (Global)', [$this, 'render_global_widget'], 'show', 'side', 'default');
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget('global_tracklist_dashboard', 'Show Tracklist (Global)', [$this, 'render_global_widget']);
    }

    public function render_local_metabox($post) {
        $tracklist = get_post_meta($post->ID, 'tracklist', true) ?: [];
        wp_nonce_field('save_tracklist_meta', 'tracklist_meta_nonce');
        // Show transfer buttons = true
        $this->render_editor_html($tracklist, 'post', false, '', true);
    }

    public function render_global_widget() {
        $tracklist = get_option('global_tracklist_data', []);
        $user_id = get_current_user_id();
        $lock = get_transient('global_tracklist_lock');
        
        $is_locked = ($lock && $lock['user_id'] != $user_id);
        $owner = $is_locked ? (get_userdata($lock['user_id'])->display_name ?? 'Another user') : '';

        // Only show transfer buttons on show edit screen
        global $pagenow, $post;
        $show_transfer = ($pagenow === 'post.php' || $pagenow === 'post-new.php') && isset($post) && $post->post_type === 'show';

        $this->render_editor_html($tracklist, 'global', $is_locked, $owner, $show_transfer);
    }

    /**
     * Shared HTML Renderer for Tracklists
     */
    private function render_editor_html($tracks, $scope, $locked, $owner, $show_transfer) {
        $tracks = is_array($tracks) ? $tracks : [];
        $prefix = ($scope === 'global') ? 'global_tracklist' : 'tracklist';
        $wrapper_class = ($scope === 'global') ? 'tracklist-wrapper is-global' : 'tracklist-wrapper is-local';
        $disabled = $locked ? 'disabled' : '';
        
        // include get_stylesheet_directory() . '/parts/admin-tracklist-editor.php';
        ?>
        <div class="<?php echo esc_attr($wrapper_class); ?>" 
             data-scope="<?php echo esc_attr($scope); ?>" 
             data-allow-transfer="<?php echo $show_transfer ? '1' : '0'; ?>"
             style="position: relative;">

            <?php if ($scope === 'global'): ?>
                <div class="tracklist-lock-overlay <?php echo $locked ? '' : 'hidden'; ?>">
                    <div class="lock-message"><span class="dashicons dashicons-lock"></span> <?php echo esc_html($owner); ?> is editing.</div>
                </div>
            <?php endif; ?>
            <div class="tracklist-items">
                <?php foreach ($tracks as $i => $item): 
                     $type = $item['type'] ?? 'track';
                     $title = $item['track_title'] ?? '';
                     $url = $item['track_url'] ?? '';
                     $dur = $item['duration'] ?? '';
                     $link = $item['link_to_section'] ?? false;
                ?>
                <div class="track-row <?php echo $type === 'spacer' ? 'is-spacer' : ''; ?>">

                    <span class="drag-handle">|||</span>

                    <input type="hidden" name="<?php echo $prefix; ?>[<?php echo $i; ?>][type]" value="<?php echo esc_attr($type); ?>" class="track-type">

                    <input type="text" name="<?php echo $prefix; ?>[<?php echo $i; ?>][track_title]" value="<?php echo esc_attr($title); ?>" class="track-title-input" <?php echo $disabled; ?> placeholder="<?php echo $type === 'spacer' ? 'Segment Title...' : 'Title'; ?>">
                    <input type="url" name="<?php echo $prefix; ?>[<?php echo $i; ?>][track_url]" value="<?php echo esc_attr($url); ?>" class="track-url-input" style="<?php echo $type === 'spacer' ? 'display:none' : ''; ?>" <?php echo $disabled; ?> placeholder="URL">
                    <input type="text" name="<?php echo $prefix; ?>[<?php echo $i; ?>][duration]" value="<?php echo esc_attr($dur); ?>" class="track-duration-input" style="width:60px;<?php echo $type === 'spacer' ? 'display:none' : ''; ?>" <?php echo $disabled; ?> placeholder="0:00">
                    
                    <label class="link-checkbox-label" style="<?php echo $type === 'spacer' ? '' : 'display:none'; ?>">
                        <input type="checkbox" name="<?php echo $prefix; ?>[<?php echo $i; ?>][link_to_section]" value="1" <?php checked($link); ?> class="link-to-section-checkbox" <?php echo $disabled; ?>> Link
                    </label>

                    <button type="button" class="fetch-duration button" style="<?php echo $type === 'spacer' ? 'display:none' : ''; ?>" <?php echo $disabled; ?>>Fetch</button>

                    <?php if ($show_transfer): ?>
                        <button type="button" class="transfer-track button" data-target-scope="<?php echo $scope === 'global' ? 'post' : 'global'; ?>" <?php echo $disabled; ?>><?php echo $scope === 'global' ? 'To Local' : 'To Global'; ?></button>
                    <?php endif; ?>

                    <button type="button" class="remove-track button" <?php echo $disabled; ?>>Delete</button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="tracklist-controls">
                <span class="total-duration-display">0:00</span>
                <button type="button" class="add-track button" <?php echo $disabled; ?>>+ Track</button>
                <button type="button" class="add-spacer button" <?php echo $disabled; ?>>+ Spacer</button>
                <?php if ($show_transfer): ?>
                    <button type="button" class="copy-all-to-<?php echo $scope === 'global' ? 'local' : 'global'; ?> button">All &rarr; <?php echo $scope === 'global' ? 'Local' : 'Global'; ?></button>
                <?php endif; ?>
                <span class="youtube-playlist-container"></span>
                <?php if ($scope === 'global'): ?>
                    <div class="global-actions">
                        <span class="spinner-inline global-spinner"></span>
                        <button type="button" class="button button-primary global-save-btn" <?php echo $disabled; ?>>Save</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // SAVING & AJAX
    // =========================================================================

    public function save_local_tracklist($post_id) {
        if (!isset($_POST['tracklist_meta_nonce']) || !wp_verify_nonce($_POST['tracklist_meta_nonce'], 'save_tracklist_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['tracklist']) && is_array($_POST['tracklist'])) {
            $sanitized = $this->sanitize_tracks($_POST['tracklist']);
            update_post_meta($post_id, 'tracklist', $sanitized);
        } else {
            delete_post_meta($post_id, 'tracklist');
        }
    }

    public function ajax_save_global_tracklist() {
        check_ajax_referer('global_tracklist_nonce', 'nonce');
        $user_id = get_current_user_id();
        $lock = get_transient('global_tracklist_lock');
        
        if ($lock && $lock['user_id'] != $user_id) wp_send_json_error(['message' => 'Locked by another user.']);
        
        $data = isset($_POST['data']) ? $this->sanitize_tracks($_POST['data']) : [];
        update_option('global_tracklist_data', $data);
        wp_send_json_success(['message' => 'Global list saved.']);
    }

    private function sanitize_tracks($tracks) {
        $clean = [];
        foreach ($tracks as $t) {
            if (empty($t['track_title']) && $t['type'] !== 'spacer') continue;
            $clean[] = [
                'type' => sanitize_text_field($t['type'] ?? 'track'),
                'track_title' => sanitize_text_field($t['track_title']),
                'duration' => sanitize_text_field($t['duration'] ?? ''),
                'track_url' => esc_url_raw($t['track_url'] ?? ''),
                'link_to_section' => isset($t['link_to_section']) && $t['link_to_section'] == '1',
            ];
        }
        return $clean;
    }

    public function handle_heartbeat($response, $data) {
        if (empty($data['global_tl_check'])) return $response;
        
        $user_id = get_current_user_id();
        $lock = get_transient('global_tracklist_lock');
        $is_editing = !empty($data['global_tl_editing']);
        
        if ($lock && $lock['user_id'] != $user_id) {
            $user_info = get_userdata($lock['user_id']);
            $response['global_tl_status'] = 'locked';
            $response['global_tl_owner'] = $user_info ? $user_info->display_name : 'Someone';
            $response['global_tl_data'] = get_option('global_tracklist_data', []);
        } else {
            if ($is_editing) {
                set_transient('global_tracklist_lock', ['user_id' => $user_id, 'time' => time()], 30);
                $response['global_tl_status'] = 'owned';
            } else {
                $response['global_tl_status'] = 'free';
            }
        }
        return $response;
    }

    // =========================================================================
    // ASSETS & HELPERS
    // =========================================================================

    public function enqueue_assets($hook) {
        $is_dashboard = ($hook === 'index.php');
        $is_show_edit = ($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'show';

        // 1. Tracklist JS
        if ($is_dashboard || $is_show_edit) {
            wp_enqueue_script('tracklist-js', get_theme_file_uri() . '/js/tracklist.js', ['jquery', 'jquery-ui-sortable', 'heartbeat'], '3.3.1', true);
            wp_localize_script('tracklist-js', 'tracklistSettings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('global_tracklist_nonce'),
                'user_id' => get_current_user_id()
            ]);
        }

        // 2. Template Button JS (Merged from inc/show-template-button.php)
        if ($is_show_edit) {
            wp_enqueue_script('show-template-button', get_stylesheet_directory_uri() . '/js/show-template-button.js', ['jquery'], '1.0.0', true);
            wp_localize_script('show-template-button', 'showTemplate', [
                'title' => "CNJ " . date('Y-m-d'),
                'body' => "### In The Cinema\n* []()\n\n### The Pin Drop\n* []()\n\n### Walking on Thin Ice\n* []()\n\n### One Up P1\n* []()\n\n### One Up P2\n* []()"
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