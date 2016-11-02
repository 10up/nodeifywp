<?php

namespace ReactifyWP;

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
	 * Singleton class
	 */
	public function __construct() { }

	/**
	 * Render our isomorphic application
	 *
	 * @since 0.5
	 */
	public function render() {
		do_action( 'reactifywp_render' );

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
	public function register_template_tag( $tag_name, $tag_function, $constant = true, $on_action = 'reactifywp_render' ) {
		if ( ! $constant && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$context = $this->v8->context;

		$register = function() use ( &$context, $tag_name, $tag_function ) {
			ob_start();

			$tag_function();

			$output = ob_get_clean();

			$context->template_tags[ $tag_name ] = $output;
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

		add_filter( 'reactifywp_register_post_tags', function( $post ) use ( $tag_function, $tag_name ) {
			setup_postdata( $post );

			ob_start();

			$tag_function( $post );

			wp_reset_postdata();

			$post->{$tag_name} = ob_get_clean();

			return $post;
		} );
	}

	/**
	 * Setup application for use in theme
	 *
	 * @since 0.5
	 */
	public function init() {
		$this->v8 = new \V8Js();
		$this->v8->context = new \stdClass(); // v8js didn't like an array here :(
		$this->v8->context->template_tags = [];
		$this->v8->context->route = [];
		$this->v8->context->posts = [];
		$this->v8->context->nav_menus = [];
		$this->v8->context->sidebars = [];
		$this->v8->client_js_url = $this->client_js_url;

		add_action( 'after_setup_theme', array( $this, 'register_menus' ), 11 );
		add_action( 'reactifywp_render', array( $this, 'register_route' ), 11 );
		add_action( 'reactifywp_render', array( $this, 'register_posts' ), 9 );
		add_action( 'reactifywp_render', array( $this, 'register_sidebars' ), 9 );
		add_action( 'template_redirect', array( $this, 'render' ) );
		add_filter( 'show_admin_bar', '__return_false' );

		require_once __DIR__ . '/standard-tags.php';
		require_once __DIR__ . '/vendor/autoload.php';
	}

	/**
	 * Register route object for use in JS. This tells our app where we are. Available as PHP.context.$route
	 *
	 * @since  0.5
	 */
	public function register_route() {
		$route = [
			'type'        => null,
			'object_id'   => null,
		];

		if ( is_home() || is_front_page() ) {

			if ( is_home() ) {
				$route['type'] = 'home';
			} else {
				$route['type'] = 'front_page';
			}
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

		$this->v8->context->route = $route;
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

			$this->v8->context->posts[ $key ] = (array) apply_filters( 'reactifywp_register_post_tags', $this->v8->context->posts[ $key ] );
		}
	}

	/**
	 * Setup our API route for navigation
	 *
	 * @since 0.8
	 */
	public function setup_api() {
		require_once __DIR__ . '/API.php';

		add_action( 'rest_api_init', function() {
			$reactify_api = new API();
			$reactify_api->register_routes();
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
	 * Singleton class. Start app by calling ReactifyWP::setup(); in functions.php of theme.
	 * 
	 * @since 0.5
	 * @return  object
	 */
	public static function setup( $server_js_path, $client_js_url ) {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->server_js_path = $server_js_path;
			self::$instance->client_js_url = $client_js_url;
			self::$instance->init();
			self::$instance->setup_api();
		}

		return self::$instance;
	}
}

