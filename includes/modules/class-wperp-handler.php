<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP ERP Handler -- data access for WP ERP HR tables.
 *
 * WP ERP stores HR data in its own custom tables ({prefix}erp_hr_employees,
 * {prefix}erp_hr_departments, {prefix}erp_hr_designations,
 * {prefix}erp_hr_leaves). This handler queries them via $wpdb since
 * WP ERP does not use WordPress CPTs for HR entities.
 *
 * Called by WPERP_Module via its load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.2.5
 */
class WPERP_Handler {

	/**
	 * WP ERP leave status map: WP ERP numeric status -> Odoo hr.leave state.
	 *
	 * WP ERP: 1=pending, 2=approved, 3=rejected.
	 * Odoo:   draft, confirm, validate, validate1, refuse.
	 *
	 * Keys are prefixed with 's' to prevent PHP integer key coercion.
	 * The resolve() call strips the prefix via array_combine().
	 *
	 * @var array<string, string>
	 */
	private const LEAVE_STATUS_MAP = [
		'pending'  => 'draft',
		'approved' => 'validate',
		'rejected' => 'refuse',
	];

	/**
	 * Map from WP ERP numeric status to internal key.
	 *
	 * @var array<int, string>
	 */
	private const WP_STATUS_LABELS = [
		1 => 'pending',
		2 => 'approved',
		3 => 'rejected',
	];

	/**
	 * Reverse leave status map: Odoo hr.leave state -> WP ERP numeric status.
	 *
	 * @var array<string, string>
	 */
	private const LEAVE_REVERSE_STATUS_MAP = [
		'draft'     => '1',
		'confirm'   => '1',
		'validate'  => '2',
		'validate1' => '2',
		'refuse'    => '3',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	// --- Load employee ---------------------------------------------------

	/**
	 * Load an employee from WP ERP's custom table.
	 *
	 * Queries erp_hr_employees, enriches with user data from get_userdata()
	 * and designation name from erp_hr_designations.
	 *
	 * @param int $emp_id WP ERP employee ID (= WordPress user ID).
	 * @return array<string, mixed> Employee data for Odoo, or empty if not found.
	 */
	public function load_employee( int $emp_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_employees';
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $emp_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP employee not found.', [ 'emp_id' => $emp_id ] );
			return [];
		}

		$user  = get_userdata( $emp_id );
		$name  = $user ? $user->display_name : '';
		$email = $user ? $user->user_email : '';

		// Designation lookup.
		$designation_id   = (int) ( $row['designation'] ?? 0 );
		$designation_name = '';
		if ( $designation_id > 0 ) {
			$desig_table      = $wpdb->prefix . 'erp_hr_designations';
			$designation_name = (string) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				$wpdb->prepare( "SELECT title FROM {$desig_table} WHERE id = %d", $designation_id )
			);
		}

		$gender = (string) ( $row['gender'] ?? '' );

