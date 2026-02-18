<?php
/**
 * WP Document Revisions / WP Download Manager class stubs for PHPUnit tests.
 *
 * Provides minimal stubs for Document_Revisions detection class,
 * WPDM_VERSION constant, and document-related function stubs.
 *
 * @package WP4Odoo\Tests
 */

// ─── Constants ─────────────────────────────────────────────

if ( ! defined( 'WPDM_VERSION' ) ) {
	define( 'WPDM_VERSION', '3.5.0' );
}

// ─── Detection class ───────────────────────────────────────

if ( ! class_exists( 'Document_Revisions' ) ) {
	/**
	 * Stub for WP Document Revisions detection.
	 */
	class Document_Revisions {}
}

// ─── Document storage ──────────────────────────────────────

if ( ! isset( $GLOBALS['_documents_files'] ) ) {
	$GLOBALS['_documents_files'] = [];
}

// ─── Term functions ────────────────────────────────────────

if ( ! function_exists( 'wp_delete_term' ) ) {
	/**
	 * Stub for wp_delete_term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return bool|int True on success, false or WP_Error on failure.
	 */
	function wp_delete_term( int $term_id, string $taxonomy ) {
		return true;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	/**
	 * Stub for wp_insert_term.
	 *
	 * @param string $term     Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $args     Optional arguments.
	 * @return array{term_id: int, term_taxonomy_id: int}|WP_Error
	 */
	function wp_insert_term( string $term, string $taxonomy, array $args = [] ) {
		static $id = 1000;
		++$id;

		return [
			'term_id'          => $id,
			'term_taxonomy_id' => $id,
		];
	}
}

if ( ! function_exists( 'wp_update_term' ) ) {
	/**
	 * Stub for wp_update_term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $args     Arguments.
	 * @return array{term_id: int, term_taxonomy_id: int}|WP_Error
	 */
	function wp_update_term( int $term_id, string $taxonomy, array $args = [] ) {
		return [
			'term_id'          => $term_id,
			'term_taxonomy_id' => $term_id,
		];
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	/**
	 * Stub for wp_set_object_terms.
	 *
	 * @param int          $object_id Object ID.
	 * @param array|string $terms     Term IDs or slugs.
	 * @param string       $taxonomy  Taxonomy name.
	 * @return array<int>
	 */
	function wp_set_object_terms( int $object_id, $terms, string $taxonomy ) {
		return is_array( $terms ) ? $terms : [ $terms ];
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	/**
	 * Stub for wp_get_object_terms.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @param array  $args      Arguments.
	 * @return array
	 */
	function wp_get_object_terms( int $object_id, string $taxonomy, array $args = [] ) {
		return $GLOBALS['_wp_object_terms'][ $object_id ][ $taxonomy ] ?? [];
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Stub for wp_delete_file.
	 *
	 * @param string $file File path.
	 * @return void
	 */
	function wp_delete_file( string $file ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'get_children' ) ) {
	/**
	 * Stub for get_children.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	function get_children( array $args = [] ) {
		return [];
	}
}

// ─── Global test stores ────────────────────────────────────

if ( ! isset( $GLOBALS['_wp_object_terms'] ) ) {
	$GLOBALS['_wp_object_terms'] = [];
}
