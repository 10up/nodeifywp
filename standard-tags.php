<?php

namespace NodeifyWP;

/**
 * Standard template tags
 */

App::instance()->register_template_tag( 'wp_head', function() {
	ob_start();

	wp_head();

	return ob_get_clean();
} );

App::instance()->register_template_tag( 'admin_bar', function() {
	global $wp_admin_bar;

	if ( ! is_user_logged_in() ) {
		return;
	}

	require_once( ABSPATH . WPINC . '/class-wp-admin-bar.php' );
	
	$wp_admin_bar = new \WP_Admin_Bar;
	$wp_admin_bar->initialize();
	$wp_admin_bar->add_menus();

	do_action_ref_array( 'admin_bar_menu', array( &$wp_admin_bar ) );

	ob_start();
	
	$wp_admin_bar->render();

	return ob_get_clean();
}, false );

App::instance()->register_template_tag( 'wp_footer', function() {
	ob_start();

	wp_footer();

	return ob_get_clean();
}, false );

App::instance()->register_template_tag( 'get_body_class', function() {
	return get_body_class();
} );

App::instance()->register_template_tag( 'home_url', function() {
	return home_url();
} );

App::instance()->register_template_tag( 'stylesheet_directory_url', function() {
	return get_stylesheet_directory_uri();
} );

App::instance()->register_template_tag( 'bloginfo_name', function() {
	return get_bloginfo( 'name' );
} );

App::instance()->register_template_tag( 'bloginfo_description', function() {
	return get_bloginfo( 'description' );
} );

App::instance()->register_template_tag( 'header_image', function() {
	return header_image();
} );
