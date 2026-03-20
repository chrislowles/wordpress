<!-- content-page.php -->
<?php
    $redirect_url     = get_post_meta( $post->ID, '_redirect_url',     true );
    $redirect_enabled = get_post_meta( $post->ID, '_redirect_enabled', true );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header>
        <h1><?php the_title(); ?></h1>
    </header>
    <?php if ( $redirect_url && ! $redirect_enabled ): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="alert">
            <span class="text-muted">&#8594;</span>
            <a href="<?php echo esc_url( $redirect_url ); ?>"><?php echo esc_html( $redirect_url ); ?></a>
        </div>
    <?php endif; ?>
    <div class="entry">
        <?php the_content(); ?>
        <?php wp_link_pages(); ?>
    </div>
</article>
<!-- /content-page.php -->