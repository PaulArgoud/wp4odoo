<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strategy-based field extractor for form plugin submissions.
 *
 * Each supported form plugin has a registered extraction strategy
 * dispatched via extract(). Strategies normalise raw form data
 * into a lead array for Lead_Manager::save_lead_data().
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class Form_Field_Extractor {

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

	private const NAME_LABELS = [
		'name',
		'nom',
		'nombre',
		'full name',
		'your name',
	];

	/**
	 * @var array<string, \Closure>
	 */
	private array $strategies = [];

	private Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->register_default_strategies();
	}

	/**
	 * Extract lead data using the strategy for the given plugin.
	 *
	 * @param string $plugin Plugin identifier.
	 * @param mixed  ...$args Strategy-specific arguments.
	 * @return array Normalised lead data, or empty array if invalid.
	 *
	 * @since 3.4.0
	 */
	public function extract( string $plugin, mixed ...$args ): array {
		if ( ! isset( $this->strategies[ $plugin ] ) ) {
			$this->logger->debug(
				'No extraction strategy registered.',
				[ 'plugin' => $plugin ]
			);
			return [];
		}

		return ( $this->strategies[ $plugin ] )( ...$args );
	}

	/**
	 * @since 3.4.0
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
	 * @since 3.4.0
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

	private function register_default_strategies(): void {
		$this->strategies['gravity_forms'] = $this->gravity_forms_strategy();
		$this->strategies['wpforms']       = $this->wpforms_strategy();
		$this->strategies['cf7']           = $this->cf7_strategy();
		$this->strategies['fluent_forms']  = $this->fluent_forms_strategy();
		$this->strategies['formidable']    = $this->formidable_strategy();
		$this->strategies['ninja_forms']   = $this->ninja_forms_strategy();
		$this->strategies['forminator']    = $this->forminator_strategy();
	}

	private function gravity_forms_strategy(): \Closure {
		return function ( array $entry, array $form ): array {
			$data = $this->empty_lead(
				sprintf(
					/* translators: %s: form title */
					__( 'Gravity Forms: %s', 'wp4odoo' ),
					$form['title'] ?? __( 'Unknown Form', 'wp4odoo' )
				)
			);

			$fields = $form['fields'] ?? [];

			foreach ( $fields as $field ) {
				$type  = $field->type ?? '';
				$id    = (string) ( $field->id ?? '' );
				$label = $field->label ?? '';

				if ( 'name' === $type ) {
					$first = trim( (string) ( $entry[ $id . '.3' ] ?? '' ) );
					$last  = trim( (string) ( $entry[ $id . '.6' ] ?? '' ) );
					$value = trim( $first . ' ' . $last );
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
		};
	}

	private function wpforms_strategy(): \Closure {
		return function ( array $fields, array $form_data ): array {
			$form_title = $form_data['settings']['form_title'] ?? __( 'Unknown Form', 'wp4odoo' );

			$normalised = [];
			foreach ( $fields as $field ) {
				$normalised[] = [
					'type'  => $field['type'] ?? '',
					'label' => $field['name'] ?? '',
					'value' => $field['value'] ?? '',
				];
			}

			return $this->extract_normalised(
				$normalised,
				sprintf(
					/* translators: %s: form title */
					__( 'WPForms: %s', 'wp4odoo' ),
					$form_title
				),
				$form_title
			);
		};
	}

	private function cf7_strategy(): \Closure {
		return function ( array $posted_data, array $tags, string $form_title ): array {
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

				$mapped = match ( $type ) {
					'tel'   => 'phone',
					default => $type,
				};

				$this->assign_field( $data, $mapped, $name, $value );
			}

			return $this->finalise( $data, $form_title );
		};
	}

	private function fluent_forms_strategy(): \Closure {
		return function ( array $form_data, string $form_title ): array {
			$data = $this->empty_lead(
				sprintf(
					/* translators: %s: form title */
					__( 'Fluent Forms: %s', 'wp4odoo' ),
					$form_title ?: __( 'Unknown Form', 'wp4odoo' )
				)
			);

			foreach ( $form_data as $name => $value ) {
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
		};
	}

	private function formidable_strategy(): \Closure {
		return function ( array $fields, string $form_title ): array {
			return $this->extract_normalised(
				$fields,
				sprintf(
					/* translators: %s: form title */
					__( 'Formidable: %s', 'wp4odoo' ),
					$form_title ?: __( 'Unknown Form', 'wp4odoo' )
				),
				$form_title
			);
		};
	}

	private function ninja_forms_strategy(): \Closure {
		return function ( array $fields, string $form_title ): array {
			$data = $this->empty_lead(
				sprintf(
					/* translators: %s: form title */
					__( 'Ninja Forms: %s', 'wp4odoo' ),
					$form_title ?: __( 'Unknown Form', 'wp4odoo' )
				)
			);

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

				$mapped = match ( $type ) {
					'textbox' => 'text',
					default   => $type,
				};

				$this->assign_field( $data, $mapped, $label, $value );
			}

			if ( '' === $data['name'] && ( '' !== $first_name || '' !== $last_name ) ) {
				$data['name'] = sanitize_text_field( trim( $first_name . ' ' . $last_name ) );
			}

			return $this->finalise( $data, $form_title );
		};
	}

	private function forminator_strategy(): \Closure {
		return function ( array $fields, string $form_title ): array {
			return $this->extract_normalised(
				$fields,
				sprintf(
					/* translators: %s: form title */
					__( 'Forminator: %s', 'wp4odoo' ),
					$form_title ?: __( 'Unknown Form', 'wp4odoo' )
				),
				$form_title
			);
		};
	}

	// ─── Shared pipeline ────────────────────────────────────

	private function extract_normalised( array $fields, string $source, string $form_title ): array {
		$data = $this->empty_lead( $source );

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

	// ─── Private helpers ────────────────────────────────────

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

	private function finalise( array $data, string $form_title ): array {
		if ( '' === $data['name'] && '' !== $data['email'] ) {
			$data['name'] = $data['email'];
		}

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
