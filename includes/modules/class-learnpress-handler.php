<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\CPT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnPress Handler — data access for courses, orders, and enrollments.
 *
 * Loads LearnPress entities and formats data for Odoo push.
 * Orders are pre-formatted as Odoo `account.move` data (invoice).
 * Enrollments are formatted as Odoo `sale.order` data.
 *
 * @package WP4Odoo
 * @since   3.7.0
 */
class LearnPress_Handler extends LMS_Handler_Base {

	// ─── Load course ───────────────────────────────────────

	/**
	 * Load a LearnPress course.
	 *
	 * @param int $course_id Course post ID (CPT lp_course).
	 * @return array<string, mixed> Course data, or empty if not found.
	 */
	public function load_course( int $course_id ): array {
		$post = get_post( $course_id );
		if ( ! $post || 'lp_course' !== $post->post_type ) {
			$this->logger->warning( 'LearnPress course not found.', [ 'course_id' => $course_id ] );
			return [];
		}

		$price = (float) get_post_meta( $course_id, '_lp_price', true );

		return [
			'title'       => $post->post_title,
			'description' => wp_strip_all_tags( $post->post_content ),
			'list_price'  => $price,
			'type'        => 'service',
		];
	}

	// ─── Load order ────────────────────────────────────────

	/**
	 * Load a LearnPress order as raw data.
	 *
	 * @param int $order_id Order post ID (CPT lp_order).
	 * @return array<string, mixed> Order data, or empty if not found.
	 */
	public function load_order( int $order_id ): array {
		$post = get_post( $order_id );
		if ( ! $post || 'lp_order' !== $post->post_type ) {
			$this->logger->warning( 'LearnPress order not found.', [ 'order_id' => $order_id ] );
			return [];
		}

		$meta = get_post_meta( $order_id );

		return [
			'order_id'   => $order_id,
			'course_id'  => (int) ( $meta['_lp_course_id'][0] ?? 0 ),
			'user_id'    => (int) ( $meta['_user_id'][0] ?? $post->post_author ),
			'amount'     => (float) ( $meta['_order_total'][0] ?? 0 ),
			'currency'   => (string) ( $meta['_order_currency'][0] ?? 'USD' ),
			'created_at' => $post->post_date_gmt,
		];
	}

	// ─── Load enrollment ───────────────────────────────────

	/**
	 * Load a LearnPress enrollment (user + course access).
	 *
	 * Queries the learnpress_user_items table via $wpdb.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id LearnPress course ID.
	 * @return array<string, mixed> Enrollment data, or empty if not found.
	 */
	public function load_enrollment( int $user_id, int $course_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'User not found for enrollment.', [ 'user_id' => $user_id ] );
			return [];
		}

		$post = get_post( $course_id );
		if ( ! $post || 'lp_course' !== $post->post_type ) {
			$this->logger->warning( 'Course not found for enrollment.', [ 'course_id' => $course_id ] );
			return [];
		}

		global $wpdb;

		$table = $wpdb->prefix . 'learnpress_user_items';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course' ORDER BY user_item_id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$course_id
			),
			ARRAY_A
		);

		$enrolled_date = '';
		if ( $row ) {
			$enrolled_date = $row['start_time'] ?? '';
		}

		return [
			'user_id'    => $user_id,
			'course_id'  => $course_id,
			'user_email' => $user->user_email,
			'user_name'  => $user->display_name,
			'date'       => $enrolled_date ? substr( $enrolled_date, 0, 10 ) : gmdate( 'Y-m-d' ),
		];
	}

	// ─── Format invoice ────────────────────────────────────

	/**
	 * Format order data as an Odoo account.move (invoice).
	 *
	 * @param array<string, mixed> $data           Order data from load_order().
	 * @param int                  $product_odoo_id Resolved Odoo product.product ID.
	 * @param int                  $partner_id      Resolved Odoo partner ID.
	 * @param bool                 $auto_post       Whether to auto-post the invoice.
	 * @return array<string, mixed> Odoo account.move data.
	 */
	public function format_invoice( array $data, int $product_odoo_id, int $partner_id, bool $auto_post ): array {
		return $this->build_invoice(
			$data['course_id'] ?? 0,
			$product_odoo_id,
			$partner_id,
			(float) ( $data['amount'] ?? 0 ),
			substr( $data['created_at'] ?? '', 0, 10 ),
			sprintf( 'LP-ORDER-%d', $data['order_id'] ?? 0 ),
			__( 'LearnPress course', 'wp4odoo' ),
			$auto_post
		);
	}

	// ─── Format sale order ─────────────────────────────────

	/**
	 * Format enrollment data as an Odoo sale.order.
	 *
	 * @param int    $product_odoo_id Resolved Odoo product.product ID.
	 * @param int    $partner_id      Resolved Odoo partner ID.
	 * @param string $date            Enrollment date (Y-m-d).
	 * @param string $course_name     Course name for the order line.
	 * @return array<string, mixed> Odoo sale.order data.
	 */
	public function format_sale_order( int $product_odoo_id, int $partner_id, string $date, string $course_name = '' ): array {
		return $this->build_sale_order( $product_odoo_id, $partner_id, $date, $course_name ?: __( 'LearnPress enrollment', 'wp4odoo' ) );
	}

	// ─── Parse course from Odoo ───────────────────────────

	/**
	 * Parse Odoo product data into WordPress course format.
	 *
	 * @param array<string, mixed> $odoo_data Odoo record data.
	 * @return array<string, mixed> WordPress course data.
	 */
	public function parse_course_from_odoo( array $odoo_data ): array {
		return CPT_Helper::parse_service_product( $odoo_data );
	}

	/**
	 * Save course data to a lp_course CPT post.
	 *
	 * @param array<string, mixed> $data  Parsed course data.
	 * @param int                  $wp_id Existing post ID (0 to create new).
	 * @return int The post ID, or 0 on failure.
	 */
	public function save_course( array $data, int $wp_id = 0 ): int {
		$post_id = CPT_Helper::save_from_odoo( 'lp_course', $data, $wp_id, $this->logger );

		// Save price meta if present.
		if ( $post_id > 0 && isset( $data['list_price'] ) ) {
			update_post_meta( $post_id, '_lp_price', (string) $data['list_price'] );
		}

		return $post_id;
	}
}
