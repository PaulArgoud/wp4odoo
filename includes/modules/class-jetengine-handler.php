<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JetEngine Handler — reads CPT data and converts field values.
 *
 * Provides a unified interface for reading data from any WordPress CPT,
 * including standard post fields, post meta, JetEngine meta fields,
 * and taxonomy terms.
 *
 * Field source prefixes:
 * - (no prefix) or 'post_*': standard post fields (post_title, post_content, etc.)
 * - 'meta:key': post meta via get_post_meta()
 * - 'jet:key': JetEngine meta (stored as post_meta, read via get_post_meta)
 * - 'tax:taxonomy': taxonomy terms (concatenated names)
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class JetEngine_Handler {

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
	 * Standard post fields accessible directly from WP_Post.
	 *
	 * @var array<string, true>
	 */
	private const POST_FIELDS = [
		'post_title'   => true,
		'post_content' => true,
		'post_excerpt' => true,
		'post_status'  => true,
		'post_date'    => true,
		'post_name'    => true,
		'post_author'  => true,
	];

	// ─── CPT data loading ──────────────────────────────────

	/**
	 * Load data from a CPT post according to field mappings.
	 *
	 * @param int                                           $post_id        WordPress post ID.
	 * @param array<int, array{wp_field: string, odoo_field: string, type: string}> $field_mappings Field mapping rules.
	 * @return array<string, mixed> Odoo-ready field values keyed by odoo_field.
	 */
	public function load_cpt_data( int $post_id, array $field_mappings ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return [];
		}

		$result                  = [];
		$result['_wp_entity_id'] = $post_id;

		foreach ( $field_mappings as $mapping ) {
			$wp_field   = $mapping['wp_field'];
			$odoo_field = $mapping['odoo_field'];
			$type       = $mapping['type'];

			if ( '' === $wp_field || '' === $odoo_field ) {
				continue;
			}

			$raw_value = $this->read_field_value( $post, $post_id, $wp_field );
			$converted = $this->convert_to_odoo( $raw_value, $type );

			if ( null !== $converted ) {
				$result[ $odoo_field ] = $converted;
			}
		}

		return $result;
	}

	// ─── Field reading ─────────────────────────────────────

	/**
	 * Read a field value from a post based on the source prefix.
	 *
	 * @param \WP_Post|object $post    Post object.
	 * @param int             $post_id Post ID.
	 * @param string          $source  Field source (e.g., 'post_title', 'meta:_price', 'tax:category').
	 * @return mixed Field value.
	 */
	private function read_field_value( object $post, int $post_id, string $source ): mixed {
		// Prefixed sources.
		if ( str_starts_with( $source, 'meta:' ) ) {
			$key = substr( $source, 5 );
			return get_post_meta( $post_id, $key, true );
		}

		if ( str_starts_with( $source, 'jet:' ) ) {
			$key = substr( $source, 4 );
			// JetEngine stores meta in standard post_meta.
			return get_post_meta( $post_id, $key, true );
		}

		if ( str_starts_with( $source, 'tax:' ) ) {
			$taxonomy = substr( $source, 4 );
			return $this->read_taxonomy_terms( $post_id, $taxonomy );
		}

		// Standard post fields.
		if ( isset( self::POST_FIELDS[ $source ] ) && property_exists( $post, $source ) ) {
			return $post->{$source};
		}

		// Fallback: try as meta key.
		return get_post_meta( $post_id, $source, true );
	}

	/**
	 * Read taxonomy terms as a comma-separated string.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string Comma-separated term names.
	 */
	private function read_taxonomy_terms( int $post_id, string $taxonomy ): string {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$names = [];
		foreach ( $terms as $term ) {
			if ( ! empty( $term->name ) ) {
				$names[] = $term->name;
			}
		}

		return implode( ', ', $names );
	}

	// ─── Type conversions ──────────────────────────────────

	/**
	 * Convert a WordPress field value to the appropriate Odoo type.
	 *
	 * @param mixed  $value The raw field value.
	 * @param string $type  The conversion type key.
	 * @return mixed|null Converted value, or null to skip.
	 */
	public function convert_to_odoo( mixed $value, string $type ): mixed {
		if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
			return $value;
		}

		return match ( $type ) {
			'text', 'html', 'select' => (string) ( $value ?? '' ),
			'number'                 => Field_Mapper::format_price( $value ),
			'integer'                => (int) $value,
			'boolean'                => Field_Mapper::from_bool( $value ),
			'date'                   => $this->normalize_date( (string) ( $value ?? '' ) ),
			'datetime'               => Field_Mapper::wp_date_to_odoo( (string) ( $value ?? '' ) ),
		};
	}

	// ─── Validation ────────────────────────────────────────

	/**
	 * Validate and sanitize a CPT mapping configuration.
	 *
	 * @param array<string, mixed> $mapping Raw mapping from settings.
	 * @return array<string, mixed>|null Sanitized mapping, or null if invalid.
	 */
	public static function validate_mapping( array $mapping ): ?array {
		$cpt_slug    = sanitize_key( $mapping['cpt_slug'] ?? '' );
		$entity_type = sanitize_key( $mapping['entity_type'] ?? '' );
		$odoo_model  = sanitize_text_field( $mapping['odoo_model'] ?? '' );
		$dedup_field = sanitize_key( $mapping['dedup_field'] ?? '' );

		if ( '' === $cpt_slug || '' === $entity_type || '' === $odoo_model ) {
			return null;
		}

		// Validate fields array.
		$fields    = $mapping['fields'] ?? [];
		$validated = [];

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$wp_field   = sanitize_text_field( $field['wp_field'] ?? '' );
				$odoo_field = sanitize_key( $field['odoo_field'] ?? '' );
				$type       = sanitize_key( $field['type'] ?? 'text' );

				if ( '' === $wp_field || '' === $odoo_field ) {
					continue;
				}

				if ( ! isset( self::VALID_TYPES[ $type ] ) ) {
					$type = 'text';
				}

				$validated[] = [
					'wp_field'   => $wp_field,
					'odoo_field' => $odoo_field,
					'type'       => $type,
				];
			}
		}

		return [
			'cpt_slug'    => $cpt_slug,
			'entity_type' => $entity_type,
			'odoo_model'  => $odoo_model,
			'dedup_field' => $dedup_field,
			'fields'      => $validated,
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

	// ─── Date helper ───────────────────────────────────────

	/**
	 * Normalize a date value to Odoo format (Y-m-d).
	 *
	 * @param string $value Raw date value.
	 * @return string Odoo date string, or empty.
	 */
	private function normalize_date( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		// Try common formats.
		foreach ( [ 'Ymd', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'd/m/Y', 'm/d/Y' ] as $format ) {
			$dt = \DateTime::createFromFormat( $format, $value );
			if ( false !== $dt ) {
				return $dt->format( 'Y-m-d' );
			}
		}

		return '';
	}
}
