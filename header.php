<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="color-scheme" content="light dark" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
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
                    wp_nav_menu(
                        array(
                            'theme_location' => is_user_logged_in() ? 'logged-in-menu' : 'logged-out-menu', // Make sure this matches your registered menu location
                            'container'       => false,     // Prevents WordPress from wrapping the <ul> in a <div>
                            'menu_class'      => 'navbar-nav ms-auto mb-2 mb-lg-0', // Bootstrap classes for the <ul>
                            'fallback_cb'     => '__return_false', // Hides the menu if nothing is assigned
                            'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                            'depth'           => 2,
                        )
                    );
                    ?>
                    <form class="d-flex ml-2" action="/" method="get" role="search">
                        <input type="text" name="s" id="search" aria-label="Search" placeholder="Search" value="<?php the_search_query(); ?>" class="form-control me-2" />
                        <button class="btn btn-outline-success" type="submit">Search</button>
                    </form>
                </div>

            </div>
        </nav>
    </header>

    <main class="site-main container mt-4">