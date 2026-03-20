<!-- content-single.php -->
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header>
        <h2>
            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
        </h2>
        <?php get_template_part( 'parts/entry', 'meta' ); ?>
    </header>
    <div>
        <div>
            <?php if ( has_post_thumbnail() ): ?>
                <a href="<?php the_post_thumbnail_url( 'full' ); ?>" title="<?php the_title_attribute(); ?>">
                    <?php
                        the_post_thumbnail(
                            'full',
                            [
                                'class' => 'img-fluid',
                                'title' => 'Thumbnail'
                            ]
                        );
                    ?>
                </a>
            <?php endif; ?>
        </div>
        <div>
            <?php the_content(); ?>
        </div>
        <div>
            <?php wp_link_pages(); ?>
        </div>
    </div>
</article>
<!-- /content-single.php -->