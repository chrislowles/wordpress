<!-- author.php -->
<?php get_header(); ?>
    <?php
        $author       = get_queried_object();
        $current_type = ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'show' ) ? 'show' : 'post';
        $author_url   = get_author_posts_url( $author->ID );
        $post_count   = count_user_posts( $author->ID, 'post', true );
        $show_count   = count_user_posts( $author->ID, 'show', true );
    ?>
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_type === 'post' ? 'active' : ''; ?>"
               href="<?php echo esc_url( $author_url ); ?>">
                Posts
                <?php if ( $post_count ): ?>
                    <span class="badge text-bg-secondary"><?php echo $post_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_type === 'show' ? 'active' : ''; ?>"
               href="<?php echo esc_url( add_query_arg( 'post_type', 'show', $author_url ) ); ?>">
                Shows
                <?php if ( $show_count ): ?>
                    <span class="badge text-bg-secondary"><?php echo $show_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
    <?php if ( have_posts() ): while ( have_posts() ): the_post(); ?>
        <?php get_template_part( 'parts/content', 'summary' ); ?>
    <?php endwhile; else: ?>
        <p class="text-muted">No <?php echo $current_type === 'show' ? 'shows' : 'posts'; ?> found.</p>
    <?php endif; ?>
    <?php get_template_part( 'parts/nav', 'below' ); ?>
<?php get_footer(); ?>
<!-- /author.php -->