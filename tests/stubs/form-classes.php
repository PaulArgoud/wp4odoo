<?php
/**
 * Form plugin stubs for PHPUnit tests.
 *
 * Covers: Gravity Forms, WPForms, Contact Form 7, Fluent Forms,
 * Formidable Forms, Ninja Forms, Forminator.
 *
 * @package WP4Odoo\Tests
 */

// ── Gravity Forms ───────────────────────────────────────

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

// ── WPForms ─────────────────────────────────────────────

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

// ── Contact Form 7 ─────────────────────────────────────

if ( ! defined( 'WPCF7_VERSION' ) ) {
	define( 'WPCF7_VERSION', '6.0' );
}

if ( ! class_exists( 'WPCF7_FormTag' ) ) {
	/**
	 * CF7 form tag stub.
	 */
	class WPCF7_FormTag {
		/** @var string */
		public string $basetype = '';

		/** @var string */
		public string $name = '';
	}
}

if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	/**
	 * CF7 contact form stub.
	 */
	class WPCF7_ContactForm {
		/** @var string */
		private string $title_value = '';

		/** @var WPCF7_FormTag[] */
		private array $tags = [];

		/**
		 * Set the form title (for tests).
		 *
		 * @param string $title Title.
		 */
		public function set_title( string $title ): void {
			$this->title_value = $title;
		}

		/**
		 * Get the form title.
		 *
		 * @return string
		 */
		public function title(): string {
			return $this->title_value;
		}

		/**
		 * Set form tags (for tests).
		 *
		 * @param WPCF7_FormTag[] $tags Tags.
		 */
		public function set_tags( array $tags ): void {
			$this->tags = $tags;
		}

		/**
		 * Scan form tags.
		 *
		 * @return WPCF7_FormTag[]
		 */
		public function scan_form_tags(): array {
			return $this->tags;
		}
	}
}

if ( ! class_exists( 'WPCF7_Submission' ) ) {
	/**
	 * CF7 submission stub.
	 */
	class WPCF7_Submission {
		/** @var self|null */
		private static ?self $instance = null;

		/** @var array */
		private array $posted_data = [];

		/**
		 * Set the singleton instance (for tests).
		 *
		 * @param self|null $instance Instance.
		 */
		public static function set_instance( ?self $instance ): void {
			self::$instance = $instance;
		}

		/**
		 * Get the singleton instance.
		 *
		 * @return self|null
		 */
		public static function get_instance(): ?self {
			return self::$instance;
		}

		/**
		 * Set posted data (for tests).
		 *
		 * @param array $data Posted data.
		 */
		public function set_posted_data( array $data ): void {
			$this->posted_data = $data;
		}

		/**
		 * Get posted data.
		 *
		 * @return array
		 */
		public function get_posted_data(): array {
			return $this->posted_data;
		}
	}
}

// ── Fluent Forms ────────────────────────────────────────

if ( ! defined( 'FLUENTFORM' ) ) {
	define( 'FLUENTFORM', true );
}

// ── Formidable Forms ────────────────────────────────────

if ( ! class_exists( 'FrmAppHelper' ) ) {
	/**
	 * Formidable app helper stub (detection only).
	 */
	class FrmAppHelper {}
}

if ( ! class_exists( 'FrmField' ) ) {
	/**
	 * Formidable field stub.
	 */
	class FrmField {
		/**
		 * Get all fields matching conditions.
		 *
		 * Returns data from global store for test control.
		 *
		 * @param array $conditions Query conditions.
		 * @return array
		 */
		public static function getAll( array $conditions = [] ): array {
			return $GLOBALS['_frm_fields'] ?? [];
		}
	}
}

if ( ! class_exists( 'FrmEntryMeta' ) ) {
	/**
	 * Formidable entry meta stub.
	 */
	class FrmEntryMeta {
		/**
		 * Get all entry metas matching conditions.
		 *
		 * Returns data from global store for test control.
		 *
		 * @param array $conditions Query conditions.
		 * @return array
		 */
		public static function getAll( array $conditions = [] ): array {
			return $GLOBALS['_frm_entry_metas'] ?? [];
		}
	}
}

if ( ! class_exists( 'FrmForm' ) ) {
	/**
	 * Formidable form stub.
	 */
	class FrmForm {
		/** @var string */
		public string $name = '';

		/**
		 * Get a form by ID.
		 *
		 * Returns data from global store for test control.
		 *
		 * @param int $form_id Form ID.
		 * @return self|null
		 */
		public static function getOne( int $form_id ): ?self {
			return $GLOBALS['_frm_forms'][ $form_id ] ?? null;
		}
	}
}

// ── Ninja Forms ─────────────────────────────────────────

if ( ! class_exists( 'Ninja_Forms' ) ) {
	/**
	 * Ninja Forms stub (detection only).
	 */
	class Ninja_Forms {}
}

// ── Forminator ──────────────────────────────────────────

if ( ! defined( 'FORMINATOR_VERSION' ) ) {
	define( 'FORMINATOR_VERSION', '1.34.0' );
}
