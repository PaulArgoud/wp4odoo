<?php
/**
 * Minimal wpdb stub for PHPUnit tests.
 *
 * Records method calls and returns configurable values.
 *
 * @package WP4Odoo\Tests
 */

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

class WP_DB_Stub {

	public string $prefix = 'wp_';
	public int $insert_id = 0;

	/** @var mixed */
	public $get_var_return = null;
	/** @var array|object|null */
	public $get_row_return = null;
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

	/** @return array|object|null */
	public function get_row( $query, $output = OBJECT, $y = 0 ) {
		$this->calls[] = [ 'method' => 'get_row', 'args' => [ $query, $output ] ];
		return $this->get_row_return;
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
		$this->calls              = [];
		$this->get_var_return     = null;
		$this->get_row_return     = null;
		$this->get_results_return = [];
		$this->delete_return      = 1;
		$this->query_return       = 1;
		$this->insert_id          = 0;
	}
}
