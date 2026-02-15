<?php
/**
 * FluentCRM class and function stubs for PHPUnit tests.
 *
 * @package WP4Odoo\Tests
 */

// ─── Global constants ───────────────────────────────────

namespace {

	if ( ! defined( 'FLUENTCRM' ) ) {
		define( 'FLUENTCRM', true );
	}
	if ( ! defined( 'FLUENTCRM_PLUGIN_VERSION' ) ) {
		define( 'FLUENTCRM_PLUGIN_VERSION', '2.9.0' );
	}
}

// ─── FluentCRM model stubs ──────────────────────────────

namespace FluentCrm\App\Models {

	if ( ! class_exists( 'FluentCrm\App\Models\Subscriber' ) ) {
		/**
		 * FluentCRM Subscriber model stub.
		 */
		class Subscriber {
			/** @var int */
			public int $id = 0;
			/** @var string */
			public string $email = '';
			/** @var string */
			public string $first_name = '';
			/** @var string */
			public string $last_name = '';
			/** @var string */
			public string $status = 'subscribed';
		}
	}

	if ( ! class_exists( 'FluentCrm\App\Models\Lists' ) ) {
		/**
		 * FluentCRM Lists model stub.
		 */
		class Lists {
			/** @var int */
			public int $id = 0;
			/** @var string */
			public string $title = '';
			/** @var string */
			public string $description = '';
		}
	}

	if ( ! class_exists( 'FluentCrm\App\Models\Tag' ) ) {
		/**
		 * FluentCRM Tag model stub.
		 */
		class Tag {
			/** @var int */
			public int $id = 0;
			/** @var string */
			public string $title = '';
		}
	}
}
