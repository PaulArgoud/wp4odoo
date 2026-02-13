<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPML translation adapter.
 *
 * Implements Translation_Adapter using WPML's filter-based API:
 *   - wpml_default_language
 *   - wpml_active_languages
 *   - wpml_get_element_translations
 *   - wpml_post_language_details
 *   - wpml_object_id
 *
 * Detection: `defined('ICL_SITEPRESS_VERSION') && class_exists('SitePress')`
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class WPML_Adapter implements Translation_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_default_language(): string {
		/** @var string $lang */
		$lang = apply_filters( 'wpml_default_language', '' );
		return $lang;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_active_languages(): array {
		/** @var array<string, array<string, mixed>> $languages */
		$languages = apply_filters( 'wpml_active_languages', [], [ 'skip_missing' => 0 ] );

		return array_keys( $languages );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_translations( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return [];
		}

		$element_type = 'post_' . $post_type;

		/** @var \stdClass[]|null $translations */
		$translations = apply_filters( 'wpml_get_element_translations', null, $this->get_trid( $post_id, $element_type ), $element_type );

		if ( ! is_array( $translations ) ) {
			return [];
		}

		$default_lang = $this->get_default_language();
		$result       = [];

		foreach ( $translations as $translation ) {
			if ( ! isset( $translation->language_code, $translation->element_id ) ) {
				continue;
			}

			$lang     = $translation->language_code;
			$trans_id = (int) $translation->element_id;

			// Exclude the original (source) post.
			if ( $lang === $default_lang || $trans_id === $post_id ) {
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
		/** @var array<string, mixed>|null $details */
		$details = apply_filters( 'wpml_post_language_details', null, $post_id );

		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return (string) $details['language_code'];
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_original_post_id( int $post_id ): int {
		$default_lang = $this->get_default_language();
		$post_type    = get_post_type( $post_id );

		if ( ! $post_type ) {
			return $post_id;
		}

		/** @var int|null $original */
		$original = apply_filters( 'wpml_object_id', $post_id, $post_type, true, $default_lang );

		return $original ? (int) $original : $post_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_translation( int $post_id ): bool {
		return $this->get_original_post_id( $post_id ) !== $post_id;
	}

	/**
	 * Get the WPML translation group ID (trid) for a post.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $element_type WPML element type (e.g. 'post_product').
	 * @return int|null Translation group ID.
	 */
	private function get_trid( int $post_id, string $element_type ): ?int {
		/** @var int|null $trid */
		$trid = apply_filters( 'wpml_element_trid', null, $post_id, $element_type );
		return $trid;
	}
}
