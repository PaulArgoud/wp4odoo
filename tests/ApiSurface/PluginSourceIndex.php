<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\ApiSurface;

/**
 * Indexes a plugin's PHP source code for classes, functions, constants and hooks.
 *
 * Scans all PHP files recursively, builds lookup tables used by PluginContractTest
 * to verify that third-party APIs WP4Odoo depends on still exist.
 */
class PluginSourceIndex {

	/** @var array<string, true> Class names (short + FQN). */
	public array $classes = [];

	/** @var array<string, true> Function names. */
	public array $functions = [];

	/** @var array<string, true> Constants defined via define(). */
	public array $constants = [];

	/** @var array<string, true> Actions fired via do_action() or registered via add_action(). */
	public array $actions = [];

	/** @var array<string, true> Filters fired via apply_filters() or registered via add_filter(). */
	public array $filters = [];

	/** @var array<string, true> All string literals found in source (fallback for dynamic hooks). */
	public array $string_refs = [];

	/**
	 * Build an index from a plugin directory.
	 *
	 * @param string $dir Absolute path to plugin root.
	 * @return self
	 */
	public static function build( string $dir ): self {
		$index    = new self();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}
			$content = file_get_contents( $file->getPathname() );
			if ( false === $content ) {
				continue;
			}
			$index->index_file( $content );
		}

		return $index;
	}

	/**
	 * Index a single PHP file's contents.
	 *
	 * @param string $content PHP source code.
	 */
	private function index_file( string $content ): void {
		// Extract namespace.
		$namespace = '';
		if ( preg_match( '/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $m ) ) {
			$namespace = $m[1];
		}

		// Classes (class, abstract class, final class).
		if ( preg_match_all( '/^\s*(?:abstract\s+|final\s+)?class\s+(\w+)/m', $content, $matches ) ) {
			foreach ( $matches[1] as $class ) {
				$this->classes[ $class ] = true;
				if ( '' !== $namespace ) {
					$this->classes[ $namespace . '\\' . $class ] = true;
				}
			}
		}

		// Interfaces and traits (also useful for detection).
		if ( preg_match_all( '/^\s*(?:interface|trait)\s+(\w+)/m', $content, $matches ) ) {
			foreach ( $matches[1] as $name ) {
				$this->classes[ $name ] = true;
				if ( '' !== $namespace ) {
					$this->classes[ $namespace . '\\' . $name ] = true;
				}
			}
		}

		// Functions.
		if ( preg_match_all( '/^\s*function\s+(\w+)\s*\(/m', $content, $matches ) ) {
			foreach ( $matches[1] as $func ) {
				$this->functions[ $func ] = true;
			}
		}

		// Constants via define() or wrapper functions (e.g., maybe_define_constant()).
		if ( preg_match_all( '~define(?:_constant)?\s*\(\s*[\'"](\w+)[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $const ) {
				$this->constants[ $const ] = true;
			}
		}

		// Actions via do_action() — literal hook names.
		if ( preg_match_all( '~do_action\s*\(\s*[\'"]([a-zA-Z0-9_/]+)[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $action ) {
				$this->actions[ $action ] = true;
			}
		}

		// Actions via add_action() — proves a hook exists even if fired dynamically.
		if ( preg_match_all( '~add_action\s*\(\s*[\'"]([a-zA-Z0-9_/]+)[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $action ) {
				$this->actions[ $action ] = true;
			}
		}

		// Filters via apply_filters() — literal filter names.
		if ( preg_match_all( '~apply_filters\s*\(\s*[\'"]([a-zA-Z0-9_/]+)[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $filter ) {
				$this->filters[ $filter ] = true;
			}
		}

		// Filters via add_filter() — proves a filter exists.
		if ( preg_match_all( '~add_filter\s*\(\s*[\'"]([a-zA-Z0-9_/]+)[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $filter ) {
				$this->filters[ $filter ] = true;
			}
		}

		// String references — catches hooks in array keys, comments, webhook tables, etc.
		// Used as fallback for dynamically constructed hooks.
		if ( preg_match_all( '~[\'"]([a-z][a-z0-9_/]{15,})[\'"]~', $content, $matches ) ) {
			foreach ( $matches[1] as $ref ) {
				$this->string_refs[ $ref ] = true;
			}
		}
	}

	public function has_class( string $name ): bool {
		return isset( $this->classes[ $name ] );
	}

	public function has_function( string $name ): bool {
		return isset( $this->functions[ $name ] );
	}

	public function has_constant( string $name ): bool {
		return isset( $this->constants[ $name ] );
	}

	public function has_action( string $name ): bool {
		return isset( $this->actions[ $name ] ) || isset( $this->string_refs[ $name ] );
	}

	public function has_filter( string $name ): bool {
		return isset( $this->filters[ $name ] ) || isset( $this->string_refs[ $name ] );
	}
}
