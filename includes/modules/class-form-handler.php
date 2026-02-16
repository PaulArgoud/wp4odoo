<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts lead data from form plugin submissions.
 *
 * Stateless handler: receives raw form data, returns a normalised
 * lead array ready for Lead_Manager::save_lead_data().
 *
 * Delegates extraction to Form_Field_Extractor strategies.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Form_Handler {

	private Form_Field_Extractor $extractor;

	public function __construct( Logger $logger ) {
		$this->extractor = new Form_Field_Extractor( $logger );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_gravity_forms( array $entry, array $form ): array {
		return $this->extractor->extract( 'gravity_forms', $entry, $form );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_wpforms( array $fields, array $form_data ): array {
		return $this->extractor->extract( 'wpforms', $fields, $form_data );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_cf7( array $posted_data, array $tags, string $form_title ): array {
		return $this->extractor->extract( 'cf7', $posted_data, $tags, $form_title );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_fluent_forms( array $form_data, string $form_title ): array {
		return $this->extractor->extract( 'fluent_forms', $form_data, $form_title );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_formidable( array $fields, string $form_title ): array {
		return $this->extractor->extract( 'formidable', $fields, $form_title );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_ninja_forms( array $fields, string $form_title ): array {
		return $this->extractor->extract( 'ninja_forms', $fields, $form_title );
	}

	/**
	 * @since 2.0.0
	 */
	public function extract_from_forminator( array $fields, string $form_title ): array {
		return $this->extractor->extract( 'forminator', $fields, $form_title );
	}

	/**
	 * @since 2.0.0
	 */
	public function is_company_label( string $label ): bool {
		return $this->extractor->is_company_label( $label );
	}

	/**
	 * @since 2.0.0
	 */
	public function is_name_label( string $label ): bool {
		return $this->extractor->is_name_label( $label );
	}
}
