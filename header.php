<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php wp_title( '|', true, 'right' ); ?></title>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container">
                <a class="navbar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php bloginfo( 'name' ); ?>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNavbar">
                    <?php
                    // Display the WordPress menu
                    wp_nav_menu( array(
                        'theme_location'  => 'primary', // Make sure this matches your registered menu location
                        'container'       => false,     // Prevents WordPress from wrapping the <ul> in a <div>
                        'menu_class'      => 'navbar-nav ms-auto mb-2 mb-lg-0', // Bootstrap classes for the <ul>
                        'fallback_cb'     => '__return_false', // Hides the menu if nothing is assigned
                        'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                        'depth'           => 2,
                    ) );
                    ?>
                </div>
            </div>
        </nav>
    </header>

    <main class="site-main container mt-4">