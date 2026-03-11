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
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark" aria-label="Offcanvas navbar large">
            <div class="container-fluid">
                <a class="navbar-brand" href="/" title="<?php echo esc_attr( get_bloginfo('name') ); ?>"><?php echo get_bloginfo('name'); ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar2" aria-controls="offcanvasNavbar2" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="offcanvasNavbar2" aria-labelledby="offcanvasNavbar2Label">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="offcanvasNavbar2Label">Offcanvas</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body">
                        <?php
                            wp_nav_menu(
                                array(
                                    //'menu'              => "", // (int|string|WP_Term) Desired menu. Accepts a menu ID, slug, name, or object.
                                    'menu_class'        => 'navbar-nav justify-content-end flex-grow-1 pe-3', // (string) CSS class to use for the ul element which forms the menu. Default 'menu'.
                                    //'menu_id'           => "", // (string) The ID that is applied to the ul element which forms the menu. Default is the menu slug, incremented.
                                    'container'         => false, // (string) Whether to wrap the ul, and what to wrap it with. Default 'div'.
                                    //'container_class'   => "", // (string) Class that is applied to the container. Default 'menu-{menu slug}-container'.
                                    //'container_id'      => "", // (string) The ID that is applied to the container.
                                    //'fallback_cb'       => "", // (callable|bool) If the menu doesn't exists, a callback function will fire. Default is 'wp_page_menu'. Set to false for no fallback.
                                    //'before'            => "", // (string) Text before the link markup.
                                    //'after'             => "", // (string) Text after the link markup.
                                    'link_before'       => '<span>', // (string) Text before the link text.
                                    'link_after'        => '</span>', // (string) Text after the link text.
                                    //'echo'              => "", // (bool) Whether to echo the menu or return it. Default true.
                                    //'depth'             => "", // (int) How many levels of the hierarchy are to be included. 0 means all. Default 0.
                                    //'walker'            => "", // (object) Instance of a custom walker class.
                                    'theme_location'    => is_user_logged_in() ? 'logged-in-menu' : 'logged-out-menu', // (string) Theme location to be used. Must be registered with register_nav_menu() in order to be selectable by the user.
                                    //'items_wrap'        => "", // (string) How the list items should be wrapped. Default is a ul with an id and class. Uses printf() format with numbered placeholders.
                                    //'item_spacing'      => "", // (string) Whether to preserve whitespace within the menu's HTML. Accepts 'preserve' or 'discard'. Default 'preserve'.
                                )
                            );
                        ?>
                        <li class="nav-item">
                            <a class="nav-link">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Link</a>
                        </li>
                        <form class="d-flex mt-3 mt-lg-0" action="/" method="get" role="search">
                            <input type="text" name="s" id="search" aria-label="Search" placeholder="Search" value="<?php the_search_query(); ?>" class="form-control me-2" />
                            <button class="btn btn-outline" type="submit">Search</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
        <main id="content" class="container my-4">
<!-- /header.php -->