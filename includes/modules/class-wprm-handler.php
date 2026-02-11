<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Recipe Maker Handler — data access for recipes.
 *
 * Loads WPRM recipe data and formats it for Odoo product.product.
 *
 * Called by WPRM_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class WPRM_Handler {

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

	// ─── Load recipe ──────────────────────────────────────

	/**
	 * Load a WP Recipe Maker recipe as a service product.
	 *
	 * Reads the wprm_recipe CPT and associated meta fields to build
	 * a description containing the summary, times, and servings info.
	 *
	 * @param int $post_id WPRM recipe post ID.
	 * @return array<string, mixed> Recipe data for field mapping, or empty if not found.
	 */
	public function load_recipe( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'wprm_recipe' !== $post->post_type ) {
			$this->logger->warning( 'WP Recipe Maker recipe not found.', [ 'post_id' => $post_id ] );
			return [];
		}

		$summary = wp_strip_all_tags( (string) get_post_meta( $post_id, 'wprm_summary', true ) );
		$cost    = (string) get_post_meta( $post_id, 'wprm_cost', true );

		return [
			'recipe_name' => $post->post_title,
			'description' => $this->build_description( $post_id, $summary ),
			'list_price'  => '' !== $cost ? (float) $cost : 0.0,
			'type'        => 'service',
		];
	}

	/**
	 * Build a plain-text description from recipe meta.
	 *
	 * Format:
	 *   {summary}
	 *
	 *   Servings: 4 | Prep: 15min | Cook: 30min | Total: 45min
	 *
	 * Each part is omitted if the corresponding meta is empty.
	 *
	 * @param int    $post_id Recipe post ID.
	 * @param string $summary Plain-text summary.
	 * @return string
	 */
	private function build_description( int $post_id, string $summary ): string {
		$parts = [];

		$servings      = (string) get_post_meta( $post_id, 'wprm_servings', true );
		$servings_unit = (string) get_post_meta( $post_id, 'wprm_servings_unit', true );
		$prep_time     = (string) get_post_meta( $post_id, 'wprm_prep_time', true );
		$cook_time     = (string) get_post_meta( $post_id, 'wprm_cook_time', true );
		$total_time    = (string) get_post_meta( $post_id, 'wprm_total_time', true );

		if ( '' !== $servings ) {
			$label = $servings_unit ? $servings . ' ' . $servings_unit : $servings;
			/* translators: %s: number of servings (e.g. "4" or "4 persons"). */
			$parts[] = sprintf( __( 'Servings: %s', 'wp4odoo' ), $label );
		}

		if ( '' !== $prep_time ) {
			/* translators: %s: preparation time in minutes (e.g. "15min"). */
			$parts[] = sprintf( __( 'Prep: %smin', 'wp4odoo' ), $prep_time );
		}

		if ( '' !== $cook_time ) {
			/* translators: %s: cooking time in minutes (e.g. "30min"). */
			$parts[] = sprintf( __( 'Cook: %smin', 'wp4odoo' ), $cook_time );
		}

		if ( '' !== $total_time ) {
			/* translators: %s: total time in minutes (e.g. "45min"). */
			$parts[] = sprintf( __( 'Total: %smin', 'wp4odoo' ), $total_time );
		}

		$info_line = implode( ' | ', $parts );

		if ( '' !== $summary && '' !== $info_line ) {
			return $summary . "\n\n" . $info_line;
		}

		if ( '' !== $summary ) {
			return $summary;
		}

		return $info_line;
	}
}
