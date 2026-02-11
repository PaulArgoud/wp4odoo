<?php
/**
 * LearnDash class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_learndash_prices'] = [];

// ─── LearnDash function stubs ──────────────────────────

if ( ! function_exists( 'learndash_get_course_price' ) ) {

	/**
	 * Get course price data.
	 *
	 * @param int $course_id Course post ID.
	 * @return array{type: string, price: string}
	 */
	function learndash_get_course_price( int $course_id = 0 ): array {
		if ( isset( $GLOBALS['_learndash_prices'][ $course_id ] ) ) {
			return $GLOBALS['_learndash_prices'][ $course_id ];
		}

		return [
			'type'  => 'free',
			'price' => '',
		];
	}
}

if ( ! function_exists( 'learndash_get_group_price' ) ) {

	/**
	 * Get group price data.
	 *
	 * @param int $group_id Group post ID.
	 * @return array{type: string, price: string}
	 */
	function learndash_get_group_price( int $group_id = 0 ): array {
		if ( isset( $GLOBALS['_learndash_prices'][ $group_id ] ) ) {
			return $GLOBALS['_learndash_prices'][ $group_id ];
		}

		return [
			'type'  => 'free',
			'price' => '',
		];
	}
}

if ( ! function_exists( 'learndash_get_setting' ) ) {

	/**
	 * Get a LearnDash setting value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Setting key.
	 * @return mixed
	 */
	function learndash_get_setting( int $post_id = 0, string $key = '' ) {
		return get_post_meta( $post_id, '_ld_' . $key, true );
	}
}

if ( ! function_exists( 'learndash_user_get_course_date' ) ) {

	/**
	 * Get the date a user enrolled in a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course post ID.
	 * @return string Date string (Y-m-d) or empty.
	 */
	function learndash_user_get_course_date( int $user_id = 0, int $course_id = 0 ): string {
		$key = "ld_course_{$course_id}_enrolled";
		$ts  = get_user_meta( $user_id, $key, true );

		if ( $ts ) {
			return gmdate( 'Y-m-d', (int) $ts );
		}

		return '';
	}
}
