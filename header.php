<!-- header.php -->
<!DOCTYPE html>
<html <?php language_attributes(); ?> <?php blankslate_schema_type(); ?>>

	<head>

		<meta name="color-scheme" content="light dark" />
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1" />

		<?php wp_head(); ?>

	</head>

	<body <?php body_class(); ?>>

		<?php wp_body_open(); ?>

		<header id="header">

			<div>

				<h1>

					<a href="/" title="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><?php echo get_bloginfo( 'name' ); ?></a>

				</h1>

				<p><?php bloginfo( 'description' ); ?></p>

			</div>

			<nav>

				<?php wp_nav_menu( array( 'theme_location' => 'main-menu', 'link_before' => '<span>', 'link_after' => '</span>' ) ); ?>

				<div><?php get_search_form(); ?></div>

			</nav>

		</header>

		<main id="content">

<!-- /header.php -->