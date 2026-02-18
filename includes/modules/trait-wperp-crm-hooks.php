<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP CRM hook callbacks for push operations.
 *
 * Handles CRM contact and activity events via WP ERP CRM action hooks.
 *
 * Expects the using class to provide:
 * - should_sync(): bool           (from Module_Base)
 * - push_entity(): void           (from Module_Helpers)
 * - get_mapping(): ?int           (from Module_Base)
 * - safe_callback(): \Closure     (from Module_Base)
 * - is_importing(): bool          (from Module_Base)
 * - $id: string                   (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait WPERP_CRM_Hooks {

	/**
	 * Register WP ERP CRM hooks.
	 *
	 * Called from boot() after plugin detection.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_leads'] ) ) {
			add_action( 'erp_crm_create_contact', $this->safe_callback( [ $this, 'on_contact_created' ] ), 10, 2 );
			add_action( 'erp_crm_update_contact', $this->safe_callback( [ $this, 'on_contact_updated' ] ), 10, 2 );
			add_action( 'erp_crm_delete_contact', $this->safe_callback( [ $this, 'on_contact_deleted' ] ), 10, 1 );
		}

		if ( ! empty( $settings['sync_activities'] ) ) {
			add_action( 'erp_crm_create_activities', $this->safe_callback( [ $this, 'on_activity_created' ] ), 10, 2 );
		}
	}

	// ─── Contact callbacks ────────────────────────────────

	/**
	 * Handle new CRM contact creation.
	 *
	 * @param int                  $contact_id Contact ID (erp_peoples.id).
	 * @param array<string, mixed> $data       Contact data from WP ERP.
	 * @return void
	 */
	public function on_contact_created( int $contact_id, array $data = [] ): void {
		if ( $contact_id <= 0 ) {
			return;
		}

		$this->push_entity( 'lead', 'sync_leads', $contact_id );
	}

	/**
	 * Handle CRM contact update.
	 *
	 * @param int                  $contact_id Contact ID (erp_peoples.id).
	 * @param array<string, mixed> $data       Contact data from WP ERP.
	 * @return void
	 */
	public function on_contact_updated( int $contact_id, array $data = [] ): void {
		if ( $contact_id <= 0 ) {
			return;
		}

		$this->push_entity( 'lead', 'sync_leads', $contact_id );
	}

	/**
	 * Handle CRM contact deletion.
	 *
	 * @param int $contact_id Contact ID (erp_peoples.id).
	 * @return void
	 */
	public function on_contact_deleted( int $contact_id ): void {
		if ( ! $this->should_sync( 'sync_leads' ) || $contact_id <= 0 ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'lead', $contact_id );
		if ( ! $odoo_id ) {
			return;
		}

		Queue_Manager::push( $this->id, 'lead', 'delete', $contact_id, $odoo_id );
	}

	// ─── Activity callbacks ────────────────────────────────

	/**
	 * Handle new CRM activity creation.
	 *
	 * @param int                  $activity_id Activity ID.
	 * @param array<string, mixed> $data        Activity data from WP ERP.
	 * @return void
	 */
	public function on_activity_created( int $activity_id, array $data = [] ): void {
		if ( $activity_id <= 0 ) {
			return;
		}

		$this->push_entity( 'activity', 'sync_activities', $activity_id );
	}
}
