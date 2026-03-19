<!-- entry-meta.php -->
<div class="d-flex align-items-center gap-2 small text-secondary mb-3">
    <span class="author">
        <?php the_author_posts_link(); ?>
    </span>
    <div class="vr"></div>
    <span class="date">
        <a href="<?php the_permalink(); ?>" class="text-decoration-none text-secondary" title="<?php echo esc_attr( get_the_date() ); ?>">
            <?php the_time( get_option( 'date_format' ) ); ?>
        </a>
    </span>
</div>
<!-- /entry-meta.php -->