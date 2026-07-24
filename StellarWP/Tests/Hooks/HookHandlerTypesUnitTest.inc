<?php

class Example {

	public function register() {
		// Filter, same-file method: every param + the return type must go.
		add_filter( 'the_content', [ $this, 'filter_method' ] );

		// Action, same-file method: params must go; a void return is allowed.
		add_action( 'save_post', [ $this, 'action_method' ] );

		// Filter with several context arguments: all of them must go.
		add_filter( 'user_has_cap', [ $this, 'filter_context_method' ] );

		// Action with a typed param: the param must go (void return is allowed).
		add_action( 'transition_post_status', [ $this, 'action_typed' ] );

		// Action closure: param flagged, void return allowed.
		add_action( 'init', function ( int $x ): void {} );

		// Filter closure: both params + the return type.
		add_filter( 'login_redirect', function ( bool $valid, int $id ): bool {
			return $valid;
		} );

		// Action closure with nullable / by-reference / variadic params.
		add_action( 'pre_get_posts', function ( ?WP_Query $q, int &$ref, string ...$rest ) {} );

		// Filter via self::class static handler.
		add_filter( 'wp_title', [ self::class, 'static_filter' ] );

		// Action via the old-style [ &$this, 'method' ] reference.
		add_action( 'wp_mail_failed', [ &$this, 'ref_action' ] );

		// Filter via a same-file global-function handler.
		add_filter( 'excerpt_length', 'global_filter' );

		// Dynamic hook name - skipped by the sniff (the PHPStan rule catches it).
		$hook = 'the_content';
		add_filter( $hook, [ $this, 'filter_method' ] );

		// Cross-file callback - left to the PHPStan rule.
		add_filter( 'get_the_excerpt', 'some_cross_file_handler' );

		// Wrapper method calls on $this: the handler is a method on this class.
		$this->add_action( 'save_post', 'wrapper_action' );
		$this->add_filter( 'the_title', 'wrapper_filter' );
	}

	public function filter_method( string $content ): string {
		return $content;
	}

	public function action_method( int $post_id, WP_Post $post ): void {}

	public function filter_context_method( bool $has_access, int $post_id, int $user_id ): bool {
		return $has_access;
	}

	public function action_typed( int $id ): void {}

	public function ref_action( \WP_Error $error ) {}

	public static function static_filter( string $title ): string {
		return $title;
	}

	public function wrapper_action( int $id ): void {}

	public function wrapper_filter( string $title ): string {
		return $title;
	}
}

function global_filter( string $length ): string {
	return $length;
}
