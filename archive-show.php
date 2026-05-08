<?php get_header(); ?>

<main id="content" role="main">
    <header class="header">
        <h1 class="entry-title">
            <?php post_type_archive_title(); ?>
        </h1>
        <?php if ( is_post_type_archive( 'show' ) && function_exists( 'the_archive_description' ) ) : ?>
            <div class="archive-meta">
                <?php the_archive_description(); ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <?php get_template_part( 'parts/content', 'show' ); ?>
    <?php endwhile; ?>
    
    <?php get_template_part( 'parts/nav', 'below' ); ?>
    
    <?php else : ?>
        <article id="post-0" class="post no-results not-found">
            <header class="header">
                <h2 class="entry-title"><?php esc_html_e( 'No Shows Found', 'generic-theme' ); ?></h2>
            </header>
            <section class="entry-content">
                <p><?php esc_html_e( 'It seems we cannot find any shows at the moment. Perhaps searching can help.', 'generic-theme' ); ?></p>
                <?php get_search_form(); ?>
            </section>
        </article>
    <?php endif; ?>

</main>

<?php get_sidebar(); ?>

<?php get_footer(); ?>