<?php
/**
 * Gravity Forms and WPForms stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ---- Gravity Forms ----

if ( ! class_exists( 'GFAPI' ) ) {
	/**
	 * Gravity Forms API stub.
	 */
	class GFAPI {
		/**
		 * Get entry by ID.
		 *
		 * @param int $entry_id Entry ID.
		 * @return array
		 */
		public static function get_entry( int $entry_id ): array {
			return [];
		}

		/**
		 * Get form by ID.
		 *
		 * @param int $form_id Form ID.
		 * @return array
		 */
		public static function get_form( int $form_id ): array {
			return [];
		}
	}
}

if ( ! class_exists( 'GF_Field' ) ) {
	/**
	 * Gravity Forms field stub.
	 */
	class GF_Field {
		/** @var string */
		public string $type = '';

		/** @var int */
		public int $id = 0;

		/** @var string */
		public string $label = '';

		/**
		 * Constructor.
		 *
		 * @param array $data Field properties.
		 */
		public function __construct( array $data = [] ) {
			$this->type  = $data['type'] ?? '';
			$this->id    = $data['id'] ?? 0;
			$this->label = $data['label'] ?? '';
		}
	}
}

// ---- WPForms ----

if ( ! function_exists( 'wpforms' ) ) {
	/**
	 * WPForms main function stub.
	 *
	 * @return stdClass
	 */
	function wpforms() {
		return new stdClass();
	}
}
