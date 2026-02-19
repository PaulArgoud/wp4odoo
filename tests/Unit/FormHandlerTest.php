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

	// ─── Contact Form 7 extraction ──────────────────────────

	public function test_cf7_extracts_email(): void {
		$posted = [ 'your-email' => 'bob@example.com', 'your-name' => 'Bob' ];
		$tags   = [
			[ 'type' => 'email', 'name' => 'your-email' ],
			[ 'type' => 'text', 'name' => 'your-name' ],
		];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( 'bob@example.com', $data['email'] );
	}

	public function test_cf7_maps_tel_to_phone(): void {
		$posted = [ 'your-email' => 'bob@example.com', 'your-tel' => '+33612345678' ];
		$tags   = [
			[ 'type' => 'email', 'name' => 'your-email' ],
			[ 'type' => 'tel', 'name' => 'your-tel' ],
		];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( '+33612345678', $data['phone'] );
	}

	public function test_cf7_extracts_description_from_textarea(): void {
		$posted = [ 'your-email' => 'bob@example.com', 'your-message' => 'Hello!' ];
		$tags   = [
			[ 'type' => 'email', 'name' => 'your-email' ],
			[ 'type' => 'textarea', 'name' => 'your-message' ],
		];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( 'Hello!', $data['description'] );
	}

	public function test_cf7_extracts_company_from_text_with_company_label(): void {
		$posted = [ 'your-email' => 'bob@example.com', 'company' => 'Acme Inc' ];
		$tags   = [
			[ 'type' => 'email', 'name' => 'your-email' ],
			[ 'type' => 'text', 'name' => 'company' ],
		];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( 'Acme Inc', $data['company'] );
	}

	public function test_cf7_returns_empty_when_no_email(): void {
		$posted = [ 'your-name' => 'Bob' ];
		$tags   = [ [ 'type' => 'text', 'name' => 'your-name' ] ];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_cf7_uses_email_as_name_fallback(): void {
		$posted = [ 'your-email' => 'bob@example.com' ];
		$tags   = [ [ 'type' => 'email', 'name' => 'your-email' ] ];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( 'bob@example.com', $data['name'] );
	}

	public function test_cf7_sets_correct_source(): void {
		$posted = [ 'your-email' => 'bob@example.com' ];
		$tags   = [ [ 'type' => 'email', 'name' => 'your-email' ] ];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'My CF7 Form' );

		$this->assertSame( 'Contact Form 7: My CF7 Form', $data['source'] );
	}

	public function test_cf7_extracts_name_from_text_with_name_label(): void {
		$posted = [ 'your-email' => 'bob@example.com', 'your-name' => 'Bob Smith' ];
		$tags   = [
			[ 'type' => 'email', 'name' => 'your-email' ],
			[ 'type' => 'text', 'name' => 'your-name' ],
		];

		$data = $this->handler->extract_from_cf7( $posted, $tags, 'Contact' );

		$this->assertSame( 'Bob Smith', $data['name'] );
	}

	// ─── Fluent Forms extraction ────────────────────────────

	public function test_ff_extracts_email(): void {
		$form_data = [ 'email' => 'alice@example.com', 'names' => 'Alice' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( 'alice@example.com', $data['email'] );
	}

	public function test_ff_extracts_name_from_names_array(): void {
		$form_data = [
			'email' => 'alice@example.com',
			'names' => [ 'first_name' => 'Alice', 'last_name' => 'Wonder' ],
		];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( 'Alice Wonder', $data['name'] );
	}

	public function test_ff_extracts_phone(): void {
		$form_data = [ 'email' => 'alice@example.com', 'phone' => '+44123456' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( '+44123456', $data['phone'] );
	}

	public function test_ff_extracts_description_from_message(): void {
		$form_data = [ 'email' => 'alice@example.com', 'message' => 'Please help.' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( 'Please help.', $data['description'] );
	}

	public function test_ff_returns_empty_when_no_email(): void {
		$form_data = [ 'names' => 'Alice' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( [], $data );
	}

	public function test_ff_uses_email_as_name_fallback(): void {
		$form_data = [ 'email' => 'alice@example.com' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Subscribe' );

		$this->assertSame( 'alice@example.com', $data['name'] );
	}

	public function test_ff_sets_correct_source(): void {
		$form_data = [ 'email' => 'alice@example.com' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'My Fluent Form' );

		$this->assertSame( 'Fluent Forms: My Fluent Form', $data['source'] );
	}

	public function test_ff_infers_tel_as_phone(): void {
		$form_data = [ 'email' => 'alice@example.com', 'telephone' => '+33612345' ];

		$data = $this->handler->extract_from_fluent_forms( $form_data, 'Form' );

		$this->assertSame( '+33612345', $data['phone'] );
	}

	// ─── Formidable Forms extraction ────────────────────────

	public function test_frm_extracts_email(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Mark' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( 'mark@example.com', $data['email'] );
	}

	public function test_frm_extracts_name(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Mark Johnson' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( 'Mark Johnson', $data['name'] );
	}

	public function test_frm_extracts_phone(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
			[ 'type' => 'phone', 'label' => 'Phone', 'value' => '+1234567' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( '+1234567', $data['phone'] );
	}

	public function test_frm_extracts_description(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
			[ 'type' => 'textarea', 'label' => 'Message', 'value' => 'I need help.' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( 'I need help.', $data['description'] );
	}

	public function test_frm_extracts_company_by_label(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
			[ 'type' => 'text', 'label' => 'Company', 'value' => 'BigCo' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( 'BigCo', $data['company'] );
	}

	public function test_frm_returns_empty_when_no_email(): void {
		$fields = [
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Mark' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_frm_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Contact' );

		$this->assertSame( 'mark@example.com', $data['name'] );
	}

	public function test_frm_sets_correct_source(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'mark@example.com' ],
		];

		$data = $this->handler->extract_from_formidable( $fields, 'Inquiry Form' );

		$this->assertSame( 'Formidable: Inquiry Form', $data['source'] );
	}

	// ─── Ninja Forms extraction ─────────────────────────────

	public function test_nf_extracts_email(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( 'sam@example.com', $data['email'] );
	}

	public function test_nf_concatenates_firstname_lastname(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
			[ 'type' => 'firstname', 'label' => 'First', 'value' => 'Sam' ],
			[ 'type' => 'lastname', 'label' => 'Last', 'value' => 'Jones' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( 'Sam Jones', $data['name'] );
	}

	public function test_nf_extracts_phone(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
			[ 'type' => 'phone', 'label' => 'Phone', 'value' => '+999' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( '+999', $data['phone'] );
	}

	public function test_nf_extracts_description_from_textarea(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
			[ 'type' => 'textarea', 'label' => 'Message', 'value' => 'Need info.' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( 'Need info.', $data['description'] );
	}

	public function test_nf_maps_textbox_to_text(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
			[ 'type' => 'textbox', 'label' => 'Company', 'value' => 'NinjaCo' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( 'NinjaCo', $data['company'] );
	}

	public function test_nf_returns_empty_when_no_email(): void {
		$fields = [
			[ 'type' => 'firstname', 'label' => 'First', 'value' => 'Sam' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_nf_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'Contact' );

		$this->assertSame( 'sam@example.com', $data['name'] );
	}

	public function test_nf_sets_correct_source(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'sam@example.com' ],
		];

		$data = $this->handler->extract_from_ninja_forms( $fields, 'NF Contact' );

		$this->assertSame( 'Ninja Forms: NF Contact', $data['source'] );
	}

	// ─── Forminator extraction ──────────────────────────────

	public function test_ftr_extracts_email(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( 'liz@example.com', $data['email'] );
	}

	public function test_ftr_extracts_name(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
			[ 'type' => 'name', 'label' => 'name-1', 'value' => 'Liz Taylor' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( 'Liz Taylor', $data['name'] );
	}

	public function test_ftr_extracts_phone(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
			[ 'type' => 'phone', 'label' => 'phone-1', 'value' => '+44888' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( '+44888', $data['phone'] );
	}

	public function test_ftr_extracts_description(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
			[ 'type' => 'textarea', 'label' => 'textarea-1', 'value' => 'Hi there.' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( 'Hi there.', $data['description'] );
	}

	public function test_ftr_extracts_company_by_label(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
			[ 'type' => 'text', 'label' => 'Company', 'value' => 'FormCo' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( 'FormCo', $data['company'] );
	}

	public function test_ftr_returns_empty_when_no_email(): void {
		$fields = [
			[ 'type' => 'name', 'label' => 'name-1', 'value' => 'Liz' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_ftr_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Contact' );

		$this->assertSame( 'liz@example.com', $data['name'] );
	}

	public function test_ftr_sets_correct_source(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'email-1', 'value' => 'liz@example.com' ],
		];

		$data = $this->handler->extract_from_forminator( $fields, 'Forminator Form' );

		$this->assertSame( 'Forminator: Forminator Form', $data['source'] );
	}

	// ─── Elementor Pro extraction ───────────────────────────────

	public function test_elementor_extracts_email(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Ella' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( 'ella@example.com', $data['email'] );
	}

	public function test_elementor_extracts_name(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Ella Martin' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( 'Ella Martin', $data['name'] );
	}

	public function test_elementor_extracts_phone(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
			[ 'type' => 'phone', 'label' => 'Phone', 'value' => '+33612345' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( '+33612345', $data['phone'] );
	}

	public function test_elementor_extracts_description(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
			[ 'type' => 'textarea', 'label' => 'Message', 'value' => 'I have a question.' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( 'I have a question.', $data['description'] );
	}

	public function test_elementor_extracts_company_by_label(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
			[ 'type' => 'text', 'label' => 'Company', 'value' => 'ElementorCo' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( 'ElementorCo', $data['company'] );
	}

	public function test_elementor_returns_empty_when_no_email(): void {
		$fields = [
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Ella' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_elementor_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'Contact' );

		$this->assertSame( 'ella@example.com', $data['name'] );
	}

	public function test_elementor_sets_correct_source(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'ella@example.com' ],
		];

		$data = $this->handler->extract_from_elementor( $fields, 'My Elementor Form' );

		$this->assertSame( 'Elementor: My Elementor Form', $data['source'] );
	}

	// ─── Divi extraction ────────────────────────────────────────

	public function test_divi_extracts_email(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Dave' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( 'dave@example.com', $data['email'] );
	}

	public function test_divi_extracts_name(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Dave Ross' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( 'Dave Ross', $data['name'] );
	}

	public function test_divi_extracts_phone(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
			[ 'type' => 'phone', 'label' => 'Phone', 'value' => '+1555999' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( '+1555999', $data['phone'] );
	}

	public function test_divi_extracts_description(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
			[ 'type' => 'textarea', 'label' => 'Message', 'value' => 'Interested in your services.' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( 'Interested in your services.', $data['description'] );
	}

	public function test_divi_extracts_company_by_label(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
			[ 'type' => 'text', 'label' => 'Company', 'value' => 'DiviCo' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( 'DiviCo', $data['company'] );
	}

	public function test_divi_returns_empty_when_no_email(): void {
		$fields = [
			[ 'type' => 'name', 'label' => 'Name', 'value' => 'Dave' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_divi_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'Contact' );

		$this->assertSame( 'dave@example.com', $data['name'] );
	}

	public function test_divi_sets_correct_source(): void {
		$fields = [
			[ 'type' => 'email', 'label' => 'Email', 'value' => 'dave@example.com' ],
		];

		$data = $this->handler->extract_from_divi( $fields, 'My Divi Form' );

		$this->assertSame( 'Divi: My Divi Form', $data['source'] );
	}

	// ─── Bricks extraction ──────────────────────────────────────

	public function test_bricks_extracts_email_by_label(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
			[ 'label' => 'Name', 'value' => 'Bea' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( 'bea@example.com', $data['email'] );
	}

	public function test_bricks_extracts_name_by_label(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
			[ 'label' => 'Your Name', 'value' => 'Bea Stone' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( 'Bea Stone', $data['name'] );
	}

	public function test_bricks_extracts_phone_by_label(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
			[ 'label' => 'Phone', 'value' => '+44777888' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( '+44777888', $data['phone'] );
	}

	public function test_bricks_extracts_description_by_label(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
			[ 'label' => 'Message', 'value' => 'Need a quote.' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( 'Need a quote.', $data['description'] );
	}

	public function test_bricks_extracts_company_by_label(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
			[ 'label' => 'Company', 'value' => 'BricksCo' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( 'BricksCo', $data['company'] );
	}

	public function test_bricks_returns_empty_when_no_email(): void {
		$fields = [
			[ 'label' => 'Name', 'value' => 'Bea' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( [], $data );
	}

	public function test_bricks_uses_email_as_name_fallback(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'Contact' );

		$this->assertSame( 'bea@example.com', $data['name'] );
	}

	public function test_bricks_sets_correct_source(): void {
		$fields = [
			[ 'label' => 'Email', 'value' => 'bea@example.com' ],
		];

		$data = $this->handler->extract_from_bricks( $fields, 'My Bricks Form' );

		$this->assertSame( 'Bricks: My Bricks Form', $data['source'] );
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
