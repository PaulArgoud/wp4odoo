<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Field_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Product Add-Ons Handler — reads add-on data from multiple plugins.
 *
 * Provides a unified interface for reading product add-on/option data from:
 * - WooCommerce Product Add-Ons (official, WooCommerce.com)
 * - ThemeHigh Product Add-Ons (THWEPO)
 * - PPOM for WooCommerce
 *
 * Formats add-ons as either Odoo product attributes (product.attribute +
 * product.template.attribute.line) or BOM lines (mrp.bom.line).
 *
 * @package WP4Odoo
 * @since   3.5.0
 */
class WC_Addons_Handler {

	/**
	 * Cached addon source detection.
	 *
	 * @var string
	 */
	private string $source = '';

	// ─── Unified add-on loading ────────────────────────────

	/**
	 * Load add-ons for a WooCommerce product.
	 *
	 * Returns a normalized array of add-on groups, regardless of source plugin.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}>
	 */
	public function load_addons( int $product_id ): array {
		$source = $this->get_addon_source();

		return match ( $source ) {
			'official'  => $this->load_official_addons( $product_id ),
			'themeHigh' => $this->load_theme_high_addons( $product_id ),
			'ppom'      => $this->load_ppom_addons( $product_id ),
			default     => [],
		};
	}

	/**
	 * Detect which add-on plugin is active.
	 *
	 * @return string 'official', 'themeHigh', 'ppom', or '' if none.
	 */
	public function get_addon_source(): string {
		if ( '' !== $this->source ) {
			return $this->source;
		}

		if ( class_exists( 'WC_Product_Addons' ) ) {
			$this->source = 'official';
		} elseif ( defined( 'THWEPO_VERSION' ) ) {
			$this->source = 'themeHigh';
		} elseif ( defined( 'PPOM_VERSION' ) ) {
			$this->source = 'ppom';
		}

		return $this->source;
	}

	// ─── Official WC Product Add-Ons ───────────────────────

	/**
	 * Load add-ons from WooCommerce Product Add-Ons (official).
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}>
	 */
	private function load_official_addons( int $product_id ): array {
		$raw = get_post_meta( $product_id, '_product_addons', true );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$addons = [];

		foreach ( $raw as $group ) {
			if ( ! is_array( $group ) || empty( $group['name'] ) ) {
				continue;
			}

			$options       = [];
			$group_options = $group['options'] ?? [];

			if ( is_array( $group_options ) ) {
				foreach ( $group_options as $opt ) {
					if ( ! is_array( $opt ) ) {
						continue;
					}
					$options[] = [
						'label' => (string) ( $opt['label'] ?? '' ),
						'price' => (float) ( $opt['price'] ?? 0 ),
					];
				}
			}

			$addons[] = [
				'name'    => (string) $group['name'],
				'type'    => (string) ( $group['type'] ?? 'custom_text' ),
				'options' => $options,
			];
		}

		return $addons;
	}

	// ─── ThemeHigh Product Add-Ons (THWEPO) ────────────────

	/**
	 * Load add-ons from ThemeHigh Product Add-Ons.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}>
	 */
	private function load_theme_high_addons( int $product_id ): array {
		$raw = get_post_meta( $product_id, 'thwepo_custom_fields', true );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$addons = [];

		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}

			$options    = [];
			$raw_values = $field['options_json'] ?? $field['value'] ?? '';

