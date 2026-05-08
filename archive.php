<!-- archive.php -->
<?php get_header(); ?>
	<?php if ( have_posts() ): while ( have_posts() ): the_post(); ?>
		<?php 
			if ( 'show' === get_post_type() ) {
				get_template_part( 'parts/content', 'show' );
			} else {
				get_template_part( 'parts/content', 'summary' );
			}
		?>
	<?php endwhile; endif; ?>
	<?php get_template_part( 'parts/nav', 'below' ); ?>
<?php get_footer(); ?>
<!-- /archive.php -->