<?php
/**
 * LifterLMS class and function stubs for PHPUnit tests.
 *
 * LifterLMS uses CPTs (llms_course, llms_membership, llms_order)
 * and a custom `wp_lifterlms_user_postmeta` table for enrollments.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_llms_orders']     = [];
$GLOBALS['_llms_enrollments'] = [];

// ─── LLMS_Order ────────────────────────────────────────

if ( ! class_exists( 'LLMS_Order' ) ) {

	/**
	 * LLMS_Order stub — represents a LifterLMS order (CPT llms_order).
	 */
	class LLMS_Order {

		/**
		 * Order post ID.
		 *
		 * @var int
		 */
		private int $id = 0;

		/**
		 * Order data (post meta equivalent).
		 *
		 * @var array<string, mixed>
		 */
		private array $data = [];

		/**
		 * Constructor — loads from global store or WP post.
		 *
		 * @param int $post_id Order post ID.
		 */
		public function __construct( int $post_id = 0 ) {
			$this->id = $post_id;
			if ( $post_id > 0 && isset( $GLOBALS['_llms_orders'][ $post_id ] ) ) {
				$this->data = $GLOBALS['_llms_orders'][ $post_id ];
			}
		}

		/**
		 * Get the order ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Get the product (course/membership) ID.
		 *
		 * @return int
		 */
		public function get_product_id(): int {
			return (int) ( $this->data['product_id'] ?? 0 );
		}

		/**
		 * Get the customer (WP user) ID.
		 *
		 * @return int
		 */
		public function get_customer_id(): int {
			return (int) ( $this->data['user_id'] ?? 0 );
		}

		/**
		 * Get the order total.
		 *
		 * @return float
		 */
		public function get_total(): float {
			return (float) ( $this->data['total'] ?? 0 );
		}

		/**
		 * Get the order status (with llms- prefix).
		 *
		 * @return string
		 */
		public function get_status(): string {
			return $this->data['status'] ?? '';
		}

		/**
		 * Get the order date.
		 *
		 * @return string
		 */
		public function get_date(): string {
			return $this->data['date'] ?? '';
		}

		/**
		 * Get the payment gateway.
		 *
		 * @return string
		 */
		public function get_payment_gateway(): string {
			return $this->data['gateway'] ?? '';
		}

		/**
		 * Get a property value.
		 *
		 * @param string $key Property name.
		 * @return mixed
		 */
		public function get( string $key ) {
			return $this->data[ $key ] ?? '';
		}

		/**
		 * Set a property value (test helper).
		 *
		 * @param string $key   Property name.
		 * @param mixed  $value Property value.
		 * @return void
		 */
		public function set( string $key, $value ): void {
			$this->data[ $key ] = $value;
		}
	}
}

// ─── LLMS_Student ──────────────────────────────────────

if ( ! class_exists( 'LLMS_Student' ) ) {

	/**
	 * LLMS_Student stub — wraps a WordPress user with LMS student data.
	 */
	class LLMS_Student {

		/**
		 * WordPress user ID.
		 *
		 * @var int
		 */
		private int $id = 0;

		/**
		 * Constructor.
		 *
		 * @param int $user_id WordPress user ID.
		 */
		public function __construct( int $user_id = 0 ) {
			$this->id = $user_id;
		}

		/**
		 * Get the student ID (WP user ID).
		 *
		 * @return int
		 */
		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Check if enrolled in a product.
		 *
		 * @param int $product_id Course or membership ID.
		 * @return bool
		 */
		public function is_enrolled( int $product_id ): bool {
			$key = $this->id . '_' . $product_id;
			return isset( $GLOBALS['_llms_enrollments'][ $key ] );
		}

		/**
		 * Get enrollment status.
		 *
		 * @param int $product_id Course or membership ID.
		 * @return string Status or empty string.
		 */
		public function get_enrollment_status( int $product_id ): string {
			$key = $this->id . '_' . $product_id;
			return $GLOBALS['_llms_enrollments'][ $key ]['status'] ?? '';
		}

		/**
		 * Get enrollment date.
		 *
		 * @param int    $product_id Course or membership ID.
		 * @param string $format     Date format.
		 * @return string
		 */
		public function get_enrollment_date( int $product_id, string $format = 'Y-m-d' ): string {
			$key = $this->id . '_' . $product_id;
			return $GLOBALS['_llms_enrollments'][ $key ]['date'] ?? '';
		}
	}
}

// ─── Global functions ───────────────────────────────────

if ( ! function_exists( 'llms_get_student' ) ) {
	/**
	 * Get a student by user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return LLMS_Student|false
	 */
	function llms_get_student( int $user_id = 0 ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return new LLMS_Student( $user_id );
	}
}

if ( ! function_exists( 'llms_get_enrolled_students' ) ) {
	/**
	 * Get enrolled students for a product.
	 *
	 * @param int    $product_id Course or membership ID.
	 * @param string $status     Enrollment status filter.
	 * @return array<int> Array of user IDs.
	 */
	function llms_get_enrolled_students( int $product_id = 0, string $status = 'enrolled' ): array {
		return [];
	}
}
