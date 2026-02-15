<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\CPT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TutorLMS Handler — data access for courses, orders, and enrollments.
 *
 * Loads TutorLMS entities and formats data for Odoo push.
 * Orders are pre-formatted as Odoo `account.move` data (invoice).
 * Enrollments are formatted as Odoo `sale.order` data.
 *
 * Called by TutorLMS_Module via its load_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
class TutorLMS_Handler extends LMS_Handler_Base {

	// ─── Load course ───────────────────────────────────────

	/**
	 * Load a TutorLMS course.
	 *
	 * @param int $course_id Course post ID (CPT courses).
	 * @return array<string, mixed> Course data for field mapping, or empty if not found.
	 */
	public function load_course( int $course_id ): array {
		$post = get_post( $course_id );
		if ( ! $post || 'courses' !== $post->post_type ) {
			$this->logger->warning( 'TutorLMS course not found.', [ 'course_id' => $course_id ] );
			return [];
		}

		$price = (float) get_post_meta( $course_id, '_tutor_course_price', true );

		return [
			'title'       => $post->post_title,
			'description' => wp_strip_all_tags( $post->post_content ),
			'list_price'  => $price,
			'type'        => 'service',
		];
	}

	// ─── Load order ────────────────────────────────────────

	/**
	 * Load a TutorLMS order as raw data.
	 *
	 * Returns parsed order meta for resolution by the module.
	 * Tutor LMS 2.x+ uses its own order system.
	 *
	 * @param int $order_id Order post ID.
	 * @return array<string, mixed> Order data, or empty if not found.
	 */
	public function load_order( int $order_id ): array {
		$post = get_post( $order_id );
		if ( ! $post ) {
			$this->logger->warning( 'TutorLMS order not found.', [ 'order_id' => $order_id ] );
			return [];
		}

		$meta = get_post_meta( $order_id );

		return [
			'user_id'    => (int) ( $meta['_tutor_order_user_id'][0] ?? $post->post_author ),
			'amount'     => (float) ( $meta['_tutor_order_total'][0] ?? 0 ),
			'currency'   => strtoupper( $meta['_tutor_order_currency'][0] ?? 'USD' ),
			'created_at' => $post->post_date_gmt,
			'course_id'  => (int) ( $meta['_tutor_order_course_id'][0] ?? 0 ),
		];
	}

	// ─── Load enrollment ──────────────────────────────────

	/**
	 * Load a TutorLMS enrollment (user + course access).
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id TutorLMS course ID.
	 * @return array<string, mixed> Enrollment data, or empty if not found.
	 */
	public function load_enrollment( int $user_id, int $course_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			$this->logger->warning( 'User not found for enrollment.', [ 'user_id' => $user_id ] );
			return [];
		}

		$post = get_post( $course_id );
		if ( ! $post || 'courses' !== $post->post_type ) {
			$this->logger->warning( 'Course not found for enrollment.', [ 'course_id' => $course_id ] );
			return [];
		}

		return [
			'user_id'    => $user_id,
			'course_id'  => $course_id,
			'user_email' => $user->user_email,
			'user_name'  => $user->display_name,
			'date'       => gmdate( 'Y-m-d' ),
		];
	}

	// ─── Format invoice ────────────────────────────────────

	/**
	 * Format order data as an Odoo account.move (invoice).
	 *
	 * Returns pre-formatted Odoo data with invoice_line_ids as
	 * One2many tuples [(0, 0, {...})].
	 *
	 * @param array<string, mixed> $data            Order data from load_order().
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
			sprintf( 'TUTOR-ORD-%d', $data['order_id'] ?? 0 ),
			__( 'TutorLMS course', 'wp4odoo' ),
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
		return $this->build_sale_order( $product_odoo_id, $partner_id, $date, $course_name ?: __( 'TutorLMS enrollment', 'wp4odoo' ) );
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
	 * Save course data to a courses CPT post.
	 *
	 * @param array<string, mixed> $data  Parsed course data.
	 * @param int                  $wp_id Existing post ID (0 to create new).
	 * @return int The post ID, or 0 on failure.
	 */
	public function save_course( array $data, int $wp_id = 0 ): int {
		return CPT_Helper::save_from_odoo( 'courses', $data, $wp_id, $this->logger );
	}
}
