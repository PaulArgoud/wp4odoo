<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Partner_Service;
use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amelia Module — push booking services and appointments to Odoo.
 *
 * Syncs Amelia services as Odoo service products (product.product)
 * and appointments as Odoo calendar events (calendar.event), with
 * automatic partner resolution via Partner_Service.
 *
 * Unlike WooCommerce or EDD, Amelia stores data in its own custom
 * tables — the handler queries them directly via $wpdb.
 *
 * Push-only (WP → Odoo). No mutual exclusivity with other modules.
 *
 * Requires the Amelia Booking plugin to be active.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Amelia_Module extends Module_Base {

	use Amelia_Hooks;

	/**
	 * Module identifier.
	 *
	 * @var string
	 */
	protected string $id = 'amelia';

	/**
	 * Human-readable module name.
	 *
	 * @var string
	 */
	protected string $name = 'Amelia';

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
		'service'     => 'product.product',
		'appointment' => 'calendar.event',
	];

	/**
	 * Default field mappings.
	 *
	 * Appointment mappings are minimal because map_to_odoo() is overridden
	 * to pass handler-formatted data directly to Odoo (partner_ids M2M commands).
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $default_mappings = [
		'service'     => [
			'name'        => 'name',
			'description' => 'description_sale',
			'price'       => 'list_price',
		],
		'appointment' => [
			'name'          => 'name',
			'bookingStart'  => 'start',
			'bookingEnd'    => 'stop',
			'partner_ids'   => 'partner_ids',
			'internalNotes' => 'description',
		],
	];

	/**
	 * Amelia data handler.
	 *
	 * Initialized in __construct() (not boot()) because Sync_Engine can
	 * call push_to_odoo on non-booted modules for residual queue jobs.
	 *
	 * @var Amelia_Handler
	 */
	private Amelia_Handler $handler;

	/**
	 * Constructor.
	 *
	 * @param \Closure                      $client_provider Returns the shared Odoo_Client instance.
	 * @param \WP4Odoo\Entity_Map_Repository $entity_map      Shared entity map repository.
	 * @param \WP4Odoo\Settings_Repository   $settings        Settings repository.
	 */
	public function __construct( \Closure $client_provider, \WP4Odoo\Entity_Map_Repository $entity_map, \WP4Odoo\Settings_Repository $settings ) {
		parent::__construct( $client_provider, $entity_map, $settings );
		$this->handler = new Amelia_Handler( $this->logger );
	}

	/**
	 * Boot the module: register Amelia hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->register_hooks();
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, bool>
	 */
	public function get_default_settings(): array {
		return [
			'sync_services'     => true,
			'sync_appointments' => true,
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_settings_fields(): array {
		return [
			'sync_services'     => [
				'label'       => __( 'Sync services', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push Amelia services to Odoo as service products.', 'wp4odoo' ),
			],
			'sync_appointments' => [
				'label'       => __( 'Sync appointments', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push Amelia appointments to Odoo as calendar events.', 'wp4odoo' ),
			],
		];
	}

	/**
	 * Get external dependency status for Amelia.
	 *
	 * @return array{available: bool, notices: array<array{type: string, message: string}>}
	 */
	public function get_dependency_status(): array {
		if ( ! defined( 'AMELIA_VERSION' ) ) {
			return [
				'available' => false,
				'notices'   => [
					[
						'type'    => 'warning',
						'message' => __( 'Amelia Booking must be installed and activated to use this module.', 'wp4odoo' ),
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
	 * For appointments: ensures the associated service is synced first,
	 * and resolves the customer to an Odoo partner for calendar.event.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       Amelia entity ID.
	 * @param int    $odoo_id     Odoo ID (0 if creating).
	 * @param array  $payload     Additional data.
	 * @return bool True on success.
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): bool {
		if ( 'appointment' === $entity_type && 'delete' !== $action ) {
			$this->ensure_service_synced( $wp_id );
		}

		return parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );
	}

	/**
	 * Map WP data to Odoo values.
	 *
	 * Appointments bypass standard mapping — the handler pre-formats
	 * data for calendar.event (including partner_ids M2M commands).
	 * Services use standard field mapping plus a hardcoded type.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $wp_data     WordPress data from load_wp_data().
	 * @return array<string, mixed> Odoo-ready data.
	 */
	public function map_to_odoo( string $entity_type, array $wp_data ): array {
		if ( 'appointment' === $entity_type ) {
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
	 * For appointments, loads appointment data and enriches it with
	 * service name and resolved partner ID for calendar.event creation.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       Amelia entity ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		return match ( $entity_type ) {
			'service'     => $this->handler->load_service( $wp_id ),
			'appointment' => $this->load_appointment_data( $wp_id ),
			default       => [],
		};
	}

	/**
	 * Load and resolve an appointment with Odoo references.
	 *
	 * Reads appointment data from Amelia tables, resolves the customer
	 * to an Odoo partner, and formats as calendar.event data.
	 *
	 * @param int $appointment_id Amelia appointment ID.
	 * @return array<string, mixed>
	 */
	private function load_appointment_data( int $appointment_id ): array {
		$data = $this->handler->load_appointment( $appointment_id );
		if ( empty( $data ) ) {
			return [];
		}

		// Load service name for the event title.
		$service_id   = $data['service_id'] ?? 0;
		$service_data = $service_id > 0 ? $this->handler->load_service( $service_id ) : [];
		$service_name = $service_data['name'] ?? __( 'Appointment', 'wp4odoo' );

		// Resolve customer → Odoo partner.
		$partner_ids = [];
		$customer_id = $data['customer_id'] ?? 0;
		if ( $customer_id > 0 ) {
			$customer = $this->handler->get_customer_data( $customer_id );
			if ( ! empty( $customer['email'] ) ) {
				$full_name  = trim( $customer['firstName'] . ' ' . $customer['lastName'] );
				$partner_id = $this->partner_service()->get_or_create(
					$customer['email'],
					[ 'name' => $full_name ?: $customer['email'] ],
					0
				);
				if ( $partner_id ) {
					$partner_ids = [ [ 4, $partner_id, 0 ] ];
				}
			}
		}

		// Compose event name: "Service — Customer".
		$customer_name = '';
		if ( ! empty( $customer['firstName'] ) || ! empty( $customer['lastName'] ) ) {
			$customer_name = trim( $customer['firstName'] . ' ' . $customer['lastName'] );
		}

		$event_name = $customer_name
			/* translators: %1$s: service name, %2$s: customer name */
			? sprintf( __( '%1$s — %2$s', 'wp4odoo' ), $service_name, $customer_name )
			: $service_name;

		return [
			'name'        => $event_name,
			'start'       => $data['bookingStart'],
			'stop'        => $data['bookingEnd'],
			'partner_ids' => $partner_ids,
			'description' => $data['internalNotes'] ?? '',
		];
	}

	/**
	 * Ensure the Amelia service is synced to Odoo before pushing an appointment.
	 *
	 * Same pattern as ensure_parent_synced() in SimplePay: reads the
	 * service_id from the appointment, checks if it is already mapped,
	 * and does a synchronous push if not.
	 *
	 * @param int $appointment_id Amelia appointment ID.
	 * @return void
	 */
	private function ensure_service_synced( int $appointment_id ): void {
		$service_id = $this->handler->get_service_id_for_appointment( $appointment_id );
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
