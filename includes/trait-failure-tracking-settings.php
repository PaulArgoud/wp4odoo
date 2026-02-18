<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Failure tracking settings for the notification system.
 *
 * Manages consecutive failure count and last notification email
 * timestamp used by Failure_Notifier to throttle admin emails.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait Failure_Tracking_Settings {

	public const OPT_CONSECUTIVE_FAILURES = 'wp4odoo_consecutive_failures';
	public const OPT_LAST_FAILURE_EMAIL   = 'wp4odoo_last_failure_email';

	/**
	 * Get the consecutive failure count.
	 *
	 * @return int
	 */
	public function get_consecutive_failures(): int {
		return $this->get_int_option( self::OPT_CONSECUTIVE_FAILURES );
	}

	/**
	 * Save the consecutive failure count.
	 *
	 * @param int $count Failure count.
	 * @return bool
	 */
	public function save_consecutive_failures( int $count ): bool {
		return $this->set_int_option( self::OPT_CONSECUTIVE_FAILURES, $count );
	}

	/**
	 * Get the last failure email timestamp.
	 *
	 * @return int Unix timestamp.
	 */
	public function get_last_failure_email(): int {
		return $this->get_int_option( self::OPT_LAST_FAILURE_EMAIL );
	}

	/**
	 * Save the last failure email timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return bool
	 */
	public function save_last_failure_email( int $timestamp ): bool {
		return $this->set_int_option( self::OPT_LAST_FAILURE_EMAIL, $timestamp );
	}
}
