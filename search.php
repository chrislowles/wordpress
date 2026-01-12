<!-- search.php -->
<?php get_header(); ?>
	<?php if (have_posts()): ?>
		<header class="header">
			<h1 class="entry-title"><?php printf('Search Results for: %s', get_search_query()); ?></h1>
		</header>
		<?php while (have_posts()): the_post(); ?>
			<?php get_template_part('entry'); ?>
		<?php endwhile; ?>
		<?php get_template_part('nav', 'below'); ?>
	<?php else: ?>
		<article id="post-0" class="post no-results not-found">
			<header class="header">
				<h1 class="entry-title">Nothing Found</h1>
			</header>
			<div class="entry-content">
				<p>Sorry, nothing matched your search. Please try again.</p>
			</div>
		</article>
	<?php endif; ?>
<?php get_footer(); ?>
<!-- /search.php -->