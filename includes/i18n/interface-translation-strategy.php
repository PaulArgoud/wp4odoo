<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

use WP4Odoo\API\Odoo_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation strategy interface — version-specific Odoo translation I/O.
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
interface Translation_Strategy {

	/**
	 * Read translated field values for a batch of Odoo records.
	 *
	 * @param Odoo_Client        $client      Odoo client instance.
	 * @param string             $model       Odoo model name.
	 * @param array<int, int>    $odoo_ids    Record IDs.
	 * @param array<int, string> $fields      Field names to read.
	 * @param string             $odoo_locale Odoo locale (e.g. 'fr_FR').
	 * @return array<int, array<string, mixed>> Records indexed by position.
	 */
	public function read_translated_batch(
		Odoo_Client $client,
		string $model,
		array $odoo_ids,
		array $fields,
		string $odoo_locale
	): array;

	/**
	 * Push translated field values to Odoo for a specific record.
	 *
	 * @param Odoo_Client        $client      Odoo client instance.
	 * @param string             $model       Odoo model name.
	 * @param int                $odoo_id     Record ID.
	 * @param array<string, mixed> $values    Translated field values.
	 * @param string             $odoo_locale Odoo locale (e.g. 'fr_FR').
	 * @return void
	 */
	public function push_translation(
		Odoo_Client $client,
		string $model,
		int $odoo_id,
		array $values,
		string $odoo_locale
	): void;
}
