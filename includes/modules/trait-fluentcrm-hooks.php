<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Queue_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FluentCRM hook callbacks for subscriber, list, and tag sync.
 *
 * Extracted from FluentCRM_Module for single responsibility.
 * Handles subscriber creation/status changes, list creation, and tag creation.
 *
 * Expects the using class to provide:
 * - should_sync(string $key): bool (from Module_Base)
 * - get_mapping(string $type, int $id): ?int (from Module_Base)
 *
 * @package WP4Odoo
 * @since   3.2.0
 */
trait FluentCRM_Hooks {

	/**
	 * Enqueue a subscriber create job when a new subscriber is added.
	 *
	 * @param mixed $subscriber The FluentCRM Subscriber object.
	 * @return void
	 */
	public function on_subscriber_created( $subscriber ): void {
		if ( ! $this->should_sync( 'sync_subscribers' ) ) {
			return;
		}

		$id = is_object( $subscriber ) ? ( $subscriber->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			Queue_Manager::push( 'fluentcrm', 'subscriber', 'create', $id );
		}
	}

	/**
	 * Enqueue a subscriber update job when status changes.
	 *
	 * @param mixed  $subscriber The FluentCRM Subscriber object.
	 * @param string $old_status The previous subscriber status.
	 * @return void
	 */
	public function on_subscriber_status_changed( $subscriber, string $old_status = '' ): void {
		if ( ! $this->should_sync( 'sync_subscribers' ) ) {
			return;
		}

		$id = is_object( $subscriber ) ? ( $subscriber->id ?? 0 ) : 0;
		if ( $id <= 0 ) {
			return;
		}

		$odoo_id = $this->get_mapping( 'subscriber', $id ) ?? 0;
		Queue_Manager::push( 'fluentcrm', 'subscriber', 'update', $id, $odoo_id );
	}

	/**
	 * Enqueue a list create job when a new mailing list is added.
	 *
	 * @param mixed $list The FluentCRM Lists object.
	 * @return void
	 */
	public function on_list_created( $list ): void {
		if ( ! $this->should_sync( 'sync_lists' ) ) {
			return;
		}

		$id = is_object( $list ) ? ( $list->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			Queue_Manager::push( 'fluentcrm', 'list', 'create', $id );
		}
	}

	/**
	 * Enqueue a tag create job when a new tag is added.
	 *
	 * @param mixed $tag The FluentCRM Tag object.
	 * @return void
	 */
	public function on_tag_created( $tag ): void {
		if ( ! $this->should_sync( 'sync_tags' ) ) {
			return;
		}

		$id = is_object( $tag ) ? ( $tag->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			Queue_Manager::push( 'fluentcrm', 'tag', 'create', $id );
		}
	}
}
