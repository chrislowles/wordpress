<?php
/**
 * Class: Scratchpad Widget
 * Adds a collaborative scratchpad to Dashboard and Post Edit screens.
 */
class ChrisLowles_Scratchpad {

    public function __construct() {
        add_action('admin_init', [$this, 'init_widgets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('heartbeat_received', [$this, 'handle_heartbeat'], 10, 2);
        add_action('wp_ajax_save_scratchpad', [$this, 'ajax_save']);
    }

    public function init_widgets() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget('scratchpad_widget', 'Scratchpad', [$this, 'render']);
        });

        add_action('add_meta_boxes', function() {
            add_meta_box('scratchpad_metabox', 'Scratchpad', [$this, 'render'], 'show', 'side', 'high');
        });
    }

    public function render() {
        $content = get_option('scratchpad_content', '');
        ?>
        <div id="scratchpad-wrapper" style="position: relative;">
            <div id="scratchpad-lock-overlay" class="hidden">
                <div class="lock-message"><span id="lock-owner-name"></span> is editing.</div>
            </div>
            <textarea id="scratchpad-content" class="widefat" rows="10" placeholder="Type notes here..."><?php echo esc_textarea($content); ?></textarea>
            <div class="scratchpad-controls">
                <button type="button" id="scratchpad-save" class="button button-primary">Save</button>
                <span id="scratchpad-status" class="spinner-inline"></span>
            </div>
        </div>
        <?php
    }

    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['index.php', 'post.php', 'post-new.php'])) return;

        wp_enqueue_script('scratchpad-js', get_stylesheet_directory_uri() . '/js/scratchpad.js', ['jquery', 'heartbeat'], '1.0.0', true);
        wp_localize_script('scratchpad-js', 'scratchpadSettings', [
            'user_id'  => get_current_user_id(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('scratchpad_save_nonce')
        ]);
    }

    public function handle_heartbeat($response, $data) {
        if (empty($data['scratchpad_check'])) return $response;

        $user_id = get_current_user_id();
        $lock = get_transient('scratchpad_lock');
        $is_editing = !empty($data['scratchpad_is_editing']);

        if ($lock && $lock['user_id'] != $user_id) {
            $user_info = get_userdata($lock['user_id']);
            $response['scratchpad_status'] = 'locked';
            $response['scratchpad_owner'] = $user_info ? $user_info->display_name : 'Another user';
            $response['scratchpad_content'] = get_option('scratchpad_content', '');
        } else {
            if ($is_editing) {
                set_transient('scratchpad_lock', ['user_id' => $user_id, 'time' => time()], 30);
                $response['scratchpad_status'] = 'owned';
            } else {
                $response['scratchpad_status'] = 'free';
            }
        }
        return $response;
    }

    public function ajax_save() {
        check_ajax_referer('agenda_save_nonce', 'nonce');
        $user_id = get_current_user_id();
        $lock = get_transient('scratchpad_lock');

        if ($lock && $lock['user_id'] != $user_id) wp_send_json_error(['message' => 'Locked by another user.']);

        if (isset($_POST['content'])) {
            update_option('scratchpad_content', wp_kses_post($_POST['content']));
            wp_send_json_success(['message' => 'Saved!']);
        }
    }
}