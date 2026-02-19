<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field Service Handler — data access for WordPress CPT ↔ Odoo field_service.task.
 *
 * Loads, saves, and parses field service task data between the wp4odoo_fs_task
 * CPT and Odoo's field_service.task model. Handles status mapping, meta fields,
 * and date formatting.
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class Field_Service_Handler {

	/**
	 * Meta key constants — single source of truth.
	 *
	 * @var string[]
	 */
	private const META_KEYS = [
		'planned_date'  => '_fs_planned_date',
		'date_deadline' => '_fs_date_deadline',
		'stage'         => '_fs_stage',
		'priority'      => '_fs_priority',
		'partner_user'  => '_fs_partner_user_id',
	];

	/**
	 * Push status map: WP post status → Odoo stage name.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_MAP = [
		'draft'   => 'New',
		'publish' => 'In Progress',
		'private' => 'Done',
	];

	/**
	 * Pull status map: Odoo stage name → WP post status.
	 *
	 * @var array<string, string>
	 */
	private const REVERSE_STATUS_MAP = [
		'New'         => 'draft',
		'In Progress' => 'publish',
		'Done'        => 'private',
		'Cancelled'   => 'trash',
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

	// ─── Load task ────────────────────────────────────────

	/**
	 * Load a WordPress field service task for push to Odoo.
	 *
	 * Reads the CPT post and its meta fields, mapping them to Odoo
	 * field_service.task field names.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Task data for field mapping, or empty if not found.
	 */
	public function load_task( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || Field_Service_Module::CPT !== $post->post_type ) {
			$this->logger->warning( 'Field service task not found or wrong post type.', [ 'post_id' => $post_id ] );
			return [];
		}

		$stage = Status_Mapper::resolve(
			$post->post_status,
			self::STATUS_MAP,
			'wp4odoo_field_service_status_map',
			'New'
		);

		$data = [
			'name'        => $post->post_title,
			'description' => $post->post_content,
		];

		$planned = get_post_meta( $post_id, self::META_KEYS['planned_date'], true );
		if ( '' !== $planned ) {
			$data['planned_date_begin'] = (string) $planned;
		}

		$deadline = get_post_meta( $post_id, self::META_KEYS['date_deadline'], true );
		if ( '' !== $deadline ) {
			$data['date_deadline'] = (string) $deadline;
		}

		$priority         = get_post_meta( $post_id, self::META_KEYS['priority'], true );
		$data['priority'] = '' !== $priority ? (string) $priority : '0';

		// Stage name will be resolved to stage_id via search by Odoo.
		$data['stage_name'] = $stage;

		return $data;
	}

	// ─── Parse from Odoo ─────────────────────────────────

	/**
	 * Parse Odoo field_service.task data to WordPress post format.
	 *
	 * Handles Many2one [id, "Name"] → integer extraction for partner_id,
	 * project_id, and stage_id.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WordPress-compatible post data.
	 */
	public function parse_task_from_odoo( array $odoo_data ): array {
		$data = [];

		if ( isset( $odoo_data['name'] ) ) {
			$data['post_title'] = (string) $odoo_data['name'];
		}

		if ( isset( $odoo_data['description'] ) ) {
			$data['post_content'] = (string) $odoo_data['description'];
		}

		if ( isset( $odoo_data['planned_date_begin'] ) ) {
			$data['planned_date'] = (string) $odoo_data['planned_date_begin'];
		}

		if ( isset( $odoo_data['date_deadline'] ) ) {
			$data['date_deadline'] = (string) $odoo_data['date_deadline'];
		}

		if ( isset( $odoo_data['priority'] ) ) {
			$data['priority'] = (string) $odoo_data['priority'];
		}

		// Handle stage_id Many2one: [id, "Name"] or false.
		if ( isset( $odoo_data['stage_id'] ) && is_array( $odoo_data['stage_id'] ) && ! empty( $odoo_data['stage_id'] ) ) {
			$stage_name          = (string) ( $odoo_data['stage_id'][1] ?? '' );
			$data['post_status'] = Status_Mapper::resolve(
				$stage_name,
				self::REVERSE_STATUS_MAP,
				'wp4odoo_field_service_reverse_status_map',
				'draft'
			);
		}

		// Handle partner_id Many2one: [id, "Name"] or false.
		if ( isset( $odoo_data['partner_id'] ) && is_array( $odoo_data['partner_id'] ) && ! empty( $odoo_data['partner_id'] ) ) {
			$data['partner_odoo_id'] = (int) $odoo_data['partner_id'][0];
		}

		return $data;
	}

	// ─── Save task ───────────────────────────────────────

	/**
	 * Save task data as a WordPress CPT post.
	 *
	 * Creates a new task or updates an existing one. Meta fields are stored
	 * as post meta.
	 *
	 * @param array<string, mixed> $data  Mapped post data.
	 * @param int                  $wp_id Existing post ID (0 to create new).
	 * @return int Post ID or 0 on failure.
	 */
	public function save_task( array $data, int $wp_id = 0 ): int {
		$post_data = [
			'post_type' => Field_Service_Module::CPT,
		];

		if ( isset( $data['post_title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['post_title'] );
		}

		if ( isset( $data['post_content'] ) ) {
			$post_data['post_content'] = $data['post_content'];
		}

		if ( isset( $data['post_status'] ) ) {
			$post_data['post_status'] = sanitize_key( $data['post_status'] );
		}

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			if ( ! isset( $post_data['post_title'] ) ) {
				$post_data['post_title'] = __( 'Field Service Task', 'wp4odoo' );
			}
			if ( ! isset( $post_data['post_status'] ) ) {
				$post_data['post_status'] = 'draft';
			}
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to save field service task.',
				[ 'error' => $result->get_error_message() ]
			);
			return 0;
		}

		$new_id = (int) $result;

		// Save meta fields.
		if ( isset( $data['planned_date'] ) ) {
			update_post_meta( $new_id, self::META_KEYS['planned_date'], sanitize_text_field( $data['planned_date'] ) );
		}

		if ( isset( $data['date_deadline'] ) ) {
			update_post_meta( $new_id, self::META_KEYS['date_deadline'], sanitize_text_field( $data['date_deadline'] ) );
		}

		if ( isset( $data['priority'] ) ) {
			update_post_meta( $new_id, self::META_KEYS['priority'], sanitize_text_field( $data['priority'] ) );
		}

		return $new_id;
	}
}
