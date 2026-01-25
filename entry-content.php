<!-- entry-content.php -->
<div class="entry content">
	<div class="thumbnail">
		<?php if (has_post_thumbnail()): ?>
			<a class="thumbnail-link" href="<?php the_post_thumbnail_url('full'); ?>" title="<?php $attachment_id = get_post_thumbnail_id($post->ID); the_title_attribute(array('post' => get_post($attachment_id))); ?>">
				<?php the_post_thumbnail('full', array('itemprop' => 'image')); ?>
			</a>
		<?php endif; ?>
	</div>
	<div class="body-content">
		<?php the_content(); ?>
	</div>
	<div class="links"><?php wp_link_pages(); ?></div>
</div>
<!-- /entry-content.php -->