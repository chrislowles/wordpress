<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<header>

		<h2>

			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>

		</h2>

	</header>

	<div class="entry">

		<div class="thumbnail">

			<?php if ( has_post_thumbnail() ): ?>

				<a class="thumbnail-link" href="<?php the_post_thumbnail_url( 'full' ); ?>">

					<?php the_post_thumbnail( 'full' ); ?>

				</a>

			<?php endif; ?>

		</div>

		<?php $tracklist = get_post_meta( $post->ID, 'tracklist', true ); if ( is_array( $tracklist ) && ! empty( $tracklist ) ): ?>
			<div class="tracklist-display">
				<h3>Tracklist / Timeline</h3>
				<ul>
					<?php foreach ( $tracklist as $track ):
						$type  = $track['type']  ?? 'track';
						$title = $track['title'] ?? $track['track_title'] ?? '';
						$dur   = $track['duration'] ?? '';
						$url   = $track['url']   ?? $track['track_url']   ?? '';
						$link  = $track['link_to_section'] ?? false;
					?>

						<?php if ( $type === 'spacer' ): ?>

							<li class="track-item type-spacer">
								<?php if ( $link ): ?>
									<a href="#<?php echo esc_attr( sanitize_title( $title ) ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php else: ?>
									<?php echo esc_html( $title ); ?>
								<?php endif; ?>
							</li>

						<?php else: ?>

							<li class="track-item type-track">
								<span class="track-title">
									<?php if ( ! empty( $url ) ): ?>
										<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a>
									<?php else: ?>
										<?php echo esc_html( $title ); ?>
									<?php endif; ?>
								</span>
								<?php if ( ! empty( $dur ) ): ?>
									<span class="track-duration">[<?php echo esc_html( $dur ); ?>]</span>
								<?php endif; ?>
							</li>

						<?php endif; ?>

					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="content">
			<?php the_content(); ?>
		</div>

		<div class="links">
			<?php wp_link_pages(); ?>
		</div>

	</div>

</article>