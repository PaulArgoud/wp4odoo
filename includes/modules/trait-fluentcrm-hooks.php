<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

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
 * - push_entity(string $type, string $key, int $id): void (from Module_Helpers)
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
		$id = is_object( $subscriber ) ? ( $subscriber->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			$this->push_entity( 'subscriber', 'sync_subscribers', $id );
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
		$id = is_object( $subscriber ) ? ( $subscriber->id ?? 0 ) : 0;
		if ( $id <= 0 ) {
			return;
		}

		$this->push_entity( 'subscriber', 'sync_subscribers', $id );
	}

	/**
	 * Enqueue a list create job when a new mailing list is added.
	 *
	 * @param mixed $list The FluentCRM Lists object.
	 * @return void
	 */
	public function on_list_created( $list ): void {
		$id = is_object( $list ) ? ( $list->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			$this->push_entity( 'list', 'sync_lists', $id );
		}
	}

	/**
	 * Enqueue a tag create job when a new tag is added.
	 *
	 * @param mixed $tag The FluentCRM Tag object.
	 * @return void
	 */
	public function on_tag_created( $tag ): void {
		$id = is_object( $tag ) ? ( $tag->id ?? 0 ) : 0;
		if ( $id > 0 ) {
			$this->push_entity( 'tag', 'sync_tags', $id );
		}
	}
}
