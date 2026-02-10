<?php
declare( strict_types=1 );

namespace WP4Odoo\Tests\Unit;

use WP4Odoo\Logger;
use WP4Odoo\Modules\Form_Handler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Form_Handler.
 *
 * Tests field extraction from Gravity Forms and WPForms submissions.
 */
class FormHandlerTest extends TestCase {

	private Form_Handler $handler;

	protected function setUp(): void {
		global $wpdb;
		$wpdb = new \WP_DB_Stub();

		$GLOBALS['_wp_options'] = [];

		$this->handler = new Form_Handler( new Logger( 'test' ) );
	}

	// ─── Gravity Forms extraction ────────────────────────────

	public function test_gf_extracts_email_from_email_field(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'text', 'id' => 2, 'label' => 'Your Name' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => 'John Doe' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'john@example.com', $data['email'] );
	}

	public function test_gf_extracts_name_from_name_sub_fields(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'name', 'id' => 1, 'label' => 'Name' ] ),
			new \GF_Field( [ 'type' => 'email', 'id' => 2, 'label' => 'Email' ] ),
		] );
		$entry = [ '1.3' => 'John', '1.6' => 'Doe', '2' => 'john@example.com' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'John Doe', $data['name'] );
	}

	public function test_gf_extracts_phone_from_phone_field(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'phone', 'id' => 2, 'label' => 'Phone' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => '+33612345678' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( '+33612345678', $data['phone'] );
	}

	public function test_gf_extracts_description_from_textarea(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'textarea', 'id' => 2, 'label' => 'Message' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => 'Hello, I need info.' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'Hello, I need info.', $data['description'] );
	}

	public function test_gf_extracts_company_from_text_with_company_label(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'text', 'id' => 2, 'label' => 'Company' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => 'Acme Corp' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'Acme Corp', $data['company'] );
	}

	public function test_gf_extracts_name_from_text_with_name_label(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'text', 'id' => 2, 'label' => 'Your Name' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => 'John Doe' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'John Doe', $data['name'] );
	}

	public function test_gf_returns_empty_when_no_email(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'text', 'id' => 1, 'label' => 'Name' ] ),
		] );
		$entry = [ '1' => 'John Doe' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( [], $data );
	}

	public function test_gf_returns_empty_when_invalid_email(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
		] );
		$entry = [ '1' => 'not-an-email' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( [], $data );
	}

	public function test_gf_uses_email_as_name_fallback(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
		] );
		$entry = [ '1' => 'jane@example.com' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'jane@example.com', $data['name'] );
	}

	public function test_gf_sets_source_with_form_title(): void {
		$form  = $this->gf_form(
			[ new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ) ],
			'Contact Form'
		);
		$entry = [ '1' => 'john@example.com' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'Gravity Forms: Contact Form', $data['source'] );
	}

	public function test_gf_detects_company_label_in_french(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'text', 'id' => 2, 'label' => 'Société' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => 'Entreprise FR' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( 'Entreprise FR', $data['company'] );
	}

	public function test_gf_ignores_empty_field_values(): void {
		$form  = $this->gf_form( [
			new \GF_Field( [ 'type' => 'email', 'id' => 1, 'label' => 'Email' ] ),
			new \GF_Field( [ 'type' => 'phone', 'id' => 2, 'label' => 'Phone' ] ),
		] );
		$entry = [ '1' => 'john@example.com', '2' => '' ];

		$data = $this->handler->extract_from_gravity_forms( $entry, $form );

		$this->assertSame( '', $data['phone'] );
	}

	// ─── WPForms extraction ──────────────────────────────────

	public function test_wpf_extracts_email_from_email_field(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
		] );
		$form_data = $this->wpf_form_data( 'Contact' );

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'jane@example.com', $data['email'] );
	}

	public function test_wpf_extracts_name_from_name_field(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'name', 'value' => 'Jane Smith', 'name' => 'Name' ],
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'Jane Smith', $data['name'] );
	}

	public function test_wpf_extracts_phone_from_phone_field(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
			[ 'type' => 'phone', 'value' => '+44123456', 'name' => 'Phone' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( '+44123456', $data['phone'] );
	}

	public function test_wpf_extracts_description_from_textarea(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
			[ 'type' => 'textarea', 'value' => 'Please contact me.', 'name' => 'Message' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'Please contact me.', $data['description'] );
	}

	public function test_wpf_extracts_company_from_text_with_organization_label(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
			[ 'type' => 'text', 'value' => 'Big Corp', 'name' => 'Organization' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'Big Corp', $data['company'] );
	}

	public function test_wpf_returns_empty_when_no_email(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'name', 'value' => 'Jane', 'name' => 'Name' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( [], $data );
	}

	public function test_wpf_uses_email_as_name_fallback(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
		] );
		$form_data = $this->wpf_form_data();

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'jane@example.com', $data['name'] );
	}

	public function test_wpf_sets_source_with_form_title(): void {
		$fields = $this->wpf_fields( [
			[ 'type' => 'email', 'value' => 'jane@example.com', 'name' => 'Email' ],
		] );
		$form_data = $this->wpf_form_data( 'Newsletter Signup' );

		$data = $this->handler->extract_from_wpforms( $fields, $form_data );

		$this->assertSame( 'WPForms: Newsletter Signup', $data['source'] );
	}

	// ─── Label detection ─────────────────────────────────────

	public function test_is_company_label_matches_english(): void {
		$this->assertTrue( $this->handler->is_company_label( 'Company' ) );
		$this->assertTrue( $this->handler->is_company_label( 'Your Organization' ) );
	}

	public function test_is_company_label_matches_french(): void {
		$this->assertTrue( $this->handler->is_company_label( 'Société' ) );
		$this->assertTrue( $this->handler->is_company_label( 'Votre entreprise' ) );
	}

	public function test_is_company_label_matches_spanish(): void {
		$this->assertTrue( $this->handler->is_company_label( 'Empresa' ) );
		$this->assertTrue( $this->handler->is_company_label( 'Organización' ) );
	}

	public function test_is_company_label_rejects_non_company(): void {
		$this->assertFalse( $this->handler->is_company_label( 'Email' ) );
		$this->assertFalse( $this->handler->is_company_label( 'Phone' ) );
		$this->assertFalse( $this->handler->is_company_label( 'Message' ) );
	}

	public function test_is_name_label_matches(): void {
		$this->assertTrue( $this->handler->is_name_label( 'Name' ) );
		$this->assertTrue( $this->handler->is_name_label( 'Nom' ) );
		$this->assertTrue( $this->handler->is_name_label( 'Your Name' ) );
	}

	public function test_is_name_label_rejects_non_name(): void {
		$this->assertFalse( $this->handler->is_name_label( 'Email' ) );
		$this->assertFalse( $this->handler->is_name_label( 'Company' ) );
	}

	// ─── Helpers ─────────────────────────────────────────────

	/**
	 * Build a minimal GF form structure.
	 *
	 * @param array  $fields GF_Field instances.
	 * @param string $title  Form title.
	 * @return array
	 */
	private function gf_form( array $fields, string $title = 'Test Form' ): array {
		return [
			'title'  => $title,
			'fields' => $fields,
		];
	}

	/**
	 * Build a minimal WPForms fields array.
	 *
	 * @param array $field_defs Array of ['type', 'value', 'name'] definitions.
	 * @return array
	 */
	private function wpf_fields( array $field_defs ): array {
		$fields = [];
		foreach ( $field_defs as $i => $def ) {
			$id            = $i + 1;
			$fields[ $id ] = [
				'id'    => $id,
				'type'  => $def['type'],
				'value' => $def['value'],
				'name'  => $def['name'],
			];
		}
		return $fields;
	}

	/**
	 * Build a minimal WPForms form_data structure.
	 *
	 * @param string $title Form title.
	 * @return array
	 */
	private function wpf_form_data( string $title = 'Test Form' ): array {
		return [
			'id'       => 1,
			'settings' => [ 'form_title' => $title ],
		];
	}
}
