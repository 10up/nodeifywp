<?php

namespace NodeifyWP;

class AppTest extends \PHPUnit_Framework_TestCase {
	/**
	 * Set up with WP_Mock
	 */
	public function setUp() {
		\WP_Mock::setUp();

		\WP_Mock::userFunction( 'remove_action' );
		App::setup( 'server.js', 'client.js' );
	}

	/**
	 * Tear down with WP_Mock
	 */
	public function tearDown() {
		\WP_Mock::tearDown();

		$GLOBALS['wp_filters'] = [];
		App::$instance = false;
	}

	/**
	 * Test setup method making sure v8 and context are available
	 */
	public function test_setup() {
		$this->assertTrue( is_a( App::$instance->v8, '\V8Js' ) );
		$this->assertTrue( is_object( App::$instance->v8->context ) );
	}

	/**
	 * Test empty user object when logged out
	 *
	 * @group register-user
	 */
	public function test_register_user_logged_out() {
		\WP_Mock::userFunction( 'is_user_logged_in', [
			'times'  => 1,
			'return' => false,
		] );

		App::$instance->register_user();

		$this->assertTrue( empty( App::$instance->v8->context->user ) );
	}

	/**
	 * Test populated user object when user logged in
	 *
	 * @group register-user
	 */
	public function test_register_user_logged_in() {
		\WP_Mock::userFunction( 'is_user_logged_in', [
			'times'  => 1,
			'return' => true,
		] );

		$user = new \stdClass();
		$user->ID = 1;
		$user->user_login = 'user_login';
		$user->user_nicename = 'user_nicename';
		$user->display_name = 'display_name';

		\WP_Mock::userFunction( 'wp_get_current_user', [
			'times'  => 1,
			'return' => $user,
		] );

		\WP_Mock::userFunction( 'wp_create_nonce', [
			'times'  => 1,
			'args'   => 'wp_rest',
			'return' => 'nonce',
		] );

		App::$instance->register_user();

		$this->assertTrue( ! empty( App::$instance->v8->context->user ) );
		$this->assertEquals( $user->ID, App::$instance->v8->context->user['ID'] );
		$this->assertEquals( $user->user_login, App::$instance->v8->context->user['user_login'] );
	}

	/**
	 * Test sidebars are properly registered and populated
	 *
	 * @group register-sidebar
	 */
	public function test_register_sidebar() {
		global $wp_registered_sidebars;

		$wp_registered_sidebars = [
			[
				'id' => 'sidebar-1'
			],
			[
				'id' => 'sidebar-2'
			],
			[
				'id' => 'sidebar-3'
			],
		];

		\WP_Mock::userFunction( 'dynamic_sidebar', [
			'times'  => 3,
			'return' => function() {
				echo 'sidebar';
			},
		] );

		App::$instance->register_sidebars();

		$this->assertTrue( ! empty( App::$instance->v8->context->sidebars ) );
		$this->assertEquals( 3, count( App::$instance->v8->context->sidebars ) );
		$this->assertEquals( 'sidebar', App::$instance->v8->context->sidebars['sidebar-1'] );
	}

	/**
	 * Test posts context is properly registered and that dummy posts populate
	 *
	 * @group register-posts
	 */
	public function test_register_posts() {
		$dummy_count = 3;

		$GLOBALS['wp_the_query'] = new \stdClass();
		$GLOBALS['wp_the_query']->posts = [];

		// Create dummy posts
		for ( $i = 1; $i <= $dummy_count; $i++ ) {
			$post = new \stdClass();

			$post->ID = $i;
			$post->post_title = 'Post Title ' . $i;
			$post->post_content = 'Post Content ' . $i;

			$GLOBALS['wp_the_query']->posts[] = $post;
		}

		\WP_Mock::userFunction( 'get_post_class', [
			'times'  => $dummy_count,
			'return' => 'post-class',
		] );

		\WP_Mock::userFunction( 'get_permalink', [
			'times'  => $dummy_count,
			'return' => 'permalink',
		] );

		App::$instance->register_posts();
		$this->assertEquals( 3, count( App::$instance->v8->context->posts ) );
		$this->assertEquals( 'Post Title 2',  App::$instance->v8->context->posts[1]['post_title'] );
		$this->assertEquals( 'Post Content 2',  App::$instance->v8->context->posts[1]['post_content'] );
		$this->assertEquals( 'Post Title 2',  App::$instance->v8->context->posts[1]['the_title'] );
		$this->assertEquals( 'Post Content 2',  App::$instance->v8->context->posts[1]['the_content'] );
		$this->assertTrue( ! empty( App::$instance->v8->context->posts[1]['post_class'] ) );
		$this->assertTrue( ! empty( App::$instance->v8->context->posts[1]['permalink'] ) );
	}

