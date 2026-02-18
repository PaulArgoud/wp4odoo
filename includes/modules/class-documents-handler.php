<?php
declare( strict_types=1 );

namespace WP4Odoo\Modules;

use WP4Odoo\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents Handler — data access for WordPress documents ↔ Odoo Documents.
 *
 * Loads, saves, and formats document and folder data. Handles base64 file
 * encoding/decoding for Odoo documents.document `datas` field.
 *
 * Supports WP Document Revisions ('document' CPT) and WP Download Manager
 * ('wpdmpro' CPT). File content is tracked via SHA-256 hashes stored in
 * post meta to avoid re-uploading unchanged files.
 *
 * Called by Documents_Module via its load_wp_data / save_wp_data dispatch.
 *
 * @package WP4Odoo
 * @since   3.6.0
 */
class Documents_Handler {

	/**
	 * Maximum allowed file size (bytes). Default 10 MB.
	 *
	 * @var int
	 */
	private const MAX_FILE_BYTES = 10 * 1024 * 1024;

	/**
	 * Post meta key for the document file content hash.
	 *
	 * @var string
	 */
	private const FILE_HASH_META = '_wp4odoo_doc_hash';

	/**
	 * Document CPT for WP Document Revisions.
	 *
	 * @var string
	 */
	private const CPT_WP_DOC_REVISIONS = 'document';

	/**
	 * Document CPT for WP Download Manager.
	 *
	 * @var string
	 */
	private const CPT_WPDM = 'wpdmpro';

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

	// ─── Load document ──────────────────────────────────────

	/**
	 * Load document data from a WordPress post.
	 *
	 * Reads the post and its attached file, base64-encodes the content.
	 * Detects MIME type from the attachment.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $source  Source plugin: 'wp_document_revisions' or 'wpdm'.
	 * @return array<string, mixed> Document data, or empty if not found.
	 */
	public function load_document( int $post_id, string $source ): array {
		if ( $post_id <= 0 ) {
			return [];
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			$this->logger->warning( 'Document post not found.', [ 'post_id' => $post_id ] );
			return [];
		}

		$expected_type = $this->get_cpt_for_source( $source );
		if ( $expected_type && $post->post_type !== $expected_type ) {
			return [];
		}

		$file_data = $this->encode_file_content( $post_id );

		return [
			'name'     => $post->post_title,
			'datas'    => $file_data['base64'],
			'mimetype' => $file_data['mimetype'],
		];
	}

	// ─── Load folder ────────────────────────────────────────

	/**
	 * Load folder data from a taxonomy term.
	 *
	 * @param int $term_id Term ID in 'document_category' taxonomy.
	 * @return array<string, mixed> Folder data, or empty if not found.
	 */
	public function load_folder( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return [];
		}

		$term = get_term( $term_id, 'document_category' );
		if ( ! $term || is_wp_error( $term ) ) {
			$this->logger->warning( 'Document folder term not found.', [ 'term_id' => $term_id ] );
			return [];
		}

