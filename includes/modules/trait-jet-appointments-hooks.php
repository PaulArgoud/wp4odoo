<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetAppointments hook registrations.
 *
 * Registers WordPress hooks for JetAppointments service and appointment
 * events. Services are CPTs (hook via save_post), appointments are managed
 * by JetAppointments' own DB layer (hook via plugin actions).
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait Jet_Appointments_Hooks {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		if ( ! defined( 'JET_APB_VERSION' ) && ! class_exists( 'JET_APB\Plugin' ) ) {
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

		if ( ! empty( $settings['sync_appointments'] ) ) {
			// JetAppointments fires these actions after DB operations.
			add_action(
				'jet-apb/db/appointment/after-insert',
				$this->safe_callback( [ $this, 'on_appointment_created' ] ),
				10,
				2
			);
			add_action(
				'jet-apb/db/appointment/after-update',
				$this->safe_callback( [ $this, 'on_appointment_updated' ] ),
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

	// ─── Appointment callbacks ─────────────────────────────

	/**
	 * Handle appointment creation.
	 *
	 * @param int              $appointment_id Appointment ID.
	 * @param array<string, mixed> $data           Appointment data.
	 * @return void
	 */
	public function on_appointment_created( int $appointment_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'appointment', 'sync_appointments', $appointment_id );
	}

	/**
	 * Handle appointment update.
	 *
	 * @param int              $appointment_id Appointment ID.
	 * @param array<string, mixed> $data           Appointment data.
	 * @return void
	 */
	public function on_appointment_updated( int $appointment_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'appointment', 'sync_appointments', $appointment_id );
	}
}
