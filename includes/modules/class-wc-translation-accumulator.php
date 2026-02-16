<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\I18n\Translation_Service;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation accumulator for the WooCommerce module.
 *
 * Buffers pulled products, categories, and attribute values during a
 * Sync_Engine batch, then flushes all translations in a single batched
 * Odoo read per language after all jobs are processed.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class WC_Translation_Accumulator {

	/**
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * @var \Closure
	 */
	private \Closure $settings_fn;

	/**
	 * @var \Closure(): Translation_Service
	 */
	private \Closure $translation_fn;

	/**
	 * Accumulator for pulled products: Odoo ID => WP ID.
	 *
	 * @var array<int, int>
	 */
	private array $pulled_products = [];

	/**
	 * Accumulator for pulled categories: Odoo categ_id => WP term_id.
	 *
	 * @var array<int, int>
	 */
	private array $pulled_categories = [];

	/**
	 * Accumulator for pulled attribute values: Odoo value_id => WP term_id.
	 *
	 * @var array<int, int>
	 */
	private array $pulled_attribute_values = [];

	/**
	 * @param Logger   $logger         Logger instance.
	 * @param \Closure $settings_fn    Returns the module settings array.
	 * @param \Closure $translation_fn Returns the Translation_Service.
	 */
	public function __construct(
		Logger $logger,
		\Closure $settings_fn,
		\Closure $translation_fn
	) {
		$this->logger         = $logger;
		$this->settings_fn    = $settings_fn;
		$this->translation_fn = $translation_fn;
	}

	/**
	 * Record a pulled product for deferred translation flush.
	 *
	 * @param int $odoo_id Odoo product.template ID.
	 * @param int $wp_id   WC product ID.
	 * @return void
	 */
	public function accumulate_product( int $odoo_id, int $wp_id ): void {
		$this->pulled_products[ $odoo_id ] = $wp_id;
	}

	/**
	 * Accumulate the category from captured Odoo data.
	 *
	 * Reads categ_id (Many2one: [id, name]) from the provided data and
	 * resolves it to an existing product_cat term for accumulation.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return void
	 */
	public function accumulate_category( array $odoo_data ): void {
		$categ = $odoo_data['categ_id'] ?? null;

		if ( ! is_array( $categ ) || count( $categ ) < 2 ) {
			return;
		}

		$odoo_categ_id = (int) $categ[0];
		$categ_name    = (string) $categ[1];

		if ( $odoo_categ_id <= 0 || '' === $categ_name ) {
			return;
		}

		$term = term_exists( $categ_name, 'product_cat' );
		if ( $term ) {
			$term_id                                   = (int) ( is_array( $term ) ? $term['term_id'] : $term ); // @phpstan-ignore function.alreadyNarrowedType
			$this->pulled_categories[ $odoo_categ_id ] = $term_id;
		}
	}

	/**
	 * Merge attribute value mappings into the accumulator.
	 *
	 * @param array<int, int> $attr_values Odoo value_id => WP term_id.
	 * @return void
	 */
	public function accumulate_attribute_values( array $attr_values ): void {
		$this->pulled_attribute_values = array_replace( $this->pulled_attribute_values, $attr_values );
	}

	/**
	 * Flush accumulated translations in batch.
	 *
	 * Called after all jobs in a Sync_Engine batch are processed.
	 * Makes one Odoo read() per language for ALL accumulated entities.
	 *
	 * @return void
	 */
	public function flush_translations(): void {
		$has_products   = ! empty( $this->pulled_products );
		$has_categories = ! empty( $this->pulled_categories );
		$has_attr_vals  = ! empty( $this->pulled_attribute_values );

		if ( ! $has_products && ! $has_categories && ! $has_attr_vals ) {
			return;
		}

		$settings     = ( $this->settings_fn )();
		$sync_setting = $settings['sync_translations'] ?? [];

		// Backward compat: old boolean format (true = all languages, false = none).
		if ( ! is_array( $sync_setting ) ) {
			if ( empty( $sync_setting ) ) {
				$this->clear_all_accumulators();
				return;
			}
			$enabled_langs = []; // Boolean true → all languages.
		} else {
			if ( empty( $sync_setting ) ) {
				$this->clear_all_accumulators();
				return;
			}
			$enabled_langs = $sync_setting;
		}

		/** @var Translation_Service $ts */
		$ts = ( $this->translation_fn )();
		if ( ! $ts->is_available() ) {
			$this->clear_all_accumulators();
			return;
		}

		// ── Product translations ────────────────────────────
		if ( $has_products ) {
			/**
			 * Filter the translatable field map for WooCommerce products.
			 *
			 * Keys are Odoo field names, values are WP field names.
			 *
			 * @since 3.0.0
			 *
			 * @param array<string, string> $field_map Odoo field => WP field.
			 */
			$field_map = apply_filters(
				'wp4odoo_translatable_fields_woocommerce',
				[
					'name'             => 'post_title',
					'description_sale' => 'post_content',
				]
			);

			$ts->pull_translations_batch(
				'product.template',
				$this->pulled_products,
				array_keys( $field_map ),
				$field_map,
				'product',
				[ $this, 'apply_product_translation' ],
				$enabled_langs
			);

			$this->logger->info(
				'Flushed product translations.',
				[ 'count' => count( $this->pulled_products ) ]
			);
		}

		// ── Category translations ───────────────────────────
		if ( $has_categories ) {
			$ts->pull_term_translations_batch(
				'product.category',
				$this->pulled_categories,
				'product_cat',
				[ $this, 'apply_term_translation' ],
				$enabled_langs
			);

			$this->logger->info(
				'Flushed category translations.',
				[ 'count' => count( $this->pulled_categories ) ]
			);
		}

		// ── Attribute value translations ────────────────────
		if ( $has_attr_vals ) {
			$by_taxonomy = $this->group_attribute_values_by_taxonomy();

			foreach ( $by_taxonomy as $taxonomy => $odoo_wp_map ) {
				$ts->pull_term_translations_batch(
					'product.attribute.value',
					$odoo_wp_map,
					$taxonomy,
					[ $this, 'apply_term_translation' ],
					$enabled_langs
				);
			}

			$this->logger->info(
				'Flushed attribute value translations.',
				[ 'count' => count( $this->pulled_attribute_values ) ]
			);
		}

		$this->clear_all_accumulators();
	}

	/**
	 * Apply translated field values to a WC product post.
	 *
	 * @param int                  $trans_wp_id Translated WP post ID.
	 * @param array<string, string> $data       WP field => translated value.
	 * @param string               $lang        Language code.
	 * @return void
	 */
	public function apply_product_translation( int $trans_wp_id, array $data, string $lang ): void {
		$product = wc_get_product( $trans_wp_id );
		if ( $product ) {
			if ( isset( $data['post_title'] ) ) {
				$product->set_name( $data['post_title'] );
			}
			if ( isset( $data['post_content'] ) ) {
				$product->set_description( $data['post_content'] );
			}
			$product->save();
			return;
		}

		// Fallback: direct post update if WC product cannot be loaded.
		$update = [ 'ID' => $trans_wp_id ];
		if ( isset( $data['post_title'] ) ) {
			$update['post_title'] = $data['post_title'];
		}
		if ( isset( $data['post_content'] ) ) {
			$update['post_content'] = $data['post_content'];
		}

		wp_update_post( $update );
	}

	/**
	 * Apply a translated name to a taxonomy term.
	 *
	 * @param int    $trans_term_id Translated WP term ID.
	 * @param string $name         Translated term name.
	 * @param string $lang         Language code.
	 * @return void
	 */
	public function apply_term_translation( int $trans_term_id, string $name, string $lang ): void {
		wp_update_term( $trans_term_id, '', [ 'name' => $name ] );
	}

	/**
	 * Group accumulated attribute values by their WP taxonomy.
	 *
	 * @return array<string, array<int, int>> taxonomy => [odoo_id => term_id].
	 */
	private function group_attribute_values_by_taxonomy(): array {
		$grouped = [];

		foreach ( $this->pulled_attribute_values as $odoo_id => $term_id ) {
			$term = get_term( $term_id );

			if ( $term && ! is_wp_error( $term ) ) {
				$grouped[ $term->taxonomy ][ $odoo_id ] = $term_id;
			}
		}

		return $grouped;
	}

	/**
	 * Clear all translation accumulators.
	 *
	 * @return void
	 */
	private function clear_all_accumulators(): void {
		$this->pulled_products         = [];
		$this->pulled_categories       = [];
		$this->pulled_attribute_values = [];
	}
}
