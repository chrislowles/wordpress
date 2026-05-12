<!-- entry-meta.php -->
<div class="d-flex align-items-center gap-2 small text-secondary mb-3">
    <span class="author">
        <?php the_author_posts_link(); ?>
    </span>
    <div class="vr"></div>
    <span>
        <a href="<?php the_permalink(); ?>" class="text-decoration-none text-secondary" title="<?php echo esc_attr( get_the_date() ); ?>">
            <?php the_time( get_option( 'date_format' ) ); ?>
        </a>
    </span>
    <div class="vr"></div>
    <?php $post_tags = get_the_tags(); if ( $post_tags ): ?>
        <?php foreach ( $post_tags as $tag ) : ?>
            <a href="<?php esc_url( get_tag_link( $tag->term_id ) ) ?>" class="badge text-bg-secondary text-decoration-none me-1"><?php esc_html( $tag->name ) ?></a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<!-- /entry-meta.php -->