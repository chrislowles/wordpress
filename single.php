<!-- single.php -->
<?php get_header(); ?>
	<?php if (have_posts()): while (have_posts()): the_post(); ?>
		<?php get_template_part('entry'); ?>
	<?php endwhile; endif; ?>
	<footer>
		<?php get_template_part('nav', 'below-single'); ?>
	</footer>
<?php get_footer(); ?>
<!-- /single.php -->