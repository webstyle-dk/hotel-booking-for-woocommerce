<?php
/**
 * Database upgrade routines for WSH Hotel Booking Management Free.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs database upgrade routines for existing plugin installs.
 */
class CNWSHOTEL_Upgrade {

	const DB_VERSION_OPTION     = 'cnwshotel_db_version';
	const LEGACY_VERSION_OPTION = 'cnwshotel_core_db_version';

	/**
	 * Register upgrade check.
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'maybe_run_upgrades' ) );
	}

	/**
	 * Runs normal admin upgrade check.
	 */
	public function maybe_run_upgrades() {

		self::maybe_upgrade_database( false );
	}

	/**
	 * Ensures missing tables, columns and indexes exist.
	 *
	 * @param bool $force Whether to run even if version option is already current.
	 */
	public static function maybe_upgrade_database( $force = false ) {

		$target_version    = self::get_target_db_version();
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( ! $force && version_compare( $installed_version, $target_version, '>=' ) ) {
			return;
		}

		if ( class_exists( 'CNWSHOTEL_Installer' ) && method_exists( 'CNWSHOTEL_Installer', 'maybe_create_tables' ) ) {
			CNWSHOTEL_Installer::maybe_create_tables();
		}

		self::sync_table_schema();
		self::rebuild_room_capacities();

		update_option( self::DB_VERSION_OPTION, $target_version, false );
		update_option( self::LEGACY_VERSION_OPTION, $target_version, false );
	}

	/**
	 * Returns current database schema version.
	 *
	 * @return string
	 */
	private static function get_target_db_version() {

		if ( defined( 'CNWSHOTEL_DB_VERSION' ) ) {
			return (string) CNWSHOTEL_DB_VERSION;
		}

		if ( defined( 'CNWSHOTEL_VERSION' ) ) {
			return (string) CNWSHOTEL_VERSION;
		}

		return '1.1.1';
	}

