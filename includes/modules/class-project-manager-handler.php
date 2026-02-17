<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Project Manager Handler — reads/writes project, task, and timesheet data.
 *
 * WP Project Manager (weDevs) stores projects as a CPT (`cpm_project`) and
 * tasks in custom tables (`pm_tasks`, `pm_boards`, etc.). Timesheet entries
 * are in `pm_time_tracker`.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class Project_Manager_Handler {

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

	// ─── Project data access ───────────────────────────────

	/**
	 * Load a project by post ID.
	 *
	 * WP Project Manager stores projects as CPT `cpm_project`.
	 *
	 * @param int $project_id Project post ID.
	 * @return array<string, mixed> Project data, or empty if not found.
	 */
	public function load_project( int $project_id ): array {
		$post = get_post( $project_id );

		if ( ! $post || 'cpm_project' !== $post->post_type ) {
			return [];
		}

		return [
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'status'      => $post->post_status,
		];
	}

	/**
	 * Parse Odoo project.project data into WP format for pull.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP project data.
	 */
	public function parse_project_from_odoo( array $odoo_data ): array {
		return [
			'title'       => (string) ( $odoo_data['name'] ?? '' ),
			'description' => Field_Mapper::html_to_text( (string) ( $odoo_data['description'] ?? '' ) ),
		];
	}

	/**
	 * Save a project to WordPress.
	 *
	 * @param array<string, mixed> $data  Project data.
	 * @param int                  $wp_id Existing post ID (0 to create).
	 * @return int Post ID, or 0 on failure.
	 */
	public function save_project( array $data, int $wp_id ): int {
		$post_data = [
			'post_title'   => $data['title'] ?? '',
			'post_content' => $data['description'] ?? '',
			'post_type'    => 'cpm_project',
			'post_status'  => 'publish',
		];

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data );
		} else {
			$result = wp_insert_post( $post_data );
		}

		if ( 0 === $result ) {
			$this->logger->warning( 'Failed to save project.', [ 'wp_id' => $wp_id ] );
			return 0;
		}

		return (int) $result;
	}

	/**
	 * Delete a project from WordPress.
	 *
	 * @param int $project_id Project post ID.
	 * @return bool True on success.
	 */
	public function delete_project( int $project_id ): bool {
		$result = wp_delete_post( $project_id, true );
		return false !== $result && null !== $result;
	}

	// ─── Task data access ──────────────────────────────────

	/**
	 * Load a task from the pm_tasks table.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string, mixed> Task data, or empty if not found.
	 */
	public function load_task( int $task_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pm_tasks';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title, description, estimation, start_at, due_date, status, project_id FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return [];
		}

		return [
			'task_id'     => (int) $row['id'],
			'title'       => (string) ( $row['title'] ?? '' ),
			'description' => (string) ( $row['description'] ?? '' ),
			'estimation'  => (float) ( $row['estimation'] ?? 0 ),
			'start_at'    => (string) ( $row['start_at'] ?? '' ),
			'due_date'    => (string) ( $row['due_date'] ?? '' ),
			'status'      => (int) ( $row['status'] ?? 0 ),
			'project_id'  => (int) ( $row['project_id'] ?? 0 ),
		];
	}

	/**
	 * Parse Odoo project.task data into WP format for pull.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WP task data.
	 */
	public function parse_task_from_odoo( array $odoo_data ): array {
		return [
			'title'       => (string) ( $odoo_data['name'] ?? '' ),
			'description' => Field_Mapper::html_to_text( (string) ( $odoo_data['description'] ?? '' ) ),
			'due_date'    => (string) ( $odoo_data['date_deadline'] ?? '' ),
		];
	}

	/**
	 * Save a task to WordPress.
	 *
	 * @param array<string, mixed> $data  Task data (must include project_id).
	 * @param int                  $wp_id Existing task ID (0 to create).
	 * @return int Task ID, or 0 on failure.
	 */
	public function save_task( array $data, int $wp_id ): int {
		global $wpdb;

		$table  = $wpdb->prefix . 'pm_tasks';
		$values = [
			'title'       => $data['title'] ?? '',
			'description' => $data['description'] ?? '',
			'due_date'    => $data['due_date'] ?? null,
		];

		if ( $wp_id > 0 ) {
			$wpdb->update( $table, $values, [ 'id' => $wp_id ] );
			return $wp_id;
		}

		$values['project_id'] = $data['project_id'] ?? 0;
		$values['status']     = 0;
		$wpdb->insert( $table, $values );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a task from WordPress.
	 *
	 * @param int $task_id Task ID.
	 * @return bool True on success.
	 */
	public function delete_task( int $task_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'pm_tasks';
		return false !== $wpdb->delete( $table, [ 'id' => $task_id ] );
	}

	/**
	 * Get the project ID for a task.
	 *
	 * @param int $task_id Task ID.
	 * @return int Project ID, or 0 if not found.
	 */
	public function get_project_id_for_task( int $task_id ): int {
		global $wpdb;

		$table  = $wpdb->prefix . 'pm_tasks';
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT project_id FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$task_id
			)
		);

		return (int) ( $result ?? 0 );
	}

	// ─── Timesheet data access ─────────────────────────────

	/**
	 * Load a timesheet entry from the pm_time_tracker table.
	 *
	 * @param int $entry_id Timesheet entry ID.
	 * @return array<string, mixed> Timesheet data, or empty if not found.
	 */
	public function load_timesheet( int $entry_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'pm_time_tracker';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, task_id, user_id, start, stop, total FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$entry_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return [];
		}

		return [
			'entry_id' => (int) $row['id'],
			'task_id'  => (int) ( $row['task_id'] ?? 0 ),
			'user_id'  => (int) ( $row['user_id'] ?? 0 ),
			'start'    => (string) ( $row['start'] ?? '' ),
			'stop'     => (string) ( $row['stop'] ?? '' ),
			'total'    => (float) ( $row['total'] ?? 0 ),
		];
	}

	/**
	 * Format a timesheet entry for Odoo account.analytic.line.
	 *
	 * @param array<string, mixed> $data           Timesheet entry data.
	 * @param int                  $task_odoo_id   Odoo project.task ID.
	 * @param int                  $project_odoo_id Odoo project.project ID.
	 * @param int                  $employee_id    Odoo hr.employee ID.
	 * @return array<string, mixed> Odoo-ready analytic line data.
	 */
	public function format_timesheet( array $data, int $task_odoo_id, int $project_odoo_id, int $employee_id ): array {
		$hours = $data['total'] ?? 0;

		// Convert seconds to hours if value is large (>24 likely seconds).
		if ( $hours > 24 ) {
			$hours = $hours / 3600;
		}

		$date = ! empty( $data['start'] ) ? substr( $data['start'], 0, 10 ) : gmdate( 'Y-m-d' );

		return [
			'name'        => $data['task_name'] ?? __( 'Time entry', 'wp4odoo' ),
			'project_id'  => $project_odoo_id,
			'task_id'     => $task_odoo_id,
			'employee_id' => $employee_id,
			'unit_amount' => round( (float) $hours, 2 ),
			'date'        => $date,
		];
	}
}
