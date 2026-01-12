<!-- entry-summary.php -->
<div class="entry summary">
	<?php if ((has_post_thumbnail()) && (!is_search())): ?>
		<a class="thumbnail-link" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_post_thumbnail(); ?></a>
	<?php endif; ?>
	<div><?php the_excerpt(); ?></div>
	<?php if (is_search()): ?>
		<div class="links"><?php wp_link_pages(); ?></div>
	<?php endif; ?>
</div>
<!-- /entry-summary.php -->