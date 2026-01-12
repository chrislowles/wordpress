<?php

// Nuke Hostinger Bullshit Plugin :D
add_action('admin_init', function() {
	$plugin_path = 'hostinger/hostinger.php';
	// 1. Force Deactivate
	if ( is_plugin_active( $plugin_path ) ) {
		deactivate_plugins( $plugin_path );
	}
	// 2. Hide from Plugin List
	add_filter(
		'all_plugins',
		function( $plugins ) use ( $plugin_path ) {
			if ( isset( $plugins[ $plugin_path ] ) ) {
				unset( $plugins[ $plugin_path ] );
			}
			return $plugins;
		}
	);
});