			if ( is_string( $raw_values ) && '' !== $raw_values ) {
				$decoded = json_decode( $raw_values, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $opt ) {
						$options[] = [
							'label' => (string) ( $opt['text'] ?? $opt['label'] ?? '' ),
							'price' => (float) ( $opt['price'] ?? 0 ),
						];
					}
				}
			} elseif ( is_array( $raw_values ) ) {
				foreach ( $raw_values as $opt ) {
					if ( ! is_array( $opt ) ) {
						continue;
					}
					$options[] = [
						'label' => (string) ( $opt['text'] ?? $opt['label'] ?? '' ),
						'price' => (float) ( $opt['price'] ?? 0 ),
					];
				}
			}

			$addons[] = [
				'name'    => (string) ( $field['title'] ?? $field['name'] ),
				'type'    => (string) ( $field['type'] ?? 'text' ),
				'options' => $options,
			];
		}

		return $addons;
	}

	// ─── PPOM for WooCommerce ──────────────────────────────

	/**
	 * Load add-ons from PPOM for WooCommerce.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}>
	 */
	private function load_ppom_addons( int $product_id ): array {
		$meta_id = get_post_meta( $product_id, '_ppom_product_meta_id', true );

		if ( empty( $meta_id ) ) {
			return [];
		}

		$raw = get_post_meta( (int) $meta_id, 'ppom_product_meta', true );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$addons = [];

		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) || empty( $field['title'] ) ) {
				continue;
			}

			$options = [];
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $opt ) {
					if ( ! is_array( $opt ) ) {
						continue;
					}
					$options[] = [
						'label' => (string) ( $opt['option'] ?? '' ),
						'price' => (float) ( $opt['price'] ?? 0 ),
					];
				}
			}

			$addons[] = [
				'name'    => (string) $field['title'],
				'type'    => (string) ( $field['type'] ?? 'text' ),
				'options' => $options,
			];
		}

		return $addons;
	}

	// ─── Format as product attributes ──────────────────────

	/**
	 * Format add-ons as Odoo product attribute lines.
	 *
	 * Creates attribute + attribute values, then formats as One2many
	 * tuples for product.template.attribute.line.
	 *
	 * @param array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}> $addons          Normalized add-ons.
	 * @param int                                                                                                     $product_tmpl_id Odoo product.template ID.
	 * @return array<string, mixed> Odoo-ready data with attribute_line_ids.
	 */
	public function format_as_attributes( array $addons, int $product_tmpl_id ): array {
		$attribute_lines = [];

		foreach ( $addons as $addon ) {
			if ( empty( $addon['options'] ) ) {
				continue;
			}

			$value_ids = [];
			foreach ( $addon['options'] as $opt ) {
				$value_ids[] = [
					'name'  => $opt['label'],
					'price' => $opt['price'],
				];
			}

			$attribute_lines[] = [
				0,
				0,
				[
					'attribute_id' => [
						'name' => $addon['name'],
					],
					'value_ids'    => array_map(
						fn( $v ) => [ 0, 0, $v ],
						$value_ids
					),
				],
			];
		}

		return [
			'product_tmpl_id'    => $product_tmpl_id,
			'attribute_line_ids' => $attribute_lines,
		];
	}

	// ─── Format as BOM lines ───────────────────────────────

	/**
	 * Format add-ons as BOM lines for mrp.bom.
	 *
	 * Each add-on option becomes a BOM line component. Uses the addon
	 * label as product name reference (resolved later by Odoo or via
	 * optional products).
	 *
	 * @param array<int, array{name: string, type: string, options: array<int, array{label: string, price: float}>}> $addons          Normalized add-ons.
	 * @param int                                                                                                     $product_tmpl_id Odoo product.template ID.
	 * @return array<string, mixed> Odoo-ready BOM data.
	 */
	public function format_as_bom_lines( array $addons, int $product_tmpl_id ): array {
		$bom_lines = [];

		foreach ( $addons as $addon ) {
			foreach ( $addon['options'] as $opt ) {
				$bom_lines[] = [
					0,
					0,
					[
						'product_id'   => false,
						'product_qty'  => 1,
						'bom_product_template_attribute_value_ids' => [],
						'_addon_name'  => $addon['name'],
						'_option_name' => $opt['label'],
					],
				];
			}
		}

		return [
			'product_tmpl_id' => $product_tmpl_id,
			'type'            => 'phantom',
			'product_qty'     => 1,
			'bom_line_ids'    => $bom_lines,
		];
	}
}
