<!-- entry.php -->
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header>
		<h2>
			<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
		</h2>
		<?php if (!is_search()): get_template_part('entry', 'meta'); endif; ?>
	</header>
	<?php get_template_part('entry', (is_front_page() || is_home() || is_front_page() && is_home() || is_archive() || is_search() ? 'summary' : 'content')); ?>
	<?php if (is_singular()): get_template_part('entry', 'footer'); endif; ?>
</article>
<!-- /entry.php -->

<!--
/**
 * TODO: Implement automatic h1-6 id code to post body content, transforms "The Main Header Text" into <h2 id="the-main-header-text">...
 * Intended for as yet unfinished inner linking from Spacers in the tracklist metabox to sections in the agenda body text
 */
-->