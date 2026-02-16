<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate limiter with dual-strategy: atomic object cache or transient fallback.
 *
 * On sites with a persistent external object cache (Redis, Memcached),
 * uses wp_cache_add() / wp_cache_incr() for atomic counter increments
 * that are safe against TOCTOU race conditions across concurrent requests.
 *
 * On sites without an external object cache, falls back to the original
 * transient-based approach (get_transient + set_transient). This is
 * acceptable because transients without persistent cache are backed by
 * the database, and the small race window (~1 extra request in a burst)
 * is tolerable for a LOW-severity rate limiter.
 *
 * @package WP4Odoo
 * @since   3.3.0
 */
class Rate_Limiter {

	/**
	 * Key prefix for both cache and transient keys.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Maximum allowed requests within the window.
	 *
	 * @var int
	 */
	private int $max_requests;

	/**
	 * Rate limit window in seconds.
	 *
	 * @var int
	 */
	private int $window;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param string      $prefix       Key prefix (e.g. 'wp4odoo_rl_').
	 * @param int         $max_requests Maximum requests per window.
	 * @param int         $window       Window duration in seconds.
	 * @param Logger|null $logger       Optional logger for rate limit events.
	 */
	public function __construct( string $prefix, int $max_requests, int $window, ?Logger $logger = null ) {
		$this->prefix       = $prefix;
		$this->max_requests = $max_requests;
		$this->window       = $window;
		$this->logger       = $logger;
	}

	/**
	 * Check whether an identifier is within the rate limit.
	 *
	 * Increments the counter on each call. Returns true if under the limit,
	 * or a WP_Error with status 429 if the limit is exceeded.
	 *
	 * Strategy selection:
	 * - External object cache (Redis/Memcached): uses wp_cache_add() +
	 *   wp_cache_incr() for atomic, race-free counter increments.
	 * - No external cache: falls back to transient-based get/set. A minor
	 *   TOCTOU race exists (~1 extra request under heavy concurrency) but
	 *   is acceptable given the low severity of the rate limiter.
	 *
	 * @param string $identifier Unique identifier to rate-limit (e.g. IP address).
	 * @return true|\WP_Error True if under limit, WP_Error if exceeded.
	 */
	public function check( string $identifier ): true|\WP_Error {
		if ( wp_using_ext_object_cache() ) {
			return $this->check_via_object_cache( $identifier );
		}

		return $this->check_via_transient( $identifier );
	}

	/**
	 * Atomic rate-limit check via the persistent object cache.
	 *
	 * Uses wp_cache_add() to atomically initialize the counter (returns false
	 * if the key already exists), then wp_cache_incr() for atomic increments.
	 * Both operations are atomic on Redis/Memcached backends.
	 *
	 * @param string $identifier Unique identifier to rate-limit.
	 * @return true|\WP_Error True if under limit, WP_Error if exceeded.
	 */
	private function check_via_object_cache( string $identifier ): true|\WP_Error {
		$key   = $this->prefix . md5( $identifier );
		$group = 'wp4odoo_rate_limit';

		// Try to atomically create the key with value 1.
		$added = wp_cache_add( $key, 1, $group, $this->window );

		if ( $added ) {
			// First request in this window — always allowed.
			return true;
		}

		// Key exists — atomically increment.
		$count = wp_cache_incr( $key, 1, $group );

		if ( false === $count ) {
			// Increment failed (key expired between add and incr) — re-initialize.
			wp_cache_set( $key, 1, $group, $this->window );
			return true;
		}

		if ( $count > $this->max_requests ) {
			return $this->reject( $identifier, $count );
		}

		return true;
	}

	/**
	 * Transient-based rate-limit check (fallback for sites without external object cache).
	 *
	 * Note: a minor TOCTOU race exists between get_transient() and
	 * set_transient(). Under heavy concurrency, ~1 extra request may
	 * slip through. This is acceptable for a rate limiter that protects
	 * against abuse, not billing.
	 *
	 * @param string $identifier Unique identifier to rate-limit.
	 * @return true|\WP_Error True if under limit, WP_Error if exceeded.
	 */
	private function check_via_transient( string $identifier ): true|\WP_Error {
		$key   = $this->prefix . md5( $identifier );
		$count = (int) get_transient( $key );

		if ( $count >= $this->max_requests ) {
			return $this->reject( $identifier, $count );
		}

		set_transient( $key, $count + 1, $this->window );

		return true;
	}

	/**
	 * Log and return a rate-limit exceeded WP_Error.
	 *
	 * @param string $identifier The rate-limited identifier.
	 * @param int    $count      Current request count.
	 * @return \WP_Error Error with code 429.
	 */
	private function reject( string $identifier, int $count ): \WP_Error {
		if ( null !== $this->logger ) {
			$this->logger->warning(
				'Rate limit exceeded.',
				[
					'identifier' => $identifier,
					'count'      => $count,
				]
			);
		}

		return new \WP_Error(
			'wp4odoo_rate_limited',
			__( 'Too many requests. Please try again later.', 'wp4odoo' ),
			[ 'status' => 429 ]
		);
	}
}
