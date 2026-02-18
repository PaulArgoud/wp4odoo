<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Survey Extractor — plugin-specific quiz/survey data extraction.
 *
 * Strategy-based extractor (like Form_Field_Extractor) that normalizes
 * quiz/survey data from different plugins into a common format.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class Survey_Extractor {

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

	// ─── Survey structure extraction ──────────────────────

	/**
	 * Extract quiz structure from Quiz Maker (Ays).
	 *
	 * Queries the ays_quizzes and ays_questions tables via $wpdb.
	 *
	 * @param int $quiz_id Ays quiz ID.
	 * @return array<string, mixed> Normalized survey data, or empty.
	 */
	public function extract_survey_from_ays( int $quiz_id ): array {
		global $wpdb;

		$quiz_table = $wpdb->prefix . 'ays_quizzes';
		$quiz       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$quiz_table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			),
			ARRAY_A
		);

		if ( ! $quiz ) {
			$this->logger->warning( 'Ays quiz not found.', [ 'quiz_id' => $quiz_id ] );
			return [];
		}

		$question_ids = $quiz['question_ids'] ?? '';
		$questions    = [];

		if ( '' !== $question_ids ) {
			$ids            = array_map( 'intval', explode( ',', $question_ids ) );
			$question_table = $wpdb->prefix . 'ays_questions';
			$placeholders   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$q_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$question_table} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					...$ids
				),
				ARRAY_A
			);

			if ( is_array( $q_rows ) ) {
				foreach ( $q_rows as $q ) {
					$questions[] = [
						'title'   => (string) ( $q['question'] ?? '' ),
						'type'    => (string) ( $q['type'] ?? 'radio' ),
						'answers' => $this->parse_ays_answers( $q['answers'] ?? '' ),
					];
				}
			}
		}

		return [
			'title'       => (string) ( $quiz['title'] ?? '' ),
			'description' => (string) ( $quiz['description'] ?? '' ),
			'questions'   => $questions,
			'source'      => 'ays',
		];
	}

	/**
	 * Extract quiz structure from QSM.
	 *
	 * QSM stores quizzes as the 'qsm_quiz' CPT.
	 *
	 * @param int $quiz_id QSM quiz post ID.
	 * @return array<string, mixed> Normalized survey data, or empty.
	 */
	public function extract_survey_from_qsm( int $quiz_id ): array {
		$post = get_post( $quiz_id );
		if ( ! $post || 'qsm_quiz' !== $post->post_type ) {
			$this->logger->warning( 'QSM quiz not found.', [ 'quiz_id' => $quiz_id ] );
			return [];
		}

		$questions_meta = get_post_meta( $quiz_id, '_qsm_questions', true );
		$questions      = [];

		if ( is_array( $questions_meta ) ) {
			foreach ( $questions_meta as $q ) {
				$questions[] = [
					'title'   => (string) ( $q['question'] ?? '' ),
					'type'    => (string) ( $q['type'] ?? 'text' ),
					'answers' => is_array( $q['answers'] ?? null ) ? $q['answers'] : [],
				];
			}
		}

		return [
			'title'       => $post->post_title,
			'description' => wp_strip_all_tags( $post->post_content ),
			'questions'   => $questions,
			'source'      => 'qsm',
		];
	}

	// ─── Response extraction ──────────────────────────────

	/**
	 * Extract response data from Quiz Maker (Ays) result.
	 *
	 * @param int $result_id Ays result ID.
	 * @return array<string, mixed> Normalized response data, or empty.
	 */
	public function extract_response_from_ays( int $result_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ays_results';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'Ays quiz result not found.', [ 'result_id' => $result_id ] );
			return [];
		}

		$user_answers = json_decode( $row['options'] ?? '{}', true );
		if ( ! is_array( $user_answers ) ) {
			$user_answers = [];
		}

		$answers = [];
		foreach ( $user_answers as $question => $answer ) {
			$answers[] = [
				'question' => (string) $question,
				'answer'   => is_array( $answer ) ? implode( ', ', $answer ) : (string) $answer,
				'score'    => 0.0,
			];
		}

		return [
			'quiz_id'     => (int) ( $row['quiz_id'] ?? 0 ),
			'user_email'  => (string) ( $row['user_email'] ?? '' ),
			'user_name'   => (string) ( $row['user_name'] ?? '' ),
			'answers'     => $answers,
			'total_score' => (float) ( $row['score'] ?? 0 ),
			'date'        => (string) ( $row['end_date'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'source'      => 'ays',
		];
	}

	/**
	 * Extract response data from QSM result.
	 *
	 * @param int $result_id QSM result ID.
	 * @return array<string, mixed> Normalized response data, or empty.
	 */
	public function extract_response_from_qsm( int $result_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'mlw_results';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE result_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->warning( 'QSM result not found.', [ 'result_id' => $result_id ] );
			return [];
		}

		$quiz_results = json_decode( $row['quiz_results'] ?? '{}', true );
		if ( ! is_array( $quiz_results ) ) {
			$quiz_results = [];
		}

		$answers = [];
		foreach ( $quiz_results as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$answers[] = [
				'question' => (string) ( $item['question'] ?? '' ),
				'answer'   => (string) ( $item['answer'] ?? '' ),
				'score'    => (float) ( $item['points'] ?? 0 ),
			];
		}

		return [
			'quiz_id'     => (int) ( $row['quiz_id'] ?? 0 ),
			'user_email'  => (string) ( $row['email'] ?? '' ),
			'user_name'   => (string) ( $row['name'] ?? '' ),
			'answers'     => $answers,
			'total_score' => (float) ( $row['point_score'] ?? 0 ),
			'date'        => (string) ( $row['time_taken'] ?? gmdate( 'Y-m-d H:i:s' ) ),
			'source'      => 'qsm',
		];
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Parse Ays quiz answer options from serialized/JSON string.
	 *
	 * @param string $answers_raw Raw answers string.
	 * @return array<int, string> Answer options.
	 */
	private function parse_ays_answers( string $answers_raw ): array {
		if ( '' === $answers_raw ) {
			return [];
		}

		$decoded = json_decode( $answers_raw, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_map( 'strval', $decoded ) );
		}

		return [];
	}
}
