<!-- footer.php -->
		</main>
	    <footer id="footer" class="border-top py-3 mt-4">
        	<div class="container text-muted small">
    	        &copy; <?php echo esc_html( date_i18n('Y') ); ?> <?php echo esc_html( get_bloginfo('name') ); ?>
				<?php
					wp_nav_menu(
						array(
							// 'menu_class'     => 'navbar-nav justify-content-end flex-grow-1 pe-3',
							'container'      => false, // Removes the extra <div> wrapper
							'fallback_cb'    => false, // Prevents falling back to a page list
							'theme_location' => 'footer',
						)
					);
				?>
	        </div>
	    </footer>
	    <?php wp_footer(); ?>
	</body>
</html>
<!-- /footer.php -->