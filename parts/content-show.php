<?php
/**
 * Template part for displaying Show CPT posts.
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header>
		<h2 class="entry-title">
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
		</h2>
		<?php get_template_part('parts/entry-meta'); ?>
	</header>

	<div class="entry content">
		<?php
		// 1. Retrieve the Tracklist Data
		$tracklist = get_post_meta($post->ID, 'tracklist', true);
		
		// 2. The Simple Array Loop
		if (is_array($tracklist) && !empty($tracklist)): ?>
			<div class="tracklist-display" style="margin-bottom: 2em; padding: 1.5em; background: #f7f7f7; border: 1px solid #ddd; border-radius: 4px;">
				<h3 style="margin-top: 0; font-size: 1.2em; border-bottom: 1px solid #ccc; padding-bottom: 0.5em;">Tracklist / Timeline</h3>
				<ul style="list-style: none; margin: 0; padding: 0;">
					<?php foreach ($tracklist as $track): 
						$type = $track['type'] ?? 'track';
						$title = $track['track_title'] ?? '';
						$duration = $track['duration'] ?? '';
						$url = $track['track_url'] ?? '';
					?>
						<?php if ($type === 'spacer'): ?>
							<li class="track-item type-spacer" style="margin-top: 1em; font-weight: bold; border-bottom: 1px solid #eee;">
								<?php echo esc_html($title); ?>
							</li>
						<?php else: ?>
							<li class="track-item type-track" style="display: flex; justify-content: space-between; padding: 4px 0;">
								<span class="track-title">
									<?php if (!empty($url)): ?>
										<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo esc_html($title); ?>
										</a>
									<?php else: ?>
										<?php echo esc_html($title); ?>
									<?php endif; ?>
								</span>
								<?php if (!empty($duration)): ?>
									<span class="track-duration" style="color: #666; font-family: monospace;">
										<?php echo esc_html($duration); ?>
									</span>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

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

	<?php get_template_part('entry', 'footer'); ?>
</article>