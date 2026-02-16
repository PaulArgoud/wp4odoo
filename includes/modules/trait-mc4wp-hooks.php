<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MC4WP hook callbacks for subscriber sync.
 *
 * Extracted from MC4WP_Module for single responsibility.
 * Handles subscriber creation/updates from MC4WP form submissions
 * and integration subscriptions.
 *
 * Lists are managed on the Mailchimp side, so no list hooks are needed.
 *
 * Expects the using class to provide:
 * - push_entity(string $type, string $key, int $id): void (from Module_Helpers)
 *
 * @package WP4Odoo
 * @since   3.4.0
 */
trait MC4WP_Hooks {

	/**
	 * Register MC4WP hooks based on current settings.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->get_settings();

		if ( ! empty( $settings['sync_subscribers'] ) ) {
			add_action( 'mc4wp_form_subscribed', $this->safe_callback( [ $this, 'on_subscriber_created' ] ), 10, 2 );
			add_action( 'mc4wp_form_updated_subscriber', $this->safe_callback( [ $this, 'on_subscriber_updated' ] ), 10, 2 );
			add_action( 'mc4wp_integration_subscribed', $this->safe_callback( [ $this, 'on_integration_subscribed' ] ), 10, 3 );
		}
	}

	/**
	 * Enqueue a subscriber create job when a form subscription occurs.
	 *
	 * @param mixed $form         MC4WP_Form instance.
	 * @param mixed $request_data Form request data (contains EMAIL field).
	 * @return void
	 */
	public function on_subscriber_created( $form, $request_data = [] ): void {
		if ( ! $this->should_sync( 'sync_subscribers' ) ) {
			return;
		}

		$email = $this->extract_email_from_request( $request_data );
		$id    = $this->resolve_user_id_from_email( $email );

		if ( $id > 0 ) {
			$this->push_entity( 'subscriber', 'sync_subscribers', $id );
		}
	}

	/**
	 * Enqueue a subscriber update job when an existing subscriber is updated via form.
	 *
	 * @param mixed $form         MC4WP_Form instance.
	 * @param mixed $request_data Form request data (contains EMAIL field).
	 * @return void
	 */
	public function on_subscriber_updated( $form, $request_data = [] ): void {
		if ( ! $this->should_sync( 'sync_subscribers' ) ) {
			return;
		}

		$email = $this->extract_email_from_request( $request_data );
		$id    = $this->resolve_user_id_from_email( $email );

		if ( $id > 0 ) {
			$this->push_entity( 'subscriber', 'sync_subscribers', $id );
		}
	}

	/**
	 * Enqueue a subscriber create job when an integration subscription occurs.
	 *
	 * @param mixed  $integration Integration instance.
	 * @param string $email       Subscriber email address.
	 * @param array  $merge_vars  Merge variables.
	 * @return void
	 */
	public function on_integration_subscribed( $integration, $email = '', $merge_vars = [] ): void {
		if ( ! $this->should_sync( 'sync_subscribers' ) ) {
			return;
		}

		$id = $this->resolve_user_id_from_email( (string) $email );

		if ( $id > 0 ) {
			$this->push_entity( 'subscriber', 'sync_subscribers', $id );
		}
	}

	/**
	 * Extract email from MC4WP form request data.
	 *
	 * @param mixed $request_data Form request data.
	 * @return string Email address or empty string.
	 */
	private function extract_email_from_request( $request_data ): string {
		if ( is_array( $request_data ) && ! empty( $request_data['EMAIL'] ) ) {
			return (string) $request_data['EMAIL'];
		}
		return '';
	}

	/**
	 * Resolve a WP user ID from an email address.
	 *
	 * @param string $email Email address.
	 * @return int User ID or 0 if not found.
	 */
	private function resolve_user_id_from_email( string $email ): int {
		if ( '' === $email ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return (int) $user->ID;
		}

		return 0;
	}
}
