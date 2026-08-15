<?php
/**
 * Controlled database helper for custom hotel tables.
 *
 * WordPress core APIs cannot express the indexed date-range conflict lookups
 * required by a booking engine. This helper keeps custom-table access in one
 * place and ensures every custom query is executed through one audited path.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps audited custom-table database operations.
 */
class CNWSHOTEL_DB {

	/**
		* Cache group used for stable custom-table reads.
	*/
	const CACHE_GROUP = 'cnwshotel';

	/**
	 * Starts a DB transaction for booking allocation.
	 *
	 * The SQL statement is a fixed internal literal and contains no user input.
	 * WordPress has no CRUD wrapper for transaction control statements.
	 *
	 * @return int|bool
	 */
	public static function start_transaction() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed transaction control statement; caching does not apply.
		return $wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commits the current DB transaction.
	 *
	 * The SQL statement is a fixed internal literal and contains no user input.
	 * WordPress has no CRUD wrapper for transaction control statements.
	 *
	 * @return int|bool
	 */
	public static function commit() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed transaction control statement; caching does not apply.
		return $wpdb->query( 'COMMIT' );
	}

	/**
	 * Rolls back the current DB transaction.
	 *
	 * The SQL statement is a fixed internal literal and contains no user input.
	 * WordPress has no CRUD wrapper for transaction control statements.
	 *
	 * @return int|bool
	 */
	public static function rollback() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed transaction control statement; caching does not apply.
		return $wpdb->query( 'ROLLBACK' );
	}

	/**
	 * Inserts a row into a custom hotel table.
	 *
	 * @param string       $table Table name.
	 * @param array<mixed> $data Data.
	 * @param array<mixed> $format Format.
	 * @return int|false
	 */
	public static function insert( $table, $data, $format = array() ) {

		global $wpdb;

		wp_cache_delete( 'cnwshotel_db_last_write', self::CACHE_GROUP );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Central custom-table insert wrapper; writes are not cached and callers pass explicit formats.
		return $wpdb->insert( (string) $table, (array) $data, (array) $format );
	}

	/**
	 * Updates rows in a custom hotel table.
	 *
	 * @param string       $table Table name.
	 * @param array<mixed> $data Data.
	 * @param array<mixed> $where Where.
	 * @param array<mixed> $format Format.
	 * @param array<mixed> $where_format Where format.
	 * @return int|false
	 */
	public static function update( $table, $data, $where, $format = array(), $where_format = array() ) {

		global $wpdb;

		wp_cache_delete( 'cnwshotel_db_last_write', self::CACHE_GROUP );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Central custom-table update wrapper; writes are not cached and callers pass explicit formats.
		return $wpdb->update( (string) $table, (array) $data, (array) $where, (array) $format, (array) $where_format );
	}

	/**
	 * Deletes rows from a custom hotel table.
	 *
	 * @param string       $table Table name.
	 * @param array<mixed> $where Where.
	 * @param array<mixed> $where_format Where format.
	 * @return int|false
	 */
	public static function delete( $table, $where, $where_format = array() ) {

		global $wpdb;

		wp_cache_delete( 'cnwshotel_db_last_write', self::CACHE_GROUP );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Central custom-table delete wrapper; writes are not cached and callers pass explicit where formats.
		return $wpdb->delete( (string) $table, (array) $where, (array) $where_format );
	}

	/**
	 * Gets the ID of the last inserted row.
	 *
	 * @return int
	 */
	public static function insert_id() {

		global $wpdb;

		return absint( $wpdb->insert_id );
	}
}
