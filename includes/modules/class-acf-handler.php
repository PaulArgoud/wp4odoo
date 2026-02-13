<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Handler — reads/writes ACF custom fields and converts types.
 *
 * Enriches other modules' push/pull data by injecting ACF field values
 * into Odoo values (push) and writing Odoo x_ values back to ACF fields (pull).
 *
 * @package WP4Odoo
 * @since   3.1.0
 */
class ACF_Handler {

	/**
	 * Supported type conversion keys.
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
		'binary'   => true,
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
	 * Enrich Odoo values with ACF field data during a push.
	 *
	 * Called from the wp4odoo_map_to_odoo_{module}_{entity} filter.
	 * Reads ACF fields from the WP entity and injects converted values.
	 *
	 * @param array<string, mixed>                                    $odoo_values Current Odoo values.
	 * @param array<string, mixed>                                    $wp_data     WP entity data (must contain _wp_entity_id).
	 * @param array<int, array{acf_field: string, odoo_field: string, type: string, context: string}> $rules       Mapping rules.
	 * @return array<string, mixed> Enriched Odoo values.
	 */
	public function enrich_push( array $odoo_values, array $wp_data, array $rules ): array {
		$wp_id = (int) ( $wp_data['_wp_entity_id'] ?? 0 );

		if ( 0 === $wp_id ) {
			$this->logger->warning( 'ACF enrich_push: missing _wp_entity_id.' );
			return $odoo_values;
		}

		foreach ( $rules as $rule ) {
			$acf_context = $this->resolve_acf_post_id( $wp_id, $rule['context'] );
			$value       = $this->read_acf_field( $rule['acf_field'], $acf_context );

			if ( null === $value ) {
				continue;
			}

			$converted = $this->convert_to_odoo( $value, $rule['type'] );

			if ( null !== $converted ) {
				$odoo_values[ $rule['odoo_field'] ] = $converted;
			}
		}

		return $odoo_values;
	}

	// ─── Pull: extract Odoo values to _acf_ keys ────────

	/**
	 * Enrich WP data with Odoo field values during a pull.
	 *
	 * Called from the wp4odoo_map_from_odoo_{module}_{entity} filter.
	 * Stores Odoo values as _acf_{acf_field} keys in wp_data for later writing.
	 *
	 * @param array<string, mixed>                                    $wp_data   Current WP data.
	 * @param array<string, mixed>                                    $odoo_data Raw Odoo record.
	 * @param array<int, array{acf_field: string, odoo_field: string, type: string, context: string}> $rules     Mapping rules.
	 * @return array<string, mixed> Enriched WP data.
	 */
	public function enrich_pull( array $wp_data, array $odoo_data, array $rules ): array {
		foreach ( $rules as $rule ) {
			if ( ! array_key_exists( $rule['odoo_field'], $odoo_data ) ) {
				continue;
			}

			$value     = $odoo_data[ $rule['odoo_field'] ];
			$converted = $this->convert_from_odoo( $value, $rule['type'] );

			$wp_data[ '_acf_' . $rule['acf_field'] ] = $converted;
		}

		return $wp_data;
	}

	// ─── Pull: write ACF fields after save ───────────────

	/**
	 * Write ACF field values to a WP entity after pull save.
	 *
	 * Called from the wp4odoo_after_save_{module}_{entity} action.
	 * Reads _acf_* keys from wp_data and calls update_field().
	 *
	 * @param int                                                     $wp_id   WP entity ID.
	 * @param array<string, mixed>                                    $wp_data WP data containing _acf_* keys.
	 * @param array<int, array{acf_field: string, odoo_field: string, type: string, context: string}> $rules   Mapping rules.
	 * @return void
	 */
	public function write_acf_fields( int $wp_id, array $wp_data, array $rules ): void {
		if ( ! function_exists( 'update_field' ) ) {
			return;
		}

		foreach ( $rules as $rule ) {
			$key = '_acf_' . $rule['acf_field'];

			if ( ! array_key_exists( $key, $wp_data ) ) {
				continue;
			}

			$acf_context = $this->resolve_acf_post_id( $wp_id, $rule['context'] );
			update_field( $rule['acf_field'], $wp_data[ $key ], $acf_context );
		}
	}

	// ─── Type conversions: WP → Odoo ────────────────────

	/**
	 * Convert an ACF field value to the appropriate Odoo type.
	 *
	 * @param mixed  $value The ACF field value.
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
			'date'                   => $this->acf_date_to_odoo( (string) $value ),
			'datetime'               => Field_Mapper::wp_date_to_odoo( (string) $value ),
			'binary'                 => $this->image_to_base64( $value ),
		};
	}

	// ─── Type conversions: Odoo → WP ────────────────────

	/**
	 * Convert an Odoo field value to the appropriate ACF type.
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
			'date'                   => $this->odoo_date_to_acf( (string) ( $value ?? '' ) ),
			'datetime'               => Field_Mapper::odoo_date_to_wp( (string) ( $value ?? '' ), 'Y-m-d H:i:s' ),
			'binary'                 => $value, // Binary pull not converted — pass through.
		};
	}

	// ─── ACF field reading ──────────────────────────────

	/**
	 * Read an ACF field value.
	 *
	 * @param string     $field_name ACF field name (slug).
	 * @param int|string $post_id    Post ID or ACF context string (e.g. 'user_5').
	 * @return mixed|null Field value or null if not set.
	 */
	public function read_acf_field( string $field_name, int|string $post_id ): mixed {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		return get_field( $field_name, $post_id );
	}

