<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Partner_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bookly Module — push booking services and appointments to Odoo.
 *
 * Syncs Bookly services as Odoo service products (product.product)
 * and customer_appointments as Odoo calendar events (calendar.event),
 * with automatic partner resolution via Partner_Service.
 *
 * Unlike Amelia which has WordPress hooks, Bookly has NO hooks for
 * booking lifecycle events. This module uses WP-Cron polling to detect
 * changes every 5 minutes via hash comparison.
 *
 * Push-only (WP → Odoo). No mutual exclusivity with other modules.
 *
 * Requires the Bookly plugin to be active.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Bookly_Module extends Module_Base {

	use Bookly_Poller;

	/**
	 * Module identifier.
	 *
	 * @var string
	 */
	protected string $id = 'bookly';

	/**
	 * Human-readable module name.
	 *
	 * @var string
	 */
	protected string $name = 'Bookly';

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
		'service' => 'product.product',
		'booking' => 'calendar.event',
	];

	/**
	 * Default field mappings.
	 *
	 * Booking mappings are minimal because map_to_odoo() is overridden
	 * to pass handler-formatted data directly to Odoo (partner_ids M2M commands).
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $default_mappings = [
		'service' => [
			'title' => 'name',
			'info'  => 'description_sale',
			'price' => 'list_price',
		],
		'booking' => [
			'name'          => 'name',
			'start_date'    => 'start',
			'end_date'      => 'stop',
			'partner_ids'   => 'partner_ids',
			'internal_note' => 'description',
		],
	];

	/**
	 * Bookly data handler.
	 *
	 * Initialized in __construct() (not boot()) because Sync_Engine can
	 * call push_to_odoo on non-booted modules for residual queue jobs.
	 *
	 * @var Bookly_Handler
	 */
	private Bookly_Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param \Closure                      $client_provider Returns the shared Odoo_Client instance.
	 * @param \WP4Odoo\Entity_Map_Repository $entity_map      Shared entity map repository.
	 * @param \WP4Odoo\Settings_Repository   $settings        Settings repository.
	 */
	public function __construct( \Closure $client_provider, \WP4Odoo\Entity_Map_Repository $entity_map, \WP4Odoo\Settings_Repository $settings ) {
		parent::__construct( $client_provider, $entity_map, $settings );
		$this->handler = new Bookly_Handler( $this->logger );
	}

	/**
	 * Boot the module: register WP-Cron polling.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->register_cron();
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return [
			'sync_services' => true,
			'sync_bookings' => true,
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_settings_fields(): array {
		return [
			'sync_services' => [
				'label'       => __( 'Sync services', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push Bookly services to Odoo as service products.', 'wp4odoo' ),
			],
			'sync_bookings' => [
				'label'       => __( 'Sync bookings', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push Bookly bookings to Odoo as calendar events.', 'wp4odoo' ),
			],
		];
	}

	/**
	 * Get external dependency status for Bookly.
	 *
	 * @return array{available: bool, notices: array<array{type: string, message: string}>}
	 */
	public function get_dependency_status(): array {
		if ( ! class_exists( 'Bookly\Lib\Plugin' ) ) {
			return [
				'available' => false,
				'notices'   => [
					[
						'type'    => 'warning',
						'message' => __( 'Bookly must be installed and activated to use this module.', 'wp4odoo' ),
					],
				],
			];
		}

		return [
			'available' => true,
			'notices'   => [],
		];
	}

	// ─── Push override ──────────────────────────────────────

	/**
	 * Push a WordPress entity to Odoo.
	 *
	 * For bookings: ensures the associated service is synced first,
	 * and resolves the customer to an Odoo partner for calendar.event.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       Bookly entity ID.
	 * @param int    $odoo_id     Odoo ID (0 if creating).
	 * @param array  $payload     Additional data.
	 * @return bool True on success.
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): bool {
		if ( 'booking' === $entity_type && 'delete' !== $action ) {
			$this->ensure_service_synced( $wp_id );
		}

		return parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );
	}

	/**
	 * Map WP data to Odoo values.
	 *
	 * Bookings bypass standard mapping — the handler pre-formats
	 * data for calendar.event (including partner_ids M2M commands).
	 * Services use standard field mapping plus a hardcoded type.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $wp_data     WordPress data from load_wp_data().
	 * @return array<string, mixed> Odoo-ready data.
	 */
	public function map_to_odoo( string $entity_type, array $wp_data ): array {
		if ( 'booking' === $entity_type ) {
			return $wp_data;
		}

		$mapped = parent::map_to_odoo( $entity_type, $wp_data );

		if ( 'service' === $entity_type ) {
			$mapped['type'] = 'service';
		}

		return $mapped;
	}

	// ─── Data access ────────────────────────────────────────

	/**
	 * Load WordPress data for an entity.
	 *
	 * For bookings, loads booking data and enriches it with
	 * service name and resolved partner ID for calendar.event creation.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       Bookly entity ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		return match ( $entity_type ) {
			'service' => $this->handler->load_service( $wp_id ),
			'booking' => $this->load_booking_data( $wp_id ),
			default   => [],
		};
	}

	/**
	 * Load and resolve a booking with Odoo references.
	 *
	 * Reads booking data from Bookly tables, resolves the customer
	 * to an Odoo partner, and formats as calendar.event data.
	 *
	 * @param int $ca_id Bookly customer_appointment ID.
	 * @return array<string, mixed>
	 */
	private function load_booking_data( int $ca_id ): array {
		$data = $this->handler->load_booking( $ca_id );
		if ( empty( $data ) ) {
			return [];
		}

		// Load service name for the event title.
		$service_id   = $data['service_id'] ?? 0;
		$service_data = $service_id > 0 ? $this->handler->load_service( $service_id ) : [];
		$service_name = $service_data['title'] ?? __( 'Booking', 'wp4odoo' );

		// Resolve customer → Odoo partner.
		$partner_ids = [];
		$customer_id = $data['customer_id'] ?? 0;
		$customer    = [];
		if ( $customer_id > 0 ) {
			$customer = $this->handler->get_customer_data( $customer_id );
			if ( ! empty( $customer['email'] ) ) {
				$name       = $customer['full_name'] ?: trim( $customer['first_name'] . ' ' . $customer['last_name'] );
				$partner_id = $this->partner_service()->get_or_create(
					$customer['email'],
					[ 'name' => $name ?: $customer['email'] ],
					0
				);
				if ( $partner_id ) {
					$partner_ids = [ [ 4, $partner_id, 0 ] ];
				}
			}
		}

		// Compose event name: "Service — Customer".
		$customer_name = '';
		if ( ! empty( $customer['full_name'] ) ) {
			$customer_name = $customer['full_name'];
		} elseif ( ! empty( $customer['first_name'] ) || ! empty( $customer['last_name'] ) ) {
			$customer_name = trim( $customer['first_name'] . ' ' . $customer['last_name'] );
		}

		$event_name = $customer_name
			/* translators: %1$s: service name, %2$s: customer name */
			? sprintf( __( '%1$s — %2$s', 'wp4odoo' ), $service_name, $customer_name )
			: $service_name;

		return [
			'name'        => $event_name,
			'start'       => $data['start_date'],
			'stop'        => $data['end_date'],
			'partner_ids' => $partner_ids,
			'description' => $data['internal_note'] ?? '',
		];
	}

	/**
	 * Ensure the Bookly service is synced to Odoo before pushing a booking.
	 *
	 * Same pattern as ensure_service_synced() in Amelia: reads the
	 * service_id from the booking, checks if it is already mapped,
	 * and does a synchronous push if not.
	 *
	 * @param int $ca_id Bookly customer_appointment ID.
	 * @return void
	 */
	private function ensure_service_synced( int $ca_id ): void {
		$service_id = $this->handler->get_service_id_for_booking( $ca_id );
		if ( $service_id <= 0 ) {
			return;
		}

		$existing = $this->get_mapping( 'service', $service_id );
		if ( $existing ) {
			return;
		}

		// Synchronous push — parent::push_to_odoo handles create + mapping.
		parent::push_to_odoo( 'service', 'create', $service_id );
	}

	/**
	 * Get the Partner_Service instance for customer resolution.
	 *
	 * @return Partner_Service
	 */
	private function partner_service(): Partner_Service {
		return new Partner_Service(
			fn() => $this->client(),
			$this->entity_map()
		);
	}
}
