<?php get_header(); ?>
	<?php if (have_posts()): while (have_posts()): the_post(); ?>
		<?php
			if ( get_post_type() === 'show' ) {
				get_template_part( 'parts/content', 'show' );
			} else {
				get_template_part( 'parts/content', 'single' );
			}
		?>
	<?php endwhile; endif; ?>
	<footer>
		<?php get_template_part( 'parts/nav', 'below-single' ); ?>
	</footer>
<?php get_footer(); ?>