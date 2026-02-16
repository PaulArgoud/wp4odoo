<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

use WP4Odoo\API\Odoo_Client;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy translation strategy — Odoo 14-15 ir.translation model.
 *
 * Odoo 14-15 stores translations in the `ir.translation` model.
 * Reading requires a search_read on ir.translation and reconstructing
 * per-record arrays. Writing requires searching for existing translation
 * records and updating or creating them.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class Translation_Strategy_Legacy implements Translation_Strategy {

	private Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read_translated_batch(
		Odoo_Client $client,
		string $model,
		array $odoo_ids,
		array $fields,
		string $odoo_locale
	): array {
		$field_names = array_map(
			fn( string $field ) => $model . ',' . $field,
			$fields
		);

		$translations = $client->search_read(
			'ir.translation',
			[
				[ 'type', '=', 'model' ],
				[ 'name', 'in', $field_names ],
				[ 'res_id', 'in', $odoo_ids ],
				[ 'lang', '=', $odoo_locale ],
			],
			[ 'name', 'res_id', 'value' ]
		);

		// Reconstruct per-record arrays: [id => [field => value, ...]].
		$result = [];
		foreach ( $translations as $row ) {
			$res_id = (int) ( $row['res_id'] ?? 0 );
			$name   = $row['name'] ?? '';
			$value  = $row['value'] ?? '';

			// Extract field name from "model,field".
			$parts = explode( ',', $name, 2 );
			$field = $parts[1] ?? '';

			if ( $res_id > 0 && '' !== $field ) {
				if ( ! isset( $result[ $res_id ] ) ) {
					$result[ $res_id ] = [ 'id' => $res_id ];
				}
				$result[ $res_id ][ $field ] = $value;
			}
		}

		return array_values( $result );
	}

	/**
	 * {@inheritDoc}
	 */
	public function push_translation(
		Odoo_Client $client,
		string $model,
		int $odoo_id,
		array $values,
		string $odoo_locale
	): void {
		foreach ( $values as $field => $value ) {
			$field_name = $model . ',' . $field;

			try {
				// Search for existing translation.
				$existing = $client->search(
					'ir.translation',
					[
						[ 'type', '=', 'model' ],
						[ 'name', '=', $field_name ],
						[ 'res_id', '=', $odoo_id ],
						[ 'lang', '=', $odoo_locale ],
					]
				);

				if ( ! empty( $existing ) ) {
					$client->write( 'ir.translation', $existing, [ 'value' => (string) $value ] );
				} else {
					$client->create(
						'ir.translation',
						[
							'type'   => 'model',
							'name'   => $field_name,
							'res_id' => $odoo_id,
							'lang'   => $odoo_locale,
							'src'    => '',
							'value'  => (string) $value,
						]
					);
				}

				$this->logger->info(
					'Pushed translation via ir.translation.',
					[
						'field'  => $field_name,
						'id'     => $odoo_id,
						'locale' => $odoo_locale,
					]
				);
			} catch ( \Exception $e ) {
				$this->logger->warning(
					'Failed to push translation via ir.translation.',
					[
						'field'  => $field_name,
						'id'     => $odoo_id,
						'locale' => $odoo_locale,
						'error'  => $e->getMessage(),
					]
				);
			}
		}
	}
}
