<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base for event plugin handlers.
 *
 * Provides the shared Logger dependency, dual-model event formatting
 * (event.event / calendar.event), Odoo-to-WP event parsing, and
 * attendance (event.registration) formatting used by
 * Events_Calendar_Handler, MEC_Handler, and FooEvents_Handler.
 *
 * @package WP4Odoo
 * @since   3.9.1
 */
abstract class Events_Handler_Base {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	// ─── Format event ─────────────────────────────────────

	/**
	 * Format event data for Odoo.
	 *
	 * Returns data formatted for event.event or calendar.event depending
	 * on the $use_event_model flag.
	 *
	 * @param array<string, mixed> $data            Event data from load_event().
	 * @param bool                 $use_event_model True for event.event, false for calendar.event.
	 * @return array<string, mixed> Odoo-ready data.
	 */
	public function format_event( array $data, bool $use_event_model ): array {
		if ( $use_event_model ) {
			return [
				'name'        => $data['name'] ?? '',
				'date_begin'  => $data['start_date'] ?? '',
				'date_end'    => $data['end_date'] ?? '',
				'date_tz'     => ( $data['timezone'] ?? '' ) ?: 'UTC',
				'description' => $data['description'] ?? '',
			];
		}

		return [
			'name'        => $data['name'] ?? '',
			'start'       => $data['start_date'] ?? '',
			'stop'        => $data['end_date'] ?? '',
			'allday'      => $data['all_day'] ?? false,
			'description' => $data['description'] ?? '',
		];
	}

	// ─── Parse event from Odoo ────────────────────────────

	/**
	 * Parse Odoo event data into WordPress-compatible format.
	 *
	 * Reverse of format_event(). Handles both event.event and
	 * calendar.event field layouts.
	 *
	 * @param array<string, mixed> $odoo_data       Odoo record data.
	 * @param bool                 $use_event_model True for event.event, false for calendar.event.
	 * @return array<string, mixed> WordPress event data.
	 */
	public function parse_event_from_odoo( array $odoo_data, bool $use_event_model ): array {
		if ( $use_event_model ) {
			return [
				'name'        => $odoo_data['name'] ?? '',
				'start_date'  => $odoo_data['date_begin'] ?? '',
				'end_date'    => $odoo_data['date_end'] ?? '',
				'timezone'    => $odoo_data['date_tz'] ?? 'UTC',
				'description' => $odoo_data['description'] ?? '',
			];
		}

		return [
			'name'        => $odoo_data['name'] ?? '',
			'start_date'  => $odoo_data['start'] ?? '',
			'end_date'    => $odoo_data['stop'] ?? '',
			'timezone'    => '',
			'description' => $odoo_data['description'] ?? '',
		];
	}

	// ─── Format attendance ────────────────────────────────

	/**
	 * Format attendance data for Odoo event.registration.
	 *
	 * @param array<string, mixed> $data          Attendee/booking data.
	 * @param int                  $partner_id    Resolved Odoo partner ID.
	 * @param int                  $event_odoo_id Resolved Odoo event ID.
	 * @return array<string, mixed> Data for event.registration create/write.
	 */
	public function format_attendance( array $data, int $partner_id, int $event_odoo_id ): array {
		return [
			'event_id'   => $event_odoo_id,
			'partner_id' => $partner_id,
			'name'       => $data['name'] ?? '',
			'email'      => $data['email'] ?? '',
		];
	}
}
