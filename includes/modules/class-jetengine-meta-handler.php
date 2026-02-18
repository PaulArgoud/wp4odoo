<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetEngine Meta Handler — reads/writes JetEngine meta fields.
 *
 * Enriches other modules' push/pull data by injecting JetEngine meta field
 * values into Odoo values (push) and writing Odoo x_ values back to post
 * meta (pull). Follows the same pattern as ACF_Handler.
 *
 * JetEngine stores custom meta fields as regular post_meta (or user_meta),
 * so we use get_post_meta() / update_post_meta() directly.
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class JetEngine_Meta_Handler {

	/**
	 * Supported type conversion keys (same as ACF_Handler).
	 *
	 * @var array<string, true>
	 */
	private const VALID_TYPES = [
		'text'     => true,
		'number'   => true,
		'integer'  => true,
		'boolean'  => true,
		'date'     => true,
		'datetime' => true,
		'html'     => true,
		'select'   => true,
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

	// ─── Push: enrich Odoo values ────────────────────────

	/**
	 * Enrich Odoo values with JetEngine meta field data during a push.
	 *
	 * Called from the wp4odoo_map_to_odoo_{module}_{entity} filter.
	 * Reads meta fields from the WP entity and injects converted values.
	 *
	 * @param array<string, mixed>                                      $odoo_values Current Odoo values.
	 * @param array<string, mixed>                                      $wp_data     WP entity data (must contain _wp_entity_id).
	 * @param array<int, array{jet_field: string, odoo_field: string, type: string}> $rules       Mapping rules.
	 * @return array<string, mixed> Enriched Odoo values.
	 */
	public function enrich_push( array $odoo_values, array $wp_data, array $rules ): array {
		$wp_id = (int) ( $wp_data['_wp_entity_id'] ?? 0 );

		if ( 0 === $wp_id ) {
			$this->logger->warning( 'JetEngine Meta enrich_push: missing _wp_entity_id.' );
			return $odoo_values;
		}

		foreach ( $rules as $rule ) {
			$value = get_post_meta( $wp_id, $rule['jet_field'], true );

			if ( '' === $value || false === $value ) {
				continue;
			}

			$converted = $this->convert_to_odoo( $value, $rule['type'] );

			if ( null !== $converted ) {
				$odoo_values[ $rule['odoo_field'] ] = $converted;
			}
		}

		return $odoo_values;
	}

	// ─── Pull: extract Odoo values to _jet_ keys ────────

	/**
	 * Enrich WP data with Odoo field values during a pull.
	 *
	 * Called from the wp4odoo_map_from_odoo_{module}_{entity} filter.
	 * Stores Odoo values as _jet_{field} keys in wp_data for later writing.
	 *
	 * @param array<string, mixed>                                      $wp_data   Current WP data.
	 * @param array<string, mixed>                                      $odoo_data Raw Odoo record.
	 * @param array<int, array{jet_field: string, odoo_field: string, type: string}> $rules     Mapping rules.
	 * @return array<string, mixed> Enriched WP data.
	 */
	public function enrich_pull( array $wp_data, array $odoo_data, array $rules ): array {
		foreach ( $rules as $rule ) {
			if ( ! array_key_exists( $rule['odoo_field'], $odoo_data ) ) {
				continue;
			}

			$value     = $odoo_data[ $rule['odoo_field'] ];
			$converted = $this->convert_from_odoo( $value, $rule['type'] );

			$wp_data[ '_jet_' . $rule['jet_field'] ] = $converted;
		}

		return $wp_data;
	}

	// ─── Pull: write meta fields after save ──────────────

	/**
	 * Write JetEngine meta field values to a WP entity after pull save.
	 *
	 * Called from the wp4odoo_after_save_{module}_{entity} action.
	 * Reads _jet_* keys from wp_data and calls update_post_meta().
	 *
	 * @param int                                                       $wp_id   WP entity ID.
	 * @param array<string, mixed>                                      $wp_data WP data containing _jet_* keys.
	 * @param array<int, array{jet_field: string, odoo_field: string, type: string}> $rules   Mapping rules.
	 * @return void
	 */
	public function write_jet_fields( int $wp_id, array $wp_data, array $rules ): void {
		foreach ( $rules as $rule ) {
			$key = '_jet_' . $rule['jet_field'];

			if ( ! array_key_exists( $key, $wp_data ) ) {
				continue;
			}

			update_post_meta( $wp_id, $rule['jet_field'], $wp_data[ $key ] );
		}
	}

	// ─── Type conversions: WP → Odoo ────────────────────

	/**
	 * Convert a meta field value to the appropriate Odoo type.
	 *
	 * @param mixed  $value The meta field value.
	 * @param string $type  The conversion type key.
	 * @return mixed|null Converted value, or null to skip.
	 */
	public function convert_to_odoo( mixed $value, string $type ): mixed {
		if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
			return $value;
		}

		return match ( $type ) {
			'text', 'html', 'select' => (string) $value,
			'number'                 => Field_Mapper::format_price( $value ),
			'integer'                => (int) $value,
			'boolean'                => Field_Mapper::from_bool( $value ),
			'date'                   => Field_Mapper::wp_date_to_odoo( (string) $value ),
			'datetime'               => Field_Mapper::wp_date_to_odoo( (string) $value ),
		};
	}

	// ─── Type conversions: Odoo → WP ────────────────────

	/**
	 * Convert an Odoo field value to the appropriate meta type.
	 *
	 * @param mixed  $value The Odoo field value.
	 * @param string $type  The conversion type key.
	 * @return mixed Converted value.
	 */
	public function convert_from_odoo( mixed $value, string $type ): mixed {
		if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
			return $value;
		}

		return match ( $type ) {
			'text', 'html', 'select' => (string) ( $value ?? '' ),
			'number'                 => (float) $value,
			'integer'                => (int) $value,
			'boolean'                => Field_Mapper::to_bool( $value ),
			'date'                   => Field_Mapper::odoo_date_to_wp( (string) ( $value ?? '' ), 'Y-m-d' ),
			'datetime'               => Field_Mapper::odoo_date_to_wp( (string) ( $value ?? '' ), 'Y-m-d H:i:s' ),
		};
	}

	// ─── Validation ─────────────────────────────────────

	/**
	 * Validate and sanitize a mapping rule.
	 *
	 * @param array<string, string> $rule Raw rule from settings.
	 * @return array<string, string>|null Sanitized rule, or null if invalid.
	 */
	public static function validate_rule( array $rule ): ?array {
		$jet_field     = sanitize_key( $rule['jet_field'] ?? '' );
		$odoo_field    = sanitize_key( $rule['odoo_field'] ?? '' );
		$type          = sanitize_key( $rule['type'] ?? 'text' );
		$target_module = sanitize_key( $rule['target_module'] ?? '' );
		$entity_type   = sanitize_key( $rule['entity_type'] ?? '' );

		if ( '' === $jet_field || '' === $odoo_field || '' === $target_module || '' === $entity_type ) {
			return null;
		}

		if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
			$type = 'text';
		}

		return [
			'target_module' => $target_module,
			'entity_type'   => $entity_type,
			'jet_field'     => $jet_field,
			'odoo_field'    => $odoo_field,
			'type'          => $type,
		];
	}

	/**
	 * Get the list of valid type keys.
	 *
	 * @return array<string> Valid type names.
	 */
	public static function get_valid_types(): array {
		return array_keys( self::VALID_TYPES );
	}
}
