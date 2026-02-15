<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Module_Base;
use WP4Odoo\Odoo_Model;
use WP4Odoo\Field_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for helpdesk/support ticket modules
 * (Awesome Support, SupportCandy).
 *
 * Provides shared sync logic for modules that sync support tickets
 * to Odoo helpdesk tickets (helpdesk.ticket — Enterprise) or project
 * tasks (project.task — Community fallback).
 *
 * Dual-model: probes Odoo for helpdesk.ticket at runtime.
 * If available, uses helpdesk.ticket with team_id.
 * If not, falls back to project.task with project_id.
 *
 * Bidirectional: tickets are pushed to Odoo, status updates are
 * pulled back from Odoo. Only status is pulled — ticket creation
 * and deletion from Odoo are not supported.
 *
 * Subclasses provide plugin-specific handler delegation via
 * abstract methods (all one-liners).
 *
 * @package WP4Odoo
 * @since   3.0.0
 */
abstract class Helpdesk_Module_Base extends Module_Base {

	// ─── Subclass configuration ─────────────────────────────

	/**
	 * Get the WP status string that means "closed" for this plugin.
	 *
	 * @return string E.g. 'closed' for Awesome Support.
	 */
	abstract protected function get_closed_status(): string;

	// ─── Handler delegation ─────────────────────────────────

	/**
	 * Load a ticket from the plugin's data store.
	 *
	 * Must return: name, description, _user_id, _wp_status, priority.
	 *
	 * @param int $ticket_id Plugin-native ticket ID.
	 * @return array<string, mixed> Ticket data, or empty if not found.
	 */
	abstract protected function handler_load_ticket( int $ticket_id ): array;

	/**
	 * Save a ticket status from Odoo to the plugin's data store.
	 *
	 * @param int    $ticket_id WordPress ticket ID.
	 * @param string $wp_status Target WP status.
	 * @return bool True on success.
	 */
	abstract protected function handler_save_ticket_status( int $ticket_id, string $wp_status ): bool;

	/**
	 * Parse an Odoo ticket record into WordPress format.
	 *
	 * Must return: _stage_name (string), priority (string|null).
	 *
	 * @param array<string, mixed> $odoo_data   Raw Odoo record data.
	 * @param bool                 $is_helpdesk Whether the Odoo model is helpdesk.ticket.
	 * @return array<string, mixed>
	 */
	abstract protected function handler_parse_ticket_from_odoo( array $odoo_data, bool $is_helpdesk ): array;

	// ─── Shared sync direction ──────────────────────────────

	/**
	 * Sync direction: bidirectional (tickets → Odoo, status ← Odoo).
	 *
	 * @return string
	 */
	public function get_sync_direction(): string {
		return 'bidirectional';
	}

	protected string $exclusive_group = 'helpdesk';

	// ─── Deduplication ─────────────────────────────────────

	/**
	 * Deduplication domain for search-before-create.
	 *
	 * Tickets dedup by name (subject line).
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_values Odoo-ready field values.
	 * @return array Odoo domain filter, or empty to skip dedup.
	 */
	protected function get_dedup_domain( string $entity_type, array $odoo_values ): array {
		if ( 'ticket' === $entity_type && ! empty( $odoo_values['name'] ) ) {
			return [ [ 'name', '=', $odoo_values['name'] ] ];
		}

		return [];
	}

	// ─── Dual-model detection ───────────────────────────────

	/**
	 * Check whether Odoo has the helpdesk.ticket model (Enterprise).
	 *
	 * @return bool
	 */
	protected function has_helpdesk_model(): bool {
		return $this->has_odoo_model( Odoo_Model::HelpdeskTicket, 'wp4odoo_has_helpdesk_ticket' );
	}

	/**
	 * Resolve the Odoo model for an entity type at runtime.
	 *
	 * Falls back to project.task when helpdesk.ticket is unavailable.
	 *
	 * @param string $entity_type Entity type.
	 * @return string Odoo model name.
	 */
	protected function get_odoo_model( string $entity_type ): string {
		if ( 'ticket' === $entity_type && ! $this->has_helpdesk_model() ) {
			return Odoo_Model::ProjectTask->value;
		}

		return parent::get_odoo_model( $entity_type );
	}

	// ─── Push override ──────────────────────────────────────

