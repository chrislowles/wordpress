<!-- entry-meta.php -->
<div class="entry meta">
	<?php // the_author_posts_link(); ?>
	<?php echo get_multi_authors_list(); ?>
	<span class="sep">|</span>
	<span class="hyperlink">
		<a href="<?php the_permalink(); ?>" class="date" title="<?php echo esc_attr(get_the_date()); ?>">
			<?php the_time(get_option('date_format')); ?>
		</a>
	</span>
	<?php if (has_tag()): ?>
		<span class="sep">|</span>
		<span class="tags"><?php the_tags(); ?></span>
	<?php endif; ?>
</div>
<!-- /entry-meta.php -->