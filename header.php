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
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand" href="/" title="<?php echo esc_attr(get_bloginfo('name')); ?>"><?php echo get_bloginfo('name'); ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-content" aria-controls="navbar-content" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbar-content">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="collapse" aria-label="Close"></button>
                    <div class="collapse-body">
                        <?php
                            wp_nav_menu(
                                array(
                                    'menu_class'     => 'navbar-nav ms-auto mb-2 mb-lg-0 pe-3',
                                    'container'      => false,
                                    'link_before'    => '<span>',
                                    'link_after'     => '</span>',
                                    'theme_location' => is_user_logged_in() ? 'logged-in-menu' : 'logged-out-menu',
                                )
                            );
                        ?>
                        <form class="d-flex" action="/" method="get" role="search">
                            <input class="form-control me-2" type="text" name="s" id="search" aria-label="Search" placeholder="Search" value="<?php the_search_query(); ?>" />
                            <button class="btn btn-outline-success" type="submit">Search</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
        <main id="content" class="container my-4">
<!-- /header.php -->