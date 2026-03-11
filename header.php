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
        <header id="header" class="py-3 border-bottom">
            <div class="container d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-0">
                        <a href="/" title="<?php echo esc_attr( get_bloginfo('name') ); ?>"><?php echo get_bloginfo('name'); ?></a>
                    </h1>
                    <p class="text-muted small mb-0"><?php bloginfo('description'); ?></p>
                </div>
                <nav class="d-flex align-items-center gap-3">
                    <?php wp_nav_menu(array(
                        'theme_location' => 'main-menu',
                        'container'      => false,
                        'menu_class'     => 'd-flex list-unstyled gap-3 mb-0',
                        'link_before'    => '<span>',
                        'link_after'     => '</span>',
                    )); ?>
                    <div><?php get_search_form(); ?></div>
                </nav>
            </div>
        </header>
        <main id="content" class="container my-4">

<!-- /header.php -->