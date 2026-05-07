<!-- content-page.php -->
<?php
    $redirect_url     = get_post_meta( $post->ID, '_redirect_url',     true );
    $redirect_enabled = get_post_meta( $post->ID, '_redirect_enabled', true );
    $is_protected     = post_password_required( $post );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header>
        <h1><?php the_title(); ?></h1>
    </header>

    <?php if ( $is_protected ) : ?>
        <?php
        // Page is password-protected. Show the WP password form.
        // The redirect notice (if any) is intentionally suppressed here —
        // the destination URL should not be visible to unauthenticated visitors.
        // Once the visitor authenticates:
        //   - If redirect is enabled, handle_redirect() fires and they're sent there.
        //   - If redirect is disabled, the notice below becomes visible on next load.
        echo get_the_password_form();
        ?>

    <?php elseif ( $redirect_enabled && $redirect_url ) : ?>
        <?php
        // Redirect is enabled and the visitor is not password-gated.
        // handle_redirect() should have already fired on template_redirect
        // and sent a 301, so reaching here is unexpected. Show a fallback
        // link in case something prevented the server-side redirect.
        ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="alert">
            <span class="text-muted">&#8594;</span>
            <span>Redirecting to <a href="<?php echo esc_url( $redirect_url ); ?>"><?php echo esc_html( $redirect_url ); ?></a>&hellip;</span>
        </div>
        <div class="entry">
            <?php the_content(); ?>
            <?php wp_link_pages(); ?>
        </div>

    <?php elseif ( ! $redirect_enabled && $redirect_url ) : ?>
        <?php
        // Redirect is disabled. Show the destination URL as an informational
        // notice above the page content. Only reached when not password-protected
        // (or after successful authentication).
        ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4" role="alert">
            <span class="text-muted">&#8594;</span>
            <a href="<?php echo esc_url( $redirect_url ); ?>"><?php echo esc_html( $redirect_url ); ?></a>
        </div>
        <div class="entry">
            <?php the_content(); ?>
            <?php wp_link_pages(); ?>
        </div>

    <?php else : ?>
        <?php // Standard page — no redirect configured. ?>
        <div class="entry">
            <?php the_content(); ?>
            <?php wp_link_pages(); ?>
        </div>

    <?php endif; ?>
</article>
<!-- /content-page.php -->