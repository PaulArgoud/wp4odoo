<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\API\Odoo_Client;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Inconsistency A — is_session_error() word-boundary fix.
 *
 * Uses reflection to test the private method directly.
 */
class SessionErrorTest extends TestCase {

	private Odoo_Client $client;
	private \ReflectionMethod $method;

	protected function setUp(): void {
		global $wpdb;
		$wpdb                      = new \WP_DB_Stub();
		$GLOBALS['_wp_options']    = [];
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [ 'enabled' => true, 'level' => 'debug' ];

		$this->client = new Odoo_Client();
		$this->method = new \ReflectionMethod( $this->client, 'is_session_error' );
		$this->method->setAccessible( true );
	}

	// ─── True positives: SHOULD trigger re-auth ─────────────

	public function test_exception_code_403_is_session_error(): void {
		$e = new \RuntimeException( 'Whatever message', 403 );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_session_expired_keyword_is_detected(): void {
		$e = new \RuntimeException( 'Odoo returned session expired error' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_session_expired_underscore_is_detected(): void {
		$e = new \RuntimeException( 'Error: session_expired' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_odoo_session_keyword_is_detected(): void {
		$e = new \RuntimeException( 'Invalid Odoo session' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_access_denied_is_detected(): void {
		$e = new \RuntimeException( 'Access Denied for user admin' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_http_403_forbidden_is_detected(): void {
		$e = new \RuntimeException( 'HTTP 403 Forbidden' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	public function test_http403_no_space_is_detected(): void {
		$e = new \RuntimeException( 'Received HTTP403 from server' );
		$this->assertTrue( $this->method->invoke( $this->client, $e ) );
	}

	// ─── True negatives: should NOT trigger re-auth ─────────

	public function test_product_id_containing_403_is_not_session_error(): void {
		$e = new \RuntimeException( 'Product #1403 not found' );
		$this->assertFalse( $this->method->invoke( $this->client, $e ) );
	}

	public function test_error_code_14031_is_not_session_error(): void {
		$e = new \RuntimeException( 'Error 14031: validation failed' );
		$this->assertFalse( $this->method->invoke( $this->client, $e ) );
	}

	public function test_generic_runtime_error_is_not_session_error(): void {
		$e = new \RuntimeException( 'Connection timeout after 30s' );
		$this->assertFalse( $this->method->invoke( $this->client, $e ) );
	}

	public function test_non_403_exception_code_is_not_session_error(): void {
		$e = new \RuntimeException( 'Not found', 404 );
		$this->assertFalse( $this->method->invoke( $this->client, $e ) );
	}

	public function test_empty_message_is_not_session_error(): void {
		$e = new \RuntimeException( '' );
		$this->assertFalse( $this->method->invoke( $this->client, $e ) );
	}
}
