<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polylang translation adapter.
 *
 * Implements Translation_Adapter using Polylang's function API:
 *   - pll_default_language()
 *   - pll_languages_list()
 *   - pll_get_post_translations()
 *   - pll_get_post_language()
 *   - pll_get_post()
 *
 * Detection: `defined('POLYLANG_VERSION') && function_exists('pll_default_language')`
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class Polylang_Adapter implements Translation_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_default_language(): string {
		return pll_default_language();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_active_languages(): array {
		return pll_languages_list();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_translations( int $post_id ): array {
		$all = pll_get_post_translations( $post_id );

		$self_lang = $this->get_post_language( $post_id );
		$result    = [];

		foreach ( $all as $lang => $trans_id ) {
			$trans_id = (int) $trans_id;

			// Exclude the source post itself.
			if ( $trans_id === $post_id || $lang === $self_lang ) {
				continue;
			}

			$result[ $lang ] = $trans_id;
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_post_language( int $post_id ): string {
		$lang = pll_get_post_language( $post_id );
		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_original_post_id( int $post_id ): int {
		$default_lang = $this->get_default_language();
		$original     = pll_get_post( $post_id, $default_lang );

		return $original ? (int) $original : $post_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_translation( int $post_id ): bool {
		return $this->get_original_post_id( $post_id ) !== $post_id;
	}
}
