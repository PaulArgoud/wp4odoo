<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Modules\EDD_Download_Handler;
use WP4Odoo\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EDD_Download_Handler.
 *
 * Tests load/save/delete operations for EDD downloads.
 */
class EDDDownloadHandlerTest extends TestCase {

	private EDD_Download_Handler $handler;
	private \WP_DB_Stub $wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->wpdb = new \WP_DB_Stub();
		$wpdb       = $this->wpdb;

		$GLOBALS['_wp_options']                         = [];
		$GLOBALS['_wp_options']['wp4odoo_log_settings'] = [ 'enabled' => true, 'level' => 'debug' ];
		$GLOBALS['_wp_posts']                           = [];
		$GLOBALS['_wp_post_meta']                       = [];

		$logger        = new Logger( 'test' );
		$this->handler = new EDD_Download_Handler( $logger );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['_wp_posts'],
			$GLOBALS['_wp_post_meta']
		);
	}

	// ─── Load ────────────────────────────────────────────────

	public function test_load_returns_empty_for_nonexistent_post(): void {
		$this->assertSame( [], $this->handler->load( 999 ) );
	}

	public function test_load_returns_empty_for_wrong_post_type(): void {
		$post              = new \stdClass();
		$post->ID          = 1;
		$post->post_type   = 'post';
		$post->post_title  = 'Not a download';
		$post->post_content = '';

		$GLOBALS['_wp_posts'][1] = $post;

		$this->assertSame( [], $this->handler->load( 1 ) );
	}

	public function test_load_returns_data_for_valid_download(): void {
		$post               = new \stdClass();
		$post->ID           = 10;
		$post->post_type    = 'download';
		$post->post_title   = 'Premium eBook';
		$post->post_content = 'A great digital product.';

		$GLOBALS['_wp_posts'][10]    = $post;
		$GLOBALS['_wp_post_meta'][10] = [ 'edd_price' => '29.99' ];

		$result = $this->handler->load( 10 );

		$this->assertSame( 'Premium eBook', $result['post_title'] );
		$this->assertSame( 'A great digital product.', $result['post_content'] );
		$this->assertSame( '29.99', $result['_edd_price'] );
	}

	public function test_load_returns_default_price_when_no_meta(): void {
		$post              = new \stdClass();
		$post->ID          = 11;
		$post->post_type   = 'download';
		$post->post_title  = 'Free eBook';
		$post->post_content = '';

		$GLOBALS['_wp_posts'][11] = $post;

		$result = $this->handler->load( 11 );

		$this->assertSame( '0.00', $result['_edd_price'] );
	}

	// ─── Save ────────────────────────────────────────────────

	public function test_save_creates_new_download(): void {
		$result = $this->handler->save( [ 'post_title' => 'New eBook' ], 0 );

		$this->assertGreaterThan( 0, $result );
	}

	public function test_save_updates_existing_download(): void {
		$post              = new \stdClass();
		$post->ID          = 20;
		$post->post_type   = 'download';
		$post->post_title  = 'Old Title';

		$GLOBALS['_wp_posts'][20] = $post;

		$result = $this->handler->save( [ 'post_title' => 'Updated Title' ], 20 );

		$this->assertSame( 20, $result );
	}

	public function test_save_sets_edd_price_meta(): void {
		$result = $this->handler->save( [ 'post_title' => 'Priced eBook', '_edd_price' => '49.99' ], 0 );

		$this->assertGreaterThan( 0, $result );
		$meta = $GLOBALS['_wp_post_meta'][ $result ] ?? [];
		$this->assertSame( '49.99', $meta['edd_price'] ?? '' );
	}

	// ─── Delete ──────────────────────────────────────────────

	public function test_delete_returns_true_for_existing_post(): void {
		$post             = new \stdClass();
		$post->ID         = 30;
		$post->post_type  = 'download';
		$post->post_title = 'To Delete';

		$GLOBALS['_wp_posts'][30] = $post;

		$this->assertTrue( $this->handler->delete( 30 ) );
	}

	public function test_delete_returns_false_for_nonexistent_post(): void {
		$this->assertFalse( $this->handler->delete( 999 ) );
	}
}
