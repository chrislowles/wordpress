<?php
/**
 * Class: Page Redirect Manager
 * Pages can have an optional redirect URL. If "auto-redirect" is enabled,
 * the visitor is sent there immediately. If a URL is set but auto-redirect
 * is off, a notice is shown above the page content instead.
 *
 * Password-protected pages:
 *   When a page is password-protected AND auto-redirect is enabled, the
 *   password form is shown first. The redirect fires only after the visitor
 *   successfully authenticates — the destination URL is never exposed to
 *   unauthenticated visitors. Once authenticated, the redirect fires on the
 *   next request as normal.
 *
 *   When a page is password-protected AND auto-redirect is disabled, the
 *   redirect URL notice in the page content is also gated behind the
 *   password form (handled in parts/content-page.php).
 */
class ChrisLowles_PageRedirects {

    public function __construct() {
        add_action( 'add_meta_boxes',    [ $this, 'add_meta_box' ] );
        add_action( 'save_post_page',    [ $this, 'save_meta' ] );
        add_action( 'template_redirect', [ $this, 'handle_redirect' ], 1 );
    }

    public function add_meta_box() {
        add_meta_box(
            'page_redirect',
            'Redirect',
            [ $this, 'render_meta_box' ],
            'page',
            'side',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'save_page_redirect_meta', 'page_redirect_nonce' );
        $enabled      = get_post_meta( $post->ID, '_redirect_enabled', true );
        $url          = get_post_meta( $post->ID, '_redirect_url',     true );
        $is_protected = ! empty( $post->post_password );
        ?>
        <p>
            <label for="redirect_url"><strong>Redirect URL</strong></label><br>
            <input type="url" name="redirect_url" id="redirect_url"
                   value="<?php echo esc_attr( $url ); ?>"
                   class="widefat" placeholder="https://..." />
        </p>
        <p>
            <label>
                <input type="checkbox" name="redirect_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
                Automatically redirect visitors
            </label>
        </p>
        <?php if ( $is_protected && $enabled && $url ) : ?>
            <p class="description" style="color: #996800; background: #fcf9e8; border: 1px solid #e5c93a; border-radius: 3px; padding: 6px 8px; margin-top: 4px;">
                &#x1F512; This page is password-protected. Visitors will see the password form first and be redirected only after authenticating.
            </p>
        <?php elseif ( $is_protected && ! $enabled && $url ) : ?>
            <p class="description" style="color: #996800; background: #fcf9e8; border: 1px solid #e5c93a; border-radius: 3px; padding: 6px 8px; margin-top: 4px;">
                &#x1F512; This page is password-protected. The redirect link will only be visible to visitors after they authenticate.
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['page_redirect_nonce'] ) || ! wp_verify_nonce( $_POST['page_redirect_nonce'], 'save_page_redirect_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        update_post_meta( $post_id, '_redirect_enabled',
            ( isset( $_POST['redirect_enabled'] ) && $_POST['redirect_enabled'] === '1' ) ? '1' : ''
        );
        update_post_meta( $post_id, '_redirect_url',
            isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : ''
        );
    }

    public function handle_redirect() {
        if ( ! is_page() ) return;

        global $post;
        if ( ! $post ) return;

        // Don't redirect password-protected pages before the visitor has
        // authenticated. post_password_required() returns false once the
        // correct password cookie is present, so the redirect will fire
        // on the next request after a successful authentication.
        if ( post_password_required( $post ) ) return;

        if ( ! get_post_meta( $post->ID, '_redirect_enabled', true ) ) return;

        $url = get_post_meta( $post->ID, '_redirect_url', true );
        if ( ! $url ) return;

        wp_redirect( $url, 301 );
        exit;
    }
}