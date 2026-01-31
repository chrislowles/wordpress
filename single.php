<!-- single.php -->
<?php get_header(); ?>
	<?php if (have_posts()): while (have_posts()): the_post(); ?>
		<?php get_template_part('parts/content', 'single'); ?>
	<?php endwhile; endif; ?>
	<footer>
		<?php get_template_part('nav', 'below-single'); ?>
	</footer>
<?php get_footer(); ?>
<!-- /single.php -->

<!--
/**
 * TODO: Implement automatic h1-6 id code to post body content, transforms "The Main Header Text" into <h2 id="the-main-header-text">...
 * Intended for as yet unfinished inner linking from Spacers in the tracklist metabox to sections in the agenda body text
 */
-->