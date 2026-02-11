<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paid Memberships Pro Module — push memberships to Odoo accounting.
 *
 * Syncs PMPro membership levels as Odoo membership products (product.product),
 * payment orders as invoices (account.move), and user memberships as
 * membership lines (membership.membership_line). Push-only (WP → Odoo).
 *
 * Each payment automatically creates an invoice in Odoo,
 * eliminating manual recurring accounting entries.
 *
 * PMPro stores data in custom DB tables (not CPTs):
 * - pmpro_membership_levels — level definitions
 * - pmpro_membership_orders — payment orders
 * - pmpro_memberships_users — user membership records
 *
 * Requires the Paid Memberships Pro plugin to be active. Mutually exclusive
 * with the MemberPress and WC Memberships modules (same Odoo models).
 *
 * @package WP4Odoo
 * @since   2.6.5
 */
class PMPro_Module extends Module_Base {

	use PMPro_Hooks;

	protected string $exclusive_group = 'memberships';
	protected int $exclusive_priority = 15;

	/**
	 * Sync direction: push-only (WP → Odoo).
	 *
	 * @return string
	 */
	public function get_sync_direction(): string {
		return 'wp_to_odoo';
	}

	/**
	 * Odoo models by entity type.
	 *
	 * @var array<string, string>
	 */
	protected array $odoo_models = [
		'level'      => 'product.product',
		'order'      => 'account.move',
		'membership' => 'membership.membership_line',
	];

	/**
	 * Default field mappings.
	 *
	 * Order mappings are identity (WP key = Odoo key) because
	 * load_order() returns pre-formatted Odoo data. Module_Base::map_to_odoo()
	 * only renames keys without type conversion, so invoice_line_ids tuples pass intact.
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $default_mappings = [
		'level'      => [
			'level_name' => 'name',
			'list_price' => 'list_price',
			'membership' => 'membership',
			'type'       => 'type',
		],
		'order'      => [
			'move_type'        => 'move_type',
			'partner_id'       => 'partner_id',
			'invoice_date'     => 'invoice_date',
			'ref'              => 'ref',
			'invoice_line_ids' => 'invoice_line_ids',
		],
		'membership' => [
			'partner_id'    => 'partner_id',
			'membership_id' => 'membership_id',
			'date_from'     => 'date_from',
			'date_to'       => 'date_to',
			'state'         => 'state',
			'member_price'  => 'member_price',
		],
	];

	/**
	 * PMPro data handler.
	 *
	 * Initialized in __construct() (not boot()) because Sync_Engine can
	 * call push_to_odoo on non-booted modules for residual queue jobs.
	 *
	 * @var PMPro_Handler
	 */
	private PMPro_Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param \Closure                       $client_provider Client provider closure.
	 * @param \WP4Odoo\Entity_Map_Repository $entity_map      Entity map repository.
	 * @param \WP4Odoo\Settings_Repository   $settings        Settings repository.
	 */
	public function __construct( \Closure $client_provider, \WP4Odoo\Entity_Map_Repository $entity_map, \WP4Odoo\Settings_Repository $settings ) {
		parent::__construct( 'pmpro', 'Paid Memberships Pro', $client_provider, $entity_map, $settings );
		$this->handler = new PMPro_Handler( $this->logger );
	}

