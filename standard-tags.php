<?php

namespace NodeifyWP;

/**
 * Standard template tags
 */

App::instance()->register_template_tag( 'wp_head', function() {
	do_action( 'wp_head' );
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

	$wp_admin_bar->render();
}, false );

App::instance()->register_template_tag( 'wp_footer', function() {
	do_action( 'wp_footer' );
}, false );

App::instance()->register_template_tag( 'get_body_class', function() {
	body_class();
} );

App::instance()->register_template_tag( 'home_url', function() {
	echo home_url();
} );

App::instance()->register_template_tag( 'stylesheet_directory_url', function() {
	echo get_stylesheet_directory_uri();
} );

App::instance()->register_template_tag( 'bloginfo_name', function() {
	bloginfo( 'name' );
} );

App::instance()->register_template_tag( 'bloginfo_description', function() {
	bloginfo( 'description' );
} );

App::instance()->register_template_tag( 'header_image', function() {
	echo header_image();
} );
