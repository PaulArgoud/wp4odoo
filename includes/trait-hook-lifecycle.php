<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook lifecycle management for modules.
 *
 * Provides graceful degradation via safe_callback(), automatic hook
 * registration with teardown tracking via register_hook(), and
 * bulk hook removal via teardown().
 *
 * Expects the using class to provide:
 * - Logger $logger  (property)
 * - string $id      (property)
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait Hook_Lifecycle {

	/**
	 * Registered WordPress hooks for teardown support.
	 *
	 * Each entry is [tag, callback, priority]. Populated by
	 * register_hook() and consumed by teardown().
	 *
	 * @var array<int, array{string, \Closure, int}>
	 */
	private array $registered_hooks = [];

	/**
	 * Wrap a hook callback in a try/catch for graceful degradation.
	 *
	 * Returns a Closure that forwards all arguments to the original callable.
	 * If the callback throws any \Throwable (fatal error, TypeError, etc.),
	 * the exception is caught and logged instead of crashing the WordPress
	 * request. This protects against third-party plugin API changes.
	 *
	 * Usage: `add_action( 'third_party_hook', $this->safe_callback( [ $this, 'on_event' ] ) );`
	 *
	 * @param callable $callback The original callback to wrap.
	 * @return \Closure Wrapped callback that never throws.
	 */
	protected function safe_callback( callable $callback ): \Closure {
		return function () use ( $callback ): void {
			try {
				$callback( ...func_get_args() );
			} catch ( \Throwable $e ) {
				$this->logger->critical(
					'Hook callback crashed (graceful degradation).',
					[
						'module'    => $this->id,
						'exception' => get_class( $e ),
						'message'   => $e->getMessage(),
						'file'      => $e->getFile(),
						'line'      => $e->getLine(),
					]
				);
			}
		};
	}

	/**
	 * Register a WordPress hook with automatic teardown tracking.
	 *
	 * Wraps add_action() and records the hook reference so teardown()
	 * can remove it later. Uses safe_callback() internally for graceful
	 * degradation. Prefer this over manual add_action() + safe_callback()
	 * in new module code.
	 *
	 * @param string   $tag           Hook name.
	 * @param callable $callback      The method to call (e.g. [$this, 'on_event']).
	 * @param int      $priority      Hook priority (default 10).
	 * @param int      $accepted_args Number of arguments (default 1).
	 * @return void
	 */
	protected function register_hook( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$wrapped = $this->safe_callback( $callback );
		add_action( $tag, $wrapped, $priority, $accepted_args );
		$this->registered_hooks[] = [ $tag, $wrapped, $priority ];
	}

	/**
	 * Remove all hooks registered via register_hook().
	 *
	 * Called when a module is disabled at runtime (e.g. via admin toggle)
	 * to stop processing hook callbacks for the remainder of the request.
	 * Hooks not registered via register_hook() (legacy pattern) are
	 * unaffected — they rely on should_sync() for short-circuiting.
	 *
	 * @return void
	 */
	public function teardown(): void {
		foreach ( $this->registered_hooks as [ $tag, $callback, $priority ] ) {
			remove_action( $tag, $callback, $priority );
		}
		$this->registered_hooks = [];
	}
}
