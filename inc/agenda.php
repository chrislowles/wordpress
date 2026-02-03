<?php
/**
 * Class: Agenda Widget
 * Adds a collaborative scratchpad to Dashboard and Post Edit screens.
 */
class ChrisLowles_Agenda {

    public function __construct() {
        add_action('admin_init', [$this, 'init_widgets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('heartbeat_received', [$this, 'handle_heartbeat'], 10, 2);
        add_action('wp_ajax_save_agenda', [$this, 'ajax_save']);
    }

    public function init_widgets() {
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget('agenda_scratchpad_widget', 'Agenda Scratchpad', [$this, 'render']);
        });

        add_action('add_meta_boxes', function() {
            add_meta_box('agenda_scratchpad_metabox', 'Agenda Scratchpad', [$this, 'render'], 'show', 'side', 'high');
        });
    }

    public function render() {
        $content = get_option('agenda_scratchpad_content', '');
        ?>
        <div id="agenda-wrapper" style="position: relative;">
            <div id="agenda-lock-overlay" class="hidden">
                <div class="lock-message"><span id="lock-owner-name"></span> is editing.</div>
            </div>
            <textarea id="agenda-content" class="widefat" rows="10" placeholder="Type notes here..."><?php echo esc_textarea($content); ?></textarea>
            <div class="agenda-controls">
                <button type="button" id="agenda-save" class="button button-primary">Save</button>
                <span id="agenda-status" class="spinner-inline"></span>
            </div>
        </div>
        <?php
    }

    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['index.php', 'post.php', 'post-new.php'])) return;

        wp_enqueue_script('agenda-js', get_stylesheet_directory_uri() . '/js/agenda.js', ['jquery', 'heartbeat'], '1.0.0', true);
        wp_localize_script('agenda-js', 'agendaSettings', [
            'user_id' => get_current_user_id(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('agenda_save_nonce')
        ]);
    }

    public function handle_heartbeat($response, $data) {
        if (empty($data['agenda_check'])) return $response;

        $user_id = get_current_user_id();
        $lock = get_transient('agenda_scratchpad_lock');
        $is_editing = !empty($data['agenda_is_editing']);

        if ($lock && $lock['user_id'] != $user_id) {
            $user_info = get_userdata($lock['user_id']);
            $response['agenda_status'] = 'locked';
            $response['agenda_owner'] = $user_info ? $user_info->display_name : 'Another user';
            $response['agenda_content'] = get_option('agenda_scratchpad_content', '');
        } else {
            if ($is_editing) {
                set_transient('agenda_scratchpad_lock', ['user_id' => $user_id, 'time' => time()], 30);
                $response['agenda_status'] = 'owned';
            } else {
                $response['agenda_status'] = 'free';
            }
        }
        return $response;
    }

    public function ajax_save() {
        check_ajax_referer('agenda_save_nonce', 'nonce');
        $user_id = get_current_user_id();
        $lock = get_transient('agenda_scratchpad_lock');

        if ($lock && $lock['user_id'] != $user_id) wp_send_json_error(['message' => 'Locked by another user.']);

        if (isset($_POST['content'])) {
            update_option('agenda_scratchpad_content', wp_kses_post($_POST['content']));
            wp_send_json_success(['message' => 'Saved!']);
        }
    }
}