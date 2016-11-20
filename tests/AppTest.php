<?php

namespace NodeifyWP;

class AppTest extends \PHPUnit_Framework_TestCase {
	/**
	 * Set up with WP_Mock
	 *
	 * @since  1.0
	 */
	public function setUp() {
		\WP_Mock::setUp();

		\WP_Mock::userFunction( 'remove_action' );
		App::setup( 'server.js', 'client.js' );
	}

	/**
	 * Tear down with WP_Mock
	 *
	 * @since  1.0
	 */
	public function tearDown() {
		\WP_Mock::tearDown();
	}

	public function test_setup() {
		$this->assertTrue( is_a( App::$instance->v8, '\V8Js' ) );
		$this->assertTrue( ! empty( App::$instance->v8->context ) );
	}

	public function test_register_user_logged_out() {
		\WP_Mock::userFunction( 'is_user_logged_in', [
			'times'  => 1,
			'return' => false,
		] );

		App::$instance->register_user();

		$this->assertTrue( empty( App::$instance->v8->context->user ) );
	}

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
}
