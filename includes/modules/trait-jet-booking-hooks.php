<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetBooking hook registrations.
 *
 * Registers WordPress hooks for JetBooking service and booking
 * events. Services are CPTs (hook via save_post), bookings are managed
 * by JetBooking's own DB layer (hook via plugin actions).
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait Jet_Booking_Hooks {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		if ( ! defined( 'JET_ABAF_VERSION' ) && ! class_exists( 'JET_ABAF\Plugin' ) ) {
			return;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_services'] ) ) {
			$service_cpt = $this->handler->get_service_cpt();
			add_action(
				"save_post_{$service_cpt}",
				$this->safe_callback( [ $this, 'on_service_save' ] ),
				10,
				1
			);
		}

		if ( ! empty( $settings['sync_bookings'] ) ) {
			// JetBooking fires these actions after DB operations.
			add_action(
				'jet-abaf/db/booking/after-insert',
				$this->safe_callback( [ $this, 'on_booking_created' ] ),
				10,
				2
			);
			add_action(
				'jet-abaf/db/booking/after-update',
				$this->safe_callback( [ $this, 'on_booking_updated' ] ),
				10,
				2
			);
		}
	}

	// ─── Service callbacks ─────────────────────────────────

	/**
	 * Handle service save.
	 *
	 * @param int $post_id Service post ID.
	 * @return void
	 */
	public function on_service_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, $this->handler->get_service_cpt(), 'sync_services', 'service' );
	}

	// ─── Booking callbacks ─────────────────────────────────

	/**
	 * Handle booking creation.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param array<string, mixed> $data       Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'booking', 'sync_bookings', $booking_id );
	}

	/**
	 * Handle booking update.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param array<string, mixed> $data       Booking data.
	 * @return void
	 */
	public function on_booking_updated( int $booking_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'booking', 'sync_bookings', $booking_id );
	}
}
