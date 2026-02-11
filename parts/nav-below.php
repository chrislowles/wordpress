<!-- nav-below.php -->
<?php
	the_posts_navigation(array(
		'prev_text' => sprintf(esc_html__( '%s older', 'child'), '<span class="meta-nav">&larr;</span>'),
		'next_text' => sprintf(esc_html__('newer %s', 'child'), '<span class="meta-nav">&rarr;</span>')
	));
?>
<!-- /nav-below.php -->