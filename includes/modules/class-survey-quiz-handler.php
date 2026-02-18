<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Survey & Quiz Handler — formats surveys and responses for Odoo.
 *
 * Takes normalized quiz/survey data (from Survey_Extractor) and formats
 * it as Odoo survey.survey with embedded question One2many tuples,
 * and survey.user_input for responses.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class Survey_Quiz_Handler {

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
	 * Format normalized survey data as an Odoo survey.survey.
	 *
	 * Includes questions as One2many tuples in question_and_page_ids.
	 *
	 * @param array<string, mixed> $data Normalized survey data from extractor.
	 * @return array<string, mixed> Odoo-ready survey.survey data, or empty.
	 */
	public function format_survey( array $data ): array {
		$title = $data['title'] ?? '';
		if ( '' === $title ) {
			$this->logger->warning( 'Cannot format survey: no title.' );
			return [];
		}

		$questions   = $data['questions'] ?? [];
		$description = $data['description'] ?? '';

		$result = [
			'title' => $title,
		];

		if ( '' !== $description ) {
			$result['description'] = $description;
		}

		// Build question One2many tuples.
		if ( ! empty( $questions ) ) {
			$question_tuples = [];
			foreach ( $questions as $question ) {
				$q_title = $question['title'] ?? '';
				if ( '' === $q_title ) {
					continue;
				}

				$q_type = $this->map_question_type( $question['type'] ?? '' );

				$question_tuples[] = [
					0,
					0,
					[
						'title'         => $q_title,
						'question_type' => $q_type,
					],
				];
			}

			if ( ! empty( $question_tuples ) ) {
				$result['question_and_page_ids'] = $question_tuples;
			}
		}

		return $result;
	}

	/**
	 * Format normalized response data as an Odoo survey.user_input.
	 *
	 * @param array<string, mixed> $data           Normalized response data.
	 * @param int                  $survey_odoo_id Odoo survey.survey ID (0 if unknown).
	 * @param int                  $partner_id     Odoo partner ID (0 if anonymous).
	 * @return array<string, mixed> Odoo-ready survey.user_input data, or empty.
	 */
	public function format_response( array $data, int $survey_odoo_id, int $partner_id ): array {
		$result = [
			'state' => 'done',
		];

		if ( $survey_odoo_id > 0 ) {
			$result['survey_id'] = $survey_odoo_id;
		}

		if ( $partner_id > 0 ) {
			$result['partner_id'] = $partner_id;
		}

		$score = $data['total_score'] ?? null;
		if ( null !== $score ) {
			$result['scoring_total'] = (float) $score;
		}

		// Build answer line tuples.
		$answers = $data['answers'] ?? [];
		if ( ! empty( $answers ) ) {
			$answer_lines = [];
			foreach ( $answers as $answer ) {
				$answer_lines[] = [
					0,
					0,
					[
						'display_name'   => (string) ( $answer['question'] ?? '' ),
						'value_text_box' => (string) ( $answer['answer'] ?? '' ),
					],
				];
			}

			if ( ! empty( $answer_lines ) ) {
				$result['user_input_line_ids'] = $answer_lines;
			}
		}

		return $result;
	}

	/**
	 * Map a source plugin question type to Odoo survey question type.
	 *
	 * @param string $type Source question type.
	 * @return string Odoo question type.
	 */
	private function map_question_type( string $type ): string {
		$map = [
			'radio'           => 'simple_choice',
			'checkbox'        => 'multiple_choice',
			'text'            => 'text_box',
			'textarea'        => 'text_box',
			'select'          => 'simple_choice',
			'number'          => 'numerical_box',
			'date'            => 'date',
			'multiple_choice' => 'multiple_choice',
			'true_or_false'   => 'simple_choice',
		];

		return $map[ $type ] ?? 'text_box';
	}
}
