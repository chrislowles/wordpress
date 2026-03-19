<!-- content-summary.php -->
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header>
		<h2>
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
		</h2>
		<?php // if (!is_search()): get_template_part( 'parts/entry', 'meta' ); endif; ?>
		<?php get_template_part( 'parts/entry', 'meta' ); ?>
	</header>
	<div class="entry">
		<div class="thumbnail">
			<?php if ( has_post_thumbnail() && !is_search() ): ?>
				<a class="thumbnail-link" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
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
		<div class="excerpt">
			<?php the_excerpt(); ?>
		</div>
		<?php if ( is_search() ): ?>
			<div class="links"><?php wp_link_pages(); ?></div>
		<?php endif; ?>
	</div>
</article>
<!-- /content-summary.php -->