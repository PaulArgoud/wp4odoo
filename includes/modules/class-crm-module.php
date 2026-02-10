<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRM Module — syncs WordPress users ↔ Odoo contacts, form submissions → leads.
 *
 * Contact sync is handled directly by this class.
 * Lead management is delegated to Lead_Manager.
 * Contact field refinement (name, country/state) is delegated to Contact_Refiner.
 *
 * @package WP4Odoo
 * @since   1.0.0
 */
class CRM_Module extends Module_Base {

	/**
	 * Billing/address meta fields with WooCommerce fallback keys.
	 *
	 * Keys are the canonical data keys; values are arrays of user meta
	 * keys to try (first match wins on load).
	 */
	private const CONTACT_META_FIELDS = [
		'billing_phone'     => [ 'billing_phone', 'phone' ],
		'billing_company'   => [ 'billing_company', 'company' ],
		'billing_address_1' => [ 'billing_address_1' ],
		'billing_address_2' => [ 'billing_address_2' ],
		'billing_city'      => [ 'billing_city' ],
		'billing_postcode'  => [ 'billing_postcode' ],
		'billing_country'   => [ 'billing_country' ],
		'billing_state'     => [ 'billing_state' ],
	];

	protected string $id   = 'crm';
	protected string $name = 'CRM';

	protected array $odoo_models = [
		'contact' => 'res.partner',
		'lead'    => 'crm.lead',
	];

	protected array $default_mappings = [
		'contact' => [
			'display_name'      => 'name',
			'user_email'        => 'email',
			'description'       => 'comment',
			'first_name'        => 'x_wp_first_name',
			'last_name'         => 'x_wp_last_name',
			'billing_phone'     => 'phone',
			'billing_company'   => 'company_name',
			'billing_address_1' => 'street',
			'billing_address_2' => 'street2',
			'billing_city'      => 'city',
			'billing_postcode'  => 'zip',
			'billing_country'   => 'country_id',
			'billing_state'     => 'state_id',
			'user_url'          => 'website',
		],
		'lead' => [
			'name'        => 'name',
			'email'       => 'email_from',
			'phone'       => 'phone',
			'company'     => 'partner_name',
			'description' => 'description',
			'source'      => 'x_wp_source',
		],
	];

	/**
	 * Lead management delegate.
	 *
	 * @var Lead_Manager
	 */
	private Lead_Manager $lead_manager;

	/**
	 * Contact refinement delegate.
	 *
	 * @var Contact_Refiner
	 */
	private Contact_Refiner $contact_refiner;

