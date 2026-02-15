<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge Handler — data access for WordPress posts ↔ Odoo Knowledge articles.
 *
 * Loads, saves, and parses WordPress post data for Odoo knowledge.article sync.
 * HTML body is preserved as-is (knowledge.article.body is HTML).
 *
 * Category mapping (post_status → Odoo internal_permission) via Status_Mapper:
 * - Push: publish → workspace, private → private, draft → private
 * - Pull: workspace → publish, shared → publish, private → private
 *
 * Called by Knowledge_Module via its load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
class Knowledge_Handler {

	/**
	 * Push category map: post_status → Odoo internal_permission.
	 *
	 * @var array<string, string>
	 */
	private const CATEGORY_MAP = [
		'publish' => 'workspace',
		'private' => 'private',
		'draft'   => 'private',
	];

	/**
	 * Pull category map: Odoo internal_permission → post_status.
	 *
	 * @var array<string, string>
	 */
	private const REVERSE_CATEGORY_MAP = [
		'workspace' => 'publish',
		'shared'    => 'publish',
		'private'   => 'private',
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

	// ─── Load article ────────────────────────────────────────

	/**
	 * Load a WordPress post as a Knowledge article.
	 *
	 * Reads the post and maps its fields to Odoo knowledge.article format.
	 * HTML body (post_content) is preserved as-is.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Expected post type (from module settings).
	 * @return array<string, mixed> Article data for field mapping, or empty if not found.
	 */
	public function load_article( int $post_id, string $post_type = 'post' ): array {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== $post_type ) {
			$this->logger->warning( 'Knowledge article not found or wrong post type.', [ 'post_id' => $post_id ] );
			return [];
		}

		$category = Status_Mapper::resolve(
			$post->post_status,
			self::CATEGORY_MAP,
			'wp4odoo_knowledge_category_map',
			'workspace'
		);

		return [
			'name'                => $post->post_title,
			'body'                => $post->post_content,
			'internal_permission' => $category,
			'sequence'            => $post->menu_order,
		];
	}

	// ─── Parse from Odoo ─────────────────────────────────────

	/**
	 * Parse Odoo knowledge.article data to WordPress post format.
	 *
	 * Handles parent_id Many2one [id, "Name"] → integer extraction.
	 * Maps internal_permission → post_status via reverse category map.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WordPress-compatible post data.
	 */
	public function parse_article_from_odoo( array $odoo_data ): array {
		$data = [];

		if ( isset( $odoo_data['name'] ) ) {
			$data['post_title'] = (string) $odoo_data['name'];
		}

		if ( isset( $odoo_data['body'] ) ) {
			$data['post_content'] = (string) $odoo_data['body'];
		}

		if ( isset( $odoo_data['internal_permission'] ) ) {
			$data['post_status'] = Status_Mapper::resolve(
				(string) $odoo_data['internal_permission'],
				self::REVERSE_CATEGORY_MAP,
				'wp4odoo_knowledge_reverse_category_map',
				'publish'
			);
		}

		if ( isset( $odoo_data['sequence'] ) ) {
			$data['menu_order'] = (int) $odoo_data['sequence'];
		}

		// Handle parent_id Many2one: [id, "Name"] or false.
		if ( isset( $odoo_data['parent_id'] ) ) {
			if ( is_array( $odoo_data['parent_id'] ) && ! empty( $odoo_data['parent_id'] ) ) {
				$data['parent_odoo_id'] = (int) $odoo_data['parent_id'][0];
			}
		}

		return $data;
	}

	// ─── Save article ────────────────────────────────────────

	/**
	 * Save article data as a WordPress post.
	 *
	 * Creates a new post or updates an existing one. HTML body is preserved.
	 *
	 * @param array<string, mixed> $data      Mapped post data.
	 * @param int                  $wp_id     Existing post ID (0 to create new).
	 * @param string               $post_type Post type to use (from module settings).
	 * @return int Post ID or 0 on failure.
	 */
	public function save_article( array $data, int $wp_id = 0, string $post_type = 'post' ): int {
		$post_data = [
			'post_type' => $post_type,
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

		if ( isset( $data['menu_order'] ) ) {
			$post_data['menu_order'] = (int) $data['menu_order'];
		}

		if ( isset( $data['post_parent'] ) ) {
			$post_data['post_parent'] = (int) $data['post_parent'];
		}

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			if ( ! isset( $post_data['post_title'] ) ) {
				$post_data['post_title'] = __( 'Knowledge Article', 'wp4odoo' );
			}
			if ( ! isset( $post_data['post_status'] ) ) {
				$post_data['post_status'] = 'publish';
			}
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to save Knowledge article.',
				[ 'error' => $result->get_error_message() ]
			);
			return 0;
		}

		return (int) $result;
	}

	// ─── Parent resolution ───────────────────────────────────

	/**
	 * Get the parent post ID for a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Parent post ID (0 if no parent).
	 */
	public function get_parent_id( int $post_id ): int {
		return (int) wp_get_post_parent_id( $post_id );
	}
}
