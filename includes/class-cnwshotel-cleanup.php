<?php
/**
 * Scheduled cleanup for WSH Hotel cart holds.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers scheduled cleanup for expired cart holds.
 */
class CNWSHOTEL_Cleanup {

	const EXPIRE_HOOK = 'cnwshotel_expire_cart_holds';
	const PURGE_HOOK  = 'cnwshotel_purge_cart_holds';

	/**
	 * Registers cron hooks only.
	 *
	 * Scheduling is performed on activation instead of on every init request.
	 */
	public function __construct() {

		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( self::EXPIRE_HOOK, array( $this, 'expire_old_holds' ) );
		add_action( self::PURGE_HOOK, array( $this, 'purge_old_holds' ) );
	}

	/**
	 * Adds a 15-minute schedule.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public static function add_cron_schedules( $schedules ) {

		if ( ! isset( $schedules['cnwshotel_every_15_minutes'] ) ) {
			$schedules['cnwshotel_every_15_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 Minutes', 'wsh-hotel' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedules cleanup events.
	 */
	public static function schedule_events() {

		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		if ( ! wp_next_scheduled( self::EXPIRE_HOOK ) ) {
			wp_schedule_event( time() + 60, 'cnwshotel_every_15_minutes', self::EXPIRE_HOOK );
		}

		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
				wp_schedule_event( time() + 300, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Clears cleanup events.
	 */
	public static function clear_scheduled_events() {

		wp_clear_scheduled_hook( self::EXPIRE_HOOK );
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	/**
	 * Marks old active holds as expired.
	 */
	public function expire_old_holds() {

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_cart_holds';
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup for plugin table.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
                 SET hold_status = 'expired'
                 WHERE hold_status = 'active'
                   AND expires_at < %s",
				$table,
				$now
			)
		);
	}

	/**
	 * Deletes old expired/released/converted holds.
	 */
	public function purge_old_holds() {

		global $wpdb;

		$table            = $wpdb->prefix . 'cnwshotel_cart_holds';
		$expired_cutoff   = wp_date( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		$released_cutoff  = wp_date( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		$converted_cutoff = wp_date( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

		$this->delete_holds_by_status( $table, 'expired', $expired_cutoff );
		$this->delete_holds_by_status( $table, 'released', $released_cutoff );
		$this->delete_holds_by_status( $table, 'converted', $converted_cutoff );
	}

	/**
	 * Deletes old holds by status.
	 *
	 * @param string $table  Table name.
	 * @param string $status Hold status.
	 * @param string $cutoff Created-at cutoff.
	 */
	private function delete_holds_by_status( $table, $status, $cutoff ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup for plugin table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE hold_status = %s
                   AND created_at < %s',
				$table,
				sanitize_key( $status ),
				$cutoff
			)
		);
	}
}