		return [
			'name'       => $name,
			'work_email' => $email,
			'job_title'  => $designation_name,
			'gender'     => $gender,
			'birthday'   => $row['date_of_birth'] ?? '',
			'user_id'    => $emp_id,
			'department' => (int) ( $row['department'] ?? 0 ),
		];
	}

	// --- Load department -------------------------------------------------

	/**
	 * Load a department from WP ERP's custom table.
	 *
	 * @param int $dept_id WP ERP department ID.
	 * @return array<string, mixed> Department data, or empty if not found.
	 */
	public function load_department( int $dept_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_departments';
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $dept_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP department not found.', [ 'dept_id' => $dept_id ] );
			return [];
		}

		return [
			'name'      => $row['title'] ?? '',
			'parent_id' => (int) ( $row['parent'] ?? 0 ),
		];
	}

	// --- Load leave ------------------------------------------------------

	/**
	 * Load a leave request from WP ERP's custom table.
	 *
	 * Maps WP ERP numeric status to Odoo hr.leave state via Status_Mapper.
	 *
	 * @param int $leave_id WP ERP leave ID.
	 * @return array<string, mixed> Leave data for Odoo, or empty if not found.
	 */
	public function load_leave( int $leave_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_leaves';
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $leave_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'WP ERP leave not found.', [ 'leave_id' => $leave_id ] );
			return [];
		}

		$wp_status  = (int) ( $row['status'] ?? 1 );
		$odoo_state = $this->map_leave_status_to_odoo( $wp_status );

		return [
			'name'        => $row['reason'] ?? '',
			'date_from'   => $row['start_date'] ?? '',
			'date_to'     => $row['end_date'] ?? '',
			'state'       => $odoo_state,
			'employee_id' => (int) ( $row['user_id'] ?? 0 ),
		];
	}

	// --- Status mapping --------------------------------------------------

	/**
	 * Map WP ERP leave status to Odoo hr.leave state.
	 *
	 * @param int $wp_status WP ERP numeric status (1=pending, 2=approved, 3=rejected).
	 * @return string Odoo hr.leave state.
	 */
	public function map_leave_status_to_odoo( int $wp_status ): string {
		$label = self::WP_STATUS_LABELS[ $wp_status ] ?? 'pending';

		return Status_Mapper::resolve(
			$label,
			self::LEAVE_STATUS_MAP,
			'wp4odoo_wperp_leave_status_map',
			'draft'
		);
	}

	/**
	 * Map Odoo hr.leave state to WP ERP leave status.
	 *
	 * @param string $odoo_state Odoo hr.leave state.
	 * @return int WP ERP numeric status.
	 */
	public function map_leave_status_from_odoo( string $odoo_state ): int {
		$result = Status_Mapper::resolve( $odoo_state, self::LEAVE_REVERSE_STATUS_MAP, 'wp4odoo_wperp_leave_reverse_status_map', '1' );

		return (int) $result;
	}

	// --- Dependency resolution -------------------------------------------

	/**
	 * Get the employee (user) ID for a leave request.
	 *
	 * @param int $leave_id WP ERP leave ID.
	 * @return int Employee (user) ID, or 0 if not found.
	 */
	public function get_employee_id_for_leave( int $leave_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_leaves';
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d", $leave_id )
		);
	}

	/**
	 * Get the department ID for an employee.
	 *
	 * @param int $emp_id WP ERP employee ID (= WordPress user ID).
	 * @return int Department ID, or 0 if not found.
	 */
	public function get_department_id_for_employee( int $emp_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_employees';
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->prepare( "SELECT department FROM {$table} WHERE user_id = %d", $emp_id )
		);
	}

	// --- Parse from Odoo (pull) ------------------------------------------

	/**
	 * Parse Odoo hr.employee data into WP ERP format.
	 *
	 * Handles Many2one fields (department_id, address_home_id).
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP employee data.
	 */
	public function parse_employee_from_odoo( array $odoo_data ): array {
		$department_id = $odoo_data['department_id'] ?? false;
		$dept_name     = '';
		$dept_odoo_id  = 0;
		if ( is_array( $department_id ) && count( $department_id ) >= 2 ) {
			$dept_odoo_id = (int) $department_id[0];
			$dept_name    = (string) $department_id[1];
		}

		return [
			'name'               => (string) ( $odoo_data['name'] ?? '' ),
			'work_email'         => (string) ( $odoo_data['work_email'] ?? '' ),
			'job_title'          => (string) ( $odoo_data['job_title'] ?? '' ),
			'gender'             => (string) ( $odoo_data['gender'] ?? '' ),
			'birthday'           => (string) ( $odoo_data['birthday'] ?? '' ),
			'department_name'    => $dept_name,
			'department_odoo_id' => $dept_odoo_id,
		];
	}

	/**
	 * Parse Odoo hr.department data into WP ERP format.
	 *
	 * Handles parent_id Many2one.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP department data.
	 */
	public function parse_department_from_odoo( array $odoo_data ): array {
		$parent_id      = $odoo_data['parent_id'] ?? false;
		$parent_odoo_id = 0;
		if ( is_array( $parent_id ) && count( $parent_id ) >= 2 ) {
			$parent_odoo_id = (int) $parent_id[0];
		}

		return [
			'name'           => (string) ( $odoo_data['name'] ?? '' ),
			'parent_odoo_id' => $parent_odoo_id,
		];
	}

	/**
	 * Parse Odoo hr.leave data into WP ERP format.
	 *
	 * Handles employee_id Many2one and reverse status mapping.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP ERP leave data.
	 */
	public function parse_leave_from_odoo( array $odoo_data ): array {
		$employee_id      = $odoo_data['employee_id'] ?? false;
		$employee_odoo_id = 0;
		if ( is_array( $employee_id ) && count( $employee_id ) >= 2 ) {
			$employee_odoo_id = (int) $employee_id[0];
		}

		$odoo_state = (string) ( $odoo_data['state'] ?? 'draft' );
		$wp_status  = $this->map_leave_status_from_odoo( $odoo_state );

		return [
			'reason'           => (string) ( $odoo_data['name'] ?? '' ),
			'start_date'       => (string) ( $odoo_data['date_from'] ?? '' ),
			'end_date'         => (string) ( $odoo_data['date_to'] ?? '' ),
			'status'           => $wp_status,
			'employee_odoo_id' => $employee_odoo_id,
		];
	}

	// --- Save (pull) -----------------------------------------------------

	/**
	 * Save employee data to WP ERP's custom table.
	 *
	 * Creates a new row when $wp_id is 0, updates existing otherwise.
	 *
	 * @param array<string, mixed> $data  Parsed employee data.
	 * @param int                  $wp_id Existing employee ID (0 to create new).
	 * @return int The employee ID (user_id), or 0 on failure.
	 */
	public function save_employee( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_employees';
		$row   = [
			'designation'   => $data['job_title'] ?? '',
			'department'    => $data['department'] ?? 0,
			'gender'        => $data['gender'] ?? '',
			'date_of_birth' => $data['birthday'] ?? '',
		];

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'user_id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$row['user_id'] = $data['user_id'] ?? 0;
		$result         = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Save department data to WP ERP's custom table.
	 *
	 * Creates a new row when $wp_id is 0, updates existing otherwise.
	 *
	 * @param array<string, mixed> $data  Parsed department data.
	 * @param int                  $wp_id Existing department ID (0 to create new).
	 * @return int The department ID, or 0 on failure.
	 */
	public function save_department( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_departments';
		$row   = [
			'title'  => $data['name'] ?? '',
			'parent' => $data['parent_id'] ?? 0,
		];

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$result = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Save leave data to WP ERP's custom table.
	 *
	 * Creates a new row when $wp_id is 0, updates existing otherwise.
	 *
	 * @param array<string, mixed> $data  Parsed leave data.
	 * @param int                  $wp_id Existing leave ID (0 to create new).
	 * @return int The leave ID, or 0 on failure.
	 */
	public function save_leave( array $data, int $wp_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_leaves';
		$row   = [
			'reason'     => $data['reason'] ?? '',
			'start_date' => $data['start_date'] ?? '',
			'end_date'   => $data['end_date'] ?? '',
			'status'     => $data['status'] ?? 1,
			'user_id'    => $data['user_id'] ?? 0,
		];

		if ( $wp_id > 0 ) {
			$result = $wpdb->update( $table, $row, [ 'id' => $wp_id ] );
			return false !== $result ? $wp_id : 0;
		}

		$result = $wpdb->insert( $table, $row );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	// --- Delete (pull) ---------------------------------------------------

	/**
	 * Delete an employee from WP ERP's custom table.
	 *
	 * @param int $emp_id Employee ID (user_id).
	 * @return bool True on success.
	 */
	public function delete_employee( int $emp_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_employees';
		return false !== $wpdb->delete( $table, [ 'user_id' => $emp_id ] );
	}

	/**
	 * Delete a department from WP ERP's custom table.
	 *
	 * @param int $dept_id Department ID.
	 * @return bool True on success.
	 */
	public function delete_department( int $dept_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_departments';
		return false !== $wpdb->delete( $table, [ 'id' => $dept_id ] );
	}

	/**
	 * Delete a leave from WP ERP's custom table.
	 *
	 * @param int $leave_id Leave ID.
	 * @return bool True on success.
	 */
	public function delete_leave( int $leave_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erp_hr_leaves';
		return false !== $wpdb->delete( $table, [ 'id' => $leave_id ] );
	}
}