	// ─── ACF context resolution ─────────────────────────

	/**
	 * Resolve the ACF post ID parameter.
	 *
	 * ACF uses special prefixed IDs for non-post contexts:
	 * - Posts: numeric post ID
	 * - Users: 'user_{ID}' format (used by CRM contacts)
	 *
	 * @param int    $wp_id   WordPress entity ID.
	 * @param string $context Context type: 'post' or 'user'.
	 * @return int|string Post ID or ACF context string.
	 */
	public function resolve_acf_post_id( int $wp_id, string $context ): int|string {
		if ( 'user' === $context ) {
			return 'user_' . $wp_id;
		}

		return $wp_id;
	}

	/**
	 * Determine the ACF context for a module/entity pair.
	 *
	 * CRM contacts are WP users, so ACF uses 'user_{ID}' format.
	 * Everything else is a post.
	 *
	 * @param string $module_id   Module identifier.
	 * @param string $entity_type Entity type within the module.
	 * @return string 'user' or 'post'.
	 */
	public static function resolve_context_for_module( string $module_id, string $entity_type ): string {
		if ( 'crm' === $module_id && 'contact' === $entity_type ) {
			return 'user';
		}

		return 'post';
	}

	// ─── Date helpers ───────────────────────────────────

	/**
	 * Convert an ACF date picker value to Odoo date format.
	 *
	 * ACF date_picker returns 'Ymd' format (e.g. '20260215').
	 * Odoo expects 'Y-m-d' format (e.g. '2026-02-15').
	 *
	 * @param string $value ACF date value (Ymd or Y-m-d).
	 * @return string Odoo date string, or empty on failure.
	 */
	public function acf_date_to_odoo( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		// Already in Odoo format.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		// ACF Ymd format.
		$dt = \DateTime::createFromFormat( 'Ymd', $value );

		if ( false === $dt ) {
			return '';
		}

		return $dt->format( 'Y-m-d' );
	}

	/**
	 * Convert an Odoo date to ACF date picker format.
	 *
	 * Odoo sends 'Y-m-d' format (e.g. '2026-02-15').
	 * ACF date_picker expects 'Ymd' format (e.g. '20260215').
	 *
	 * @param string $value Odoo date string.
	 * @return string ACF date string (Ymd), or empty on failure.
	 */
	public function odoo_date_to_acf( string $value ): string {
		if ( '' === $value || 'false' === $value ) {
			return '';
		}

		$dt = \DateTime::createFromFormat( 'Y-m-d', $value );

		if ( false === $dt ) {
			return '';
		}

		return $dt->format( 'Ymd' );
	}

	// ─── Image helper ───────────────────────────────────

	/**
	 * Convert an ACF image field value to base64 for Odoo.
	 *
	 * ACF image fields can return an attachment ID (int), an array
	 * with 'ID' key, or a URL string (depending on return format setting).
	 *
	 * @param mixed $value ACF image field value.
	 * @return string|null Base64-encoded image data, or null on failure.
	 */
	public function image_to_base64( mixed $value ): ?string {
		$attachment_id = 0;

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$attachment_id = (int) $value;
		} elseif ( is_array( $value ) && isset( $value['ID'] ) ) {
			$attachment_id = (int) $value['ID'];
		}

		if ( $attachment_id <= 0 ) {
			return null;
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->logger->warning( 'ACF image file not found.', [ 'attachment_id' => $attachment_id ] );
			return null;
		}

		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file

		if ( false === $contents ) {
			return null;
		}

		return base64_encode( $contents ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Odoo binary field
	}

	// ─── Validation ─────────────────────────────────────

	/**
	 * Validate and sanitize a mapping rule.
	 *
	 * @param array<string, string> $rule Raw rule from settings.
	 * @return array<string, string>|null Sanitized rule, or null if invalid.
	 */
	public static function validate_rule( array $rule ): ?array {
		$acf_field     = sanitize_key( $rule['acf_field'] ?? '' );
		$odoo_field    = sanitize_key( $rule['odoo_field'] ?? '' );
		$type          = sanitize_key( $rule['type'] ?? 'text' );
		$target_module = sanitize_key( $rule['target_module'] ?? '' );
		$entity_type   = sanitize_key( $rule['entity_type'] ?? '' );

		if ( '' === $acf_field || '' === $odoo_field || '' === $target_module || '' === $entity_type ) {
			return null;
		}

		if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
			$type = 'text';
		}

		$context = self::resolve_context_for_module( $target_module, $entity_type );

		return [
			'target_module' => $target_module,
			'entity_type'   => $entity_type,
			'acf_field'     => $acf_field,
			'odoo_field'    => $odoo_field,
			'type'          => $type,
			'context'       => $context,
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
