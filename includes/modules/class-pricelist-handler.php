<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles pricelist price import from Odoo (product.pricelist → WC sale_price).
 *
 * Fetches computed prices from an Odoo pricelist and applies them as
 * WooCommerce sale prices on products and variations. Called by
 * WooCommerce_Module after a product or variant pull when pricelist
 * sync is enabled.
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class Pricelist_Handler {

	/**
	 * WordPress transient key prefix for cached pricelist prices.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'wp4odoo_pl_';

	/**
	 * Cache time-to-live in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Post meta key to track pricelist-managed sale prices.
	 *
	 * @var string
	 */
	private const PRICE_META = '_wp4odoo_pricelist_price';

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Closure returning the Odoo_Client.
	 *
	 * @var \Closure
	 */
	private \Closure $client_fn;

	/**
	 * Odoo pricelist ID to use (0 = disabled).
	 *
	 * @var int
	 */
	private int $pricelist_id;

	/**
	 * Exchange rate service for currency conversion.
	 *
	 * @var Exchange_Rate_Service|null
	 */
	private ?Exchange_Rate_Service $rate_service;

	/**
	 * Whether currency conversion is enabled.
	 *
	 * @var bool
	 */
	private bool $convert_currency;

	/**
	 * Flag indicating whether the configured pricelist is valid.
	 *
	 * Set to false after the first Odoo error to avoid repeated failures.
	 *
	 * @var bool
	 */
	private bool $pricelist_valid = true;

	/**
	 * Constructor.
	 *
	 * @param Logger                     $logger           Logger instance.
	 * @param \Closure                   $client_fn        Closure returning \WP4Odoo\API\Odoo_Client.
	 * @param int                        $pricelist_id     Odoo pricelist ID (0 = disabled).
	 * @param Exchange_Rate_Service|null $rate_service     Exchange rate service (null to disable conversion).
	 * @param bool                       $convert_currency Whether to convert prices on currency mismatch.
	 */
	public function __construct( Logger $logger, \Closure $client_fn, int $pricelist_id = 0, ?Exchange_Rate_Service $rate_service = null, bool $convert_currency = false ) {
		$this->logger           = $logger;
		$this->client_fn        = $client_fn;
		$this->pricelist_id     = $pricelist_id;
		$this->rate_service     = $rate_service;
		$this->convert_currency = $convert_currency;
	}

	/**
	 * Apply pricelist price to a WooCommerce product.
	 *
	 * For simple products: fetches the first product.product variant ID
	 * for the template, computes the pricelist price, and sets it as the
	 * WC sale_price (only if lower than regular_price).
	 *
	 * @param int $wp_product_id    WC product ID.
	 * @param int $odoo_template_id Odoo product.template ID.
	 * @return bool True if a price was applied.
	 */
	public function apply_pricelist_price( int $wp_product_id, int $odoo_template_id ): bool {
		if ( 0 === $this->pricelist_id || ! $this->pricelist_valid ) {
			return false;
		}

		$product = wc_get_product( $wp_product_id );
		if ( ! $product ) {
			return false;
		}

		// Variable products: skip (variations handle their own prices).
		if ( $product instanceof \WC_Product_Variable ) {
			return false;
		}

		// Find the product.product ID for this template.
		try {
			$client      = ( $this->client_fn )();
			$variant_ids = $client->search(
				'product.product',
				[ [ 'product_tmpl_id', '=', $odoo_template_id ] ]
			);
		} catch ( \Throwable $e ) {
			$this->mark_invalid( $e->getMessage() );
			return false;
		}

		if ( empty( $variant_ids ) ) {
			return false;
		}

		$odoo_product_id = (int) $variant_ids[0];
		$pricelist_price = $this->get_computed_price( $odoo_product_id );

		if ( null === $pricelist_price ) {
			return false;
		}

		return $this->set_sale_price( $product, $pricelist_price, $odoo_product_id );
	}

	/**
	 * Apply pricelist price to a single WooCommerce variation.
	 *
	 * @param int $wp_variation_id  WC variation ID.
	 * @param int $odoo_variant_id  Odoo product.product ID.
	 * @return bool True if a price was applied.
	 */
	public function apply_pricelist_price_to_variation( int $wp_variation_id, int $odoo_variant_id ): bool {
		if ( 0 === $this->pricelist_id || ! $this->pricelist_valid ) {
			return false;
		}

		$variation = wc_get_product( $wp_variation_id );
		if ( ! $variation ) {
			return false;
		}

		$pricelist_price = $this->get_computed_price( $odoo_variant_id );

		if ( null === $pricelist_price ) {
			return false;
		}

		return $this->set_sale_price( $variation, $pricelist_price, $odoo_variant_id );
	}

	/**
	 * Clear pricelist-managed sale price from a product or variation.
	 *
	 * Only clears if the product has the pricelist tracking meta,
	 * indicating the sale price was set by this handler.
	 *
	 * @param int $wp_product_id WC product or variation ID.
	 * @return void
	 */
	public function clear_pricelist_price( int $wp_product_id ): void {
		$existing = get_post_meta( $wp_product_id, self::PRICE_META, true );
		if ( '' === $existing ) {
			return;
		}

		$product = wc_get_product( $wp_product_id );
		if ( $product ) {
			$product->set_sale_price( '' );
			$product->save();
		}

		delete_post_meta( $wp_product_id, self::PRICE_META );

		$this->logger->info(
			'Cleared pricelist sale price.',
			[ 'wp_product_id' => $wp_product_id ]
		);
	}

	/**
	 * Get the computed pricelist price for a product.product.
	 *
	 * Checks transient cache first, then calls Odoo's
	 * get_product_price method on the configured pricelist.
	 *
	 * @param int $product_product_id Odoo product.product ID.
	 * @return float|null Computed price, or null on failure/cache miss error.
	 */
	public function get_computed_price( int $product_product_id ): ?float {
		if ( 0 === $this->pricelist_id || ! $this->pricelist_valid ) {
			return null;
		}

		$transient_key = self::TRANSIENT_PREFIX . $this->pricelist_id . '_' . $product_product_id;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return (float) $cached;
		}

		try {
			$client = ( $this->client_fn )();
			$price  = $client->execute(
				'product.pricelist',
				'get_product_price',
				[ [ $this->pricelist_id ], $product_product_id, 1.0, false ]
			);
		} catch ( \Throwable $e ) {
			$this->mark_invalid( $e->getMessage() );
			return null;
		}

		if ( ! is_numeric( $price ) ) {
			$this->logger->warning(
				'Unexpected pricelist price value.',
				[
					'product_id'   => $product_product_id,
					'pricelist_id' => $this->pricelist_id,
					'value'        => $price,
				]
			);
			return null;
		}

		$result = round( (float) $price, 2 );
		set_transient( $transient_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Determine whether a pricelist price should be applied as sale price.
	 *
	 * Only applies if the pricelist price is strictly lower than the
	 * regular price. Equal or higher prices are not applied.
	 *
	 * @param float $pricelist_price Computed pricelist price.
	 * @param float $regular_price   WC product regular price.
	 * @return bool True if pricelist price should be set as sale price.
	 */
	public function should_apply_price( float $pricelist_price, float $regular_price ): bool {
		return $pricelist_price > 0.0 && $regular_price > 0.0 && $pricelist_price < $regular_price;
	}

	/**
	 * Set the sale price on a WC product/variation.
	 *
	 * Handles currency guard check and optional exchange rate conversion.
	 *
	 * @param \WC_Product $product          WC product or variation.
	 * @param float       $pricelist_price  Computed pricelist price.
	 * @param int         $odoo_product_id  Odoo product.product ID.
	 * @return bool True if price was applied.
	 */
	private function set_sale_price( \WC_Product $product, float $pricelist_price, int $odoo_product_id ): bool {
		// Check currency guard: the pricelist price is in the pricelist's currency.
		$product_currency = get_post_meta( $product->get_id(), '_wp4odoo_currency', true );

		if ( '' !== $product_currency ) {
			$guard = Currency_Guard::check( $product_currency );

			if ( $guard['mismatch'] ) {
				if ( $this->convert_currency && null !== $this->rate_service ) {
					$converted = $this->rate_service->convert( $pricelist_price, $guard['odoo_currency'], $guard['wc_currency'] );

					if ( null !== $converted ) {
						$pricelist_price = $converted;
					} else {
						$this->logger->warning(
							'Pricelist price currency conversion failed, skipping.',
							[
								'odoo_product_id' => $odoo_product_id,
								'from'            => $guard['odoo_currency'],
								'to'              => $guard['wc_currency'],
							]
						);
						return false;
					}
				} else {
					$this->logger->warning(
						'Pricelist price currency mismatch, skipping.',
						[
							'odoo_product_id' => $odoo_product_id,
							'odoo_currency'   => $guard['odoo_currency'],
							'wc_currency'     => $guard['wc_currency'],
						]
					);
					return false;
				}
			}
		}

		$regular_price = (float) $product->get_regular_price();

		if ( ! $this->should_apply_price( $pricelist_price, $regular_price ) ) {
			// Pricelist price is not a discount — clear any existing pricelist-managed sale price.
			$existing_meta = get_post_meta( $product->get_id(), self::PRICE_META, true );
			if ( '' !== $existing_meta ) {
				$product->set_sale_price( '' );
				$product->save();
				delete_post_meta( $product->get_id(), self::PRICE_META );
			}
			return false;
		}

		$product->set_sale_price( (string) $pricelist_price );
		$product->save();

		update_post_meta( $product->get_id(), self::PRICE_META, (string) $pricelist_price );

		$this->logger->info(
			'Pricelist price applied.',
			[
				'wp_product_id'   => $product->get_id(),
				'odoo_id'         => $odoo_product_id,
				'pricelist_id'    => $this->pricelist_id,
				'regular_price'   => $regular_price,
				'pricelist_price' => $pricelist_price,
			]
		);

		return true;
	}

	/**
	 * Mark the pricelist as invalid to prevent repeated failed API calls.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	private function mark_invalid( string $error ): void {
		$this->pricelist_valid = false;

		$this->logger->warning(
			'Pricelist API call failed, disabling for this batch.',
			[
				'pricelist_id' => $this->pricelist_id,
				'error'        => $error,
			]
		);
	}
}