	/**
	 * Boot the module: register PMPro hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( ! defined( 'PMPRO_VERSION' ) ) {
			$this->logger->warning( __( 'PMPro module enabled but Paid Memberships Pro is not active.', 'wp4odoo' ) );
			return;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_levels'] ) ) {
			add_action( 'pmpro_save_membership_level', [ $this, 'on_level_saved' ], 10, 1 );
		}

		if ( ! empty( $settings['sync_orders'] ) ) {
			add_action( 'pmpro_added_order', [ $this, 'on_order_created' ], 10, 1 );
			add_action( 'pmpro_updated_order', [ $this, 'on_order_updated' ], 10, 1 );
		}

		if ( ! empty( $settings['sync_memberships'] ) ) {
			add_action( 'pmpro_after_change_membership_level', [ $this, 'on_membership_changed' ], 10, 3 );
		}
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return [
			'sync_levels'        => true,
			'sync_orders'        => true,
			'sync_memberships'   => true,
			'auto_post_invoices' => true,
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_settings_fields(): array {
		return [
			'sync_levels'        => [
				'label'       => __( 'Sync membership levels', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push PMPro levels to Odoo as membership products.', 'wp4odoo' ),
			],
			'sync_orders'        => [
				'label'       => __( 'Sync orders', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push completed payment orders to Odoo as invoices.', 'wp4odoo' ),
			],
			'sync_memberships'   => [
				'label'       => __( 'Sync memberships', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push user membership status to Odoo membership lines.', 'wp4odoo' ),
			],
			'auto_post_invoices' => [
				'label'       => __( 'Auto-post invoices', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically confirm invoices in Odoo for completed orders.', 'wp4odoo' ),
			],
		];
	}

	/**
	 * Get external dependency status for Paid Memberships Pro.
	 *
	 * @return array{available: bool, notices: array<array{type: string, message: string}>}
	 */
	public function get_dependency_status(): array {
		return $this->check_dependency( defined( 'PMPRO_VERSION' ), 'Paid Memberships Pro' );
	}

	// ─── Push override ──────────────────────────────────────

	/**
	 * Push a WordPress entity to Odoo.
	 *
	 * Ensures the level is synced before orders and memberships.
	 * Auto-posts invoices for completed orders.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       WordPress entity ID.
	 * @param int    $odoo_id     Odoo ID (0 if creating).
	 * @param array  $payload     Additional data.
	 * @return \WP4Odoo\Sync_Result
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): \WP4Odoo\Sync_Result {
		if ( in_array( $entity_type, [ 'order', 'membership' ], true ) && 'delete' !== $action ) {
			$this->ensure_level_synced( $wp_id, $entity_type );
		}

		$result = parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );

		if ( $result->succeeded() && 'order' === $entity_type && 'create' === $action ) {
			$this->maybe_auto_post_invoice( $wp_id );
		}

		return $result;
	}

	// ─── Data access ────────────────────────────────────────

	/**
	 * Load WordPress data for an entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		return match ( $entity_type ) {
			'level'      => $this->handler->load_level( $wp_id ),
			'order'      => $this->load_order_data( $wp_id ),
			'membership' => $this->load_membership_data( $wp_id ),
			default      => [],
		};
	}

	/**
	 * Load and resolve an order with Odoo references.
	 *
	 * Resolves user → partner and membership_id → level Odoo ID.
	 *
	 * @param int $order_id PMPro order ID.
	 * @return array<string, mixed>
	 */
	private function load_order_data( int $order_id ): array {
		$order = new \MemberOrder( $order_id );
		if ( ! $order->id ) {
			return [];
		}

		// Resolve user → partner.
		$user_id = (int) $order->user_id;
		if ( $user_id <= 0 ) {
			$this->logger->warning( 'PMPro order has no user.', [ 'order_id' => $order_id ] );
			return [];
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning(
				'Cannot find user for PMPro order.',
				[
					'order_id' => $order_id,
					'user_id'  => $user_id,
				]
			);
			return [];
		}

		$partner_id = $this->partner_service()->get_or_create(
			$user->user_email,
			[ 'name' => $user->display_name ],
			$user_id
		);

		if ( ! $partner_id ) {
			$this->logger->warning( 'Cannot resolve partner for PMPro order.', [ 'order_id' => $order_id ] );
			return [];
		}

		// Resolve membership_id → level Odoo ID.
		$level_id      = (int) $order->membership_id;
		$level_odoo_id = 0;
		if ( $level_id > 0 ) {
			$level_odoo_id = $this->get_mapping( 'level', $level_id ) ?? 0;
		}

		if ( ! $level_odoo_id ) {
			$this->logger->warning( 'Cannot resolve Odoo product for PMPro order level.', [ 'level_id' => $level_id ] );
			return [];
		}

		return $this->handler->load_order( $order_id, $partner_id, $level_odoo_id );
	}

