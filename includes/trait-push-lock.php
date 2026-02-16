<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advisory lock support for push dedup protection.
 *
 * Prevents TOCTOU race conditions in the search-before-create path
 * of push_to_odoo(). Delegates to Advisory_Lock for the actual
 * GET_LOCK/RELEASE_LOCK calls.
 *
 * @package WP4Odoo
 * @since   3.2.5
 */
trait Push_Lock {

	/**
	 * Current push advisory lock instance.
	 *
	 * @var Advisory_Lock|null
	 */
	private ?Advisory_Lock $push_lock = null;

	/**
	 * Acquire an advisory lock for push dedup protection.
	 *
	 * @param string $lock_name MySQL advisory lock name.
	 * @return bool True if lock acquired within 5 seconds.
	 */
	private function acquire_push_lock( string $lock_name ): bool {
		$this->push_lock = new Advisory_Lock( $lock_name );
		return $this->push_lock->acquire();
	}

	/**
	 * Release the current push advisory lock.
	 *
	 * @return void
	 */
	private function release_push_lock(): void {
		if ( null !== $this->push_lock ) {
			$this->push_lock->release();
			$this->push_lock = null;
		}
	}
}
