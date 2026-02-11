<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EDD Download Handler — download (product) data access.
 *
 * Centralises load, save, and delete operations for EDD downloads.
 * Called by EDD_Module via its load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   1.9.9
 */
class EDD_Download_Handler {

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

	// ─── Load ────────────────────────────────────────────────

	/**
	 * Load EDD download data.
	 *
	 * @param int $wp_id Download post ID.
	 * @return array
	 */
	public function load( int $wp_id ): array {
		$post = get_post( $wp_id );
		if ( ! $post || 'download' !== $post->post_type ) {
			return [];
		}

		return [
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'_edd_price'   => get_post_meta( $wp_id, 'edd_price', true ) ?: '0.00',
		];
	}

	// ─── Save ────────────────────────────────────────────────

	/**
	 * Save download data.
	 *
	 * @param array $data  Mapped download data.
	 * @param int   $wp_id Existing post ID (0 to create new).
	 * @return int Post ID or 0 on failure.
	 */
	public function save( array $data, int $wp_id = 0 ): int {
		$post_data = [
			'post_type'   => 'download',
			'post_status' => 'publish',
		];

		if ( isset( $data['post_title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['post_title'] );
		}

		if ( isset( $data['post_content'] ) ) {
			$post_data['post_content'] = $data['post_content'];
		}

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			if ( ! isset( $post_data['post_title'] ) ) {
				$post_data['post_title'] = __( 'Download', 'wp4odoo' );
			}
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to save EDD download.',
				[ 'error' => $result->get_error_message() ]
			);
			return 0;
		}

		$post_id = (int) $result;

		if ( isset( $data['_edd_price'] ) ) {
			update_post_meta( $post_id, 'edd_price', sanitize_text_field( (string) $data['_edd_price'] ) );
		}

		return $post_id;
	}

	// ─── Delete ──────────────────────────────────────────────

	/**
	 * Delete an EDD download.
	 *
	 * @param int $wp_id Download post ID.
	 * @return bool
	 */
	public function delete( int $wp_id ): bool {
		$post = get_post( $wp_id );
		if ( $post ) {
			wp_delete_post( $wp_id, true );
			return true;
		}
		return false;
	}
}
