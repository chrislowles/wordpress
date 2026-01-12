<?php

/// DISABLE CATEGORIES

// TAXONOMY
add_action('init', function() {
	unregister_taxonomy_for_object_type('category', 'post');
}, 20);

// MENU ITEM
add_action('admin_menu', function() {
	remove_menu_page('edit-tags.php?taxonomy=category');
	remove_meta_box('categorydiv', 'post', 'side');
}, 999);

// QUICK/BULK EDIT OPTIONS
add_action('bulk_edit_custom_box', function($column_name, $post_type) {
	if ($column_name === 'categories') {
		remove_meta_box('categorydiv', $post_type, 'side');
	}
}, 10, 2);
add_filter('quick_edit_show_taxonomy', function($show, $taxonomy, $post_type) {
	if ($taxonomy === 'category') {
		return false;
	}
	return $show;
}, 10, 3);

/// COMMENTS/TRACKBACKS/PINGBACKS