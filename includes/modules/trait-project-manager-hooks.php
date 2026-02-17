<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Project Manager hook registrations.
 *
 * Registers WordPress hooks for WP Project Manager (weDevs) project,
 * task, and timesheet events. Projects are CPTs, tasks and timesheets
 * are managed via the plugin's REST API / internal actions.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
trait Project_Manager_Hooks {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		if ( ! defined( 'CPM_VERSION' ) && ! class_exists( 'WeDevs\PM\Core\WP\WP_Project_Manager' ) ) {
			return;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_projects'] ) ) {
			add_action(
				'save_post_cpm_project',
				$this->safe_callback( [ $this, 'on_project_save' ] ),
				10,
				1
			);
			add_action(
				'before_delete_post',
				$this->safe_callback( [ $this, 'on_project_delete' ] ),
				10,
				1
			);
		}

		if ( ! empty( $settings['sync_tasks'] ) ) {
			// WP Project Manager fires these actions for task operations.
			add_action(
				'cpm_task_new',
				$this->safe_callback( [ $this, 'on_task_created' ] ),
				10,
				3
			);
			add_action(
				'cpm_task_update',
				$this->safe_callback( [ $this, 'on_task_updated' ] ),
				10,
				3
			);
			add_action(
				'cpm_task_complete',
				$this->safe_callback( [ $this, 'on_task_completed' ] ),
				10,
				2
			);
			add_action(
				'cpm_task_delete',
				$this->safe_callback( [ $this, 'on_task_deleted' ] ),
				10,
				2
			);
		}

		if ( ! empty( $settings['sync_timesheets'] ) ) {
			add_action(
				'cpm_time_entry_new',
				$this->safe_callback( [ $this, 'on_timesheet_entry' ] ),
				10,
				3
			);
			add_action(
				'cpm_time_entry_update',
				$this->safe_callback( [ $this, 'on_timesheet_updated' ] ),
				10,
				3
			);
		}
	}

	// ─── Project callbacks ─────────────────────────────────

	/**
	 * Handle project save.
	 *
	 * @param int $post_id Project post ID.
	 * @return void
	 */
	public function on_project_save( int $post_id ): void {
		$this->handle_cpt_save( $post_id, 'cpm_project', 'sync_projects', 'project' );
	}

	/**
	 * Handle project delete.
	 *
	 * @param int $post_id Project post ID.
	 * @return void
	 */
	public function on_project_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'cpm_project' !== $post->post_type ) {
			return;
		}

		if ( ! $this->should_sync( 'sync_projects' ) ) {
			return;
		}

		$odoo_id = $this->entity_map()->get_odoo_id( $this->get_id(), 'project', $post_id );
		if ( $odoo_id ) {
			$this->enqueue_push( 'project', $post_id );
		}
	}

	// ─── Task callbacks ────────────────────────────────────

	/**
	 * Handle task creation.
	 *
	 * @param int              $task_id    Task ID.
	 * @param int              $project_id Project ID.
	 * @param array<string, mixed> $data       Task data.
	 * @return void
	 */
	public function on_task_created( int $task_id, int $project_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'task', 'sync_tasks', $task_id );
	}

	/**
	 * Handle task update.
	 *
	 * @param int              $task_id    Task ID.
	 * @param int              $project_id Project ID.
	 * @param array<string, mixed> $data       Task data.
	 * @return void
	 */
	public function on_task_updated( int $task_id, int $project_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'task', 'sync_tasks', $task_id );
	}

	/**
	 * Handle task completion.
	 *
	 * @param int $task_id    Task ID.
	 * @param int $project_id Project ID.
	 * @return void
	 */
	public function on_task_completed( int $task_id, int $project_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'task', 'sync_tasks', $task_id );
	}

	/**
	 * Handle task deletion.
	 *
	 * @param int $task_id    Task ID.
	 * @param int $project_id Project ID.
	 * @return void
	 */
	public function on_task_deleted( int $task_id, int $project_id ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( ! $this->should_sync( 'sync_tasks' ) ) {
			return;
		}

		$odoo_id = $this->entity_map()->get_odoo_id( $this->get_id(), 'task', $task_id );
		if ( $odoo_id ) {
			$this->enqueue_push( 'task', $task_id );
		}
	}

	// ─── Timesheet callbacks ───────────────────────────────

	/**
	 * Handle new timesheet entry.
	 *
	 * @param int              $entry_id   Timesheet entry ID.
	 * @param int              $task_id    Task ID.
	 * @param array<string, mixed> $data       Entry data.
	 * @return void
	 */
	public function on_timesheet_entry( int $entry_id, int $task_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'timesheet', 'sync_timesheets', $entry_id );
	}

	/**
	 * Handle timesheet entry update.
	 *
	 * @param int              $entry_id   Timesheet entry ID.
	 * @param int              $task_id    Task ID.
	 * @param array<string, mixed> $data       Entry data.
	 * @return void
	 */
	public function on_timesheet_updated( int $entry_id, int $task_id, array $data = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$this->push_entity( 'timesheet', 'sync_timesheets', $entry_id );
	}
}
