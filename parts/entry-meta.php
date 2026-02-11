<div class="entry meta">

	<?php the_author_posts_link(); ?>

	<span class="sep">|</span>

	<span class="hyperlink">

		<a href="<?php the_permalink(); ?>" class="date" title="<?php echo esc_attr( get_the_date() ); ?>">

			<?php the_time( get_option( 'date_format' ) ); ?>

		</a>

	</span>

</div>