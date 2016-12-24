<?php

namespace NodeifyWP;

class App {
	/**
	 * Static reference to app
	 *
	 * @since  0.5
	 * @var object
	 */
	static $instance;

	/**
	 * We will store our one reference to our v8js app here
	 *
	 * @var object
	 * @since  0.5
	 */
	public $v8;

	/**
	 * Path to server-side JS entry
	 *
	 * @since  0.5
	 * @var string
	 */
	public $server_js_path;

	/**
	 * Url clide-side JS entry
	 *
	 * @since  0.5
	 * @var string
	 */
	public $client_js_url;

	/**
	 * Url to server side JS includes
	 *
	 * @since  0.6
	 * @var string
	 */
	public $includes_js_path = null;

	/**
	 * Url to client side JS includes
	 *
	 * @since  0.6
	 * @var string
	 */
	public $includes_js_url = null;

	/**
	 * Singleton class
	 */
	public function __construct() { }

	/**
	 * Render our isomorphic application
	 *
	 * @since 0.5
	 */
	public function render() {
		do_action( 'nodeifywp_render' );

		$server = file_get_contents( $this->server_js_path );

		$this->v8->executeString( $server, \V8Js::FLAG_FORCE_ARRAY );

		exit;
	}

	/**
	 * Register a template tag to be rendered in JS
	 *
	 * @param  string   $tag_name     Name of tag. Will be available as PHP.context.$template_tags.$tag_name in JS.
	 * @param  callable $tag_function This function will be executed to determine the contents of our tag
	 * @param  boolean  $constant     Constant tags will not be re-calculated on client side navigation
	 * @param  string   $on_action    You can choose where the template tag should be rendered
	 * @since 0.5
	 */
	public function register_template_tag( $tag_name, $tag_function, $constant = true, $on_action = 'nodeifywp_render' ) {
		if ( ! $constant && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$context = $this->v8->context;

		$register = function() use ( &$context, $tag_name, $tag_function ) {
			$context->template_tags[ $tag_name ] = $tag_function();
		};

		if ( ! empty( $on_action ) ) {
			add_action( $on_action, $register );
		} else {
			$register();
		}
	}

	/**
	 * Register a tag on each post passed to JS.
	 *
	 * @param  string   $tag_name     Name of tag on post object
	 * @param  callable $tag_function This function will be executed to determine the contents of our tag. $tag_function will
	 *                                be called with WP_Post as an argument.
	 * @since 0.5
	 */
	public function register_post_tag( $tag_name, $tag_function ) {
		$context = $this->v8->context;

		add_filter( 'nodeifywp_register_post_tags', function( $post_object ) use ( $tag_function, $tag_name ) {
			global $post;

			$post = $post_object;
			setup_postdata( $post );

			$post_object->{$tag_name} = $tag_function( $post_object );

			wp_reset_postdata();

			return $post_object;
		} );
	}

	/**
	 * Setup application for use in theme
	 *
	 * @since 0.5
	 */
	public function init() {
		$includes_snapshot = null;

		if ( ! empty( $this->includes_js_path ) ) {
			$includes_snapshot = wp_cache_get( 'nwp_includes_snapshot' );

			if ( false === $includes_snapshot ) {
				$includes_js = file_get_contents( $this->includes_js_path );
				$includes_snapshot = \V8Js::createSnapshot( $includes_js );

				if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
					wp_cache_set( 'nwp_includes_snapshot', $includes_snapshot, false, DAY_IN_SECONDS );
				}
			}
		}

		$this->v8 = new \V8Js( 'PHP', [], [], true, $includes_snapshot );

		$this->v8->context = new \stdClass(); // v8js didn't like an array here :(
		$this->v8->context->template_tags = [];
		$this->v8->context->route = [];
		$this->v8->context->posts = [];
		$this->v8->context->nav_menus = [];
		$this->v8->context->sidebars = [];
		$this->v8->context->user = [];
		$this->v8->client_js_url = $this->client_js_url;
		$this->v8->includes_js_url = $this->includes_js_url;

		add_action( 'after_setup_theme', array( $this, 'register_menus' ), 11 );
		add_action( 'nodeifywp_render', array( $this, 'register_route' ), 11 );
		add_action( 'nodeifywp_render', array( $this, 'register_posts' ), 9 );
		add_action( 'nodeifywp_render', array( $this, 'register_sidebars' ), 9 );
		add_action( 'nodeifywp_render', array( $this, 'register_user' ), 9 );
		add_action( 'template_redirect', array( $this, 'render' ) );
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );

		require_once __DIR__ . '/standard-tags.php';

