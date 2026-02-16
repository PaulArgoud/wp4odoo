<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

use WP4Odoo\API\Odoo_Client;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modern translation strategy — Odoo 16+ context-based translations.
 *
 * Odoo 16+ stores translations in JSONB inline columns. Reading and
 * writing translated values is done via the standard read/write API
 * with a `{'lang': 'fr_FR'}` context parameter.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class Translation_Strategy_Modern implements Translation_Strategy {

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
		return $client->read( $model, $odoo_ids, array_merge( [ 'id' ], $fields ), [ 'lang' => $odoo_locale ] );
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
		try {
			$client->write( $model, [ $odoo_id ], $values, [ 'lang' => $odoo_locale ] );

			$this->logger->info(
				'Pushed translation via context.',
				[
					'model'  => $model,
					'id'     => $odoo_id,
					'locale' => $odoo_locale,
					'fields' => array_keys( $values ),
				]
			);
		} catch ( \Exception $e ) {
			$this->logger->warning(
				'Failed to push translation via context.',
				[
					'model'  => $model,
					'id'     => $odoo_id,
					'locale' => $odoo_locale,
					'error'  => $e->getMessage(),
				]
			);
		}
	}
}
