<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin email notifications for persistent sync failures.
 *
 * Tracks consecutive batch failures via wp_options and sends
 * a notification email when the threshold is exceeded, with
 * a cooldown period between emails.
 *
 * Extracted from Sync_Engine for single responsibility.
 *
 * @package WP4Odoo
 * @since   2.0.0
 */
class Failure_Notifier {

	/**
	 * Failure ratio threshold for considering a batch as "failed".
	 *
	 * Aligned with Circuit_Breaker::FAILURE_RATIO (80%): a batch
	 * with fewer than 80% failures is considered healthy.
	 */
	private const FAILURE_RATIO = 0.8;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $settings;

	/**
	 * Constructor.
	 *
	 * @param Logger              $logger   Logger instance.
	 * @param Settings_Repository $settings Settings repository.
	 */
	public function __construct( Logger $logger, Settings_Repository $settings ) {
		$this->logger   = $logger;
		$this->settings = $settings;
	}

	/**
	 * Check batch results and send notification if needed.
	 *
	 * Uses the same ratio-based logic as Circuit_Breaker: a batch
	 * counts as a failure only when 80%+ of jobs failed. This prevents
	 * a single lucky success from resetting the counter during an
	 * outage, while avoiding false alarms from occasional individual
	 * failures in an otherwise healthy batch.
	 *
	 * @param int $successes Number of successful jobs in the batch.
	 * @param int $failures  Number of failed jobs in the batch.
	 * @return void
	 */
	public function check( int $successes, int $failures ): void {
		$total = $successes + $failures;
		if ( 0 === $total ) {
			return;
		}

		$consecutive   = $this->settings->get_consecutive_failures();
		$failure_ratio = $failures / $total;

		if ( $failure_ratio < self::FAILURE_RATIO ) {
			// Batch is healthy: reset the consecutive failure counter.
			if ( $consecutive > 0 ) {
				$this->settings->save_consecutive_failures( 0 );
			}
			return;
		}

		// Batch is considered failed (80%+ failures).
		$consecutive += $failures;
		$this->settings->save_consecutive_failures( $consecutive );

		if ( $consecutive < $this->settings->get_failure_threshold() ) {
			return;
		}

		$this->maybe_send( $consecutive );
	}

	/**
	 * Send a notification email if the cooldown has elapsed.
	 *
	 * @param int $consecutive Number of consecutive failures.
	 * @return void
	 */
	private function maybe_send( int $consecutive ): void {
		$last_email = $this->settings->get_last_failure_email();
		if ( ( time() - $last_email ) < $this->settings->get_failure_cooldown() ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %d: number of consecutive failures */
			__( '[WP4Odoo] %d consecutive sync failures', 'wp4odoo' ),
			$consecutive
		);

		$message = sprintf(
			/* translators: 1: number of failures, 2: queue admin URL */
			__( "The WordPress For Odoo sync queue has encountered %1\$d consecutive failures.\n\nPlease check the sync queue at %2\$s", 'wp4odoo' ),
			$consecutive,
			admin_url( 'admin.php?page=wp4odoo-settings&tab=queue' )
		);

		// Save timestamp BEFORE sending to prevent duplicate emails under
		// concurrency: two processes could both pass the cooldown check and
		// both call wp_mail() before either saves. Saving first narrows the
		// race window. If wp_mail() fails, the next cooldown cycle retries.
		$this->settings->save_last_failure_email( time() );

		if ( ! wp_mail( $admin_email, $subject, $message ) ) {
			$this->logger->error(
				'Failed to send failure notification email.',
				[ 'email' => $admin_email ]
			);
			return;
		}

		$this->logger->warning(
			'Failure notification sent to admin.',
			[
				'consecutive_failures' => $consecutive,
				'email'                => $admin_email,
			]
		);
	}
}
