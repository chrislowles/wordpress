<!-- parts/banner-latest-show.php -->
<?php
    $latest_show = get_posts( [
        'post_type'      => 'show',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );

    if ( empty( $latest_show ) ) return;

    $show      = $latest_show[0];
    $permalink = get_permalink( $show->ID );
    $title     = $show->post_title;
    $date      = get_the_date( get_option( 'date_format' ), $show->ID );
?>
<div class="alert alert-dark d-flex align-items-center justify-content-between gap-3 mb-4 py-2 px-3" role="alert">
    <div class="d-flex align-items-center gap-2 overflow-hidden">
        <span class="badge bg-secondary text-uppercase fw-semibold flex-shrink-0" style="font-size: 0.65rem; letter-spacing: 0.05em;">Latest Show</span>
        <a href="<?php echo esc_url( $permalink ); ?>" class="text-reset text-decoration-none fw-semibold text-truncate" title="<?php echo esc_attr( $title ); ?>">
            <?php echo esc_html( $title ); ?>
        </a>
        <span class="text-muted small flex-shrink-0 d-none d-sm-inline"><?php echo esc_html( $date ); ?></span>
    </div>
    <a href="<?php echo esc_url( $permalink ); ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
        View &rarr;
    </a>
</div>
<!-- /parts/banner-latest-show.php -->