	/**
	 * Boot the module: register WordPress hooks, CPT, shortcode, filters.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->lead_manager    = new Lead_Manager( $this->logger, fn() => $this->get_settings() );
		$this->contact_refiner = new Contact_Refiner( fn() => $this->client() );

		// User hooks for contact sync.
		add_action( 'user_register', [ $this, 'on_user_register' ], 10, 2 );
		add_action( 'profile_update', [ $this, 'on_profile_update' ], 10, 3 );
		add_action( 'delete_user', [ $this, 'on_delete_user' ], 10, 3 );

		// Lead CPT and shortcode (delegated to Lead_Manager).
		add_action( 'init', [ $this->lead_manager, 'register_lead_cpt' ] );
		add_shortcode( 'wp4odoo_lead_form', [ $this->lead_manager, 'render_lead_form' ] );
		add_action( 'wp_ajax_wp4odoo_submit_lead', [ $this->lead_manager, 'handle_lead_submission' ] );
		add_action( 'wp_ajax_nopriv_wp4odoo_submit_lead', [ $this->lead_manager, 'handle_lead_submission' ] );

		// Mapping refinement filters (delegated to Contact_Refiner).
		add_filter( "wp4odoo_map_to_odoo_{$this->id}_contact", [ $this->contact_refiner, 'refine_to_odoo' ], 10, 3 );
		add_filter( "wp4odoo_map_from_odoo_{$this->id}_contact", [ $this->contact_refiner, 'refine_from_odoo' ], 10, 3 );
	}

	/**
	 * Get default settings for the CRM module.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return [
			'sync_users_as_contacts' => true,
			'archive_on_delete'      => true,
			'sync_role'              => '',
			'create_users_on_pull'   => true,
			'default_user_role'      => 'subscriber',
			'lead_form_enabled'      => true,
		];
	}

	/**
	 * Get settings field definitions for the admin UI.
	 *
	 * @return array<string, array>
	 */
	public function get_settings_fields(): array {
		$roles = [];
		foreach ( wp_roles()->roles as $role_key => $role ) {
			$roles[ $role_key ] = $role['name'];
		}

		return [
			'sync_users_as_contacts' => [
				'label'       => __( 'Sync users as contacts', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Push WordPress users to Odoo res.partner.', 'wp4odoo' ),
			],
			'archive_on_delete' => [
				'label'       => __( 'Archive on delete', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Set active=false in Odoo instead of deleting the record.', 'wp4odoo' ),
			],
			'sync_role' => [
				'label'       => __( 'Sync role', 'wp4odoo' ),
				'type'        => 'select',
				'options'     => array_merge( [ '' => __( 'All roles', 'wp4odoo' ) ], $roles ),
				'description' => __( 'Only sync users with this role. Leave empty to sync all.', 'wp4odoo' ),
			],
			'create_users_on_pull' => [
				'label'       => __( 'Create users on pull', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Create new WordPress users when pulling contacts from Odoo.', 'wp4odoo' ),
			],
			'default_user_role' => [
				'label'       => __( 'Default user role', 'wp4odoo' ),
				'type'        => 'select',
				'options'     => $roles,
				'description' => __( 'Role assigned to users created by Odoo pull.', 'wp4odoo' ),
			],
			'lead_form_enabled' => [
				'label'       => __( 'Enable lead form', 'wp4odoo' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable the [wp4odoo_lead_form] shortcode.', 'wp4odoo' ),
			],
		];
	}

	// ─── User Hook Callbacks ─────────────────────────────────

	/**
	 * Enqueue a contact create job when a new user registers.
	 *
	 * @param int   $user_id  The new user ID.
	 * @param array $userdata Data passed to wp_insert_user.
	 * @return void
	 */
	public function on_user_register( int $user_id, array $userdata = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( ! $this->should_sync_user( $user_id ) ) {
			return;
		}

		Queue_Manager::push( 'crm', 'contact', 'create', $user_id );
		$this->logger->info( 'Enqueued contact create.', [ 'wp_id' => $user_id ] );
	}

	/**
	 * Enqueue a contact update job when a user profile is updated.
	 *
	 * @param int      $user_id       The user ID.
	 * @param \WP_User $old_user_data The old user object before update.
	 * @param array    $userdata      The updated user data.
	 * @return void
	 */
	public function on_profile_update( int $user_id, \WP_User $old_user_data, array $userdata = [] ): void {
		if ( $this->is_importing() ) {
			return;
		}

		if ( ! $this->should_sync_user( $user_id ) ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'contact', $user_id );
		$action  = $odoo_id ? 'update' : 'create';

		Queue_Manager::push( 'crm', 'contact', $action, $user_id, $odoo_id );
		$this->logger->info( "Enqueued contact {$action}.", [ 'wp_id' => $user_id ] );
	}

	/**
	 * Handle user deletion: archive or delete the Odoo contact.
	 *
	 * @param int      $user_id  The user ID being deleted.
	 * @param int|null $reassign ID of user to reassign posts to, or null.
	 * @param \WP_User $user     The user object.
	 * @return void
	 */
	public function on_delete_user( int $user_id, ?int $reassign, \WP_User $user ): void {
		if ( $this->is_importing() ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'contact', $user_id );
		if ( ! $odoo_id ) {
			return;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['archive_on_delete'] ) ) {
			Queue_Manager::push( 'crm', 'contact', 'update', $user_id, $odoo_id, [ '_archive' => true ] );
			$this->logger->info( 'Enqueued contact archive.', [ 'wp_id' => $user_id, 'odoo_id' => $odoo_id ] );
		} else {
			Queue_Manager::push( 'crm', 'contact', 'delete', $user_id, $odoo_id );
			$this->logger->info( 'Enqueued contact delete.', [ 'wp_id' => $user_id, 'odoo_id' => $odoo_id ] );
		}
	}

	// ─── Push / Pull Overrides ───────────────────────────────

	/**
	 * Push a WordPress entity to Odoo.
	 *
	 * Extends the base to handle archive payloads and email deduplication
	 * before creating a new contact in Odoo.
	 *
	 * @param string $entity_type The entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $wp_id       WordPress entity ID.
	 * @param int    $odoo_id     Odoo ID (0 if creating).
	 * @param array  $payload     Additional data from the queue.
	 * @return bool True on success.
	 */
	public function push_to_odoo( string $entity_type, string $action, int $wp_id, int $odoo_id = 0, array $payload = [] ): bool {
		// Handle archive payload.
		if ( 'contact' === $entity_type && ! empty( $payload['_archive'] ) ) {
			if ( $odoo_id > 0 ) {
				$model = $this->get_odoo_model( $entity_type );
				$this->client()->write( $model, [ $odoo_id ], [ 'active' => false ] );
				$this->logger->info( 'Archived Odoo contact.', compact( 'wp_id', 'odoo_id' ) );
			}
			$this->remove_mapping( $entity_type, $wp_id );
			return true;
		}

		// Email dedup on create: search Odoo by email before creating.
		if ( 'contact' === $entity_type && 'create' === $action && 0 === $odoo_id ) {
			$wp_data = $this->load_wp_data( $entity_type, $wp_id );
			$email   = $wp_data['user_email'] ?? '';

			if ( ! empty( $email ) ) {
				$model     = $this->get_odoo_model( $entity_type );
				$existing  = $this->client()->search( $model, [ [ 'email', '=', $email ] ], 0, 1 );

				if ( ! empty( $existing ) ) {
					$odoo_id = (int) $existing[0];
					$action  = 'update';
					$this->logger->info( 'Email match found in Odoo, converting create to update.', compact( 'email', 'odoo_id' ) );
				}
			}
		}

		return parent::push_to_odoo( $entity_type, $action, $wp_id, $odoo_id, $payload );
	}

	// ─── Data Loading ────────────────────────────────────────

	/**
	 * Load WordPress data for an entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return array
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		return match ( $entity_type ) {
			'contact' => $this->load_contact_data( $wp_id ),
			'lead'    => $this->lead_manager->load_lead_data( $wp_id ),
			default   => [],
		};
	}

	/**
	 * Load contact data from a WordPress user.
	 *
	 * @param int $wp_id User ID.
	 * @return array
	 */
	private function load_contact_data( int $wp_id ): array {
		$user = get_userdata( $wp_id );
		if ( ! $user ) {
			return [];
		}

		$data = [
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'description'  => $user->description,
			'user_url'     => $user->user_url,
		];

		// WooCommerce billing fields (fallback to generic meta).
		foreach ( self::CONTACT_META_FIELDS as $key => $meta_keys ) {
			$value = '';
			foreach ( $meta_keys as $meta_key ) {
				$value = get_user_meta( $wp_id, $meta_key, true );
				if ( '' !== $value ) {
					break;
				}
			}
			$data[ $key ] = $value;
		}

		return $data;
	}

	// ─── Data Saving ─────────────────────────────────────────

	/**
	 * Save data to WordPress.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $data        Mapped data.
	 * @param int    $wp_id       Existing WP ID (0 if new).
	 * @return int The WordPress entity ID (0 on failure).
	 */
	protected function save_wp_data( string $entity_type, array $data, int $wp_id = 0 ): int {
		return match ( $entity_type ) {
			'contact' => $this->save_contact_data( $data, $wp_id ),
			'lead'    => $this->lead_manager->save_lead_data( $data, $wp_id ),
			default   => 0,
		};
	}

	/**
	 * Save contact data as a WordPress user.
	 *
	 * Handles email deduplication and user meta updates.
	 *
	 * @param array $data  Mapped contact data.
	 * @param int   $wp_id Existing user ID (0 to create).
	 * @return int User ID or 0 on failure.
	 */
	private function save_contact_data( array $data, int $wp_id = 0 ): int {
		$email = $data['user_email'] ?? '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->logger->warning( 'Cannot save contact without valid email.', compact( 'data', 'wp_id' ) );
			return 0;
		}

		// Email dedup: check if a user with this email already exists.
		if ( 0 === $wp_id ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				$wp_id = $existing->ID;
				$this->logger->info( 'Pull dedup: matched existing WP user by email.', [ 'email' => $email, 'wp_id' => $wp_id ] );
			}
		}

		$settings = $this->get_settings();

		if ( $wp_id > 0 ) {
			// Update existing user.
			$userdata = [
				'ID'           => $wp_id,
				'display_name' => $data['display_name'] ?? '',
				'first_name'   => $data['first_name'] ?? '',
				'last_name'    => $data['last_name'] ?? '',
				'description'  => $data['description'] ?? '',
				'user_url'     => $data['user_url'] ?? '',
			];

			$userdata = array_filter( $userdata, fn( $v ) => '' !== $v );
			$userdata['ID'] = $wp_id;

			$result = wp_update_user( $userdata );
			if ( is_wp_error( $result ) ) {
				$this->logger->error( 'Failed to update WP user.', [ 'wp_id' => $wp_id, 'error' => $result->get_error_message() ] );
				return 0;
			}
		} else {
			// Create new user.
			if ( empty( $settings['create_users_on_pull'] ) ) {
				$this->logger->info( 'User creation on pull is disabled.', compact( 'email' ) );
				return 0;
			}

			$username = strstr( $email, '@', true );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_rand( 100, 999 );
			}

			$userdata = [
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password(),
				'display_name' => $data['display_name'] ?? $username,
				'first_name'   => $data['first_name'] ?? '',
				'last_name'    => $data['last_name'] ?? '',
				'description'  => $data['description'] ?? '',
				'user_url'     => $data['user_url'] ?? '',
				'role'         => $settings['default_user_role'] ?: 'subscriber',
			];

			$wp_id = wp_insert_user( $userdata );
			if ( is_wp_error( $wp_id ) ) {
				$this->logger->error( 'Failed to create WP user.', [ 'email' => $email, 'error' => $wp_id->get_error_message() ] );
				return 0;
			}
		}

		// Save billing / meta fields.
		foreach ( self::CONTACT_META_FIELDS as $key => $meta_keys ) {
			if ( isset( $data[ $key ] ) && '' !== $data[ $key ] ) {
				update_user_meta( $wp_id, $meta_keys[0], $data[ $key ] );
			}
		}

		return $wp_id;
	}

	/**
	 * Delete a WordPress entity.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return bool
	 */
	protected function delete_wp_data( string $entity_type, int $wp_id ): bool {
		if ( 'contact' === $entity_type ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			return wp_delete_user( $wp_id );
		}

		if ( 'lead' === $entity_type ) {
			$result = wp_delete_post( $wp_id, true );
			return false !== $result && null !== $result;
		}

		return false;
	}

	// ─── Utility ─────────────────────────────────────────────

	/**
	 * Check whether a user should be synced based on role settings.
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool True if the user should be synced.
	 */
	private function should_sync_user( int $user_id ): bool {
		$settings = $this->get_settings();

		if ( empty( $settings['sync_users_as_contacts'] ) ) {
			return false;
		}

		$sync_role = $settings['sync_role'] ?? '';

		// Empty = sync all roles.
		if ( '' === $sync_role ) {
			return true;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return in_array( $sync_role, $user->roles, true );
	}
}
