<?php
declare( strict_types=1 );

namespace WP4Odoo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for the wp4odoo_entity_map table.
 *
 * Centralizes all database operations on the entity mapping table
 * that links WordPress entities to Odoo records.
 *
 * Includes a per-request static cache to avoid redundant DB lookups
 * for the same entity during batch processing.
 *
 * @package WP4Odoo
 * @since   1.2.0
 */
class Entity_Map_Repository {

	/**
	 * Maximum number of IDs in a single SQL IN clause.
	 *
	 * Prevents oversized queries that could exceed max_allowed_packet.
	 */
	private const BATCH_CHUNK_SIZE = 500;

	/**
	 * Maximum cache entries before LRU eviction kicks in.
	 *
	 * Prevents unbounded memory growth during large batch imports.
	 * Each entry is ~100 bytes, so 5000 entries ≈ 500 KB.
	 * Higher than original 2000 to avoid counter-productive eviction
	 * during bulk operations where batch queries fill the cache.
	 */
	private const MAX_CACHE_SIZE = 5000;

	/**
	 * Maximum rows returned by get_module_entity_mappings().
	 *
	 * Safety cap to prevent unbounded memory usage during polling.
	 */
	public const POLL_LIMIT = 50000;

	/**
	 * Blog ID for multisite scoping.
	 *
	 * Defaults to the current blog ID. Single-site installs always
	 * return 1, so existing behavior is preserved.
	 *
	 * @var int
	 */
	private int $blog_id;

	/**
	 * Per-request lookup cache.
	 *
	 * Keys use the format "{module}:{entity_type}:wp:{wp_id}" or
	 * "{module}:{entity_type}:odoo:{odoo_id}".
	 *
	 * @var array<string, int|null>
	 */
	private array $cache = [];

	/**
	 * Constructor.
	 *
	 * @param int|null $blog_id Optional blog ID for multisite scoping. Defaults to current blog.
	 */
	public function __construct( ?int $blog_id = null ) {
		$this->blog_id = $blog_id ?? (int) get_current_blog_id();
	}

	/**
	 * Get the Odoo ID mapped to a WordPress entity.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return int|null The Odoo ID, or null if not mapped.
	 */
	public function get_odoo_id( string $module, string $entity_type, int $wp_id ): ?int {
		$cache_key = "{$module}:{$entity_type}:wp:{$wp_id}";

		if ( array_key_exists( $cache_key, $this->cache ) ) {
			// LRU: move to end of array on access.
			$value = $this->cache[ $cache_key ];
			unset( $this->cache[ $cache_key ] );
			$this->cache[ $cache_key ] = $value;
			return $value;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$odoo_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT odoo_id FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND wp_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, safe.
				$this->blog_id,
				$module,
				$entity_type,
				$wp_id
			)
		);

		$result = null !== $odoo_id ? (int) $odoo_id : null;

		$this->cache[ $cache_key ] = $result;

		if ( null !== $result ) {
			$this->cache[ "{$module}:{$entity_type}:odoo:{$result}" ] = $wp_id;
		}

