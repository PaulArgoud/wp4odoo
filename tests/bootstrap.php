<?php
/**
 * PHPUnit bootstrap file.
 *
 * Provides minimal WordPress function stubs so that plugin classes
 * can be loaded and tested without a full WordPress environment.
 *
 * @package WP4Odoo\Tests
 */

namespace {

	// Stub ABSPATH so includes don't call exit().
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/stub/' );
	}

	// Plugin root directory.
	define( 'WP4ODOO_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

	// ─── WordPress constants ────────────────────────────────

	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	// ─── WordPress function stubs ────────────────────────────

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, $options = 0, $depth = 512 ) {
			return json_encode( $data, $options, $depth );
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			return $default;
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return trim( (string) $str );
		}
	}

	if ( ! function_exists( 'absint' ) ) {
		function absint( $maybeint ) {
			return abs( (int) $maybeint );
		}
	}

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( $type = 'mysql', $gmt = false ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $transient ) {
			return false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ) {
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $transient ) {
			return true;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $tag, $value, ...$args ) {
			return $value;
		}
	}

	// ─── wpdb stub ──────────────────────────────────────────

	/**
	 * Minimal wpdb stub for unit tests.
	 *
	 * Records method calls and returns configurable values.
	 */
	class WP_DB_Stub {

		public string $prefix = 'wp_';
		public int $insert_id = 0;

		/** @var mixed */
		public $get_var_return = null;
		public array $get_results_return = [];
		public int $delete_return = 1;
		public int $query_return = 1;

		/** @var array<int, array{method: string, args: array}> */
		public array $calls = [];

		public function prepare( string $query, ...$args ): string {
			$this->calls[] = [ 'method' => 'prepare', 'args' => [ $query, ...$args ] ];
			return $query;
		}

		/** @return mixed */
		public function get_var( $query ) {
			$this->calls[] = [ 'method' => 'get_var', 'args' => [ $query ] ];
			return $this->get_var_return;
		}

		public function get_results( $query ): array {
			$this->calls[] = [ 'method' => 'get_results', 'args' => [ $query ] ];
			return $this->get_results_return;
		}

		/** @return int|false */
		public function insert( string $table, array $data ) {
			$this->calls[] = [ 'method' => 'insert', 'args' => [ $table, $data ] ];
			return 1;
		}

		/** @return int|false */
		public function update( string $table, array $data, array $where ) {
			$this->calls[] = [ 'method' => 'update', 'args' => [ $table, $data, $where ] ];
			return 1;
		}

		/** @return int|false */
		public function replace( string $table, array $data ) {
			$this->calls[] = [ 'method' => 'replace', 'args' => [ $table, $data ] ];
			return 1;
		}

		/** @return int|false */
		public function delete( string $table, array $where, ?array $format = null ) {
			$this->calls[] = [ 'method' => 'delete', 'args' => [ $table, $where, $format ] ];
			return $this->delete_return;
		}

		/** @return int|false */
		public function query( string $query ) {
			$this->calls[] = [ 'method' => 'query', 'args' => [ $query ] ];
			return $this->query_return;
		}

		public function reset(): void {
			$this->calls      = [];
			$this->get_var_return     = null;
			$this->get_results_return = [];
			$this->delete_return      = 1;
			$this->query_return       = 1;
			$this->insert_id          = 0;
		}
	}

}

// ─── Minimal Logger stub ─────────────────────────────────

namespace WP4Odoo {

	class Logger {
		private string $module;
		public function __construct( string $module = '' ) {
			$this->module = $module;
		}
		public function debug( string $message, array $context = [] ): void {}
		public function info( string $message, array $context = [] ): void {}
		public function warning( string $message, array $context = [] ): void {}
		public function error( string $message, array $context = [] ): void {}
		public function critical( string $message, array $context = [] ): void {}
	}

}

// ─── Load classes under test ─────────────────────────────

namespace {

	// Composer autoloader (for PHPUnit + test classes).
	require_once WP4ODOO_PLUGIN_DIR . 'vendor/autoload.php';

	// Plugin classes (WordPress-convention filenames, not PSR-4).
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-field-mapper.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-entity-map-repository.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-sync-queue-repository.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-partner-service.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-module-base.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-sync-engine.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/class-queue-manager.php';
	require_once WP4ODOO_PLUGIN_DIR . 'includes/modules/class-woocommerce-module.php';

}
