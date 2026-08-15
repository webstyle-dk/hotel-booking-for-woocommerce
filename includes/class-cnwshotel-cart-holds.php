<?php
/**
 * Cart hold handling for WSH Hotel free booking flow.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages temporary WooCommerce cart holds for room availability.
 */
class CNWSHOTEL_Cart_Holds {

	/**
	 * Registers lightweight cleanup hook.
	 */
	public function __construct() {

		static $hooks_registered = false;

		if ( $hooks_registered ) {
			return;
		}

		$hooks_registered = true;
	}

	/**
	 * Free version has fixed 20 minute cart hold.
	 *
	 * @return int
	 */
	public function get_hold_minutes() {

		return 20;
	}

	/**
	 * Gets current session key.
	 *
	 * @return string
	 */
	public function get_session_key() {

		if ( function_exists( 'WC' ) && WC()->session ) {
			$key = WC()->session->get_customer_id();

			if ( ! empty( $key ) ) {
				return (string) $key;
			}
		}

		if ( function_exists( 'wp_get_session_token' ) ) {
			$token = wp_get_session_token();

			if ( ! empty( $token ) ) {
				return (string) $token;
			}
		}

		$remote_addr = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::server_text( 'REMOTE_ADDR', '' ) : '';
		$user_agent  = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::server_text( 'HTTP_USER_AGENT', '' ) : '';

		return 'guest_' . md5( $remote_addr . '|' . $user_agent );
	}

