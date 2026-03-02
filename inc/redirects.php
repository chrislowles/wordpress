<?php
/**
 * Class: Redirect Manager
 * Handles the 'Redirect' Custom Post Type and frontend redirection logic.
 */
class ChrisLowles_Redirects {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_redirect', [ $this, 'save_meta' ] );

        // Admin Columns
        add_filter( 'manage_redirect_posts_columns', [ $this, 'custom_columns' ] );
        add_action( 'manage_redirect_posts_custom_column', [ $this, 'custom_column_content' ], 10, 2 );
        add_filter( 'manage_edit-redirect_sortable_columns', [ $this, 'sortable_columns' ]);
        add_action( 'pre_get_posts', [ $this, 'orderby' ]);

        // Search
        add_filter( 'posts_join',     [ $this, 'search_join' ] );
        add_filter( 'posts_where',    [ $this, 'search_where' ] );
        add_filter( 'posts_distinct', [ $this, 'search_distinct' ] );

        // Frontend Execution
        add_action( 'template_redirect', [ $this, 'handle_redirects' ], 1 );
    }

    public function register_post_type() {
        register_post_type( 'redirect', [
            'labels' => [
                'name'          => 'Redirects',
                'singular_name' => 'Redirect',
                'menu_name'     => 'Redirects',
                'add_new_item'  => 'Add New Redirect',
                'search_items'  => 'Search Redirects',
                'not_found'     => 'No redirects found.',
            ],
            'public'    => false,
            'show_ui'   => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-admin-links',
            'supports'  => ['title'],
        ] );
    }

    public function add_meta_boxes () {
        add_meta_box( 'redirect_details', 'Redirect Details', [ $this, 'render_meta_box' ], 'redirect', 'normal', 'high' );
    }

    public function render_meta_box ( $post ) {
        wp_nonce_field( 'save_redirect_meta', 'redirect_meta_nonce' );
        $path = get_post_meta( $post->ID, '_redirect_path', true );
        $url  = get_post_meta( $post->ID, '_redirect_url', true );
        $desc = get_post_meta( $post->ID, '_redirect_description', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label>Path</label></th>
                <td>
                    <input type="text" name="redirect_path" value="<?php echo esc_attr( $path ); ?>" class="widefat" placeholder="example-path">
                    <p class="description">Redirects from: <?php echo home_url( '/' ); ?><strong><?php echo esc_html( $path ); ?></strong></p>
                </td>
            </tr>
            <tr>
                <th><label>Target URL</label></th>
                <td><input type="url" name="redirect_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" required></td>
            </tr>
            <tr>
                <th><label>Description</label></th>
                <td><textarea name="redirect_description" rows="3" class="widefat"><?php echo esc_textarea( $desc ); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta ( $post_id ) {
        if ( !isset( $_POST[ 'redirect_meta_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'redirect_meta_nonce' ], 'save_redirect_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST[ 'redirect_path' ] ) )        update_post_meta( $post_id, '_redirect_path',        ltrim( sanitize_text_field( $_POST[ 'redirect_path' ] ), '/' ) );
        if ( isset( $_POST[ 'redirect_url' ] ) )         update_post_meta( $post_id, '_redirect_url',         esc_url_raw( $_POST[ 'redirect_url' ] ) );
        if ( isset( $_POST[ 'redirect_description' ] ) ) update_post_meta( $post_id, '_redirect_description', sanitize_textarea_field( $_POST[ 'redirect_description' ] ) );
    }

    // =========================================================================
    // ADMIN COLUMNS
    // =========================================================================

    public function custom_columns ( $columns ) {
        return [
            'cb'                   => $columns[ 'cb' ],
            'title'                => 'Title',
            'redirect_path'        => 'Path',
            'redirect_url'         => 'Destination',
            'redirect_description' => 'Description',
        ];
    }

    public function custom_column_content ( $column, $post_id ) {
        if ( $column === 'redirect_path' )        echo '<code>/' . esc_html( get_post_meta( $post_id, '_redirect_path', true ) ) . '</code>';
        if ( $column === 'redirect_url' )         echo esc_html( get_post_meta( $post_id, '_redirect_url', true ) );
        if ( $column === 'redirect_description' ) echo esc_html( get_post_meta( $post_id, '_redirect_description', true ) );
    }

    public function sortable_columns ( $c ) {
        $c[ 'redirect_path' ] = 'redirect_path';
        $c[ 'redirect_url' ]  = 'redirect_url';
        return $c;
    }

    public function orderby ( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'orderby' ) === 'redirect_path' ) { $query->set( 'meta_key', '_redirect_path' ); $query->set( 'orderby', 'meta_value' ); }
        if ( $query->get( 'orderby' ) === 'redirect_url' )  { $query->set( 'meta_key', '_redirect_url' );  $query->set( 'orderby', 'meta_value' ); }
    }

    // =========================================================================
    // SEARCH — meta field inclusion
    // =========================================================================

    /**
     * True only when we are on the Redirects list screen with an active search.
     * Replaces the identical four-condition guard that was duplicated across
     * search_join(), search_where(), and search_distinct().
     */
    private function is_redirect_search_screen (): bool {
        global $pagenow;
        return is_admin()
            && $pagenow === 'edit.php'
            && isset($_GET['post_type'])
            && $_GET['post_type'] === 'redirect'
            && !empty($_GET['s']);
    }

    public function search_join ( $join ) {
        if ( ! $this->is_redirect_search_screen() ) return $join;
        global $wpdb;
        return $join . " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ";
    }

    public function search_where ( $where ) {
        if (!$this->is_redirect_search_screen()) return $where;
        global $wpdb;
        $s = esc_sql( $wpdb->esc_like( $_GET['s'] ) );
        return preg_replace(
            "/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "({$wpdb->posts}.post_title LIKE $1) OR ({$wpdb->postmeta}.meta_key IN ('_redirect_path','_redirect_url','_redirect_description') AND {$wpdb->postmeta}.meta_value LIKE '%{$s}%')",
            $where
        );
    }

    public function search_distinct ( $distinct ) {
        return $this->is_redirect_search_screen() ? 'DISTINCT' : $distinct;
    }

    // =========================================================================
    // FRONTEND REDIRECT EXECUTION
    // =========================================================================

    public function handle_redirects () {
        if ( is_admin() ) return;
        $path = trim( parse_url( $_SERVER[ 'REQUEST_URI' ], PHP_URL_PATH ), '/');
        if ( empty( $path ) ) return;

        $query = new WP_Query(
            [
                'post_type'      => 'redirect',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key' => '_redirect_path',
                        'value' => $path,
                        'compare' => '='
                    ]
                ],
            ]
        );

        if ( $query->have_posts() ) {
            $query->the_post();
            $url = get_post_meta( get_the_ID(), '_redirect_url', true );
            if ($url) { wp_redirect( $url, 301 ); exit; }
        }
        wp_reset_postdata();
    }
}