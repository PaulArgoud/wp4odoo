<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * myCRED Handler — data access for points and badges.
 *
 * Loads myCRED entity data, formats for Odoo, and provides save
 * methods for pull sync. Called by MyCRED_Module via its
 * load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class MyCRED_Handler {

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

	// ─── Points ────────────────────────────────────────────

	/**
	 * Load point balance data for a WordPress user.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $points_type myCRED points type slug.
	 * @return array<string, mixed> Points data, or empty array if user not found.
	 */
	public function load_points( int $user_id, string $points_type ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'myCRED: user not found.', [ 'user_id' => $user_id ] );
			return [];
		}

		$points = mycred_get_users_cred( $user_id, $points_type );

		return [
			'user_id' => $user_id,
			'email'   => $user->user_email,
			'name'    => $user->display_name,
			'points'  => $points,
		];
	}

	/**
	 * Save a point balance from Odoo to WordPress.
	 *
	 * Sets the myCRED points balance for the given user using
	 * add/subtract to reach the target balance.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $points      Target point balance.
	 * @param string $points_type myCRED points type slug.
	 * @return int The user ID on success, 0 on failure.
	 */
	public function save_points( int $user_id, int $points, string $points_type ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$current = mycred_get_users_cred( $user_id, $points_type );
		$diff    = $points - $current;

		if ( $diff > 0 ) {
			mycred_add( 'odoo_sync', $user_id, $diff, __( 'Odoo sync', 'wp4odoo' ), '', '', $points_type );
		} elseif ( $diff < 0 ) {
			mycred_subtract( 'odoo_sync', $user_id, abs( $diff ), __( 'Odoo sync', 'wp4odoo' ), '', '', $points_type );
		}

		$this->logger->info(
			'Set myCRED points from Odoo.',
			[
				'user_id' => $user_id,
				'points'  => $points,
			]
		);

		return $user_id;
	}

	// ─── Badges ────────────────────────────────────────────

	/**
	 * Load badge data.
	 *
	 * @param int $badge_id WordPress post ID.
	 * @return array<string, mixed> Badge data, or empty array if not found.
	 */
	public function load_badge( int $badge_id ): array {
		if ( $badge_id <= 0 ) {
			return [];
		}

		$post = get_post( $badge_id );
		if ( ! $post ) {
			$this->logger->warning( 'myCRED: badge not found.', [ 'id' => $badge_id ] );
			return [];
		}

		$post_type = get_post_type( $badge_id );
		if ( 'mycred_badge' !== $post_type ) {
			$this->logger->warning(
				'myCRED: post is not a badge type.',
				[
					'id'        => $badge_id,
					'post_type' => $post_type,
				]
			);
			return [];
		}

		return [
			'id'          => $badge_id,
			'title'       => $post->post_title,
			'description' => $post->post_content,
		];
	}

	// ─── Formatting ────────────────────────────────────────

	/**
	 * Format loyalty card data for Odoo.
	 *
	 * @param int $points     Point balance.
	 * @param int $partner_id Odoo partner ID.
	 * @param int $program_id Odoo loyalty.program ID.
	 * @return array<string, mixed> Formatted for Odoo loyalty.card.
	 */
	public function format_loyalty_card( int $points, int $partner_id, int $program_id ): array {
		return [
			'partner_id' => $partner_id,
			'program_id' => $program_id,
			'points'     => (float) $points,
		];
	}

	/**
	 * Format badge data as an Odoo product.
	 *
	 * @param array<string, mixed> $data Badge data from load_badge().
	 * @return array<string, mixed> Formatted for Odoo product.template.
	 */
	public function format_badge_product( array $data ): array {
		return [
			'name'             => $data['title'] ?? '',
			'description_sale' => $data['description'] ?? '',
			'type'             => 'service',
			'sale_ok'          => false,
			'purchase_ok'      => false,
		];
	}
}
