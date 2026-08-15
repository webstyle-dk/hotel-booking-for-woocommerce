<?php
/**
 * Synchronizes WooCommerce order status with WSH Hotel bookings.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes WooCommerce order status changes with booking rows.
 */
class CNWSHOTEL_Woo_Order_Handler {

	/**
	 * Registers WooCommerce order hooks.
	 */
	public function __construct() {

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'ensure_bookings_from_order' ), 20, 3 );
		add_action( 'woocommerce_order_status_pending', array( $this, 'handle_pending_or_on_hold' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'handle_pending_or_on_hold' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_processing_or_completed' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_processing_or_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_cancelled_like' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'handle_cancelled_like' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'handle_cancelled_like' ) );
	}

	/**
	 * Ensures bookings exist for a WooCommerce order.
	 *
	 * @param int           $order_id Order ID.
	 * @param array         $posted_data Posted checkout data.
	 * @param WC_Order|null $order Order object.
	 */
	public function ensure_bookings_from_order( $order_id, $posted_data = array(), $order = null ) {

		$this->create_missing_bookings_from_order( $order_id, $order );
	}

	/**
	 * Handles pending/on-hold order statuses.
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_pending_or_on_hold( $order_id ) {

		$this->create_missing_bookings_from_order( $order_id );
		$this->update_order_booking_status( $order_id, 'blocked' );
	}

	/**
	 * Handles processing/completed order statuses.
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_processing_or_completed( $order_id ) {

		$this->create_missing_bookings_from_order( $order_id );
		$this->update_order_booking_status( $order_id, 'confirmed' );
	}

	/**
	 * Handles cancelled/failed/refunded order statuses.
	 *
	 * @param int $order_id Order ID.
	 */
	public function handle_cancelled_like( $order_id ) {

		$this->update_order_booking_status( $order_id, 'cancelled' );
	}

	/**
	 * Creates missing bookings from cart holds attached to order items.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order Order object.
	 */
	private function create_missing_bookings_from_order( $order_id, $order = null ) {

		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			return;
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$holds = new CNWSHOTEL_Cart_Holds();

		foreach ( $order->get_items() as $item_id => $item ) {
			$existing_booking_id = wc_get_order_item_meta( $item_id, '_cnwshotel_booking_id', true );

			if ( ! empty( $existing_booking_id ) ) {
				continue;
			}

			$hold_key = $item->get_meta( 'cnwshotel_hold_key', true );

			if ( empty( $hold_key ) ) {
				$hold_key = $item->get_meta( '_cnwshotel_hold_key', true );
			}

			if ( empty( $hold_key ) ) {
				continue;
			}

			$booking_id = $holds->convert_hold_to_booking( sanitize_text_field( $hold_key ), absint( $order_id ) );

			if ( ! $booking_id ) {
				continue;
			}

			wc_update_order_item_meta( $item_id, '_cnwshotel_booking_id', absint( $booking_id ) );
			wc_update_order_item_meta( $item_id, '_cnwshotel_booking_status', 'blocked' );
		}
	}

	/**
	 * Updates all bookings for an order with one database update.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   Booking status.
	 */
	private function update_order_booking_status( $order_id, $status ) {

		global $wpdb;

		$allowed_statuses = array( 'blocked', 'confirmed', 'cancelled' );
		$status           = sanitize_key( $status );

		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return;
		}

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';
		$rooms_table    = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads affected room post IDs for cache invalidation before bulk update.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT r.post_id
                 FROM %i b
                 LEFT JOIN %i r ON b.room_id = r.id
                 WHERE b.order_id = %d
                   AND r.post_id IS NOT NULL',
				$bookings_table,
				$rooms_table,
				absint( $order_id )
			)
		);

		if ( empty( $post_ids ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional bulk DB update for booking status sync.
		$wpdb->update(
			$bookings_table,
			array( 'booking_status' => $status ),
			array( 'order_id' => absint( $order_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		$this->update_order_item_booking_status( $order_id, $status );

		foreach ( array_unique( array_map( 'absint', (array) $post_ids ) ) as $post_id ) {
			wp_cache_delete( 'cnwshotel_availability_' . $post_id, 'cnwshotel' );
		}
	}

	/**
	 * Keeps internal WooCommerce order item booking status meta in sync.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   Booking status.
	 */
	private function update_order_item_booking_status( $order_id, $status ) {

		if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'wc_update_order_item_meta' ) ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$booking_id = $item->get_meta( '_cnwshotel_booking_id', true );

			if ( empty( $booking_id ) ) {
				continue;
			}

			wc_update_order_item_meta( absint( $item_id ), '_cnwshotel_booking_status', $status );
		}
	}
}