		if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			require_once __DIR__ . '/vendor/autoload.php';
		}
	}

	/**
	 * Setup current user info if logged in
	 *
	 * @since  0.5
	 */
	public function register_user() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();

		$this->v8->context->user['user_login'] = $user->user_login;
		$this->v8->context->user['user_nicename'] = $user->user_nicename;
		$this->v8->context->user['ID'] = $user->ID;
		$this->v8->context->user['display_name'] = $user->display_name;
		$this->v8->context->user['rest_nonce'] = wp_create_nonce( 'wp_rest' );

		$this->v8->context->user = apply_filters( 'nodeifywp_registered_user', $this->v8->context->user );
	}

	/**
	 * Register route object for use in JS. This tells our app where we are. Available as PHP.context.$route
	 *
	 * @since  0.5
	 */
	public function register_route() {
		$route = [
			'type'           => null,
			'object_id'      => null,
			'document_title' => wp_get_document_title(),
		];

		if ( is_home() || is_front_page() ) {

			if ( is_home() ) {
				$route['type'] = 'home';
			} else {
				$route['type'] = 'front_page';
			}
		} elseif ( is_404() ) {
			$route['type'] = '404';
		} else {
			$object = get_queried_object();

			if ( is_single() || is_page() ) {
				$route['type'] = 'single';
				$route['object_type'] = $object->post_type;
				$route['object_id'] = $object->ID;
			} else {
				$route['type'] = 'archive';

				if ( is_author() ) {
					$route['object_type'] = 'author';
				} elseif ( is_post_type_archive() ) {
					$route['object_type'] = $object->name;
				} elseif ( is_tax() ) {
					$route['object_type'] = $object->taxonomy;
				}
			}
		}

		$this->v8->context->route = apply_filters( 'nodeifywp_registered_route', $route );
	}

	/**
	 * Register sidebars for use in JS. Available as PHP.context.$sidebars
	 *
	 * @since 0.8
	 */
	public function register_sidebars() {
		global $wp_registered_sidebars;

		foreach ( $wp_registered_sidebars as $sidebar ) {
			ob_start();

			dynamic_sidebar( $sidebar['id'] );

			$this->v8->context->sidebars[ $sidebar['id'] ] = ob_get_clean();
		}

		$this->v8->context->sidebars = apply_filters( 'nodeifywp_registered_sidebars', $this->v8->context->sidebars );
	}

	/**
	 * Register menus for use in JS. Available as PHP.context.$nav_menus
	 *
	 * @since 0.5
	 */
	public function register_menus() {
		$menus = get_nav_menu_locations();

		foreach ( $menus as $location => $menu_id ) {
			$items = wp_get_nav_menu_items( $menu_id );

			$ref_map = [];
			$menu = [];

			foreach ( $items as $item_key => $item ) {
				$menu_item = new \stdClass(); // We use a class so we can modify objects in place
				$menu_item->url = $item->url;
				$menu_item->title = apply_filters( 'the_title', $item->title, $item->ID );
				$menu_item->children = [];

				if ( empty( $item->menu_item_parent ) ) {
					$index = ( empty( $menu ) ) ? 0 : count( $menu );
					$menu[ $index ] = $menu_item;

					$ref_map[ $item->ID ] = $menu_item;
				} else {
					$ref_map[ $item->menu_item_parent ]->children[] = $menu_item;
				}
			}

			// Convert to arrays
			foreach ( $menu as $key => $menu_item ) {
				$menu[ $key ] = $this->_convert_to_arrays( $menu_item );
			}

			$this->v8->context->nav_menus[ $location ] = $menu;
		}

		$this->v8->context->nav_menus = apply_filters( 'nodeifywp_registered_nav_menus', $this->v8->context->nav_menus );
	}

	/**
	 * Helper method to recursively convert menu items to arrays
	 *
	 * @param  array $menu_item
	 * @since  0.5
	 * @return array
	 */
	private function _convert_to_arrays( $menu_item ) {
		$menu_item = (array) $menu_item;

		if ( ! empty( $menu_item['children'] ) ) {
			foreach ( $menu_item['children'] as $child_key => $child_item ) {
				$menu_item['children'][ $child_key ] = $this->_convert_to_arrays( $child_item );
			}

			return $menu_item;
		} else {
			return $menu_item;
		}
	}

	/**
	 * Register posts for use in JS. Available as PHP.context.$posts
	 *
	 * @since 0.5
	 */
	public function register_posts( $query_args = [] ) {

		$this->v8->context->posts = $GLOBALS['wp_the_query']->posts;

		foreach ( $this->v8->context->posts as $key => $post ) {
			$this->v8->context->posts[ $key ]->the_title = apply_filters( 'the_title', $post->post_title );
			$this->v8->context->posts[ $key ]->the_content = apply_filters( 'the_content', $post->post_content );
			$this->v8->context->posts[ $key ]->post_class = get_post_class( '', $post->ID );
			$this->v8->context->posts[ $key ]->permalink = get_permalink( $post->ID );

			$this->v8->context->posts[ $key ] = (array) apply_filters( 'nodeifywp_register_post_tags', $this->v8->context->posts[ $key ] );
		}

		$this->v8->context->posts = apply_filters( 'nodeifywp_registered_posts', $this->v8->context->posts );
	}

	/**
	 * Setup our API route for navigation
	 *
	 * @since 0.8
	 */
	public function setup_api() {
		if ( ! class_exists( '\WP_REST_Controller' ) ) {
			return;
		}

		require_once __DIR__ . '/API.php';

		add_action( 'rest_api_init', function() {
			$api = new API();
			$api->register_routes();
		} );
	}

	/**
	 * Return static app instance
	 *
	 * @since  0.5
	 * @return object
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Singleton class. Start app by calling NodeifyWP::setup(); in functions.php of theme.
	 * 
	 * @since 0.5
	 * @return  object
	 */
	public static function setup( $server_js_path, $client_js_url, $includes_js_path = null, $includes_js_url = null ) {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->server_js_path = $server_js_path;
			self::$instance->includes_js_path = $includes_js_path;
			self::$instance->includes_js_url = $includes_js_url;
			self::$instance->client_js_url = $client_js_url;
			self::$instance->init();
			self::$instance->setup_api();
		}

		return self::$instance;
	}
}

