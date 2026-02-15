<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP hook callbacks for push operations.
 *
 * Extracted from WPERP_Module for single responsibility.
 * Handles employee, department, and leave events via WP ERP action hooks.
 *
 * Expects the using class to provide:
 * - should_sync(): bool              (from Module_Base)
 * - push_entity(): void              (from Module_Helpers)
 * - get_mapping(): ?int              (from Module_Base)
 * - safe_callback(): \Closure        (from Module_Base)
 * - $id: string                      (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.2.5
 */
trait WPERP_Hooks {

	/**
	 * Register WP ERP hooks.
	 *
	 * Called from boot() after plugin detection.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_employees'] ) ) {
			add_action( 'erp_hr_employee_new', $this->safe_callback( [ $this, 'on_employee_new' ] ), 10, 2 );
			add_action( 'erp_hr_employee_update', $this->safe_callback( [ $this, 'on_employee_update' ] ), 10, 2 );
			add_action( 'erp_hr_employee_delete', $this->safe_callback( [ $this, 'on_employee_delete' ] ), 10, 1 );
		}

		if ( ! empty( $settings['sync_departments'] ) ) {
			add_action( 'erp_hr_dept_new', $this->safe_callback( [ $this, 'on_department_new' ] ), 10, 2 );
			add_action( 'erp_hr_dept_update', $this->safe_callback( [ $this, 'on_department_update' ] ), 10, 2 );
			add_action( 'erp_hr_dept_delete', $this->safe_callback( [ $this, 'on_department_delete' ] ), 10, 1 );
		}

		if ( ! empty( $settings['sync_leaves'] ) ) {
			add_action( 'erp_hr_leave_new', $this->safe_callback( [ $this, 'on_leave_new' ] ), 10, 2 );
			add_action( 'erp_hr_leave_status_change', $this->safe_callback( [ $this, 'on_leave_status_change' ] ), 10, 2 );
		}
	}

	// --- Employee hooks --------------------------------------------------

	/**
	 * Handle new employee creation.
	 *
	 * @param int   $emp_id Employee (user) ID.
	 * @param array $data   Employee data from WP ERP.
	 * @return void
	 */
	public function on_employee_new( int $emp_id, array $data ): void {
		if ( $emp_id <= 0 ) {
			return;
		}

		$this->push_entity( 'employee', 'sync_employees', $emp_id );
	}

	/**
	 * Handle employee update.
	 *
	 * @param int   $emp_id Employee (user) ID.
	 * @param array $data   Employee data from WP ERP.
	 * @return void
	 */
	public function on_employee_update( int $emp_id, array $data ): void {
		if ( $emp_id <= 0 ) {
			return;
		}

		$this->push_entity( 'employee', 'sync_employees', $emp_id );
	}

	/**
	 * Handle employee deletion.
	 *
	 * @param int $emp_id Employee (user) ID.
	 * @return void
	 */
	public function on_employee_delete( int $emp_id ): void {
		if ( ! $this->should_sync( 'sync_employees' ) || $emp_id <= 0 ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'employee', $emp_id );
		if ( ! $odoo_id ) {
			return;
		}

		Queue_Manager::push( $this->id, 'employee', 'delete', $emp_id, $odoo_id );
	}

	// --- Department hooks ------------------------------------------------

	/**
	 * Handle new department creation.
	 *
	 * @param int   $dept_id Department ID.
	 * @param array $data    Department data from WP ERP.
	 * @return void
	 */
	public function on_department_new( int $dept_id, array $data ): void {
		if ( $dept_id <= 0 ) {
			return;
		}

		$this->push_entity( 'department', 'sync_departments', $dept_id );
	}

	/**
	 * Handle department update.
	 *
	 * @param int   $dept_id Department ID.
	 * @param array $data    Department data from WP ERP.
	 * @return void
	 */
	public function on_department_update( int $dept_id, array $data ): void {
		if ( $dept_id <= 0 ) {
			return;
		}

		$this->push_entity( 'department', 'sync_departments', $dept_id );
	}

	/**
	 * Handle department deletion.
	 *
	 * @param int $dept_id Department ID.
	 * @return void
	 */
	public function on_department_delete( int $dept_id ): void {
		if ( ! $this->should_sync( 'sync_departments' ) || $dept_id <= 0 ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'department', $dept_id );
		if ( ! $odoo_id ) {
			return;
		}

		Queue_Manager::push( $this->id, 'department', 'delete', $dept_id, $odoo_id );
	}

	// --- Leave hooks -----------------------------------------------------

	/**
	 * Handle new leave request.
	 *
	 * @param int   $leave_id Leave ID.
	 * @param array $data     Leave data from WP ERP.
	 * @return void
	 */
	public function on_leave_new( int $leave_id, array $data ): void {
		if ( $leave_id <= 0 ) {
			return;
		}

		$this->push_entity( 'leave', 'sync_leaves', $leave_id );
	}

	/**
	 * Handle leave status change.
	 *
	 * @param int   $leave_id Leave ID.
	 * @param array $data     Leave data from WP ERP (includes new status).
	 * @return void
	 */
	public function on_leave_status_change( int $leave_id, array $data ): void {
		if ( $leave_id <= 0 ) {
			return;
		}

		$this->push_entity( 'leave', 'sync_leaves', $leave_id );
	}
}
