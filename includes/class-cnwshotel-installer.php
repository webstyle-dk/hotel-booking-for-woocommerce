<?php
/**

 * Activation and base database table creation for WSH Hotel Booking Management.

 * This installer is intentionally conservative:

 * - It creates missing free-version tables.

 * - It does not run dbDelta() on existing tables, to avoid duplicate index warnings.

 * - Existing table changes are handled by CNWSHOTEL_Upgrade.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit;
}

/**
 * Creates and upgrades the base plugin database tables on activation.
 */
class CNWSHOTEL_Installer {

	/**
		* Database schema version used by the free plugin.
		*/

	const DB_VERSION = '1.1.1';

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {

		self::maybe_create_tables();

		if ( class_exists( 'CNWSHOTEL_Upgrade' ) && method_exists( 'CNWSHOTEL_Upgrade', 'maybe_upgrade_database' ) ) {

			CNWSHOTEL_Upgrade::maybe_upgrade_database( true );

		}

		update_option( 'cnwshotel_core_version', defined( 'CNWSHOTEL_VERSION' ) ? CNWSHOTEL_VERSION : self::DB_VERSION, false );
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {

		if ( class_exists( 'CNWSHOTEL_Cleanup' ) && method_exists( 'CNWSHOTEL_Cleanup', 'clear_scheduled_events' ) ) {

			CNWSHOTEL_Cleanup::clear_scheduled_events();

		}
	}

	/**
	 * Creates only missing free-version tables.
	 * Existing tables are never passed to dbDelta() here. This prevents duplicate
	 * key warnings during activation on sites that previously had WSH installed.
	 */
	public static function maybe_create_tables() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::get_create_table_sql() as $table_name => $sql ) {

			if ( self::table_exists( $table_name ) ) {

				continue;

			}

			dbDelta( $sql );

		}
	}

	/**
	 * Returns free-version CREATE TABLE statements.
	 *
	 * @return array<string,string>
	 */
	public static function get_create_table_sql() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';

		$room_units_table = $wpdb->prefix . 'cnwshotel_room_units';

		$room_unit_beds_table = $wpdb->prefix . 'cnwshotel_room_unit_beds';

		$bed_types_table = $wpdb->prefix . 'cnwshotel_bed_types';

		$seasonal_table = $wpdb->prefix . 'cnwshotel_seasonal_pricing';

		$cart_holds_table = $wpdb->prefix . 'cnwshotel_cart_holds';

		return array(

			$rooms_table          => "CREATE TABLE {$rooms_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                post_id BIGINT UNSIGNED NOT NULL,

                woo_product_id BIGINT UNSIGNED NULL DEFAULT NULL,

                room_number VARCHAR(191) NULL DEFAULT '',

                quantity INT NOT NULL DEFAULT 0,

                max_persons INT NOT NULL DEFAULT 0,

                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

                pricing_model VARCHAR(50) NOT NULL DEFAULT 'per_room',

                allocation_mode VARCHAR(50) NOT NULL DEFAULT 'exclusive_units',

                PRIMARY KEY  (id),

                KEY post_id (post_id),

                KEY woo_product_id (woo_product_id)

            ) {$charset_collate};",

			$bookings_table       => "CREATE TABLE {$bookings_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                order_id BIGINT UNSIGNED NULL DEFAULT NULL,

                room_id BIGINT UNSIGNED NOT NULL,

                checkin DATE NOT NULL,

                checkout DATE NOT NULL,

                guests INT NOT NULL DEFAULT 1,

                guests_real INT NOT NULL DEFAULT 1,

                guests_paid INT NOT NULL DEFAULT 1,

                booking_status VARCHAR(50) NOT NULL DEFAULT 'confirmed',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY  (id),

                KEY order_id (order_id),

                KEY room_id (room_id),

                KEY booking_status (booking_status),

                KEY booking_dates (checkin, checkout),

                KEY room_status_dates (room_id, booking_status, checkin, checkout)

            ) {$charset_collate};",

			$booking_units_table  => "CREATE TABLE {$booking_units_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                booking_id BIGINT UNSIGNED NOT NULL,

                unit_id BIGINT UNSIGNED NOT NULL,

                PRIMARY KEY  (id),

                KEY booking_id (booking_id),

                KEY unit_id (unit_id)

            ) {$charset_collate};",

			$room_units_table     => "CREATE TABLE {$room_units_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                room_type_id BIGINT UNSIGNED NOT NULL,

                unit_number VARCHAR(191) NOT NULL,

                floor INT NOT NULL DEFAULT 0,

                status VARCHAR(50) NOT NULL DEFAULT 'active',

                PRIMARY KEY  (id),

                KEY room_type_id (room_type_id),

                KEY status (status),

                KEY room_status (room_type_id, status)

            ) {$charset_collate};",

			$room_unit_beds_table => "CREATE TABLE {$room_unit_beds_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                room_unit_id BIGINT UNSIGNED NOT NULL,

                bed_type_id BIGINT UNSIGNED NOT NULL,

                PRIMARY KEY  (id),

                KEY room_unit_id (room_unit_id),

                KEY bed_type_id (bed_type_id)

            ) {$charset_collate};",

			$bed_types_table      => "CREATE TABLE {$bed_types_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                name VARCHAR(191) NOT NULL,

                capacity INT NOT NULL DEFAULT 1,

                description TEXT NULL,

                PRIMARY KEY  (id)

            ) {$charset_collate};",

			$seasonal_table       => "CREATE TABLE {$seasonal_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                room_id BIGINT UNSIGNED NOT NULL,

                date_start DATE NOT NULL,

                date_end DATE NOT NULL,

                modifier_type VARCHAR(50) NOT NULL,

                modifier_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,

                PRIMARY KEY  (id),

                KEY room_id (room_id),

                KEY date_range (date_start, date_end),

                KEY room_type_dates (room_id, modifier_type, date_start, date_end)

            ) {$charset_collate};",

			$cart_holds_table     => "CREATE TABLE {$cart_holds_table} (

                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

                session_key VARCHAR(191) NOT NULL,

                cart_item_key VARCHAR(191) NOT NULL,

                room_id BIGINT UNSIGNED NOT NULL,

                unit_id BIGINT UNSIGNED NULL DEFAULT NULL,

                checkin DATE NOT NULL,

                checkout DATE NOT NULL,

                guests_real INT NOT NULL DEFAULT 1,

                guests_paid INT NOT NULL DEFAULT 1,

                is_private TINYINT(1) NOT NULL DEFAULT 0,

                hold_status VARCHAR(20) NOT NULL DEFAULT 'active',

                expires_at DATETIME NOT NULL,

                created_at DATETIME NOT NULL,

                PRIMARY KEY  (id),

                KEY room_dates (room_id, checkin, checkout, hold_status),

                KEY session_cart (session_key, cart_item_key),

                KEY unit_id (unit_id),

                KEY unit_dates (unit_id, hold_status, checkin, checkout),

                KEY status_expires (hold_status, expires_at),

                KEY expires_at (expires_at)

            ) {$charset_collate};",

		);
	}

	/**
	 * Checks whether a database table already exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	public static function table_exists( $table_name ) {

		global $wpdb;

		$table_name = (string) $table_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check during installer.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		);

		return $found === $table_name;
	}
}
