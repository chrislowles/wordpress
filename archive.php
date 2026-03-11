<!-- archive.php -->
<?php get_header(); ?>
	<?php if ( have_posts() ): while ( have_posts() ): the_post(); ?>
		<?php get_template_part( 'parts/content', 'single' ); ?>
	<?php endwhile; endif; ?>
	<?php get_template_part( 'parts/nav', 'below' ); ?>
<?php get_footer(); ?>
<!-- /archive.php -->