		return [
			'name'           => $term->name,
			'parent_term_id' => (int) $term->parent,
		];
	}

	// ─── Save document ──────────────────────────────────────

	/**
	 * Save document data from Odoo to WordPress.
	 *
	 * Creates or updates a post with the document CPT appropriate
	 * for the active source plugin. Decodes file content from base64
	 * and creates a WP attachment.
	 *
	 * @param array<string, mixed> $data   Mapped document data.
	 * @param int                  $wp_id  Existing post ID (0 to create).
	 * @param string               $source Source plugin identifier.
	 * @return int Post ID or 0 on failure.
	 */
	public function save_document( array $data, int $wp_id = 0, string $source = '' ): int {
		$post_type = $this->get_cpt_for_source( $source );
		if ( ! $post_type ) {
			$post_type = self::CPT_WP_DOC_REVISIONS;
		}

		$post_data = [
			'post_type'   => $post_type,
			'post_status' => 'publish',
		];

		if ( isset( $data['name'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['name'] );
		}

		// Assign folder if provided.
		$folder_term_id = (int) ( $data['folder_term_id'] ?? 0 );

		if ( $wp_id > 0 ) {
			$post_data['ID'] = $wp_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			if ( ! isset( $post_data['post_title'] ) ) {
				$post_data['post_title'] = __( 'Document', 'wp4odoo' );
			}
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to save document post.',
				[ 'error' => $result->get_error_message() ]
			);
			return 0;
		}

		$post_id = (int) $result;

		// Set folder term.
		if ( $folder_term_id > 0 ) {
			wp_set_object_terms( $post_id, [ $folder_term_id ], 'document_category' );
		}

		// Decode and save file content.
		if ( ! empty( $data['datas'] ) && is_string( $data['datas'] ) ) {
			$filename = sanitize_file_name( $data['name'] ?? 'document' );
			$this->decode_file_content( $data['datas'], $filename, $post_id );
		}

		$this->logger->info(
			'Saved document from Odoo.',
			[
				'post_id' => $post_id,
				'name'    => $data['name'] ?? '',
			]
		);

		return $post_id;
	}

	// ─── Save folder ────────────────────────────────────────

	/**
	 * Save folder data from Odoo as a taxonomy term.
	 *
	 * @param array<string, mixed> $data  Mapped folder data.
	 * @param int                  $wp_id Existing term ID (0 to create).
	 * @return int Term ID or 0 on failure.
	 */
	public function save_folder( array $data, int $wp_id = 0 ): int {
		$name      = sanitize_text_field( $data['name'] ?? '' );
		$parent_id = (int) ( $data['parent_term_id'] ?? 0 );

		if ( '' === $name ) {
			return 0;
		}

		if ( $wp_id > 0 ) {
			$result = wp_update_term(
				$wp_id,
				'document_category',
				[
					'name'   => $name,
					'parent' => $parent_id,
				]
			);
		} else {
			$result = wp_insert_term(
				$name,
				'document_category',
				[ 'parent' => $parent_id ]
			);
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Failed to save document folder.',
				[ 'error' => $result->get_error_message() ]
			);
			return 0;
		}

		$term_id = (int) ( $result['term_id'] ?? 0 );

		$this->logger->info(
			'Saved document folder from Odoo.',
			[
				'term_id' => $term_id,
				'name'    => $name,
			]
		);

		return $term_id;
	}

	// ─── Parse from Odoo ────────────────────────────────────

	/**
	 * Parse Odoo documents.document data to WordPress format.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WordPress-compatible data.
	 */
	public function parse_document_from_odoo( array $odoo_data ): array {
		$data = [];

		if ( isset( $odoo_data['name'] ) ) {
			$data['name'] = (string) $odoo_data['name'];
		}

		if ( isset( $odoo_data['datas'] ) && is_string( $odoo_data['datas'] ) ) {
			$data['datas'] = $odoo_data['datas'];
		}

		if ( isset( $odoo_data['mimetype'] ) ) {
			$data['mimetype'] = (string) $odoo_data['mimetype'];
		}

		// Handle folder_id Many2one: [id, "Name"] or false.
		if ( isset( $odoo_data['folder_id'] ) ) {
			if ( is_array( $odoo_data['folder_id'] ) && ! empty( $odoo_data['folder_id'] ) ) {
				$data['folder_odoo_id'] = (int) $odoo_data['folder_id'][0];
			}
		}

		return $data;
	}

	/**
	 * Parse Odoo documents.folder data to WordPress format.
	 *
	 * @param array<string, mixed> $odoo_data Raw Odoo record data.
	 * @return array<string, mixed> WordPress-compatible data.
	 */
	public function parse_folder_from_odoo( array $odoo_data ): array {
		$data = [];

		if ( isset( $odoo_data['name'] ) ) {
			$data['name'] = (string) $odoo_data['name'];
		}

		// Handle parent_folder_id Many2one: [id, "Name"] or false.
		if ( isset( $odoo_data['parent_folder_id'] ) ) {
			if ( is_array( $odoo_data['parent_folder_id'] ) && ! empty( $odoo_data['parent_folder_id'] ) ) {
				$data['parent_odoo_id'] = (int) $odoo_data['parent_folder_id'][0];
			}
		}

		return $data;
	}

	// ─── Format for Odoo ────────────────────────────────────

	/**
	 * Format document data for Odoo documents.document.
	 *
	 * @param array<string, mixed> $data      Document data from load_document().
	 * @param int                  $folder_id Odoo folder ID.
	 * @return array<string, mixed> Odoo-compatible field values.
	 */
	public function format_document( array $data, int $folder_id ): array {
		$values = [
			'name'     => $data['name'] ?? '',
			'datas'    => $data['datas'] ?? '',
			'mimetype' => $data['mimetype'] ?? 'application/octet-stream',
		];

		if ( $folder_id > 0 ) {
			$values['folder_id'] = $folder_id;
		}

		return $values;
	}

	/**
	 * Format folder data for Odoo documents.folder.
	 *
	 * @param array<string, mixed> $data      Folder data from load_folder().
	 * @param int                  $parent_id Odoo parent folder ID.
	 * @return array<string, mixed> Odoo-compatible field values.
	 */
	public function format_folder( array $data, int $parent_id ): array {
		$values = [
			'name' => $data['name'] ?? '',
		];

		if ( $parent_id > 0 ) {
			$values['parent_folder_id'] = $parent_id;
		}

		return $values;
	}

	// ─── File encoding/decoding ─────────────────────────────

	/**
	 * Encode the file attached to a document post as base64.
	 *
	 * Uses the post's featured attachment or first attachment. Checks
	 * size limits and tracks SHA-256 hash for change detection.
	 *
	 * @param int $post_id Document post ID.
	 * @return array{base64: string, mimetype: string} File data, or empty strings.
	 */
	public function encode_file_content( int $post_id ): array {
		$attachment_id = (int) get_post_thumbnail_id( $post_id );

		// Fallback: try first attachment.
		if ( $attachment_id <= 0 ) {
			$attachments = get_children(
				[
					'post_parent' => $post_id,
					'post_type'   => 'attachment',
					'numberposts' => 1,
				]
			);

			if ( ! empty( $attachments ) ) {
				$attachment    = reset( $attachments );
				$attachment_id = $attachment->ID;
			}
		}

		if ( $attachment_id <= 0 ) {
			return [
				'base64'   => '',
				'mimetype' => '',
			];
		}

		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return [
				'base64'   => '',
				'mimetype' => '',
			];
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > self::MAX_FILE_BYTES ) {
			$this->logger->warning(
				'Document file exceeds size limit, skipping.',
				[
					'post_id' => $post_id,
					'size_mb' => $file_size ? round( $file_size / 1048576, 1 ) : 0,
				]
			);
			return [
				'base64'   => '',
				'mimetype' => '',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local attachment file.
		$binary = file_get_contents( $file_path );
		if ( false === $binary || '' === $binary ) {
			return [
				'base64'   => '',
				'mimetype' => '',
			];
		}

		// Check hash for change detection.
		$new_hash = hash( 'sha256', $binary );
		$old_hash = get_post_meta( $post_id, self::FILE_HASH_META, true );

		if ( $new_hash === $old_hash ) {
			// File unchanged — return empty to signal "no update needed".
			return [
				'base64'   => '',
				'mimetype' => '',
			];
		}

		update_post_meta( $post_id, self::FILE_HASH_META, $new_hash );

		$mime = get_post_mime_type( $attachment_id ) ?: 'application/octet-stream';

		return [
			'base64'   => base64_encode( $binary ),
			'mimetype' => $mime,
		];
	}

	/**
	 * Decode base64 file content from Odoo and create a WP attachment.
	 *
	 * @param string $base64   Base64-encoded file content.
	 * @param string $filename Desired filename.
	 * @param int    $post_id  Parent document post ID.
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function decode_file_content( string $base64, string $filename, int $post_id ): int {
		if ( '' === $base64 ) {
			return 0;
		}

		// Size guard (base64 is ~4/3 of decoded size).
		$estimated_size = (int) ( strlen( $base64 ) * 3 / 4 );
		if ( $estimated_size > self::MAX_FILE_BYTES ) {
			$this->logger->warning(
				'Odoo document file exceeds size limit.',
				[
					'post_id'      => $post_id,
					'estimated_mb' => round( $estimated_size / 1048576, 1 ),
				]
			);
			return 0;
		}

		$decoded = base64_decode( $base64, true );
		if ( false === $decoded || '' === $decoded ) {
			$this->logger->error( 'Failed to decode base64 document data.', [ 'post_id' => $post_id ] );
			return 0;
		}

		// Store hash for change detection.
		$hash = hash( 'sha256', $decoded );
		update_post_meta( $post_id, self::FILE_HASH_META, $hash );

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			$this->logger->error( 'Upload directory not available.', [ 'error' => $upload_dir['error'] ] );
			return 0;
		}

		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to upload dir during sync.
		$bytes_written = file_put_contents( $file_path, $decoded );
		if ( false === $bytes_written ) {
			$this->logger->error( 'Failed to write document file.', [ 'file_path' => $file_path ] );
			return 0;
		}

		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->buffer( $decoded );
		if ( false === $mime || '' === $mime ) {
			$mime = 'application/octet-stream';
		}

		$attachment = [
			'guid'           => trailingslashit( $upload_dir['url'] ) . $filename,
			'post_mime_type' => $mime,
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		/** @var int|\WP_Error $attachment_id */
		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );

		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			$this->logger->error( 'Failed to create document attachment.', [ 'filename' => $filename ] );
			wp_delete_file( $file_path );
			return 0;
		}

		$this->logger->info(
			'Document file decoded from Odoo.',
			[
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			]
		);

		return $attachment_id;
	}

	// ─── Helpers ────────────────────────────────────────────

	/**
	 * Get the document's folder taxonomy term ID.
	 *
	 * @param int $post_id Document post ID.
	 * @return int Term ID, or 0 if no folder assigned.
	 */
	public function get_document_folder_term_id( int $post_id ): int {
		$terms = wp_get_object_terms( $post_id, 'document_category', [ 'fields' => 'ids' ] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return (int) $terms[0];
	}

	/**
	 * Get the CPT slug for a source plugin.
	 *
	 * @param string $source Source plugin identifier.
	 * @return string CPT slug, or empty string.
	 */
	private function get_cpt_for_source( string $source ): string {
		return match ( $source ) {
			'wp_document_revisions' => self::CPT_WP_DOC_REVISIONS,
			'wpdm'                 => self::CPT_WPDM,
			default                => '',
		};
	}
}
