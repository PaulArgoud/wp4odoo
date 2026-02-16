<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI queue management subcommands.
 *
 * Provides stats, list, retry, cleanup, and cancel operations
 * for the sync queue. Used by the CLI class via trait composition.
 *
 * @package WP4Odoo
 * @since   3.3.0
 */
trait CLI_Queue_Commands {

	/**
	 * Display queue statistics.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	private function queue_stats( array $assoc_args ): void {
		$stats  = Queue_Manager::get_stats();
		$format = $assoc_args['format'] ?? 'table';

		$allowed_formats = [ 'table', 'csv', 'json', 'yaml', 'count' ];
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			/* translators: 1: format name, 2: allowed format names */
			\WP_CLI::error( sprintf( __( 'Invalid format "%1$s". Allowed: %2$s', 'wp4odoo' ), $format, implode( ', ', $allowed_formats ) ) );
		}

		\WP_CLI\Utils\format_items(
			$format,
			[
				[
					'pending'           => $stats['pending'],
					'processing'        => $stats['processing'],
					'completed'         => $stats['completed'],
					'failed'            => $stats['failed'],
					'last_completed_at' => $stats['last_completed_at'] ?: '—',
				],
			],
			[ 'pending', 'processing', 'completed', 'failed', 'last_completed_at' ]
		);
	}

	/**
	 * List queue jobs.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	private function queue_list( array $assoc_args ): void {
		$page     = max( 1, (int) ( $assoc_args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $assoc_args['per-page'] ?? 30 ) ) );
		$format   = $assoc_args['format'] ?? 'table';

		$allowed_formats = [ 'table', 'csv', 'json', 'yaml', 'count' ];
		if ( ! in_array( $format, $allowed_formats, true ) ) {
			/* translators: 1: format name, 2: allowed format names */
			\WP_CLI::error( sprintf( __( 'Invalid format "%1$s". Allowed: %2$s', 'wp4odoo' ), $format, implode( ', ', $allowed_formats ) ) );
		}

		$data = $this->query_service->get_queue_jobs( $page, $per_page );

		if ( empty( $data['items'] ) ) {
			\WP_CLI::line( __( 'No jobs found.', 'wp4odoo' ) );
			return;
		}

		$rows = [];
		foreach ( $data['items'] as $job ) {
			$rows[] = [
				'id'          => $job->id,
				'module'      => $job->module,
				'entity_type' => $job->entity_type,
				'direction'   => $job->direction,
				'action'      => $job->action,
				'status'      => $job->status,
				'attempts'    => $job->attempts . '/' . $job->max_attempts,
				'created_at'  => $job->created_at,
			];
		}

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			[
				'id',
				'module',
				'entity_type',
				'direction',
				'action',
				'status',
				'attempts',
				'created_at',
			]
		);

		/* translators: 1: current page number, 2: total pages, 3: total items */
		\WP_CLI::line( sprintf( __( 'Page %1$d/%2$d (%3$d total)', 'wp4odoo' ), $page, $data['pages'], $data['total'] ) );
	}

	/**
	 * Retry all failed jobs.
	 *
	 * @param array<string, string> $assoc_args Associative arguments (supports --yes).
	 * @return void
	 */
	private function queue_retry( array $assoc_args = [] ): void {
		\WP_CLI::confirm(
			__( 'Retry all failed jobs?', 'wp4odoo' ),
			$assoc_args
		);

		$count = Queue_Manager::retry_failed();
		/* translators: %d: number of jobs retried */
		\WP_CLI::success( sprintf( __( '%d failed job(s) retried.', 'wp4odoo' ), $count ) );
	}

	/**
	 * Clean up old completed/failed jobs.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	private function queue_cleanup( array $assoc_args ): void {
		$days    = max( 1, (int) ( $assoc_args['days'] ?? 7 ) );
		$deleted = Queue_Manager::cleanup( $days );
		/* translators: 1: number of jobs deleted, 2: number of days */
		\WP_CLI::success( sprintf( __( '%1$d job(s) deleted (older than %2$d days).', 'wp4odoo' ), $deleted, $days ) );
	}

	/**
	 * Cancel a pending job by ID.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	private function queue_cancel( int $job_id ): void {
		if ( $job_id <= 0 ) {
			\WP_CLI::error( __( 'Please provide a valid job ID. Usage: wp wp4odoo queue cancel <id>', 'wp4odoo' ) );
		}

		if ( Queue_Manager::cancel( $job_id ) ) {
			/* translators: %d: job ID */
			\WP_CLI::success( sprintf( __( 'Job %d cancelled.', 'wp4odoo' ), $job_id ) );
		} else {
			/* translators: %d: job ID */
			\WP_CLI::error( sprintf( __( 'Unable to cancel job %d (not found or not pending).', 'wp4odoo' ), $job_id ) );
		}
	}
}