	/**
	 * Load WordPress data for an entity.
	 *
	 * Loads ticket data from the handler, resolves the customer to
	 * an Odoo partner, and injects team_id or project_id from settings.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       Plugin entity ID.
	 * @return array<string, mixed>
	 */
	protected function load_wp_data( string $entity_type, int $wp_id ): array {
		if ( 'ticket' !== $entity_type ) {
			return [];
		}

		$data = $this->handler_load_ticket( $wp_id );
		if ( empty( $data ) ) {
			return [];
		}

		// Resolve customer → Odoo partner.
		$user_id = (int) ( $data['_user_id'] ?? 0 );
		if ( $user_id > 0 ) {
			$partner_id = $this->resolve_partner_from_user( $user_id );
			if ( $partner_id ) {
				$data['partner_id'] = $partner_id;
			}
		}

		// Inject Odoo team/project from settings.
		$settings    = $this->get_settings();
		$is_helpdesk = $this->has_helpdesk_model();

		if ( $is_helpdesk ) {
			$team_id = (int) ( $settings['odoo_team_id'] ?? 0 );
			if ( $team_id > 0 ) {
				$data['team_id'] = $team_id;
			}
		} else {
			$project_id = (int) ( $settings['odoo_project_id'] ?? 0 );
			if ( $project_id > 0 ) {
				$data['project_id'] = $project_id;
			}
			// project.task uses user_ids Many2many for assignment.
			if ( ! empty( $data['partner_id'] ) ) {
				$data['user_ids'] = [ [ 4, $data['partner_id'], 0 ] ];
			}
		}

		return $data;
	}

	/**
	 * Map WP data to Odoo values.
	 *
	 * Tickets use identity mapping (data is pre-formatted by load_wp_data).
	 * On ticket close, inject the resolved closed stage_id.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $wp_data     WordPress data from load_wp_data().
	 * @return array<string, mixed> Odoo-ready data.
	 */
	public function map_to_odoo( string $entity_type, array $wp_data ): array {
		if ( 'ticket' !== $entity_type ) {
			return parent::map_to_odoo( $entity_type, $wp_data );
		}

		// Start with mapped fields from parent.
		$mapped = parent::map_to_odoo( $entity_type, $wp_data );

		// Inject team_id / project_id / user_ids from pre-formatted data.
		foreach ( [ 'team_id', 'project_id', 'user_ids', 'partner_id' ] as $key ) {
			if ( isset( $wp_data[ $key ] ) ) {
				$mapped[ $key ] = $wp_data[ $key ];
			}
		}

		// On ticket close, resolve the closed stage_id.
		$wp_status = $wp_data['_wp_status'] ?? '';
		if ( $wp_status === $this->get_closed_status() ) {
			$stage_id = $this->resolve_closed_stage_id();
			if ( $stage_id > 0 ) {
				$mapped['stage_id'] = $stage_id;
			}
		}

		// Remove internal keys not for Odoo.
		unset( $mapped['_user_id'], $mapped['_wp_status'] );

		return $mapped;
	}

	// ─── Pull override ─────────────────────────────────────

	/**
	 * Pull an Odoo ticket status to WordPress.
	 *
	 * Only update actions are pulled — no create/delete from Odoo.
	 * Gated on the pull_tickets setting.
	 *
	 * @param string $entity_type Entity type.
	 * @param string $action      'create', 'update', or 'delete'.
	 * @param int    $odoo_id     Odoo record ID.
	 * @param int    $wp_id       WordPress entity ID (0 if unknown).
	 * @param array  $payload     Additional data.
	 * @return \WP4Odoo\Sync_Result
	 */
	public function pull_from_odoo( string $entity_type, string $action, int $odoo_id, int $wp_id = 0, array $payload = [] ): \WP4Odoo\Sync_Result {
		$settings = $this->get_settings();
		if ( empty( $settings['pull_tickets'] ) ) {
			return \WP4Odoo\Sync_Result::success();
		}

		// Only pull status updates — no create/delete.
		if ( 'update' !== $action ) {
			$this->logger->info(
				"Ticket pull only supports 'update' action.",
				[
					'action'  => $action,
					'odoo_id' => $odoo_id,
				]
			);
			return \WP4Odoo\Sync_Result::success();
		}

		return parent::pull_from_odoo( $entity_type, $action, $odoo_id, $wp_id, $payload );
	}

	/**
	 * Map Odoo ticket data to WordPress format for pull.
	 *
	 * Delegates to handler for stage name extraction.
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $odoo_data   Raw Odoo record data.
	 * @return array<string, mixed>
	 */
	public function map_from_odoo( string $entity_type, array $odoo_data ): array {
		if ( 'ticket' === $entity_type ) {
			return $this->handler_parse_ticket_from_odoo( $odoo_data, $this->has_helpdesk_model() );
		}

		return parent::map_from_odoo( $entity_type, $odoo_data );
	}

