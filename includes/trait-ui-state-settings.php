<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UI state settings for onboarding, checklist, and cron health.
 *
 * Manages dismissed-state flags for the admin onboarding notice
 * and setup checklist, webhook confirmation, and WP-Cron health
 * monitoring.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
trait UI_State_Settings {

	public const OPT_ONBOARDING_DISMISSED = 'wp4odoo_onboarding_dismissed';
	public const OPT_CHECKLIST_DISMISSED  = 'wp4odoo_checklist_dismissed';
	public const OPT_CHECKLIST_WEBHOOKS   = 'wp4odoo_checklist_webhooks_confirmed';
	public const OPT_LAST_CRON_RUN        = 'wp4odoo_last_cron_run';

	/**
	 * Check if onboarding notice has been dismissed.
	 *
	 * @return bool
	 */
	public function is_onboarding_dismissed(): bool {
		return $this->get_bool_option( self::OPT_ONBOARDING_DISMISSED );
	}

	/**
	 * Dismiss the onboarding notice.
	 *
	 * @return bool
	 */
	public function dismiss_onboarding(): bool {
		return $this->set_bool_option( self::OPT_ONBOARDING_DISMISSED, true );
	}

	/**
	 * Check if the setup checklist has been dismissed.
	 *
	 * @return bool
	 */
	public function is_checklist_dismissed(): bool {
		return $this->get_bool_option( self::OPT_CHECKLIST_DISMISSED );
	}

	/**
	 * Dismiss the setup checklist.
	 *
	 * @return bool
	 */
	public function dismiss_checklist(): bool {
		return $this->set_bool_option( self::OPT_CHECKLIST_DISMISSED, true );
	}

	/**
	 * Check if webhooks have been confirmed.
	 *
	 * @return bool
	 */
	public function is_webhooks_confirmed(): bool {
		return $this->get_bool_option( self::OPT_CHECKLIST_WEBHOOKS );
	}

	/**
	 * Mark webhooks as confirmed.
	 *
	 * @return bool
	 */
	public function confirm_webhooks(): bool {
		return $this->set_bool_option( self::OPT_CHECKLIST_WEBHOOKS, true );
	}

	// ── Cron health ───────────────────────────────────────

	/**
	 * Get the last cron run Unix timestamp.
	 *
	 * @return int Unix timestamp, or 0 if never run.
	 */
	public function get_last_cron_run(): int {
		return $this->get_int_option( self::OPT_LAST_CRON_RUN );
	}

	/**
	 * Record the current time as the last cron run.
	 *
	 * @return bool
	 */
	public function touch_cron_run(): bool {
		return update_option( self::OPT_LAST_CRON_RUN, time(), false );
	}

	/**
	 * Check whether WP-Cron appears to be running reliably.
	 *
	 * Returns a warning message if the cron is stale (hasn't run in
	 * 3× the configured sync interval), or empty string if healthy.
	 *
	 * @return string Warning message, or empty string.
	 */
	public function get_cron_warning(): string {
		$sync     = $this->get_sync_settings();
		$last_run = $this->get_last_cron_run();

		// No run recorded yet — only warn if plugin has been active long enough.
		if ( 0 === $last_run ) {
			return '';
		}

		$interval = 'wp4odoo_fifteen_minutes' === $sync['sync_interval'] ? 900 : 300;
		$stale    = time() - $last_run;

		// Warn if more than 3× the expected interval has elapsed.
		if ( $stale > ( $interval * 3 ) ) {
			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				return __( 'WP-Cron is disabled (DISABLE_WP_CRON). Ensure a system cron job calls wp-cron.php regularly, or queue processing will not run.', 'wp4odoo' );
			}
			return __( 'WP-Cron has not triggered recently. On low-traffic sites, scheduled sync may be delayed. Consider setting up a system cron job.', 'wp4odoo' );
		}

		return '';
	}
}