	/**
	 * Releases hold for a cart item.
	 *
	 * @param string $cart_item_key Cart item key.
	 */
	public function release_cart_item_hold( $cart_item_key ) {

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_cart_holds';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional hold release for custom cart hold table.
		$wpdb->update(
			$table,
			array( 'hold_status' => 'released' ),
			array(
				'cart_item_key' => sanitize_text_field( $cart_item_key ),
				'hold_status'   => 'active',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * Releases all active holds for current session.
	 */
	public function release_session_holds() {

		global $wpdb;

		$table       = $wpdb->prefix . 'cnwshotel_cart_holds';
		$session_key = $this->get_session_key();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional session hold release for custom cart hold table.
		$wpdb->update(
			$table,
			array( 'hold_status' => 'released' ),
			array(
				'session_key' => $session_key,
				'hold_status' => 'active',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * Gets active held unit IDs.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param string $ignore_cart_item_key Cart item key to ignore.
	 * @return array<int,int>
	 */
	public function get_active_hold_units( $room_id, $checkin, $checkout, $ignore_cart_item_key = '' ) {

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_cart_holds';

		if ( '' !== $ignore_cart_item_key ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cart hold lookup.
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT unit_id
                     FROM %i
                     WHERE room_id = %d
                       AND unit_id IS NOT NULL
                       AND checkin < %s
                       AND checkout > %s
                       AND hold_status = 'active'
                       AND expires_at > %s
                       AND cart_item_key <> %s",
					$table,
					absint( $room_id ),
					$checkout,
					$checkin,
					current_time( 'mysql' ),
					sanitize_text_field( $ignore_cart_item_key )
				)
			);
		} else {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional cart hold lookup.
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT unit_id
                     FROM %i
                     WHERE room_id = %d
                       AND unit_id IS NOT NULL
                       AND checkin < %s
                       AND checkout > %s
                       AND hold_status = 'active'
                       AND expires_at > %s",
					$table,
					absint( $room_id ),
					$checkout,
					$checkin,
					current_time( 'mysql' )
				)
			);
		}

		return array_map( 'absint', (array) $results );
	}

	/**
	 * Gets active held guest capacity.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param string $ignore_cart_item_key Cart item key to ignore.
	 * @return int
	 */
	public function get_active_hold_capacity( $room_id, $checkin, $checkout, $ignore_cart_item_key = '' ) {

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_cart_holds';

		if ( '' !== $ignore_cart_item_key ) {
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional active cart-hold capacity lookup in the plugin's custom table.
			$cnwshotel_active_hold_capacity = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(guests_paid), 0)
                     FROM %i
                     WHERE room_id = %d
                       AND checkin < %s
                       AND checkout > %s
                       AND hold_status = 'active'
                       AND expires_at > %s
                       AND cart_item_key <> %s",
					$table,
					absint( $room_id ),
					$checkout,
					$checkin,
					current_time( 'mysql' ),
					sanitize_text_field( $ignore_cart_item_key )
				)
			);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return absint( $cnwshotel_active_hold_capacity );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional active cart-hold capacity lookup in the plugin's custom table.
		$cnwshotel_active_hold_capacity = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(guests_paid), 0)
                 FROM %i
                 WHERE room_id = %d
                   AND checkin < %s
                   AND checkout > %s
                   AND hold_status = 'active'
                   AND expires_at > %s",
				$table,
				absint( $room_id ),
				$checkout,
				$checkin,
				current_time( 'mysql' )
			)
		);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return absint( $cnwshotel_active_hold_capacity );
	}

	/**
	 * Creates a cart hold for one room/unit.
	 *
	 * @param int    $product_id Woo product ID.
	 * @param string $cart_item_key Cart item key.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param int    $guests_real Guest count.
	 * @param bool   $is_private Deprecated compatibility argument.
	 * @return bool
	 */
	public function create_hold( $product_id, $cart_item_key, $checkin, $checkout, $guests_real, $is_private = false ) {
		unset( $is_private );

		global $wpdb;

		$rooms_table  = $wpdb->prefix . 'cnwshotel_rooms';
		$holds_table  = $wpdb->prefix . 'cnwshotel_cart_holds';
		$guests_real  = max( 1, absint( $guests_real ) );
		$room_post_id = absint( get_post_meta( absint( $product_id ), '_cnwshotel_room_post_id', true ) );

		if ( $room_post_id ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads marked room product by linked post ID.
			$room = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d LIMIT 1',
					$rooms_table,
					$room_post_id
				)
			);
		} else {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy fallback for older room products.
			$room = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE woo_product_id = %d LIMIT 1',
					$rooms_table,
					absint( $product_id )
				)
			);
		}

		if ( ! $room || ! class_exists( 'CNWSHOTEL_Booking_Engine' ) ) {
				return false;
		}

		if ( empty( $checkin ) || empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {
			return false;
		}

		$engine  = new CNWSHOTEL_Booking_Engine();
		$nights  = ( strtotime( $checkout ) - strtotime( $checkin ) ) / DAY_IN_SECONDS;
		$minstay = $engine->get_minimum_stay( absint( $room->post_id ), $checkin );

		if ( $nights < $minstay ) {
			return false;
		}

		$available_units = $engine->get_available_units_for_hold( absint( $room->id ), $checkin, $checkout, $cart_item_key );
		$units_to_assign = $engine->select_units_for_hold( $available_units, $guests_real );

		if ( empty( $units_to_assign ) ) {
			return false;
		}

		$expires_at  = wp_date( 'Y-m-d H:i:s', time() + ( $this->get_hold_minutes() * MINUTE_IN_SECONDS ) );
		$session_key = $this->get_session_key();
		$unit_id     = absint( $units_to_assign[0] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional hold insert.
		$insert = $wpdb->insert(
			$holds_table,
			array(
				'session_key'   => $session_key,
				'cart_item_key' => sanitize_text_field( $cart_item_key ),
				'room_id'       => absint( $room->id ),
				'unit_id'       => $unit_id,
				'checkin'       => $checkin,
				'checkout'      => $checkout,
				'guests_real'   => $guests_real,
				'guests_paid'   => $guests_real,
				'is_private'    => 0,
				'hold_status'   => 'active',
				'expires_at'    => $expires_at,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $insert ) {
			return false;
		}

		wp_cache_delete( 'cnwshotel_availability_' . absint( $room->post_id ), 'cnwshotel' );

		return true;
	}

	/**
	 * Converts an active hold to a booking.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $order_id Woo order ID.
	 * @return int|false Booking ID or false.
	 */
	public function convert_hold_to_booking( $cart_item_key, $order_id ) {

		global $wpdb;

		$holds_table         = $wpdb->prefix . 'cnwshotel_cart_holds';
		$bookings_table      = $wpdb->prefix . 'cnwshotel_bookings';
		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';
		$rooms_table         = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads active hold.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
                 FROM %i
                 WHERE cart_item_key = %s
                   AND hold_status = 'active'
                   AND expires_at > %s
                 ORDER BY id ASC",
				$holds_table,
				sanitize_text_field( $cart_item_key ),
				current_time( 'mysql' )
			)
		);

		if ( empty( $rows ) ) {
			return false;
		}

		$first = $rows[0];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads room for hold conversion.
		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d LIMIT 1',
				$rooms_table,
				absint( $first->room_id )
			)
		);

		if ( ! $room ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional booking insert.
		$insert = $wpdb->insert(
			$bookings_table,
			array(
				'room_id'        => absint( $first->room_id ),
				'order_id'       => absint( $order_id ),
				'checkin'        => $first->checkin,
				'checkout'       => $first->checkout,
				'guests'         => absint( $first->guests_real ),
				'guests_real'    => absint( $first->guests_real ),
				'guests_paid'    => absint( $first->guests_paid ),
				'booking_status' => 'blocked',
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( ! $insert ) {
			return false;
		}

		$booking_id = absint( $wpdb->insert_id );

		foreach ( $rows as $row ) {
			if ( ! empty( $row->unit_id ) ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Links held unit.
				$wpdb->insert(
					$booking_units_table,
					array(
						'booking_id' => $booking_id,
						'unit_id'    => absint( $row->unit_id ),
					),
					array( '%d', '%d' )
				);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Marks hold converted.
		$wpdb->update(
			$holds_table,
			array( 'hold_status' => 'converted' ),
			array( 'cart_item_key' => sanitize_text_field( $cart_item_key ) ),
			array( '%s' ),
			array( '%s' )
		);

		wp_cache_delete( 'cnwshotel_availability_' . absint( $room->post_id ), 'cnwshotel' );

		return $booking_id;
	}
}
