<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts lead data from form plugin submissions.
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
	 * Extract lead data from a Contact Form 7 submission.
	 *
	 * @param array  $posted_data Posted data (field_name => value).
	 * @param array  $tags        Normalised tags: [['type' => basetype, 'name' => tag_name], …].
	 * @param string $form_title  Form title.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_cf7( array $posted_data, array $tags, string $form_title ): array {
		$data = $this->empty_lead(
			sprintf(
				/* translators: %s: form title */
				__( 'Contact Form 7: %s', 'wp4odoo' ),
				$form_title ?: __( 'Unknown Form', 'wp4odoo' )
			)
		);

		foreach ( $tags as $tag ) {
			$type  = $tag['type'] ?? '';
			$name  = $tag['name'] ?? '';
			$value = trim( (string) ( $posted_data[ $name ] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			// CF7 basetype mapping: tel → phone, rest maps directly.
			$mapped = match ( $type ) {
				'tel'   => 'phone',
				default => $type,
			};

			$this->assign_field( $data, $mapped, $name, $value );
		}

		return $this->finalise( $data, $form_title );
	}

	/**
	 * Extract lead data from a Fluent Forms submission.
	 *
	 * Fluent Forms field names often hint at the type (email, phone, names, message).
	 * The `names` field returns an associative array with first/last name parts.
	 *
	 * @param array  $form_data  Submitted data (field_name => value).
	 * @param string $form_title Form title.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_fluent_forms( array $form_data, string $form_title ): array {
		$data = $this->empty_lead(
			sprintf(
				/* translators: %s: form title */
				__( 'Fluent Forms: %s', 'wp4odoo' ),
				$form_title ?: __( 'Unknown Form', 'wp4odoo' )
			)
		);

		foreach ( $form_data as $name => $value ) {
			// Fluent Forms names field returns an array of parts.
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_filter( array_map( 'trim', $value ) ) );
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			$lower = mb_strtolower( $name );
			$type  = $this->infer_type_from_key( $lower );

			$this->assign_field( $data, $type, $name, $value );
		}

		return $this->finalise( $data, $form_title );
	}

	/**
	 * Extract lead data from a Formidable Forms submission.
	 *
	 * Receives pre-normalised fields from the callback (which loads
	 * field types and values via FrmField/FrmEntryMeta).
	 *
	 * @param array  $fields     Normalised fields: [['type', 'label', 'value'], …].
	 * @param string $form_title Form title.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_formidable( array $fields, string $form_title ): array {
		$data = $this->empty_lead(
			sprintf(
				/* translators: %s: form title */
				__( 'Formidable: %s', 'wp4odoo' ),
				$form_title ?: __( 'Unknown Form', 'wp4odoo' )
			)
		);

		foreach ( $fields as $field ) {
			$type  = $field['type'] ?? 'text';
			$label = $field['label'] ?? '';
			$value = trim( (string) ( $field['value'] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			$this->assign_field( $data, $type, $label, $value );
		}

		return $this->finalise( $data, $form_title );
	}

	/**
	 * Extract lead data from a Ninja Forms submission.
	 *
	 * Ninja Forms fields already carry type/label/value.
	 * Special handling: `textbox` → `text`, `firstname`/`lastname` → concatenated name.
	 *
	 * @param array  $fields     Normalised fields: [['type', 'label', 'value'], …].
	 * @param string $form_title Form title.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_ninja_forms( array $fields, string $form_title ): array {
		$data = $this->empty_lead(
			sprintf(
				/* translators: %s: form title */
				__( 'Ninja Forms: %s', 'wp4odoo' ),
				$form_title ?: __( 'Unknown Form', 'wp4odoo' )
			)
		);

		// Collect firstname/lastname for concatenation.
		$first_name = '';
		$last_name  = '';

		foreach ( $fields as $field ) {
			$type  = $field['type'] ?? 'textbox';
			$label = $field['label'] ?? '';
			$value = trim( (string) ( $field['value'] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			if ( 'firstname' === $type ) {
				$first_name = $value;
				continue;
			}
			if ( 'lastname' === $type ) {
				$last_name = $value;
				continue;
			}

			// Ninja Forms type mapping.
			$mapped = match ( $type ) {
				'textbox' => 'text',
				default   => $type,
			};

			$this->assign_field( $data, $mapped, $label, $value );
		}

		// Concatenate name parts if found.
		if ( '' === $data['name'] && ( '' !== $first_name || '' !== $last_name ) ) {
			$data['name'] = sanitize_text_field( trim( $first_name . ' ' . $last_name ) );
		}

		return $this->finalise( $data, $form_title );
	}

	/**
	 * Extract lead data from a Forminator submission.
	 *
	 * Receives pre-normalised fields from the callback.
	 * Forminator element IDs often embed the type (e.g. `email-1`, `text-2`).
	 *
	 * @param array  $fields     Normalised fields: [['type', 'label', 'value'], …].
	 * @param string $form_title Form title.
	 * @return array Normalised lead data, or empty array if invalid.
	 */
	public function extract_from_forminator( array $fields, string $form_title ): array {
		$data = $this->empty_lead(
			sprintf(
				/* translators: %s: form title */
				__( 'Forminator: %s', 'wp4odoo' ),
				$form_title ?: __( 'Unknown Form', 'wp4odoo' )
			)
		);

		foreach ( $fields as $field ) {
			$type  = $field['type'] ?? 'text';
			$label = $field['label'] ?? '';
			$value = trim( (string) ( $field['value'] ?? '' ) );

			if ( '' === $value ) {
				continue;
			}

			$this->assign_field( $data, $type, $label, $value );
		}

		return $this->finalise( $data, $form_title );
	}

	// ─── Label detection ────────────────────────────────────

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
	 * Create an empty lead data structure with a source string.
	 *
	 * @param string $source Source label.
	 * @return array<string, string>
	 */
	private function empty_lead( string $source ): array {
		return [
			'name'        => '',
			'email'       => '',
			'phone'       => '',
			'company'     => '',
			'description' => '',
			'source'      => $source,
		];
	}

	/**
	 * Infer a standard field type from a field key/name.
	 *
	 * Used by Fluent Forms where field names contain type hints
	 * (e.g. `email`, `phone`, `names`, `message`).
	 *
	 * @param string $key Lowercased field key.
	 * @return string Mapped type for assign_field().
	 */
	private function infer_type_from_key( string $key ): string {
		if ( str_contains( $key, 'email' ) ) {
			return 'email';
		}
		if ( str_contains( $key, 'phone' ) || str_contains( $key, 'tel' ) ) {
			return 'phone';
		}
		if ( str_contains( $key, 'name' ) ) {
			return 'name';
		}
		if ( str_contains( $key, 'message' ) || str_contains( $key, 'description' ) || str_contains( $key, 'comment' ) ) {
			return 'textarea';
		}

		return 'text';
	}

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