		return $result;
	}

	/**
	 * Get the WordPress ID mapped to an Odoo entity.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $odoo_id     Odoo ID.
	 * @return int|null The WordPress ID, or null if not mapped.
	 */
	public function get_wp_id( string $module, string $entity_type, int $odoo_id ): ?int {
		$cache_key = "{$module}:{$entity_type}:odoo:{$odoo_id}";

		if ( array_key_exists( $cache_key, $this->cache ) ) {
			// LRU: move to end of array on access.
			$value = $this->cache[ $cache_key ];
			unset( $this->cache[ $cache_key ] );
			$this->cache[ $cache_key ] = $value;
			return $value;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$wp_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT wp_id FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND odoo_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, safe.
				$this->blog_id,
				$module,
				$entity_type,
				$odoo_id
			)
		);

		$result = null !== $wp_id ? (int) $wp_id : null;

		$this->cache[ $cache_key ] = $result;

		if ( null !== $result ) {
			$this->cache[ "{$module}:{$entity_type}:wp:{$result}" ] = $odoo_id;
		}

		return $result;
	}

	/**
	 * Batch-fetch WordPress IDs for multiple Odoo IDs.
	 *
	 * @param string       $module      Module identifier.
	 * @param string       $entity_type Entity type.
	 * @param array<int>   $odoo_ids    Odoo IDs to look up.
	 * @return array<int, int> Map of odoo_id => wp_id for existing mappings.
	 */
	public function get_wp_ids_batch( string $module, string $entity_type, array $odoo_ids ): array {
		if ( empty( $odoo_ids ) ) {
			return [];
		}

		// Deduplicate input IDs.
		$odoo_ids = array_values( array_unique( array_map( 'intval', $odoo_ids ) ) );

		// Collect cache hits before querying DB.
		$map      = [];
		$uncached = [];

		foreach ( $odoo_ids as $oid ) {
			$cache_key = "{$module}:{$entity_type}:odoo:{$oid}";
			if ( array_key_exists( $cache_key, $this->cache ) ) {
				if ( null !== $this->cache[ $cache_key ] ) {
					$map[ $oid ] = $this->cache[ $cache_key ];
				}
			} else {
				$uncached[] = $oid;
			}
		}

		if ( empty( $uncached ) ) {
			return $map;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		foreach ( array_chunk( $uncached, self::BATCH_CHUNK_SIZE ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$prepare_args = array_merge( [ $this->blog_id, $module, $entity_type ], array_map( 'intval', $chunk ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $placeholders are safe (prefix + array_fill).
			$sql = "SELECT odoo_id, wp_id FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND odoo_id IN ({$placeholders})";

			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders for batch query; $sql from safe prefix + array_fill.
				$wpdb->prepare( $sql, $prepare_args )
			);

			if ( $rows ) {
				foreach ( $rows as $row ) {
					$o_id = (int) $row->odoo_id;
					$w_id = (int) $row->wp_id;

					$map[ $o_id ] = $w_id;

					$this->cache[ "{$module}:{$entity_type}:odoo:{$o_id}" ] = $w_id;
					$this->cache[ "{$module}:{$entity_type}:wp:{$w_id}" ]   = $o_id;
				}
			}
		}

		$this->evict_cache();

		return $map;
	}

	/**
	 * Batch-fetch Odoo IDs for multiple WordPress IDs.
	 *
	 * @param string       $module      Module identifier.
	 * @param string       $entity_type Entity type.
	 * @param array<int>   $wp_ids      WordPress IDs to look up.
	 * @return array<int, int> Map of wp_id => odoo_id for existing mappings.
	 */
	public function get_odoo_ids_batch( string $module, string $entity_type, array $wp_ids ): array {
		if ( empty( $wp_ids ) ) {
			return [];
		}

		// Deduplicate input IDs.
		$wp_ids = array_values( array_unique( array_map( 'intval', $wp_ids ) ) );

		// Collect cache hits before querying DB.
		$map      = [];
		$uncached = [];

		foreach ( $wp_ids as $wid ) {
			$cache_key = "{$module}:{$entity_type}:wp:{$wid}";
			if ( array_key_exists( $cache_key, $this->cache ) ) {
				if ( null !== $this->cache[ $cache_key ] ) {
					$map[ $wid ] = $this->cache[ $cache_key ];
				}
			} else {
				$uncached[] = $wid;
			}
		}

		if ( empty( $uncached ) ) {
			return $map;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		foreach ( array_chunk( $uncached, self::BATCH_CHUNK_SIZE ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$prepare_args = array_merge( [ $this->blog_id, $module, $entity_type ], array_map( 'intval', $chunk ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $placeholders safe (prefix + array_fill).
			$sql = "SELECT wp_id, odoo_id FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND wp_id IN ({$placeholders})";

			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders for batch query; $sql from safe prefix + array_fill.
				$wpdb->prepare( $sql, $prepare_args )
			);

			if ( $rows ) {
				foreach ( $rows as $row ) {
					$w_id = (int) $row->wp_id;
					$o_id = (int) $row->odoo_id;

					$map[ $w_id ] = $o_id;

					$this->cache[ "{$module}:{$entity_type}:wp:{$w_id}" ]   = $o_id;
					$this->cache[ "{$module}:{$entity_type}:odoo:{$o_id}" ] = $w_id;
				}
			}
		}

		$this->evict_cache();

		return $map;
	}

	/**
	 * Save a mapping between a WordPress entity and an Odoo record.
	 *
	 * Uses INSERT ON DUPLICATE KEY UPDATE to upsert the mapping.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @param int    $odoo_id     Odoo ID.
	 * @param string $odoo_model  Odoo model name (e.g. 'res.partner').
	 * @param string $sync_hash   SHA-256 hash of synced data.
	 * @return bool True on success.
	 */
	public function save( string $module, string $entity_type, int $wp_id, int $odoo_id, string $odoo_model, string $sync_hash = '' ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		$now = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, safe.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (blog_id, module, entity_type, wp_id, odoo_id, odoo_model, sync_hash, last_synced_at)
				VALUES (%d, %s, %s, %d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE odoo_id = VALUES(odoo_id), odoo_model = VALUES(odoo_model), sync_hash = VALUES(sync_hash), last_synced_at = VALUES(last_synced_at)",
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->blog_id,
				$module,
				$entity_type,
				$wp_id,
				$odoo_id,
				$odoo_model,
				$sync_hash,
				$now
			)
		);

		if ( false !== $result ) {
			$this->cache[ "{$module}:{$entity_type}:wp:{$wp_id}" ]     = $odoo_id;
			$this->cache[ "{$module}:{$entity_type}:odoo:{$odoo_id}" ] = $wp_id;
			$this->evict_cache();
		}

		return false !== $result;
	}

	/**
	 * Remove a mapping.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return bool True if a mapping was deleted.
	 */
	public function remove( string $module, string $entity_type, int $wp_id ): bool {
		$forward_key = "{$module}:{$entity_type}:wp:{$wp_id}";
		$odoo_id     = $this->cache[ $forward_key ] ?? null;

		// If forward cache was never populated, query DB to find the odoo_id
		// so we can also invalidate the reverse cache entry.
		if ( null === $odoo_id ) {
			$odoo_id = $this->get_odoo_id( $module, $entity_type, $wp_id );
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'wp4odoo_entity_map';
		$deleted = $wpdb->delete(
			$table,
			[
				'blog_id'     => $this->blog_id,
				'module'      => $module,
				'entity_type' => $entity_type,
				'wp_id'       => $wp_id,
			],
			[ '%d', '%s', '%s', '%d' ]
		);

		unset( $this->cache[ $forward_key ] );
		if ( null !== $odoo_id ) {
			unset( $this->cache[ "{$module}:{$entity_type}:odoo:{$odoo_id}" ] );
		}

		return $deleted > 0;
	}

	/**
	 * Get all entity mappings for a module and entity type.
	 *
	 * Returns an associative array keyed by wp_id containing odoo_id and sync_hash.
	 * Used by polling modules (Bookly) to efficiently diff against source data.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @return array<int, array{odoo_id: int, sync_hash: string}>
	 */
	public function get_module_entity_mappings( string $module, string $entity_type ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		// Safety LIMIT prevents loading unbounded rows on high-volume sites.
		// Polling modules (Bookly, Ecwid) typically have < 10k entities.
		$limit = self::POLL_LIMIT;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wp_id, odoo_id, sync_hash FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, safe.
				$this->blog_id,
				$module,
				$entity_type,
				$limit
			)
		);

		$map = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$w_id = (int) $row->wp_id;
				$o_id = (int) $row->odoo_id;

				$map[ $w_id ] = [
					'odoo_id'   => $o_id,
					'sync_hash' => $row->sync_hash ?? '',
				];
			}
		}

		// Intentionally does NOT populate the per-request cache.
		// This method loads potentially thousands of rows for polling
		// diff comparison. Populating the cache would immediately trigger
		// eviction, wasting CPU and evicting useful individual lookups.

		return $map;
	}

	/**
	 * Batch-fetch entity mappings for a set of WordPress IDs.
	 *
	 * Returns wp_id-keyed array with odoo_id and sync_hash, similar to
	 * get_module_entity_mappings() but filtered to specific WP IDs.
	 * Processes IDs in chunks of BATCH_CHUNK_SIZE.
	 *
	 * Used by the optimized poll_entity_changes() to avoid loading all
	 * entity_map rows into memory.
	 *
	 * @param string    $module      Module identifier.
	 * @param string    $entity_type Entity type.
	 * @param array<int> $wp_ids     WordPress IDs to look up.
	 * @return array<int, array{odoo_id: int, sync_hash: string}>
	 */
	public function get_mappings_for_wp_ids( string $module, string $entity_type, array $wp_ids ): array {
		if ( empty( $wp_ids ) ) {
			return [];
		}

		global $wpdb;

		$table  = $wpdb->prefix . 'wp4odoo_entity_map';
		$map    = [];
		$wp_ids = array_values( array_unique( array_map( 'intval', $wp_ids ) ) );

		foreach ( array_chunk( $wp_ids, self::BATCH_CHUNK_SIZE ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$prepare_args = array_merge( [ $this->blog_id, $module, $entity_type ], $chunk );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $placeholders safe (prefix + array_fill).
			$sql = "SELECT wp_id, odoo_id, sync_hash FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND wp_id IN ({$placeholders})";

			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders for batch query.
				$wpdb->prepare( $sql, $prepare_args )
			);

			if ( $rows ) {
				foreach ( $rows as $row ) {
					$map[ (int) $row->wp_id ] = [
						'odoo_id'   => (int) $row->odoo_id,
						'sync_hash' => $row->sync_hash ?? '',
					];
				}
			}
		}

		return $map;
	}

	/**
	 * Update last_polled_at timestamp for a batch of entity mappings.
	 *
	 * Called by poll_entity_changes() to mark items seen during the
	 * current poll cycle. Processes IDs in chunks of BATCH_CHUNK_SIZE.
	 *
	 * @param string    $module      Module identifier.
	 * @param string    $entity_type Entity type.
	 * @param array<int> $wp_ids     WordPress IDs to mark as polled.
	 * @param string    $timestamp   Current poll timestamp (GMT).
	 * @return void
	 */
	public function mark_polled( string $module, string $entity_type, array $wp_ids, string $timestamp ): void {
		if ( empty( $wp_ids ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		foreach ( array_chunk( $wp_ids, self::BATCH_CHUNK_SIZE ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$prepare_args = array_merge( [ $timestamp, $this->blog_id, $module, $entity_type ], array_map( 'intval', $chunk ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $placeholders safe (prefix + array_fill).
			$sql = "UPDATE {$table} SET last_polled_at = %s WHERE blog_id = %d AND module = %s AND entity_type = %s AND wp_id IN ({$placeholders})";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic placeholders for batch update.
			$wpdb->query( $wpdb->prepare( $sql, $prepare_args ) );
		}
	}

	/**
	 * Get entity mappings that were NOT seen in the current poll cycle.
	 *
	 * Returns wp_id-keyed array of mappings where last_polled_at is
	 * older than the given timestamp. Used for deletion detection.
	 *
	 * Rows with last_polled_at IS NULL (pre-migration or never-polled)
	 * are excluded to avoid false positives during bootstrapping.
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param string $before      Timestamp (GMT). Rows with last_polled_at < this are returned.
	 * @return array<int, array{odoo_id: int}>
	 */
	public function get_stale_poll_mappings( string $module, string $entity_type, string $before ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wp4odoo_entity_map';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is from $wpdb->prefix, safe.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wp_id, odoo_id FROM {$table} WHERE blog_id = %d AND module = %s AND entity_type = %s AND last_polled_at IS NOT NULL AND last_polled_at < %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->blog_id,
				$module,
				$entity_type,
				$before,
				self::POLL_LIMIT
			)
		);

		$map = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$map[ (int) $row->wp_id ] = [
					'odoo_id' => (int) $row->odoo_id,
				];
			}
		}

		return $map;
	}

	/**
	 * Remove orphaned entity map entries where the WordPress post no longer exists.
	 *
	 * Targets post-based entity types: entries whose wp_id does not match
	 * any row in {$wpdb->posts}. Useful for cleaning up mappings left behind
	 * when delete sync jobs fail permanently.
	 *
	 * User-based modules (e.g. BuddyBoss profiles) are excluded because
	 * their wp_id references the users table, not the posts table.
	 *
	 * @param string|null $module      Optional module filter. Null = all modules.
	 * @param bool        $dry_run     If true, count orphans without deleting.
	 * @param string[]    $exclude_modules Module IDs to skip (e.g. user-based modules).
	 * @return array{found: int, removed: int, details: array<string, int>}
	 *
	 * @since 3.6.0
	 */
	public function cleanup_orphans( ?string $module = null, bool $dry_run = false, array $exclude_modules = [] ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'wp4odoo_entity_map';
		$result = [
			'found'   => 0,
			'removed' => 0,
			'details' => [],
		];

		// Default exclusions: modules whose wp_id references users, not posts.
		$user_based = [ 'buddyboss', 'wperp', 'wperp_crm', 'wperp_accounting', 'fluentcrm', 'mailpoet', 'mc4wp', 'gamipress', 'mycred', 'wc_points_rewards', 'affiliatewp' ];
		$exclude    = array_unique( array_merge( $user_based, $exclude_modules ) );

		// Build WHERE clause.
		$where = [ 'blog_id = %d' ];
		$args  = [ $this->blog_id ];

		if ( null !== $module ) {
			$where[] = 'module = %s';
			$args[]  = $module;
		}

		$placeholders = implode( ',', array_fill( 0, count( $exclude ), '%s' ) );
		$where[]      = "module NOT IN ({$placeholders})";
		$args         = array_merge( $args, $exclude );

		$where_sql = implode( ' AND ', $where );

		// Find orphans: entity_map wp_id not in posts table.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table, $where_sql from safe sources (prefix + parameterized).
		$sql = "SELECT em.id, em.module, em.entity_type, em.wp_id, em.odoo_id
			FROM {$table} em
			LEFT JOIN {$wpdb->posts} p ON em.wp_id = p.ID
			WHERE {$where_sql} AND p.ID IS NULL
			LIMIT %d";

		$args[] = self::POLL_LIMIT;

		$orphans = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic WHERE clause; all values parameterized.
			$wpdb->prepare( $sql, $args )
		);

		if ( empty( $orphans ) ) {
			return $result;
		}

		$result['found'] = count( $orphans );

		// Count per module.
		foreach ( $orphans as $row ) {
			$key                       = $row->module . ':' . $row->entity_type;
			$result['details'][ $key ] = ( $result['details'][ $key ] ?? 0 ) + 1;
		}

		if ( $dry_run ) {
			return $result;
		}

		// Delete orphans in batches.
		foreach ( array_chunk( $orphans, self::BATCH_CHUNK_SIZE ) as $chunk ) {
			$ids          = array_map( fn( $row ) => (int) $row->id, $chunk );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $placeholders safe (prefix + array_fill).
			$delete_sql = "DELETE FROM {$table} WHERE id IN ({$placeholders})";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders for batch delete.
			$deleted = $wpdb->query( $wpdb->prepare( $delete_sql, $ids ) );

			if ( false !== $deleted ) {
				$result['removed'] += $deleted;
			}
		}

		// Flush cache since we may have removed entries that are cached.
		$this->flush_cache();

		return $result;
	}

	/**
	 * Flush the per-request lookup cache.
	 *
	 * Useful for testing or after bulk operations.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		$this->cache = [];
	}

	/**
	 * Invalidate a single forward-lookup cache entry.
	 *
	 * Forces the next get_odoo_id() call for this key to hit the DB.
	 * Used by push dedup lock to re-check mapping after acquiring the
	 * advisory lock (another process may have created it in the interim).
	 *
	 * @param string $module      Module identifier.
	 * @param string $entity_type Entity type.
	 * @param int    $wp_id       WordPress ID.
	 * @return void
	 */
	public function invalidate_key( string $module, string $entity_type, int $wp_id ): void {
		unset( $this->cache[ "{$module}:{$entity_type}:wp:{$wp_id}" ] );
	}

	/**
	 * Evict least-recently-used cache entries when the cache exceeds MAX_CACHE_SIZE.
	 *
	 * get_odoo_id() and get_wp_id() move accessed entries to the end of the
	 * PHP array on each hit, so array_slice from the tail keeps the most
	 * recently used entries.
	 *
	 * @return void
	 */
	private function evict_cache(): void {
		if ( count( $this->cache ) <= self::MAX_CACHE_SIZE ) {
			return;
		}

		// Keep the most recent half of entries.
		$kept = array_slice( $this->cache, (int) ( self::MAX_CACHE_SIZE / 2 ), null, true );

		// Remove orphaned entries whose bidirectional partner was evicted.
		// Each mapping has two keys: "mod:type:wp:X" ↔ "mod:type:odoo:Y".
		// If one side was evicted, discard the surviving orphan to prevent
		// stale lookups on the remaining direction.
		foreach ( $kept as $key => $value ) {
			if ( null === $value ) {
				continue;
			}

			$wp_pos   = strrpos( $key, ':wp:' );
			$odoo_pos = strrpos( $key, ':odoo:' );

			if ( false !== $wp_pos ) {
				$partner = substr( $key, 0, $wp_pos ) . ':odoo:' . $value;
			} elseif ( false !== $odoo_pos ) {
				$partner = substr( $key, 0, $odoo_pos ) . ':wp:' . $value;
			} else {
				continue;
			}

			if ( ! array_key_exists( $partner, $kept ) ) {
				unset( $kept[ $key ] );
			}
		}

		$this->cache = $kept;
	}
}
