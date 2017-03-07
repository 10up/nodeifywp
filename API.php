<?php

namespace NodeifyWP;

class API extends \WP_REST_Controller {

	/**
	 * Register our api routes
	 *
	 * @since  0.5
	 */
	public function register_routes() {
		$version = '1';
		$namespace = 'nodeifywp/v' . $version;

		$base = 'route';
		register_rest_route( $namespace, '/' . $base, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_route' ],
				'permission_callback' => '__return_true',
				'args' => [
					'context' => [
						'default' => 'view',
					],
				],
			],
		] );
	}

	/**
	 * Given a location url, return all relevant info for that url: posts, route, menus, template tags, etc.
	 *
	 * @since  0.5
	 * @return array
	 */
	public function get_route() {
		$permalink_structure = get_option( 'permalink_structure' );

		$resolver = new UrlToQuery();
		$query_args = $resolver->resolve( $_GET['location'] );

		$GLOBALS['wp_the_query'] = new \WP_Query( $query_args );
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query']->query( $query_args );

		if ( preg_match( '#^/?%postname#is', $permalink_structure ) && ! empty( $query_args['pagename'] ) && empty( $GLOBALS['wp_the_query']->posts ) ) {
			$query_args['name'] = $query_args['pagename'];

			unset( $query_args['pagename'] );
			unset( $query_args['page'] );

			$GLOBALS['wp_the_query'] = new \WP_Query( $query_args );
			$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
			$GLOBALS['wp_the_query']->query( $query_args );
		}

		if ( ! ( $GLOBALS['wp_the_query']->is_singular || $GLOBALS['wp_the_query']->is_archive || $GLOBALS['wp_the_query']->is_search || $GLOBALS['wp_the_query']->is_feed || $GLOBALS['wp_the_query']->is_trackback || $GLOBALS['wp_the_query']->is_404 || $GLOBALS['wp_the_query']->is_admin || $GLOBALS['wp_the_query']->is_robots ) ) {
			$GLOBALS['wp_the_query']->is_home = true;
		} elseif ( empty( $GLOBALS['wp_the_query']->posts ) && ( ! empty( $query_args['name'] ) || ! empty( $query_args['pagename'] ) ) ) {
			$GLOBALS['wp_the_query']->set_404();
		}

		do_action( 'nodeifywp_render' );

		App::instance()->register_posts( $query_args );

		$output = App::instance()->v8->context;

		return $output;
	}
}
