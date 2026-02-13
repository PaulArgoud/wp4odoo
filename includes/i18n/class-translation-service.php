<?php
declare( strict_types=1 );

namespace WP4Odoo\I18n;

use WP4Odoo\API\Odoo_Client;
use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation Service — cross-cutting service for multilingual sync.
 *
 * Detects the active translation plugin (WPML or Polylang), maps
 * WordPress language codes to Odoo locales, and pushes translated
 * field values to Odoo using the appropriate method for the Odoo
 * version (context-based for 16+, ir.translation for 14-15).
 *
 * This is not a module but a shared service, lazy-instantiated
 * from Module_Helpers (same pattern as Partner_Service).
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class Translation_Service {

	/**
	 * Closure that returns the shared Odoo_Client instance.
	 *
	 * @var \Closure(): Odoo_Client
	 */
	private \Closure $client_getter;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Cached adapter instance (null = not yet detected, false = none available).
	 *
	 * @var Translation_Adapter|false|null
	 */
	private Translation_Adapter|false|null $adapter = null;

	/**
	 * In-memory cache for has_ir_translation() result.
	 *
	 * @var bool|null
	 */
	private ?bool $has_ir_translation_cache = null;

	/**
	 * WordPress 2-letter language code → Odoo locale mapping.
	 *
	 * Covers all major Odoo-supported languages. Filterable via
	 * 'wp4odoo_odoo_locale' for custom mappings.
	 *
	 * @var array<string, string>
	 */
	private const LOCALE_MAP = [
		'en' => 'en_US',
		'fr' => 'fr_FR',
		'es' => 'es_ES',
		'de' => 'de_DE',
		'it' => 'it_IT',
		'pt' => 'pt_BR',
		'nl' => 'nl_NL',
		'ru' => 'ru_RU',
		'zh' => 'zh_CN',
		'ja' => 'ja_JP',
		'ko' => 'ko_KR',
		'ar' => 'ar_001',
		'pl' => 'pl_PL',
		'sv' => 'sv_SE',
		'da' => 'da_DK',
		'nb' => 'nb_NO',
		'fi' => 'fi_FI',
		'cs' => 'cs_CZ',
		'tr' => 'tr_TR',
		'uk' => 'uk_UA',
		'ro' => 'ro_RO',
		'hu' => 'hu_HU',
		'el' => 'el_GR',
		'he' => 'he_IL',
		'th' => 'th_TH',
		'vi' => 'vi_VN',
		'id' => 'id_ID',
		'ca' => 'ca_ES',
		'hr' => 'hr_HR',
		'bg' => 'bg_BG',
		'sk' => 'sk_SK',
		'sl' => 'sl_SI',
		'et' => 'et_EE',
		'lv' => 'lv_LV',
		'lt' => 'lt_LT',
	];

	/**
	 * Transient key for ir.translation model probe.
	 *
	 * @var string
	 */
	private const TRANSIENT_IR_TRANSLATION = 'wp4odoo_has_ir_translation';

	/**
	 * Constructor.
	 *
	 * @param \Closure $client_getter Returns the shared Odoo_Client instance.
	 */
	public function __construct( \Closure $client_getter ) {
		$this->client_getter = $client_getter;
		$this->logger        = new Logger( 'i18n' );
	}

	// ─── Adapter detection ──────────────────────────────────

	/**
	 * Detect the active translation adapter.
	 *
	 * WPML is checked first (higher market share), then Polylang.
	 * Returns null if neither plugin is active.
	 *
	 * @return Translation_Adapter|null
	 */
	public function get_adapter(): ?Translation_Adapter {
		if ( null === $this->adapter ) {
			$this->adapter = $this->detect_adapter();
		}

		return $this->adapter instanceof Translation_Adapter ? $this->adapter : null;
	}

	/**
	 * Check if any translation plugin is active.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return null !== $this->get_adapter();
	}

	/**
	 * Detect which adapter to use.
	 *
	 * @return Translation_Adapter|false Adapter instance, or false if none.
	 */
	private function detect_adapter(): Translation_Adapter|false {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && class_exists( 'SitePress' ) ) {
			return new WPML_Adapter();
		}

		if ( defined( 'POLYLANG_VERSION' ) && function_exists( 'pll_default_language' ) ) {
			return new Polylang_Adapter();
		}

		return false;
	}

	// ─── Locale mapping ─────────────────────────────────────

	/**
	 * Map a WordPress language code to an Odoo locale.
	 *
	 * Uses the static LOCALE_MAP, with a fallback to {lang}_{LANG}
	 * format. Filterable via 'wp4odoo_odoo_locale'.
	 *
	 * @param string $wp_lang WordPress 2-letter language code (e.g. 'fr').
	 * @return string Odoo locale (e.g. 'fr_FR').
	 */
	public function wp_to_odoo_locale( string $wp_lang ): string {
		$wp_lang = strtolower( substr( $wp_lang, 0, 2 ) );

		$locale = self::LOCALE_MAP[ $wp_lang ] ?? ( $wp_lang . '_' . strtoupper( $wp_lang ) );

		/**
		 * Filter the Odoo locale for a WordPress language code.
		 *
		 * @since 3.0.0
		 *
		 * @param string $locale  The resolved Odoo locale.
		 * @param string $wp_lang The WordPress language code.
		 */
		return (string) apply_filters( 'wp4odoo_odoo_locale', $locale, $wp_lang );
	}

	// ─── Odoo version detection ─────────────────────────────

	/**
	 * Check if the Odoo instance uses ir.translation (Odoo < 16).
	 *
	 * Odoo 16+ replaced ir.translation with JSONB inline columns.
	 * Cached in-memory and via transient (1 hour).
	 *
	 * @return bool True if ir.translation model exists (Odoo 14-15).
	 */
	public function has_ir_translation(): bool {
		if ( null !== $this->has_ir_translation_cache ) {
			return $this->has_ir_translation_cache;
		}

		$cached = get_transient( self::TRANSIENT_IR_TRANSLATION );
		if ( false !== $cached ) {
			$this->has_ir_translation_cache = (bool) $cached;
			return $this->has_ir_translation_cache;
		}

		try {
			$count  = $this->client()->search_count(
				'ir.model',
				[ [ 'model', '=', 'ir.translation' ] ]
			);
			$result = $count > 0;
		} catch ( \Exception $e ) {
			$this->logger->warning( 'Could not probe ir.translation model.', [ 'error' => $e->getMessage() ] );
			$result = false;
		}

		set_transient( self::TRANSIENT_IR_TRANSLATION, $result ? 1 : 0, HOUR_IN_SECONDS );
		$this->has_ir_translation_cache = $result;

		return $result;
	}

	// ─── Push translations ──────────────────────────────────

	/**
	 * Push translated field values to Odoo for a specific record.
	 *
	 * For Odoo 16+ (no ir.translation): writes directly with
	 * context={'lang': 'fr_FR'} so Odoo stores translations in
	 * JSONB columns.
	 *
	 * For Odoo 14-15 (ir.translation exists): writes to the
	 * ir.translation model directly with type='model'.
	 *
	 * @param string               $model   Odoo model (e.g. 'product.product').
	 * @param int                  $odoo_id Odoo record ID.
	 * @param array<string, mixed> $values  Field values to translate.
	 * @param string               $wp_lang WP language code (e.g. 'fr').
	 * @return void
	 */
	public function push_translation( string $model, int $odoo_id, array $values, string $wp_lang ): void {
		if ( empty( $values ) || $odoo_id <= 0 ) {
			return;
		}

		$odoo_locale = $this->wp_to_odoo_locale( $wp_lang );

		if ( $this->has_ir_translation() ) {
			$this->push_via_ir_translation( $model, $odoo_id, $values, $odoo_locale );
		} else {
			$this->push_via_context( $model, $odoo_id, $values, $odoo_locale );
		}
	}

	/**
	 * Push translations using context (Odoo 16+).
	 *
	 * @param string               $model       Odoo model name.
	 * @param int                  $odoo_id     Record ID.
	 * @param array<string, mixed> $values      Translated field values.
	 * @param string               $odoo_locale Odoo locale (e.g. 'fr_FR').
	 * @return void
	 */
	private function push_via_context( string $model, int $odoo_id, array $values, string $odoo_locale ): void {
		try {
			$this->client()->write( $model, [ $odoo_id ], $values, [ 'lang' => $odoo_locale ] );

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

	/**
	 * Push translations using ir.translation (Odoo 14-15).
	 *
	 * For each field, searches for an existing ir.translation record
	 * and updates it, or creates a new one.
	 *
	 * @param string               $model       Odoo model name.
	 * @param int                  $odoo_id     Record ID.
	 * @param array<string, mixed> $values      Translated field values.
	 * @param string               $odoo_locale Odoo locale (e.g. 'fr_FR').
	 * @return void
	 */
	private function push_via_ir_translation( string $model, int $odoo_id, array $values, string $odoo_locale ): void {
		$client = $this->client();

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

	/**
	 * Get the Odoo client instance.
	 *
	 * @return Odoo_Client
	 */
	private function client(): Odoo_Client {
		return ( $this->client_getter )();
	}
}
