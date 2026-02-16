<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MC4WP data handler — loads, saves, and parses Mailchimp for WP entities.
 *
 * Accesses MC4WP data via WordPress user meta:
 * - Subscribers stored as WP users with `mc4wp_subscribed_to` user_meta
 * - Lists cached in `$GLOBALS['_mc4wp_lists']` (test) or Mailchimp-side (prod)
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
class MC4WP_Handler {

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

	// ─── Load methods ────────────────────────────────────────

	/**
	 * Load a MC4WP subscriber by WP user ID.
	 *
	 * MC4WP stores subscriptions per-user in user_meta `mc4wp_subscribed_to`
	 * (array of Mailchimp list IDs).
	 *
	 * @param int $id WordPress user ID.
	 * @return array Subscriber data or empty array if not found.
	 */
	public function load_subscriber( int $id ): array {
		$user = get_userdata( $id );

		if ( ! $user ) {
			$this->logger->warning( 'MC4WP subscriber not found.', [ 'id' => $id ] );
			return [];
		}

		$list_ids = get_user_meta( $id, 'mc4wp_subscribed_to', true );
		if ( ! is_array( $list_ids ) ) {
			$list_ids = [];
		}

		$status = ! empty( $list_ids ) ? 'subscribed' : 'unsubscribed';

		return [
			'id'         => (int) $user->ID,
			'email'      => $user->user_email ?? '',
			'first_name' => $user->first_name ?? '',
			'last_name'  => $user->last_name ?? '',
			'status'     => $status,
			'list_ids'   => $list_ids,
		];
	}

	/**
	 * Load a MC4WP list by ID.
	 *
	 * MC4WP caches Mailchimp lists in transients. For testing purposes,
	 * reads from `$GLOBALS['_mc4wp_lists'][$id]`.
	 *
	 * @param int $id List ID.
	 * @return array List data or empty array if not found.
	 */
	public function load_list( int $id ): array {
		$list = $GLOBALS['_mc4wp_lists'][ $id ] ?? null;

		if ( empty( $list ) ) {
			$this->logger->warning( 'MC4WP list not found.', [ 'id' => $id ] );
			return [];
		}

		return [
			'id'          => (int) ( $list['id'] ?? 0 ),
			'title'       => $list['name'] ?? '',
			'description' => $list['description'] ?? '',
		];
	}

	// ─── Parse from Odoo ─────────────────────────────────────

	/**
	 * Parse subscriber data from Odoo mailing.contact record.
	 *
	 * Splits the Odoo name field into first_name and last_name.
	 *
	 * @param array $odoo_data Odoo record data.
	 * @return array WordPress-compatible subscriber data.
	 */
	public function parse_subscriber_from_odoo( array $odoo_data ): array {
		$name_parts = explode( ' ', $odoo_data['name'] ?? '', 2 );

		return [
			'email'      => $odoo_data['email'] ?? '',
			'first_name' => $name_parts[0],
			'last_name'  => $name_parts[1] ?? '',
			'status'     => $odoo_data['x_status'] ?? 'subscribed',
		];
	}

	/**
	 * Parse list data from Odoo mailing.list record.
	 *
	 * @param array $odoo_data Odoo record data.
	 * @return array WordPress-compatible list data.
	 */
	public function parse_list_from_odoo( array $odoo_data ): array {
		return [
			'title'       => $odoo_data['name'] ?? '',
			'description' => $odoo_data['x_description'] ?? '',
		];
	}

	// ─── Save methods (pull) ─────────────────────────────────

	/**
	 * Save a subscriber via WordPress user API.
	 *
	 * Creates or updates a WP user with mc4wp meta. If wp_id is 0,
	 * searches by email via get_user_by().
	 *
	 * @param array $data  Subscriber data.
	 * @param int   $wp_id Existing WP user ID (0 if new).
	 * @return int The WP user ID (0 on failure).
	 */
	public function save_subscriber( array $data, int $wp_id = 0 ): int {
		try {
			$email = $data['email'] ?? '';

			// Resolve existing user by email if no wp_id given.
			if ( 0 === $wp_id && '' !== $email ) {
				$existing = get_user_by( 'email', $email );
				if ( $existing ) {
					$wp_id = (int) $existing->ID;
				}
			}

			if ( $wp_id > 0 ) {
				wp_update_user(
					[
						'ID'         => $wp_id,
						'user_email' => $email,
						'first_name' => $data['first_name'] ?? '',
						'last_name'  => $data['last_name'] ?? '',
					]
				);
			} else {
				$wp_id = (int) wp_insert_user(
					[
						'user_email' => $email,
						'user_login' => $email,
						'first_name' => $data['first_name'] ?? '',
						'last_name'  => $data['last_name'] ?? '',
					]
				);
				if ( $wp_id <= 0 ) {
					return 0;
				}
			}

			// Update mc4wp subscription meta.
			$status   = $data['status'] ?? 'subscribed';
			$list_ids = $data['list_ids'] ?? [];
			if ( 'subscribed' === $status && ! empty( $list_ids ) ) {
				update_user_meta( $wp_id, 'mc4wp_subscribed_to', $list_ids );
			} else {
				update_user_meta( $wp_id, 'mc4wp_subscribed_to', [] );
			}

			return $wp_id;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Failed to save MC4WP subscriber.', [ 'error' => $e->getMessage() ] );
			return 0;
		}
	}

	/**
	 * Save a list.
	 *
	 * In production, lists are managed on the Mailchimp side.
	 * For testing, stores in `$GLOBALS['_mc4wp_lists']`.
	 *
	 * @param array $data  List data.
	 * @param int   $wp_id Existing list ID (0 if new).
	 * @return int The list ID (0 on failure).
	 */
	public function save_list( array $data, int $wp_id = 0 ): int {
		try {
			$values = [
				'name'        => $data['title'] ?? '',
				'description' => $data['description'] ?? '',
			];

			if ( $wp_id > 0 ) {
				if ( isset( $GLOBALS['_mc4wp_lists'][ $wp_id ] ) ) {
					$GLOBALS['_mc4wp_lists'][ $wp_id ] = array_merge(
						$GLOBALS['_mc4wp_lists'][ $wp_id ],
						$values
					);
				} else {
					$values['id']                      = $wp_id;
					$GLOBALS['_mc4wp_lists'][ $wp_id ] = $values;
				}
				return $wp_id;
			}

			$id                             = count( $GLOBALS['_mc4wp_lists'] ) + 1;
			$values['id']                   = $id;
			$GLOBALS['_mc4wp_lists'][ $id ] = $values;
			return $id;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Failed to save MC4WP list.', [ 'error' => $e->getMessage() ] );
			return 0;
		}
	}
}
