<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts lead data from Gravity Forms and WPForms submissions.
 *
 * Stateless handler: receives raw form data, returns a normalised
 * lead array ready for Lead_Manager::save_lead_data().
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Form_Handler {

	/**
	 * Company-like label patterns (multilingual).
	 *
	 * @var string[]
	 */
	private const COMPANY_LABELS = [
		'company',
		'organization',
		'organisation',
		'société',
		'societe',
		'entreprise',
		'empresa',
		'organización',
		'organizacion',
	];

	/**
	 * Name-like label patterns (multilingual).
	 *
	 * @var string[]
	 */
	private const NAME_LABELS = [
		'name',
		'nom',
		'nombre',
		'full name',
		'your name',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Extract lead data from a Gravity Forms entry.
	 *
	 * Iterates form fields by type and auto-detects name, email, phone,
	 * company and description. Returns empty array if no valid email.
	 *
	 * @param array $entry GF entry array (field_id => value).
	 * @param array $form  GF form object with 'fields' and 'title'.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_gravity_forms( array $entry, array $form ): array {
		$data = [
			'name'        => '',
			'email'       => '',
			'phone'       => '',
			'company'     => '',
			'description' => '',
			'source'      => sprintf(
				/* translators: %s: form title */
				__( 'Gravity Forms: %s', 'wp4odoo' ),
				$form['title'] ?? __( 'Unknown Form', 'wp4odoo' )
			),
		];

		$fields = $form['fields'] ?? [];

		foreach ( $fields as $field ) {
			$type  = $field->type ?? '';
			$id    = (string) ( $field->id ?? '' );
			$label = $field->label ?? '';

			// GF name fields have sub-inputs: .3 = first, .6 = last.
			if ( 'name' === $type ) {
				$first = trim( (string) ( $entry[ $id . '.3' ] ?? '' ) );
				$last  = trim( (string) ( $entry[ $id . '.6' ] ?? '' ) );
				$value = trim( $first . ' ' . $last );
				// Fall back to the main field value if no sub-inputs.
				if ( '' === $value ) {
					$value = trim( (string) ( $entry[ $id ] ?? '' ) );
				}
			} else {
				$value = trim( (string) ( $entry[ $id ] ?? '' ) );
			}

			if ( '' === $value ) {
				continue;
			}

			$this->assign_field( $data, $type, $label, $value );
		}

		return $this->finalise( $data, $form['title'] ?? '' );
	}

	/**
	 * Extract lead data from a WPForms submission.
	 *
	 * Iterates submitted fields by type and auto-detects name, email,
	 * phone, company and description. Returns empty array if no valid email.
	 *
	 * @param array $fields    WPForms fields (id => ['type', 'value', 'name']).
	 * @param array $form_data WPForms form data with 'settings' => ['form_title'].
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_wpforms( array $fields, array $form_data ): array {
		$form_title = $form_data['settings']['form_title'] ?? __( 'Unknown Form', 'wp4odoo' );

		$data = [
			'name'        => '',
			'email'       => '',
			'phone'       => '',
			'company'     => '',
			'description' => '',
			'source'      => sprintf(
				/* translators: %s: form title */
				__( 'WPForms: %s', 'wp4odoo' ),
				$form_title
			),
		];

		foreach ( $fields as $field ) {
			$type  = $field['type'] ?? '';
			$value = trim( (string) ( $field['value'] ?? '' ) );
			$label = $field['name'] ?? '';

			if ( '' === $value ) {
				continue;
			}

			$this->assign_field( $data, $type, $label, $value );
		}

		return $this->finalise( $data, $form_title );
	}

	/**
	 * Check if a label matches company-related patterns.
	 *
	 * @param string $label Field label.
	 * @return bool
	 */
	public function is_company_label( string $label ): bool {
		$normalised = mb_strtolower( trim( $label ) );

		foreach ( self::COMPANY_LABELS as $pattern ) {
			if ( str_contains( $normalised, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a label matches name-related patterns.
	 *
	 * @param string $label Field label.
	 * @return bool
	 */
	public function is_name_label( string $label ): bool {
		$normalised = mb_strtolower( trim( $label ) );

		foreach ( self::NAME_LABELS as $pattern ) {
			if ( str_contains( $normalised, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	// ─── Private helpers ─────────────────────────────────────

	/**
	 * Assign a field value to the lead data array by type.
	 *
	 * @param array  $data  Lead data (passed by reference).
	 * @param string $type  Field type.
	 * @param string $label Field label.
	 * @param string $value Field value (non-empty).
	 * @return void
	 */
	private function assign_field( array &$data, string $type, string $label, string $value ): void {
		switch ( $type ) {
			case 'email':
				if ( '' === $data['email'] ) {
					$data['email'] = sanitize_email( $value );
				}
				break;

			case 'name':
				if ( '' === $data['name'] ) {
					$data['name'] = sanitize_text_field( $value );
				}
				break;

			case 'phone':
				if ( '' === $data['phone'] ) {
					$data['phone'] = sanitize_text_field( $value );
				}
				break;

			case 'textarea':
				if ( '' === $data['description'] ) {
					$data['description'] = sanitize_textarea_field( $value );
				}
				break;

			case 'text':
				if ( '' === $data['company'] && $this->is_company_label( $label ) ) {
					$data['company'] = sanitize_text_field( $value );
				} elseif ( '' === $data['name'] && $this->is_name_label( $label ) ) {
					$data['name'] = sanitize_text_field( $value );
				}
				break;
		}
	}

	/**
	 * Apply fallbacks and validate the extracted lead data.
	 *
	 * @param array  $data       Lead data.
	 * @param string $form_title Form title (for debug logging).
	 * @return array Finalised data, or empty array if invalid.
	 */
	private function finalise( array $data, string $form_title ): array {
		// Fallback: use email as name if no name found.
		if ( '' === $data['name'] && '' !== $data['email'] ) {
			$data['name'] = $data['email'];
		}

		// Validation: email is required for crm.lead.
		if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
			$this->logger->debug(
				'Skipping form submission: no valid email found.',
				[ 'form_title' => $form_title ]
			);
			return [];
		}

		return $data;
	}
}
