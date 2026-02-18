<?php
/**
 * myCRED class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global stores ──────────────────────────────────────

$GLOBALS['_mycred_points'] = [];
$GLOBALS['_mycred_badges'] = [];

// ─── Constants ──────────────────────────────────────────

if ( ! defined( 'myCRED_VERSION' ) ) {
	define( 'myCRED_VERSION', '2.6.4' );
}

// ─── Core functions ─────────────────────────────────────

if ( ! function_exists( 'mycred' ) ) {
	/**
	 * Return the myCRED singleton stub.
	 *
	 * @return stdClass
	 */
	function mycred(): stdClass {
		static $instance;
		if ( ! $instance ) {
			$instance = new stdClass();
		}
		return $instance;
	}
}

if ( ! function_exists( 'mycred_get_users_cred' ) ) {
	/**
	 * Get user points by type.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $points_type Points type slug.
	 * @return int Point balance.
	 */
	function mycred_get_users_cred( int $user_id, string $points_type = 'mycred_default' ): int {
		return (int) ( $GLOBALS['_mycred_points'][ $user_id ][ $points_type ] ?? 0 );
	}
}

if ( ! function_exists( 'mycred_add' ) ) {
	/**
	 * Add points to a user.
	 *
	 * @param string $ref         Reference string.
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $amount      Points to add.
	 * @param string $entry       Log entry.
	 * @param string $ref_id      Reference ID.
	 * @param string $data        Additional data.
	 * @param string $points_type Points type slug.
	 * @return bool
	 */
	function mycred_add( string $ref, int $user_id, int $amount, string $entry = '', string $ref_id = '', string $data = '', string $points_type = 'mycred_default' ): bool {
		if ( ! isset( $GLOBALS['_mycred_points'][ $user_id ] ) ) {
			$GLOBALS['_mycred_points'][ $user_id ] = [];
		}
		$current = $GLOBALS['_mycred_points'][ $user_id ][ $points_type ] ?? 0;
		$GLOBALS['_mycred_points'][ $user_id ][ $points_type ] = $current + $amount;
		return true;
	}
}

if ( ! function_exists( 'mycred_subtract' ) ) {
	/**
	 * Subtract points from a user.
	 *
	 * @param string $ref         Reference string.
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $amount      Points to subtract.
	 * @param string $entry       Log entry.
	 * @param string $ref_id      Reference ID.
	 * @param string $data        Additional data.
	 * @param string $points_type Points type slug.
	 * @return bool
	 */
	function mycred_subtract( string $ref, int $user_id, int $amount, string $entry = '', string $ref_id = '', string $data = '', string $points_type = 'mycred_default' ): bool {
		if ( ! isset( $GLOBALS['_mycred_points'][ $user_id ] ) ) {
			$GLOBALS['_mycred_points'][ $user_id ] = [];
		}
		$current = $GLOBALS['_mycred_points'][ $user_id ][ $points_type ] ?? 0;
		$GLOBALS['_mycred_points'][ $user_id ][ $points_type ] = max( 0, $current - $amount );
		return true;
	}
}
