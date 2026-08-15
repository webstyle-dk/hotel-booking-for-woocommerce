<?php
/**
 * Uninstall cleanup for WSH Hotel Booking Management.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$cnwshotel_table_names = array(
	'cnwshotel_cart_holds',
	'cnwshotel_seasonal_pricing',
	'cnwshotel_room_unit_beds',
	'cnwshotel_room_units',
	'cnwshotel_booking_units',
	'cnwshotel_bookings',
	'cnwshotel_bed_types',
	'cnwshotel_rooms',
);
foreach ( $cnwshotel_table_names as $cnwshotel_table_name ) {
	$cnwshotel_table = $wpdb->prefix . $cnwshotel_table_name;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall intentionally drops fixed allow-listed plugin tables.
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %i',
			$cnwshotel_table
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
}

$cnwshotel_option_names = array(
	'cnwshotel_core_version',
	'cnwshotel_core_db_version',
	'cnwshotel_layout',
	'cnwshotel_room_layout',
	'cnwshotel_cart_hold_minutes',
	'cnwshotel_cart_hold_cleanup_last_run',
	'cnwshotel_db_version',
	'cnwshotel_rewrite_flushed',
	'cnwshotel_booking_page_id',
	'cnwshotel_setup_room_id',
	'cnwshotel_latest_setup_room_id',
);
foreach ( $cnwshotel_option_names as $cnwshotel_option_name ) {
	delete_option( $cnwshotel_option_name );
}

wp_clear_scheduled_hook( 'cnwshotel_expire_cart_holds' );
wp_clear_scheduled_hook( 'cnwshotel_purge_cart_holds' );

/*
 * Room type posts are user-created WordPress content and are intentionally
 * preserved on uninstall. Plugin-owned custom tables and options are removed
 * above, while WooCommerce orders remain under WooCommerce's control.
 */
