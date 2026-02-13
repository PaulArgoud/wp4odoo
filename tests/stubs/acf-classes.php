<?php
/**
 * ACF (Advanced Custom Fields) stubs for unit testing.
 *
 * Provides minimal function stubs using a global store.
 *
 * @package WP4Odoo\Tests
 */

if ( ! defined( 'ACF_MAJOR_VERSION' ) ) {
	define( 'ACF_MAJOR_VERSION', 6 );
}

$GLOBALS['_acf_fields'] = [];

if ( ! class_exists( 'ACF' ) ) {
	/**
	 * Minimal ACF class stub.
	 */
	class ACF {}
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * Get an ACF field value.
	 *
	 * @param string     $selector Field name or key.
	 * @param int|string $post_id  Post ID or 'user_X' format.
	 * @return mixed
	 */
	function get_field( string $selector, $post_id = false ) {
		$id = is_int( $post_id ) ? $post_id : (string) $post_id;
		return $GLOBALS['_acf_fields'][ $id ][ $selector ] ?? null;
	}
}

if ( ! function_exists( 'update_field' ) ) {
	/**
	 * Update an ACF field value.
	 *
	 * @param string     $selector Field name or key.
	 * @param mixed      $value    The value to save.
	 * @param int|string $post_id  Post ID or 'user_X' format.
	 * @return bool
	 */
	function update_field( string $selector, $value, $post_id = false ): bool {
		$id = is_int( $post_id ) ? $post_id : (string) $post_id;
		if ( ! isset( $GLOBALS['_acf_fields'][ $id ] ) ) {
			$GLOBALS['_acf_fields'][ $id ] = [];
		}
		$GLOBALS['_acf_fields'][ $id ][ $selector ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_field_object' ) ) {
	/**
	 * Get an ACF field object.
	 *
	 * @param string     $selector Field name or key.
	 * @param int|string $post_id  Post ID or 'user_X' format.
	 * @return array
	 */
	function get_field_object( string $selector, $post_id = false ): array {
		return [
			'key'   => 'field_' . $selector,
			'name'  => $selector,
			'type'  => 'text',
			'value' => get_field( $selector, $post_id ),
		];
	}
}
