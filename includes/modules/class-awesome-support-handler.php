<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;
use WP4Odoo\Field_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Awesome Support Handler — data access for Awesome Support tickets.
 *
 * Awesome Support uses the `ticket` CPT with post meta for status
 * (_wpas_status) and priority (_wpas_priority).
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
class Awesome_Support_Handler {

	/**
	 * Priority map: WP priority → Odoo priority string.
	 *
	 * Odoo helpdesk.ticket priority: '0' = low, '1' = medium, '2' = high, '3' = urgent.
	 *
	 * @var array<string, string>
	 */
	private const PRIORITY_MAP = [
		'low'    => '0',
		'medium' => '1',
		'high'   => '2',
		'urgent' => '3',
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
	 * Load an Awesome Support ticket by ID.
	 *
	 * @param int $ticket_id Ticket post ID.
	 * @return array<string, mixed> Ticket data, or empty if not found.
	 */
	public function load_ticket( int $ticket_id ): array {
		if ( $ticket_id <= 0 ) {
			return [];
		}

		$post = \get_post( $ticket_id );
		if ( ! $post || 'ticket' !== $post->post_type ) {
			$this->logger->warning( 'Awesome Support ticket not found.', [ 'ticket_id' => $ticket_id ] );
			return [];
		}

		$status   = \get_post_meta( $ticket_id, '_wpas_status', true );
		$priority = \get_post_meta( $ticket_id, '_wpas_priority', true );

		return [
			'name'        => $post->post_title,
			'description' => $post->post_content,
			'_user_id'    => (int) $post->post_author,
			'_wp_status'  => ( is_string( $status ) && '' !== $status ) ? $status : 'open',
			'priority'    => $this->map_priority( is_string( $priority ) ? $priority : '' ),
		];
	}

	/**
	 * Save a ticket status from Odoo pull.
	 *
	 * @param int    $ticket_id WordPress ticket ID.
	 * @param string $wp_status Target WP status ('open' or 'closed').
	 * @return bool True on success.
	 */
	public function save_ticket_status( int $ticket_id, string $wp_status ): bool {
		if ( $ticket_id <= 0 ) {
			return false;
		}

		\wpas_update_ticket_status( $ticket_id, $wp_status );

		return true;
	}

	/**
	 * Parse an Odoo ticket record for pull.
	 *
	 * Extracts the stage_id Many2one name for status resolution
	 * by the base class.
	 *
	 * @param array<string, mixed> $odoo_data   Raw Odoo record.
	 * @param bool                 $is_helpdesk Whether model is helpdesk.ticket.
	 * @return array<string, mixed>
	 */
	public function parse_ticket_from_odoo( array $odoo_data, bool $is_helpdesk ): array {
		$stage_name = Field_Mapper::many2one_to_name( $odoo_data['stage_id'] ?? null ) ?? '';

		return [
			'_stage_name' => $stage_name,
		];
	}

	/**
	 * Map a WP priority string to an Odoo priority value.
	 *
	 * @param string $wp_priority Priority from post meta.
	 * @return string Odoo priority ('0'-'3').
	 */
	private function map_priority( string $wp_priority ): string {
		return self::PRIORITY_MAP[ strtolower( $wp_priority ) ] ?? '0';
	}
}