	/**
	 * Save pulled ticket data to WordPress.
	 *
	 * Resolves stage_name → WP status via keyword heuristic, then
	 * delegates to handler_save_ticket_status().
	 *
	 * @param string $entity_type Entity type.
	 * @param array  $data        Mapped data.
	 * @param int    $wp_id       WordPress ticket ID.
	 * @return int The ticket ID on success, 0 on failure.
	 */
	protected function save_wp_data( string $entity_type, array $data, int $wp_id = 0 ): int {
		if ( 'ticket' !== $entity_type || $wp_id <= 0 ) {
			return 0;
		}

		$stage_name = $data['_stage_name'] ?? '';
		$wp_status  = $this->resolve_wp_status_from_stage( $stage_name );

		$success = $this->handler_save_ticket_status( $wp_id, $wp_status );

		return $success ? $wp_id : 0;
	}

	/**
	 * Delete a WordPress entity during pull.
	 *
	 * Tickets are not deleted from Odoo side.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress entity ID.
	 * @return bool
	 */
	protected function delete_wp_data( string $entity_type, int $wp_id ): bool {
		return false;
	}

	// ─── Stage resolution ───────────────────────────────────

	/**
	 * Resolve the closed stage ID in Odoo.
	 *
	 * Searches for stages with the highest sequence number (= last stage
	 * in the pipeline, typically the "done/closed" stage). Result is
	 * cached for 1 hour via a transient.
	 *
	 * @return int Odoo stage ID, or 0 if not found.
	 */
	private function resolve_closed_stage_id(): int {
		$transient_key = 'wp4odoo_helpdesk_closed_stage_' . $this->id;
		$cached        = \get_transient( $transient_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$is_helpdesk = $this->has_helpdesk_model();
		$settings    = $this->get_settings();

		try {
			$client = $this->client();

			if ( $is_helpdesk ) {
				$team_id = (int) ( $settings['odoo_team_id'] ?? 0 );
				$domain  = $team_id > 0
					? [ [ 'team_ids', 'in', [ $team_id ] ] ]
					: [];

				$stages = $client->search_read(
					Odoo_Model::HelpdeskStage->value,
					$domain,
					[ 'id' ],
					0,
					1,
					'sequence DESC'
				);
			} else {
				$project_id = (int) ( $settings['odoo_project_id'] ?? 0 );
				$domain     = $project_id > 0
					? [ [ 'project_ids', 'in', [ $project_id ] ] ]
					: [];

				$stages = $client->search_read(
					Odoo_Model::ProjectTaskType->value,
					$domain,
					[ 'id' ],
					0,
					1,
					'sequence DESC'
				);
			}

			$stage_id = ! empty( $stages ) ? (int) $stages[0]['id'] : 0;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to resolve closed stage.', [ 'error' => $e->getMessage() ] );
			$stage_id = 0;
		}

		if ( $stage_id > 0 ) {
			\set_transient( $transient_key, $stage_id, HOUR_IN_SECONDS );
		}

		return $stage_id;
	}

	/**
	 * Resolve a WP ticket status from an Odoo stage name.
	 *
	 * Uses keyword heuristic: if the stage name contains close/done/
	 * resolved/solved/cancel, it maps to the closed status. Everything
	 * else maps to 'open'.
	 *
	 * Filterable via `wp4odoo_helpdesk_reverse_stage_map`.
	 *
	 * @param string $stage_name Odoo stage display name.
	 * @return string WordPress ticket status.
	 */
	private function resolve_wp_status_from_stage( string $stage_name ): string {
		$stage_lower = strtolower( $stage_name );

		$closed_keywords = [ 'close', 'done', 'resolved', 'solved', 'cancel', 'ferm', 'termin', 'résolu' ];

		$is_closed = false;
		foreach ( $closed_keywords as $keyword ) {
			if ( str_contains( $stage_lower, $keyword ) ) {
				$is_closed = true;
				break;
			}
		}

		$wp_status = $is_closed ? $this->get_closed_status() : 'open';

		/**
		 * Filter the WP status resolved from an Odoo stage name.
		 *
		 * @param string $wp_status  Resolved WordPress ticket status.
		 * @param string $stage_name Odoo stage display name.
		 * @param string $module_id  Module identifier.
		 */
		return (string) \apply_filters( 'wp4odoo_helpdesk_reverse_stage_map', $wp_status, $stage_name, $this->id );
	}
}
