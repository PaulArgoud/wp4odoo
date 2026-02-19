<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Odoo_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field Service Module — bidirectional WordPress CPT ↔ Odoo field_service.task.
 *
 * Syncs field service tasks between a dedicated WordPress CPT (wp4odoo_fs_task)
 * and Odoo's Field Service module (field_service.task). Supports bidirectional
 * push/pull, partner resolution, project assignment, and stage mapping.
 *
 * Always registered (no WP plugin dependency). Odoo-side availability is
 * guarded via has_odoo_model(FieldServiceTask) probe at push time.
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class Field_Service_Module extends Module_Base {

	use Field_Service_Hooks;

	/**
	 * Custom post type identifier.
	 *
	 * @var string
	 */
	public const CPT = 'wp4odoo_fs_task';

	/**
	 * Odoo models by entity type.
	 *
	 * @var array<string, string>
	 */
	protected array $odoo_models = [
		'task' => 'field_service.task',
	];

	/**
	 * Default field mappings.
	 *
	 * Task mappings are identity (pre-formatted by handler).
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $default_mappings = [
		'task' => [
			'name'               => 'name',
			'partner_id'         => 'partner_id',
			'project_id'         => 'project_id',
			'planned_date_begin' => 'planned_date_begin',
			'date_deadline'      => 'date_deadline',
			'stage_id'           => 'stage_id',
			'priority'           => 'priority',
			'description'        => 'description',
		],
	];

	/**
	 * Field service data handler.
	 *
	 * Initialized in __construct() (not boot()) because Sync_Engine can
	 * call push_to_odoo on non-booted modules for residual queue jobs.
	 *
	 * @var Field_Service_Handler
	 */
	private Field_Service_Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param \Closure                       $client_provider Returns the shared Odoo_Client instance.
	 * @param \WP4Odoo\Entity_Map_Repository $entity_map      Shared entity map repository.
	 * @param \WP4Odoo\Settings_Repository   $settings        Settings repository.
	 */
	public function __construct( \Closure $client_provider, \WP4Odoo\Entity_Map_Repository $entity_map, \WP4Odoo\Settings_Repository $settings ) {
		parent::__construct( 'field_service', 'Field Service', $client_provider, $entity_map, $settings );
		$this->handler = new Field_Service_Handler( $this->logger );
	}

	/**
	 * Sync direction: bidirectional.
	 *
	 * @return string
	 */
	public function get_sync_direction(): string {
		return 'bidirectional';
	}

	/**
	 * Boot the module: register CPT and WordPress hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );

		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_tasks'] ) ) {
			add_action( 'save_post_' . self::CPT, $this->safe_callback( [ $this, 'on_task_save' ] ), 10, 1 );
			add_action( 'before_delete_post', $this->safe_callback( [ $this, 'on_task_delete' ] ), 10, 1 );
		}
	}

	/**
	 * Register the wp4odoo_fs_task custom post type.
	 *
	 * @return void
	 */
	public function register_cpt(): void {
		register_post_type(
			self::CPT,
			[
				'labels'          => [
					'name'               => __( 'Field Service Tasks', 'wp4odoo' ),
					'singular_name'      => __( 'Field Service Task', 'wp4odoo' ),
					'add_new_item'       => __( 'Add New Task', 'wp4odoo' ),
					'edit_item'          => __( 'Edit Task', 'wp4odoo' ),
					'view_item'          => __( 'View Task', 'wp4odoo' ),
					'search_items'       => __( 'Search Tasks', 'wp4odoo' ),
					'not_found'          => __( 'No tasks found.', 'wp4odoo' ),
					'not_found_in_trash' => __( 'No tasks found in Trash.', 'wp4odoo' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'wp4odoo',
				'supports'        => [ 'title', 'editor' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			]
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_settings(): array {
		return [
			'sync_tasks' => true,
			'pull_tasks' => true,
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_settings_fields(): array {
		return [
			'sync_tasks' => [
				'label'       => __( 'Sync tasks', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push WordPress field service tasks to Odoo.', 'wp4odoo' ),
			],
			'pull_tasks' => [
				'label'       => __( 'Pull tasks from Odoo', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Pull Odoo field service tasks back to WordPress.', 'wp4odoo' ),
			],
		];
	}

	/**
	 * Get external dependency status.
	 *
	 * Always available — no WP plugin dependency. Odoo-side availability
	 * is checked at push time via has_field_service_model().
	 *
	 * @return array{available: bool, notices: array<array{type: string, message: string}>}
	 */
	public function get_dependency_status(): array {
		return [
			'available' => true,
			'notices'   => [],
		];
	}

	// ─── Model detection ──────────────────────────────────

	/**
	 * Check whether Odoo has the field_service.task model (Enterprise).
	 *
	 * @return bool
	 */
	private function has_field_service_model(): bool {
		return $this->has_odoo_model( Odoo_Model::FieldServiceTask, 'wp4odoo_has_field_service_task' );
	}

	// ─── Push override ────────────────────────────────────

	/**
	 * Push a WordPress field service task to Odoo.
	 *
	 * Guards with has_field_service_model() — only pushes if Odoo has the
	 * field_service.task model (Enterprise).
	 *
	 * @param string $entity_type Entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       WordPress post ID.
	 * @param int    $odoo_id     Odoo record ID (0 if creating).
	 * @param array  $payload     Additional data from the queue.
	 * @return \WP4Odoo\Sync_Result
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): \WP4Odoo\Sync_Result {
		if ( 'delete' !== $action && ! $this->has_field_service_model() ) {
			$this->logger->info( 'field_service.task not available — skipping push.', [ 'post_id' => $wp_id ] );
			return \WP4Odoo\Sync_Result::success();
		}

		return parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );
	}

	// ─── Pull override ────────────────────────────────────

	/**
	 * Pull an Odoo field service task to WordPress.
	 *
	 * Checks pull_tasks setting before proceeding.
	 *
	 * @param string $entity_type Entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $odoo_id     Odoo record ID.
	 * @param int    $wp_id       WordPress post ID (0 if unknown).
	 * @param array  $payload     Additional data.
	 * @return \WP4Odoo\Sync_Result
	 */
	public function pull_from_odoo( string $entity_type, string $action, int $odoo_id, int $wp_id = 0, array $payload = [] ): \WP4Odoo\Sync_Result {
		$settings = $this->get_settings();
		if ( empty( $settings['pull_tasks'] ) ) {
			return \WP4Odoo\Sync_Result::success();
		}

		return parent::pull_from_odoo( $entity_type, $action, $odoo_id, $wp_id, $payload );
	}

	// ─── Data access (delegates to handler) ────────────────

	/**
	 * Load WordPress data for a field service task.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress post ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		if ( 'task' !== $entity_type ) {
			return [];
		}

		$data = $this->handler->load_task( $wp_id );

		if ( empty( $data ) ) {
			return [];
		}

		// Resolve partner from post meta.
		$partner_user_id = (int) get_post_meta( $wp_id, '_fs_partner_user_id', true );
		if ( $partner_user_id > 0 ) {
			$partner_id = $this->resolve_partner_from_user( $partner_user_id );
			if ( $partner_id ) {
				$data['partner_id'] = $partner_id;
			}
		}

		return $data;
	}

	/**
	 * Map WP data to Odoo values (identity — pre-formatted).
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $wp_data     WordPress data.
	 * @return array<string, mixed>
	 */
	public function map_to_odoo( string $entity_type, array $wp_data ): array {
		return $wp_data;
	}

	/**
	 * Map Odoo field_service.task data to WordPress format for pull.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_data   Raw Odoo record data.
	 * @return array<string, mixed>
	 */
	public function map_from_odoo( string $entity_type, array $odoo_data ): array {
		if ( 'task' === $entity_type ) {
			return $this->handler->parse_task_from_odoo( $odoo_data );
		}

		return parent::map_from_odoo( $entity_type, $odoo_data );
	}

	/**
	 * Save pulled task data to WordPress.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $data        Mapped data.
	 * @param int    $wp_id       Existing WP ID (0 if new).
	 * @return int The WordPress post ID (0 on failure).
	 */
	protected function save_wp_data( string $entity_type, array $data, int $wp_id = 0 ): int {
		if ( 'task' !== $entity_type ) {
			return 0;
		}

		return $this->handler->save_task( $data, $wp_id );
	}

	/**
	 * Delete a WordPress entity during pull.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress post ID.
	 * @return bool
	 */
	protected function delete_wp_data( string $entity_type, int $wp_id ): bool {
		if ( 'task' === $entity_type ) {
			return $this->delete_wp_post( $wp_id );
		}

		return false;
	}

	// ─── Deduplication ─────────────────────────────────────

	/**
	 * Deduplication domain for search-before-create.
	 *
	 * Tasks dedup by name + partner_id.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_values Odoo-ready field values.
	 * @return array Odoo domain filter, or empty to skip dedup.
	 */
	protected function get_dedup_domain( string $entity_type, array $odoo_values ): array {
		if ( 'task' === $entity_type && ! empty( $odoo_values['name'] ) ) {
			return [ [ 'name', '=', $odoo_values['name'] ] ];
		}

		return [];
	}
}