	/**
	 * Adds missing columns and indexes without recreating existing tables.
	 */
	private static function sync_table_schema() {

		global $wpdb;

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_rooms',
			array(
				'post_id'         => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'woo_product_id'  => 'BIGINT UNSIGNED NULL DEFAULT NULL',
				'room_number'     => "VARCHAR(191) NULL DEFAULT ''",
				'quantity'        => 'INT NOT NULL DEFAULT 0',
				'max_persons'     => 'INT NOT NULL DEFAULT 0',
				'price'           => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
				'pricing_model'   => "VARCHAR(50) NOT NULL DEFAULT 'per_room'",
				'allocation_mode' => "VARCHAR(50) NOT NULL DEFAULT 'exclusive_units'",
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_rooms',
			array(
				'post_id'        => array( 'post_id' ),
				'woo_product_id' => array( 'woo_product_id' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_bookings',
			array(
				'order_id'       => 'BIGINT UNSIGNED NULL DEFAULT NULL',
				'room_id'        => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'checkin'        => 'DATE NULL DEFAULT NULL',
				'checkout'       => 'DATE NULL DEFAULT NULL',
				'guests'         => 'INT NOT NULL DEFAULT 1',
				'guests_real'    => 'INT NOT NULL DEFAULT 1',
				'guests_paid'    => 'INT NOT NULL DEFAULT 1',
				'booking_status' => "VARCHAR(50) NOT NULL DEFAULT 'confirmed'",
				'created_at'     => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_bookings',
			array(
				'order_id'          => array( 'order_id' ),
				'room_id'           => array( 'room_id' ),
				'booking_status'    => array( 'booking_status' ),
				'booking_dates'     => array( 'checkin', 'checkout' ),
				'room_status_dates' => array( 'room_id', 'booking_status', 'checkin', 'checkout' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_booking_units',
			array(
				'booking_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'unit_id'    => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_booking_units',
			array(
				'booking_id' => array( 'booking_id' ),
				'unit_id'    => array( 'unit_id' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_room_units',
			array(
				'room_type_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'unit_number'  => "VARCHAR(191) NOT NULL DEFAULT ''",
				'floor'        => 'INT NOT NULL DEFAULT 0',
				'status'       => "VARCHAR(50) NOT NULL DEFAULT 'active'",
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_room_units',
			array(
				'room_type_id' => array( 'room_type_id' ),
				'status'       => array( 'status' ),
				'room_status'  => array( 'room_type_id', 'status' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_room_unit_beds',
			array(
				'room_unit_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'bed_type_id'  => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_room_unit_beds',
			array(
				'room_unit_id' => array( 'room_unit_id' ),
				'bed_type_id'  => array( 'bed_type_id' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_bed_types',
			array(
				'name'        => "VARCHAR(191) NOT NULL DEFAULT ''",
				'capacity'    => 'INT NOT NULL DEFAULT 1',
				'description' => 'TEXT NULL',
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_seasonal_pricing',
			array(
				'room_id'        => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'date_start'     => 'DATE NULL DEFAULT NULL',
				'date_end'       => 'DATE NULL DEFAULT NULL',
				'modifier_type'  => "VARCHAR(50) NOT NULL DEFAULT ''",
				'modifier_value' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_seasonal_pricing',
			array(
				'room_id'         => array( 'room_id' ),
				'date_range'      => array( 'date_start', 'date_end' ),
				'room_type_dates' => array( 'room_id', 'modifier_type', 'date_start', 'date_end' ),
			)
		);

		self::ensure_columns(
			$wpdb->prefix . 'cnwshotel_cart_holds',
			array(
				'session_key'   => "VARCHAR(191) NOT NULL DEFAULT ''",
				'cart_item_key' => "VARCHAR(191) NOT NULL DEFAULT ''",
				'room_id'       => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
				'unit_id'       => 'BIGINT UNSIGNED NULL DEFAULT NULL',
				'checkin'       => 'DATE NULL DEFAULT NULL',
				'checkout'      => 'DATE NULL DEFAULT NULL',
				'guests_real'   => 'INT NOT NULL DEFAULT 1',
				'guests_paid'   => 'INT NOT NULL DEFAULT 1',
				'is_private'    => 'TINYINT(1) NOT NULL DEFAULT 0',
				'hold_status'   => "VARCHAR(20) NOT NULL DEFAULT 'active'",
				'expires_at'    => 'DATETIME NULL DEFAULT NULL',
				'created_at'    => 'DATETIME NULL DEFAULT NULL',
			)
		);
		self::ensure_indexes(
			$wpdb->prefix . 'cnwshotel_cart_holds',
			array(
				'room_dates'     => array( 'room_id', 'checkin', 'checkout', 'hold_status' ),
				'session_cart'   => array( 'session_key', 'cart_item_key' ),
				'unit_id'        => array( 'unit_id' ),
				'unit_dates'     => array( 'unit_id', 'hold_status', 'checkin', 'checkout' ),
				'status_expires' => array( 'hold_status', 'expires_at' ),
				'expires_at'     => array( 'expires_at' ),
			)
		);
	}

	/**
	 * Adds missing columns for a table.
	 *
	 * @param string               $table_name Full table name.
	 * @param array<string,string> $columns Column definitions without column name.
	 */
	private static function ensure_columns( $table_name, array $columns ) {

		if ( ! self::table_exists( $table_name ) ) {
			return;
		}

		foreach ( $columns as $column_name => $definition ) {
			if ( self::column_exists( $table_name, $column_name ) ) {
				continue;
			}

			self::add_column( $table_name, $column_name, $definition );
		}
	}

	/**
	 * Adds missing indexes for a table.
	 *
	 * @param string              $table_name Full table name.
	 * @param array<string,array> $indexes Index name mapped to column list.
	 */
	private static function ensure_indexes( $table_name, array $indexes ) {

		if ( ! self::table_exists( $table_name ) ) {
			return;
		}

		foreach ( $indexes as $index_name => $columns ) {
			if ( self::index_exists( $table_name, $index_name ) ) {
				continue;
			}

			self::add_index( $table_name, $index_name, $columns );
		}
	}

	/**
	 * Checks whether a table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private static function table_exists( $table_name ) {

		global $wpdb;

		$table_name = (string) $table_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		);

		return $found === $table_name;
	}

	/**
	 * Checks whether a column exists.
	 *
	 * @param string $table_name  Full table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	private static function column_exists( $table_name, $column_name ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				$column_name
			)
		);

		return $found === $column_name;
	}

	/**
	 * Checks whether an index exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $index_name Index name.
	 * @return bool
	 */
	private static function index_exists( $table_name, $index_name ) {

		global $wpdb;

		$index_name = (string) $index_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$indexes = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW INDEX FROM %i',
				$table_name
			),
			ARRAY_A
		);

		if ( empty( $indexes ) ) {
			return false;
		}

		foreach ( $indexes as $index ) {
			if ( isset( $index['Key_name'] ) && (string) $index['Key_name'] === $index_name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds a column to a table.
	 *
	 * Column definitions are fixed plugin-owned values. Each supported SQL
	 * definition is emitted as a literal query template so Plugin Check can
	 * verify that table and column identifiers still use placeholders.
	 *
	 * @param string $table_name  Full table name.
	 * @param string $column_name Column name.
	 * @param string $definition  Column definition.
	 */
	private static function add_column( $table_name, $column_name, $definition ) {

		global $wpdb;

		$definition_key = self::normalize_column_definition_key( $definition );

		switch ( $definition_key ) {
			case 'BIGINT UNSIGNED NOT NULL DEFAULT 0':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i BIGINT UNSIGNED NOT NULL DEFAULT 0', $table_name, $column_name ) );
				break;

			case 'BIGINT UNSIGNED NULL DEFAULT NULL':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i BIGINT UNSIGNED NULL DEFAULT NULL', $table_name, $column_name ) );
				break;

			case "VARCHAR(191) NULL DEFAULT ''":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(191) NULL DEFAULT ''", $table_name, $column_name ) );
				break;

			case "VARCHAR(191) NOT NULL DEFAULT ''":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(191) NOT NULL DEFAULT ''", $table_name, $column_name ) );
				break;

			case 'INT NOT NULL DEFAULT 0':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i INT NOT NULL DEFAULT 0', $table_name, $column_name ) );
				break;

			case 'INT NOT NULL DEFAULT 1':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i INT NOT NULL DEFAULT 1', $table_name, $column_name ) );
				break;

			case 'DECIMAL(10,2) NOT NULL DEFAULT 0.00':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i DECIMAL(10,2) NOT NULL DEFAULT 0.00', $table_name, $column_name ) );
				break;

			case "VARCHAR(50) NOT NULL DEFAULT 'PER_ROOM'":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(50) NOT NULL DEFAULT 'per_room'", $table_name, $column_name ) );
				break;

			case "VARCHAR(50) NOT NULL DEFAULT 'EXCLUSIVE_UNITS'":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(50) NOT NULL DEFAULT 'exclusive_units'", $table_name, $column_name ) );
				break;

			case "VARCHAR(50) NOT NULL DEFAULT 'CONFIRMED'":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(50) NOT NULL DEFAULT 'confirmed'", $table_name, $column_name ) );
				break;

			case "VARCHAR(50) NOT NULL DEFAULT 'ACTIVE'":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(50) NOT NULL DEFAULT 'active'", $table_name, $column_name ) );
				break;

			case "VARCHAR(50) NOT NULL DEFAULT ''":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(50) NOT NULL DEFAULT ''", $table_name, $column_name ) );
				break;

			case "VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'":
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i VARCHAR(20) NOT NULL DEFAULT 'active'", $table_name, $column_name ) );
				break;

			case 'DATE NULL DEFAULT NULL':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i DATE NULL DEFAULT NULL', $table_name, $column_name ) );
				break;

			case 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', $table_name, $column_name ) );
				break;

			case 'DATETIME NULL DEFAULT NULL':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i DATETIME NULL DEFAULT NULL', $table_name, $column_name ) );
				break;

			case 'TINYINT(1) NOT NULL DEFAULT 0':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i TINYINT(1) NOT NULL DEFAULT 0', $table_name, $column_name ) );
				break;

			case 'TEXT NULL':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i TEXT NULL', $table_name, $column_name ) );
				break;
		}
	}

	/**
	 * Adds a database index if it does not already exist.
	 *
	 * @param string $table_name Table name.
	 * @param string $index_name Index name.
	 * @param array  $columns    Column names.
	 */
	private static function add_index( $table_name, $index_name, array $columns ) {

		global $wpdb;

		if ( self::index_exists( $table_name, $index_name ) ) {
			return;
		}

		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table_name );
		$index_name = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $index_name );
		$columns    = array_values(
			array_filter(
				array_map(
					static function ( $column ) {

						return preg_replace( '/[^A-Za-z0-9_]/', '', (string) $column );
					},
					$columns
				)
			)
		);

		if ( empty( $table_name ) || empty( $index_name ) || empty( $columns ) ) {
			return;
		}

		switch ( count( $columns ) ) {
			case 1:
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i)', $table_name, $index_name, $columns[0] ) );
				break;

			case 2:
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i, %i)', $table_name, $index_name, $columns[0], $columns[1] ) );
				break;

			case 3:
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i, %i, %i)', $table_name, $index_name, $columns[0], $columns[1], $columns[2] ) );
				break;

			case 4:
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional allow-listed schema migration.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i, %i, %i, %i)', $table_name, $index_name, $columns[0], $columns[1], $columns[2], $columns[3] ) );
				break;
		}
	}

	/**
	 * Normalizes an allow-listed column definition key.
	 *
	 * @param string $definition Column definition.
	 * @return string
	 */
	private static function normalize_column_definition_key( $definition ) {

		$definition = preg_replace( '/\s+/', ' ', trim( (string) $definition ) );

		return strtoupper( $definition );
	}

	/**
	 * Rebuilds stored room quantities safely for the free single-unit engine.
	 *
	 * The free plugin uses capacity per room/unit, not bed allocation. If older bed
	 * rows exist, their capacity may be used. If no bed rows exist, existing room
	 * capacity is preserved instead of being reset to zero.
	 */
	private static function rebuild_room_capacities() {

		global $wpdb;

		$rooms_table_name     = $wpdb->prefix . 'cnwshotel_rooms';
		$units_table_name     = $wpdb->prefix . 'cnwshotel_room_units';
		$beds_table_name      = $wpdb->prefix . 'cnwshotel_room_unit_beds';
		$bed_types_table_name = $wpdb->prefix . 'cnwshotel_bed_types';

		if ( ! self::table_exists( $rooms_table_name ) || ! self::table_exists( $units_table_name ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional upgrade routine.
		$rooms = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT `id`, `post_id`, `max_persons` FROM %i',
				$rooms_table_name
			)
		);

		if ( empty( $rooms ) ) {
			return;
		}

		$can_read_beds = self::table_exists( $beds_table_name ) && self::table_exists( $bed_types_table_name );

		foreach ( $rooms as $room ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional upgrade routine.
			$units = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT `id`, `status`, `unit_number` FROM %i WHERE `room_type_id` = %d',
					$units_table_name,
					absint( $room->id )
				)
			);

			$total_capacity = 0;
			$total_units    = 0;

			if ( ! empty( $units ) ) {
				foreach ( $units as $unit ) {
					if ( 'active' === (string) $unit->status && '' !== (string) $unit->unit_number ) {
						++$total_units;
					}

					if ( ! $can_read_beds ) {
						continue;
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional upgrade routine.
					$capacity = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT COALESCE(SUM(bt.`capacity`), 0)
                             FROM %i rub
                             LEFT JOIN %i bt ON bt.`id` = rub.`bed_type_id`
                             WHERE rub.`room_unit_id` = %d',
							$beds_table_name,
							$bed_types_table_name,
							absint( $unit->id )
						)
					);

						$total_capacity += absint( $capacity );
				}
			}

			$stored_capacity = absint( $room->max_persons );
			$safe_capacity   = $total_capacity > 0 ? $total_capacity : $stored_capacity;

			if ( $safe_capacity < 1 ) {
				$safe_capacity = 1;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional upgrade routine.
			$wpdb->update(
				$wpdb->prefix . 'cnwshotel_rooms',
				array(
					'quantity'    => $total_units,
					'max_persons' => $safe_capacity,
				),
				array( 'id' => absint( $room->id ) ),
				array( '%d', '%d' ),
				array( '%d' )
			);

			wp_cache_delete( 'cnwshotel_availability_' . absint( $room->post_id ), 'cnwshotel' );
		}
	}
}