	/**
	 * Test menu registration and population making sure to check children
	 *
	 * @group register-menus
	 */
	public function test_register_menus() {
		\WP_Mock::userFunction( 'get_nav_menu_locations', [
			'times'  => 1,
			'return' => [
				'location-1' => 'menu-1',
			],
		] );

		$item1 = new \stdClass();
		$item1->ID = 1;
		$item1->url = 'url 1';
		$item1->title = 'title 1';

		$item2 = new \stdClass();
		$item2->ID = 2;
		$item2->url = 'url 2';
		$item2->title = 'title 2';

		$item3 = new \stdClass();
		$item3->ID = 3;
		$item3->url = 'url 3';
		$item3->title = 'title 3';
		$item3->menu_item_parent = 1;

		\WP_Mock::userFunction( 'wp_get_nav_menu_items', [
			'times' => 1,
			'return' => [
				$item1,
				$item2,
				$item3,
			],
		] );

		App::$instance->register_menus();

		$this->assertEquals( 1, count( App::$instance->v8->context->nav_menus ) );
		$this->assertEquals( 2, count( App::$instance->v8->context->nav_menus['location-1'] ) );
		$this->assertEquals( 'url 2', App::$instance->v8->context->nav_menus['location-1'][1]['url'] );
		$this->assertEquals( 'title 2', App::$instance->v8->context->nav_menus['location-1'][1]['title'] );
		$this->assertEquals( 'url 1', App::$instance->v8->context->nav_menus['location-1'][0]['url'] );
		$this->assertEquals( 'title 1', App::$instance->v8->context->nav_menus['location-1'][0]['title'] );
		$this->assertEquals( 1, count( App::$instance->v8->context->nav_menus['location-1'][0]['children'] ) );
		$this->assertEquals( 'url 3', App::$instance->v8->context->nav_menus['location-1'][0]['children'][0]['url'] );
		$this->assertEquals( 'title 3', App::$instance->v8->context->nav_menus['location-1'][0]['children'][0]['title'] );
	}

	/**
	 * Test route registration for home page
	 *
	 * @group register-route
	 */
	public function test_register_route_home() {
		\WP_Mock::userFunction( 'wp_get_document_title', [
			'times' => 1,
			'return' => 'title',
		] );

		\WP_Mock::userFunction( 'is_home', [
			'times' => 2,
			'return' => true,
		] );

		App::instance()->register_route();

		$this->assertEquals( 'title', App::$instance->v8->context->route['document_title'] );
		$this->assertEquals( 'home', App::$instance->v8->context->route['type'] );
		$this->assertEquals( null, App::$instance->v8->context->route['object_id'] );
	}

	/**
	 * Test route registration for single post
	 *
	 * @group register-route
	 */
	public function test_register_route_single() {
		\WP_Mock::userFunction( 'wp_get_document_title', [
			'times' => 1,
			'return' => 'title',
		] );

		\WP_Mock::userFunction( 'is_home', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_404', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_front_page', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_single', [
			'times' => 1,
			'return' => true,
		] );

		$object = new \stdClass();
		$object->ID = 1;
		$object->post_type = 'post';

		\WP_Mock::userFunction( 'get_queried_object', [
			'times' => 1,
			'return' => $object,
		] );

		App::instance()->register_route();

		$this->assertEquals( 'title', App::$instance->v8->context->route['document_title'] );
		$this->assertEquals( 'single', App::$instance->v8->context->route['type'] );
		$this->assertEquals( 1, App::$instance->v8->context->route['object_id'] );
		$this->assertEquals( 'post', App::$instance->v8->context->route['object_type'] );
	}

	/**
	 * Test route registration for taxonomy archive
	 *
	 * @group register-route
	 */
	public function test_register_route_archive() {
		\WP_Mock::userFunction( 'wp_get_document_title', [
			'times' => 1,
			'return' => 'title',
		] );

		\WP_Mock::userFunction( 'is_home', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_front_page', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_single', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_page', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_author', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_post_type_archive', [
			'times' => 1,
			'return' => false,
		] );

		\WP_Mock::userFunction( 'is_tax', [
			'times' => 1,
			'return' => true,
		] );

		$object = new \stdClass();
		$object->taxonomy = 'post_tag';

		\WP_Mock::userFunction( 'get_queried_object', [
			'times' => 1,
			'return' => $object,
		] );

		App::instance()->register_route();

		$this->assertEquals( 'title', App::$instance->v8->context->route['document_title'] );
		$this->assertEquals( 'archive', App::$instance->v8->context->route['type'] );
		$this->assertEquals( null, App::$instance->v8->context->route['object_id'] );
		$this->assertEquals( 'post_tag', App::$instance->v8->context->route['object_type'] );
	}

	/**
	 * Test template tag registration when tag execution happens immediately
	 *
	 * @group register-template-tag
	 */
	public function test_register_template_tag_no_action() {
		App::instance()->register_template_tag( 'my_tag', function() {
			return 'my tag';
		}, false, false );

		$this->assertEquals( 1, count( App::$instance->v8->context->template_tags ) );
		$this->assertEquals( 'my tag', App::$instance->v8->context->template_tags['my_tag'] );
	}

	/**
	 * Test template tag string string registration on action
	 *
	 * @group register-template-tag
	 */
	public function test_register_template_tag_string_on_action() {
		App::instance()->register_template_tag( 'my_tag', function() {
			return 'my tag';
		}, true, 'nodeifywp_test_render' );

		do_action( 'nodeifywp_test_render' );

		$this->assertEquals( 1, count( App::$instance->v8->context->template_tags ) );
		$this->assertEquals( 'my tag', App::$instance->v8->context->template_tags['my_tag'] );
	}

	/**
	 * Test template tag string array registration on action
	 *
	 * @group register-template-tag
	 */
	public function test_register_template_tag_array_on_action() {
		App::instance()->register_template_tag( 'my_tag', function() {
			return [ 'my tag' ];
		}, true, 'nodeifywp_test_render' );

		do_action( 'nodeifywp_test_render' );

		$this->assertEquals( 1, count( App::$instance->v8->context->template_tags ) );
		$this->assertEquals( 'my tag', App::$instance->v8->context->template_tags['my_tag'][0] );
	}
	
	/**
	 * Todo: test post tags
	 */
}