	/**
	 * Load and resolve a membership with Odoo references.
	 *
	 * Same resolution pattern as MemberPress_Module::load_subscription_data().
	 *
	 * @param int $row_id pmpro_memberships_users row ID.
	 * @return array<string, mixed>
	 */
	private function load_membership_data( int $row_id ): array {
		$data = $this->handler->load_membership( $row_id );

		if ( empty( $data ) ) {
			return [];
		}

		// Resolve WP user → Odoo partner.
		$user_id = $data['user_id'] ?? 0;
		unset( $data['user_id'] );

		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$data['partner_id'] = $this->partner_service()->get_or_create(
					$user->user_email,
					[ 'name' => $user->display_name ],
					$user_id
				);
			}
		}

		if ( empty( $data['partner_id'] ) ) {
			$this->logger->warning( 'Cannot resolve partner for PMPro membership.', [ 'row_id' => $row_id ] );
			return [];
		}

		// Resolve level → Odoo product.product ID.
		$level_id = $data['level_id'] ?? 0;
		unset( $data['level_id'] );

		if ( $level_id > 0 ) {
			$data['membership_id'] = $this->get_mapping( 'level', $level_id );
		}

		if ( empty( $data['membership_id'] ) ) {
			$this->logger->warning( 'Cannot resolve Odoo product for PMPro membership level.', [ 'level_id' => $level_id ] );
			return [];
		}

		// Resolve member_price from level.
		$level = pmpro_getLevel( $level_id );
		if ( $level ) {
			$billing = (float) $level->billing_amount;
			$price   = $billing > 0 ? $billing : (float) $level->initial_payment;
			if ( $price > 0 ) {
				$data['member_price'] = $price;
			}
		}

		return $data;
	}

	// ─── Level sync ─────────────────────────────────────────

	/**
	 * Ensure the PMPro level is synced to Odoo before pushing a dependent entity.
	 *
	 * @param int    $wp_id       Entity ID (order or membership row).
	 * @param string $entity_type 'order' or 'membership'.
	 * @return void
	 */
	private function ensure_level_synced( int $wp_id, string $entity_type ): void {
		$level_id = 0;

		if ( 'order' === $entity_type ) {
			$level_id = $this->handler->get_level_id_for_order( $wp_id );
		} elseif ( 'membership' === $entity_type ) {
			$level_id = $this->handler->get_level_id_for_membership( $wp_id );
		}

		if ( $level_id <= 0 ) {
			return;
		}

		$odoo_level_id = $this->get_mapping( 'level', $level_id );
		if ( $odoo_level_id ) {
			return;
		}

		// Level not yet in Odoo — push it synchronously.
		$this->logger->info( 'Auto-pushing PMPro level before dependent entity.', [ 'level_id' => $level_id ] );
		parent::push_to_odoo( 'level', 'create', $level_id );
	}

	// ─── Invoice auto-posting ───────────────────────────────

	/**
	 * Auto-post an invoice in Odoo for completed orders.
	 *
	 * @param int $order_id PMPro order ID.
	 * @return void
	 */
	private function maybe_auto_post_invoice( int $order_id ): void {
		$settings = $this->get_settings();
		if ( empty( $settings['auto_post_invoices'] ) ) {
			return;
		}

		$order = new \MemberOrder( $order_id );
		if ( 'success' !== $order->status ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'order', $order_id );
		if ( ! $odoo_id ) {
			return;
		}

		try {
			$this->client()->execute(
				'account.move',
				'action_post',
				[ [ $odoo_id ] ]
			);
			$this->logger->info(
				'Auto-posted PMPro invoice in Odoo.',
				[
					'order_id' => $order_id,
					'odoo_id'  => $odoo_id,
				]
			);
		} catch ( \Exception $e ) {
			$this->logger->warning(
				'Could not auto-post PMPro invoice.',
				[
					'order_id' => $order_id,
					'odoo_id'  => $odoo_id,
					'error'    => $e->getMessage(),
				]
			);
		}
	}
}
