<?php
/**
 * Template part for displaying post summaries (archives, search, index).
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<header>
		<h2 class="entry-title">
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
		</h2>
		<?php if (!is_search()) { get_template_part('template-parts/entry-meta'); } ?>
	</header>
	
	<div class="entry summary">
		<?php if (has_post_thumbnail() && !is_search()): ?>
			<a class="thumbnail-link" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
				<?php the_post_thumbnail(); ?>
			</a>
		<?php endif; ?>
		
		<div><?php the_excerpt(); ?></div>
		
		<?php if (is_search()): ?>
			<div class="links"><?php wp_link_pages(); ?></div>
		<?php endif; ?>
	</div>

</article>