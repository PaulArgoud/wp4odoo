<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Odoo_Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge Module — bidirectional WordPress posts ↔ Odoo Knowledge articles.
 *
 * Syncs native WordPress posts (configurable post type) to Odoo Knowledge
 * articles (knowledge.article model, Enterprise v16+). HTML body is preserved
 * as-is. Supports parent hierarchy (post_parent ↔ parent_id), optional
 * category filtering, and WPML/Polylang translation push+pull.
 *
 * No WP plugin dependency — always registered (detection null). Odoo-side
 * availability is guarded via has_odoo_model(KnowledgeArticle) probe.
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
class Knowledge_Module extends Module_Base {

	use Knowledge_Hooks;

	/**
	 * Sync direction: bidirectional.
	 *
	 * @return string
	 */
	public function get_sync_direction(): string {
		return 'bidirectional';
	}

	/**
	 * Odoo models by entity type.
	 *
	 * @var array<string, string>
	 */
	protected array $odoo_models = [
		'article' => 'knowledge.article',
	];

	/**
	 * Default field mappings.
	 *
	 * Article mappings are identity (pre-formatted by handler).
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $default_mappings = [
		'article' => [
			'name'                => 'name',
			'body'                => 'body',
			'internal_permission' => 'internal_permission',
			'sequence'            => 'sequence',
			'parent_id'           => 'parent_id',
		],
	];

	/**
	 * Knowledge data handler.
	 *
	 * Initialized in __construct() (not boot()) because Sync_Engine can
	 * call push_to_odoo on non-booted modules for residual queue jobs.
	 *
	 * @var Knowledge_Handler
	 */
	private Knowledge_Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param \Closure                       $client_provider Returns the shared Odoo_Client instance.
	 * @param \WP4Odoo\Entity_Map_Repository $entity_map      Shared entity map repository.
	 * @param \WP4Odoo\Settings_Repository   $settings        Settings repository.
	 */
	public function __construct( \Closure $client_provider, \WP4Odoo\Entity_Map_Repository $entity_map, \WP4Odoo\Settings_Repository $settings ) {
		parent::__construct( 'knowledge', 'Knowledge', $client_provider, $entity_map, $settings );
		$this->handler = new Knowledge_Handler( $this->logger );
	}

	/**
	 * Boot the module: register WordPress hooks for the configured post type.
	 *
	 * @return void
	 */
	public function boot(): void {
		$settings  = $this->get_settings();
		$post_type = $settings['post_type'] ?? 'post';

		if ( ! empty( $settings['sync_articles'] ) ) {
			add_action( "save_post_{$post_type}", $this->safe_callback( [ $this, 'on_article_save' ] ), 10, 1 );
			add_action( 'before_delete_post', $this->safe_callback( [ $this, 'on_article_delete' ] ), 10, 1 );
		}
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_settings(): array {
		return [
			'sync_articles'   => true,
			'pull_articles'   => true,
			'post_type'       => 'post',
			'category_filter' => '',
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_settings_fields(): array {
		return [
			'sync_articles'   => [
				'label'       => __( 'Sync articles', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push WordPress posts to Odoo Knowledge articles.', 'wp4odoo' ),
			],
			'pull_articles'   => [
				'label'       => __( 'Pull articles from Odoo', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Pull Odoo Knowledge articles back to WordPress.', 'wp4odoo' ),
			],
			'post_type'       => [
				'label'       => __( 'Post type', 'wp4odoo' ),
				'type'        => 'text',
				'description' => __( 'WordPress post type to sync (e.g. "post", "page", or a custom post type).', 'wp4odoo' ),
			],
			'category_filter' => [
				'label'       => __( 'Category filter', 'wp4odoo' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated category slugs to limit sync. Leave empty to sync all.', 'wp4odoo' ),
			],
		];
	}

	/**
	 * Get external dependency status.
	 *
	 * Always available — no WP plugin dependency. Odoo-side availability
	 * is checked at push time via has_knowledge_model().
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
	 * Check whether Odoo has the knowledge.article model (Enterprise v16+).
	 *
	 * @return bool
	 */
	private function has_knowledge_model(): bool {
		return $this->has_odoo_model( Odoo_Model::KnowledgeArticle, 'wp4odoo_has_knowledge_article' );
	}

	// ─── Push override ────────────────────────────────────

	/**
	 * Push a WordPress post to Odoo as a Knowledge article.
	 *
	 * Guards with has_knowledge_model() — only pushes if Odoo has the
	 * knowledge.article model (Enterprise v16+). Ensures parent article
	 * is synced before pushing child articles.
	 *
	 * @param string $entity_type Entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       WordPress post ID.
	 * @param int    $odoo_id     Odoo record ID (0 if creating).
	 * @param array  $payload     Additional data from the queue.
	 * @return \WP4Odoo\Sync_Result
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): \WP4Odoo\Sync_Result {
		if ( 'delete' !== $action && ! $this->has_knowledge_model() ) {
			$this->logger->info( 'knowledge.article not available — skipping push.', [ 'post_id' => $wp_id ] );
			return \WP4Odoo\Sync_Result::success();
		}

		// Ensure parent article is synced first.
		if ( 'delete' !== $action && 'article' === $entity_type ) {
			$parent_id = $this->handler->get_parent_id( $wp_id );
			if ( $parent_id > 0 ) {
				$this->ensure_entity_synced( 'article', $parent_id );
			}
		}

		return parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );
	}

	// ─── Pull override ────────────────────────────────────

	/**
	 * Pull an Odoo Knowledge article to WordPress.
	 *
	 * Checks pull_articles setting before proceeding.
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
		if ( empty( $settings['pull_articles'] ) ) {
			return \WP4Odoo\Sync_Result::success();
		}

		return parent::pull_from_odoo( $entity_type, $action, $odoo_id, $wp_id, $payload );
	}

	// ─── Translation ──────────────────────────────────────

	/**
	 * Translatable fields for articles (name + body).
	 *
	 * @param string $entity_type Entity type.
	 * @return array<string, string> Odoo field => WP field.
	 */
	protected function get_translatable_fields( string $entity_type ): array {
		if ( 'article' === $entity_type ) {
			return [
				'name' => 'post_title',
				'body' => 'post_content',
			];
		}

		return [];
	}

	// ─── Deduplication ─────────────────────────────────────

	/**
	 * Deduplication domain for search-before-create.
	 *
	 * Articles dedup by name.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_values Odoo-ready field values.
	 * @return array Odoo domain filter, or empty to skip dedup.
	 */
	protected function get_dedup_domain( string $entity_type, array $odoo_values ): array {
		if ( 'article' === $entity_type && ! empty( $odoo_values['name'] ) ) {
			return [ [ 'name', '=', $odoo_values['name'] ] ];
		}

		return [];
	}

	// ─── Data access (delegates to handler) ────────────────

	/**
	 * Load WordPress data for an entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress post ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		if ( 'article' !== $entity_type ) {
			return [];
		}

		$settings  = $this->get_settings();
		$post_type = $settings['post_type'] ?? 'post';
		$data      = $this->handler->load_article( $wp_id, $post_type );

		if ( empty( $data ) ) {
			return [];
		}

		// Resolve post_parent → Odoo parent_id via entity map.
		$parent_wp_id = $this->handler->get_parent_id( $wp_id );
		if ( $parent_wp_id > 0 ) {
			$parent_odoo_id = $this->get_mapping( 'article', $parent_wp_id );
			if ( $parent_odoo_id ) {
				$data['parent_id'] = $parent_odoo_id;
			}
		}

		return $data;
	}

	/**
	 * Map Odoo knowledge.article data to WordPress format for pull.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_data   Raw Odoo record data.
	 * @return array<string, mixed>
	 */
	public function map_from_odoo( string $entity_type, array $odoo_data ): array {
		if ( 'article' === $entity_type ) {
			return $this->handler->parse_article_from_odoo( $odoo_data );
		}

		return parent::map_from_odoo( $entity_type, $odoo_data );
	}

	/**
	 * Save pulled article data to WordPress.
	 *
	 * Resolves parent_odoo_id → WP post ID via entity map before saving.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $data        Mapped data.
	 * @param int    $wp_id       Existing WP ID (0 if new).
	 * @return int The WordPress post ID (0 on failure).
	 */
	protected function save_wp_data( string $entity_type, array $data, int $wp_id = 0 ): int {
		if ( 'article' !== $entity_type ) {
			return 0;
		}

		// Resolve parent_odoo_id → WP post ID.
		if ( isset( $data['parent_odoo_id'] ) ) {
			$parent_wp_id = $this->get_wp_mapping( 'article', (int) $data['parent_odoo_id'] );
			if ( $parent_wp_id ) {
				$data['post_parent'] = $parent_wp_id;
			}
			unset( $data['parent_odoo_id'] );
		}

		$settings  = $this->get_settings();
		$post_type = $settings['post_type'] ?? 'post';

		return $this->handler->save_article( $data, $wp_id, $post_type );
	}

	/**
	 * Delete a WordPress entity during pull.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress post ID.
	 * @return bool
	 */
	protected function delete_wp_data( string $entity_type, int $wp_id ): bool {
		if ( 'article' === $entity_type ) {
			return $this->delete_wp_post( $wp_id );
		}

		return false;
	}
}
