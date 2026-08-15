<?php
/**
 * WooCommerce sync for WSH Hotel free booking flow.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects room products, cart data and checkout meta with WooCommerce.
 */
class CNWSHOTEL_Woo_Sync {

	/**
	 * Registers WooCommerce hooks.
	 */
	public function __construct() {

		add_action( 'save_post_cnwshotel_room', array( $this, 'sync_product' ), 20, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_booking_data' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_dynamic_cart_prices' ), 20 );
		add_action( 'woocommerce_remove_cart_item', array( $this, 'release_cart_hold' ), 10, 2 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'release_current_session_holds' ), 10 );
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'disable_sold_individually_for_rooms' ), 10, 2 );
		add_filter( 'woocommerce_product_is_in_stock', array( $this, 'force_room_products_in_stock' ), 10, 2 );
		add_filter( 'woocommerce_product_backorders_allowed', array( $this, 'force_room_products_backorders' ), 10, 3 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'filter_formatted_order_item_meta' ), 10, 2 );

		// WooCommerce Checkout Blocks use the Store API and may preserve old Woo notices in the session.
		// Clear only our stale booking-nonce notice during checkout contexts; do not relax the original booking form nonce.
		add_action( 'woocommerce_init', array( $this, 'maybe_clear_checkout_booking_nonce_notice' ), 20 );
		add_action( 'wp', array( $this, 'maybe_clear_checkout_booking_nonce_notice' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_clear_checkout_booking_nonce_notice' ), 1 );
		add_action( 'woocommerce_before_checkout_process', array( $this, 'maybe_clear_checkout_booking_nonce_notice' ), 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'maybe_clear_checkout_booking_nonce_notice' ), 1 );
	}

	/**
	 * Gets room row by Woo product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return object|null
	 */
	private function get_room_by_product_id( $product_id ) {

		global $wpdb;

		static $room_cache = array();

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return null;
		}

		if ( array_key_exists( $product_id, $room_cache ) ) {
			return $room_cache[ $product_id ];
		}

		$room_post_id = absint( get_post_meta( $product_id, '_cnwshotel_room_post_id', true ) );
		$marker       = get_post_meta( $product_id, '_cnwshotel_room_product', true );

		if ( 'yes' !== $marker && ! $room_post_id ) {
			$room_cache[ $product_id ] = null;
			return null;
		}

		$table = $wpdb->prefix . 'cnwshotel_rooms';

		if ( $room_post_id ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads room by linked post ID from plugin table, cached for the current request.
			$room_cache[ $product_id ] = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d LIMIT 1',
					$table,
					$room_post_id
				)
			);

			return $room_cache[ $product_id ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy fallback for products created before marker meta was added.
		$room_cache[ $product_id ] = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE woo_product_id = %d LIMIT 1',
				$table,
				$product_id
			)
		);

		return $room_cache[ $product_id ];
	}

	/**
	 * Checks whether product is a room product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_room_product( $product_id ) {

		static $product_cache = array();

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return false;
		}

		if ( array_key_exists( $product_id, $product_cache ) ) {
				return $product_cache[ $product_id ];
		}

		$marker = get_post_meta( $product_id, '_cnwshotel_room_product', true );

		if ( 'yes' === $marker ) {
			$product_cache[ $product_id ] = true;
			return true;
		}

		$product_cache[ $product_id ] = ! empty( $this->get_room_by_product_id( $product_id ) );

		return $product_cache[ $product_id ];
	}

	/**
	 * Checks whether the current WooCommerce cart already contains the room product.
	 *
	 * Checkout/Store API may re-run product validation for items that are already
	 * in the cart. That is not the original booking form submission and must not
	 * require the frontend booking nonce again.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function cart_already_contains_product( $product_id ) {

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$product_id = absint( $product_id );

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['product_id'] ) && absint( $cart_item['product_id'] ) === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes stale booking nonce notices from WooCommerce checkout/session.
	 *
	 * The nonce is only required when the single-room booking form adds the room
	 * to the cart. Checkout and Store API requests may reuse the cart item later,
	 * and an old notice from a previous attempt must not block payment.
	 */
	private function remove_stale_booking_nonce_notice() {

		if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_set_notices' ) ) {
			return;
		}

		$notices = wc_get_notices();

		if ( empty( $notices['error'] ) || ! is_array( $notices['error'] ) ) {
			return;
		}

		$target = __( 'The booking request could not be verified. Please try again.', 'wsh-hotel' );

		foreach ( $notices['error'] as $index => $notice ) {
			$message = is_array( $notice ) && isset( $notice['notice'] ) ? (string) $notice['notice'] : (string) $notice;

			if ( wp_strip_all_tags( $message ) === $target ) {
				unset( $notices['error'][ $index ] );
			}
		}

		$notices['error'] = array_values( $notices['error'] );
		wc_set_notices( $notices );
	}

	/**
	 * Clears stale booking-nonce notices during checkout contexts.
	 *
	 * The booking nonce belongs to the single-room add-to-cart form only.
	 * Checkout Blocks submit through the WooCommerce Store API and do not send
	 * the original room booking nonce. If an earlier validation notice is left
	 * in the WooCommerce session, Store API checkout returns a 409 conflict.
	 *
	 * @param mixed ...$args Optional hook arguments.
	 * @return void
	 */
	public function maybe_clear_checkout_booking_nonce_notice( ...$args ) {
		unset( $args );

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) ) {
			return;
		}

		if ( $this->is_checkout_processing_request() ) {
				$this->remove_stale_booking_nonce_notice();
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$this->remove_stale_booking_nonce_notice();
		}
	}

	/**
	 * Gets nights for date range.
	 *
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @return int
	 */
	private function get_nights( $checkin, $checkout ) {

		$checkin_time  = strtotime( $checkin );
		$checkout_time = strtotime( $checkout );

		if ( ! $checkin_time || ! $checkout_time || $checkout_time <= $checkin_time ) {
			return 1;
		}

		$nights = ( $checkout_time - $checkin_time ) / DAY_IN_SECONDS;

		return max( 1, absint( $nights ) );
	}

	/**
	 * Sanitizes a date value from a request.
	 *
	 * @param mixed $value Raw value.
	 * @return string Sanitized date in Y-m-d format, or empty string.
	 */
	private function sanitize_booking_date( $value ) {

		$date = sanitize_text_field( wp_unslash( $value ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		[$year, $month, $day] = array_map( 'absint', explode( '-', $date ) );

		if ( ! wp_checkdate( $month, $day, $year, $date ) ) {
			return '';
		}

		return $date;
	}

	/**
	 * Gets the exact nonce-protected room booking form request.
	 *
	 * @return array{product_id:int,checkin:string,checkout:string,guests:int}|false
	 */
	private function get_booking_form_request() {

		return class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_booking_form_submission() : false;
	}

	/**
	 * Calculates room line total.
	 *
	 * @param WC_Product $product Woo product.
	 * @param object     $room Room row.
	 * @param int        $guests Guest count.
	 * @param string     $checkin Check-in date.
	 * @param string     $checkout Check-out date.
	 * @return float
	 */
	private function get_room_line_total( $product, $room, $guests, $checkin = '', $checkout = '' ) {

		$base_price = (float) $product->get_regular_price();

		if ( $base_price <= 0 ) {
			$base_price = (float) $product->get_price();
		}

		$nights = ( $checkin && $checkout ) ? $this->get_nights( $checkin, $checkout ) : 1;
		$guests = max( 1, absint( $guests ) );

		if ( $room && isset( $room->pricing_model ) && 'per_person' === $room->pricing_model ) {
			return $base_price * $guests * $nights;
		}

		return $base_price * $nights;
	}

	/**
	 * Redirects guest back to the room page with a safe search context.
	 *
	 * @param object $room Room row.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param int    $guests Guest count.
	 */
	private function redirect_to_unavailable_room( $room, $checkin, $checkout, $guests ) {

		wp_safe_redirect(
			add_query_arg(
				array(
					'cnwshotel_unavailable'  => 1,
					'cnwshotel_checkin'      => rawurlencode( $checkin ),
					'cnwshotel_checkout'     => rawurlencode( $checkout ),
					'cnwshotel_guests'       => max( 1, absint( $guests ) ),
					'cnwshotel_search_nonce' => class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_search_nonce_value() : '',
				),
				get_permalink( absint( $room->post_id ) )
			)
		);
		exit;
	}

	/**
	 * Syncs room type with Woo product.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether update.
	 */
	public function sync_product( $post_id, $post, $update ) {
		unset( $update );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post || 'cnwshotel_room' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
				return;
		}

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) || ! CNWSHOTEL_Frontend_Request::verify_room_save_request( $post_id ) ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads room for product sync from a custom table.
		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d LIMIT 1',
				$table,
				absint( $post_id )
			)
		);

		if ( ! $room ) {
			return;
		}

		$product_id = absint( $room->woo_product_id );
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( $product ) {
			$product->set_name( $post->post_title );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_regular_price( (string) $room->price );
			$product->set_price( (string) $room->price );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$saved_product_id = absint( $product->save() );
			update_post_meta( $saved_product_id, '_cnwshotel_room_product', 'yes' );
			update_post_meta( $saved_product_id, '_cnwshotel_room_post_id', absint( $post_id ) );
			return;
		}

		$product = new WC_Product_Simple();
		$product->set_name( $post->post_title );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( (string) $room->price );
		$product->set_price( (string) $room->price );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		$new_product_id = absint( $product->save() );

		if ( $new_product_id ) {
				update_post_meta( $new_product_id, '_cnwshotel_room_product', 'yes' );
			update_post_meta( $new_product_id, '_cnwshotel_room_post_id', absint( $post_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stores connected product ID in a custom table.
		$wpdb->update(
			$table,
			array( 'woo_product_id' => absint( $new_product_id ) ),
			array( 'post_id' => absint( $post_id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Checks whether WooCommerce is processing checkout/store-api requests.
	 *
	 * These requests may re-validate cart contents, but they are not the original
	 * single-room booking form submission and must not require the booking nonce.
	 *
	 * @return bool
	 */
	private function is_checkout_processing_request() {

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) ) {
			return false;
		}

		$request_uri = CNWSHOTEL_Frontend_Request::request_uri();

		if ( false !== strpos( $request_uri, '/wc/store/v1/checkout' ) ) {
			return true;
		}

		return function_exists( 'is_checkout' ) && is_checkout();
	}

	/**
	 * Checks whether the current request is the nonce-protected room booking form request.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_booking_form_add_to_cart_request( $product_id ) {

		$request = $this->get_booking_form_request();

		return is_array( $request ) && absint( $product_id ) === absint( $request['product_id'] );
	}

	/**
	 * Validates booking request before cart add.
	 *
	 * @param bool  $passed Passed.
	 * @param int   $product_id Product ID.
	 * @param int   $quantity Quantity.
	 * @param int   $variation_id Variation ID.
	 * @param array $variations Variations.
	 * @return bool
	 */
	public function validate_booking_data( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		unset( $quantity, $variation_id, $variations );

		if ( ! $this->is_room_product( $product_id ) ) {
			return $passed;
		}

		if ( $this->is_checkout_processing_request() ) {
				$this->remove_stale_booking_nonce_notice();
			return $passed;
		}

		if ( ! $this->is_booking_form_add_to_cart_request( $product_id ) && $this->cart_already_contains_product( $product_id ) ) {
			$this->remove_stale_booking_nonce_notice();
			return $passed;
		}

		$request = $this->get_booking_form_request();

		if ( ! is_array( $request ) || absint( $product_id ) !== absint( $request['product_id'] ) ) {
			wc_add_notice( esc_html__( 'The booking request could not be verified. Please try again.', 'wsh-hotel' ), 'error' );
				return false;
		}

		$checkin  = $request['checkin'];
		$checkout = $request['checkout'];
		$guests   = $request['guests'];

		if ( ! $checkin || ! $checkout ) {
			wc_add_notice( esc_html__( 'Please select valid check-in and check-out dates.', 'wsh-hotel' ), 'error' );
			return false;
		}

		$today = current_time( 'Y-m-d' );

		if ( $checkin < $today ) {
			wc_add_notice( esc_html__( 'Check-in cannot be in the past.', 'wsh-hotel' ), 'error' );
			return false;
		}

		if ( strtotime( $checkout ) <= strtotime( $checkin ) ) {
				wc_add_notice( esc_html__( 'Check-out must be after check-in.', 'wsh-hotel' ), 'error' );
			return false;
		}

		$room = $this->get_room_by_product_id( $product_id );

		if ( ! $room || ! class_exists( 'CNWSHOTEL_Booking_Engine' ) ) {
				wc_add_notice( esc_html__( 'Room could not be found.', 'wsh-hotel' ), 'error' );
				return false;
		}

		if ( $guests > max( 1, absint( $room->max_persons ) ) ) {
			wc_add_notice( esc_html__( 'This room cannot fit the selected number of guests.', 'wsh-hotel' ), 'error' );
			return false;
		}

		$engine = new CNWSHOTEL_Booking_Engine();

		if ( ! $engine->can_create_booking( absint( $room->id ), $checkin, $checkout, $guests, false ) ) {
			$this->redirect_to_unavailable_room( $room, $checkin, $checkout, $guests );
		}

		if ( class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			$holds             = new CNWSHOTEL_Cart_Holds();
				$temp_cart_key = 'pre_' . wp_hash( $product_id . '|' . $checkin . '|' . $checkout . '|' . $guests . '|' . microtime( true ) );
			$created           = $holds->create_hold( $product_id, $temp_cart_key, $checkin, $checkout, $guests, false );

			if ( ! $created ) {
				$this->redirect_to_unavailable_room( $room, $checkin, $checkout, $guests );
			}

			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'cnwshotel_pending_hold_key', $temp_cart_key );
			}
		}

		return true;
	}

	/**
	 * Adds cart item booking data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		unset( $variation_id );

		if ( ! $this->is_room_product( $product_id ) ) {
			return $cart_item_data;
		}

		$request = $this->get_booking_form_request();

		if ( ! is_array( $request ) || absint( $product_id ) !== absint( $request['product_id'] ) ) {
			return $cart_item_data;
		}

		if ( $request['checkin'] ) {
				$cart_item_data['cnwshotel_checkin'] = $request['checkin'];
		}

		if ( $request['checkout'] ) {
			$cart_item_data['cnwshotel_checkout'] = $request['checkout'];
		}

		$cart_item_data['cnwshotel_guests'] = $request['guests'];

		if ( function_exists( 'WC' ) && WC()->session ) {
			$pending_hold_key = WC()->session->get( 'cnwshotel_pending_hold_key' );

			if ( ! empty( $pending_hold_key ) ) {
				$cart_item_data['cnwshotel_booking_key'] = sanitize_text_field( $pending_hold_key );
				WC()->session->__unset( 'cnwshotel_pending_hold_key' );
			}
		}

		$cart_item_data['cnwshotel_booking_group'] = wp_generate_uuid4();

		return $cart_item_data;
	}

	/**
	 * Displays booking data in cart.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {

		if ( ! empty( $cart_item['cnwshotel_checkin'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Check-in', 'wsh-hotel' ),
				'value' => wc_clean( wp_date( 'd-m-Y', strtotime( $cart_item['cnwshotel_checkin'] ) ) ),
			);
		}

		if ( ! empty( $cart_item['cnwshotel_checkout'] ) ) {
				$item_data[] = array(
					'key'   => esc_html__( 'Check-out', 'wsh-hotel' ),
					'value' => wc_clean( wp_date( 'd-m-Y', strtotime( $cart_item['cnwshotel_checkout'] ) ) ),
				);
		}

		if ( ! empty( $cart_item['cnwshotel_guests'] ) ) {
			$item_data[] = array(
				'key'   => esc_html__( 'Guests', 'wsh-hotel' ),
				'value' => absint( $cart_item['cnwshotel_guests'] ),
			);
		}

		return $item_data;
	}

	/**
	 * Adds booking meta to order item.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values Values.
	 * @param WC_Order              $order Order.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		unset( $order );

		if ( ! empty( $values['cnwshotel_checkin'] ) ) {
			$item->add_meta_data( esc_html__( 'Check-in', 'wsh-hotel' ), wp_date( 'd-m-Y', strtotime( $values['cnwshotel_checkin'] ) ), true );
		}

		if ( ! empty( $values['cnwshotel_checkout'] ) ) {
			$item->add_meta_data( esc_html__( 'Check-out', 'wsh-hotel' ), wp_date( 'd-m-Y', strtotime( $values['cnwshotel_checkout'] ) ), true );
		}

		if ( ! empty( $values['cnwshotel_guests'] ) ) {
			$item->add_meta_data( esc_html__( 'Guests', 'wsh-hotel' ), absint( $values['cnwshotel_guests'] ), true );
		}

		if ( ! empty( $values['cnwshotel_booking_key'] ) ) {
				$item->add_meta_data( 'cnwshotel_hold_key', sanitize_text_field( $values['cnwshotel_booking_key'] ), true );
		}

		if ( ! empty( $values['cnwshotel_booking_group'] ) ) {
			$item->add_meta_data( 'cnwshotel_booking_group', sanitize_text_field( $values['cnwshotel_booking_group'] ), true );
		}
	}

	/**
	 * Sets dynamic room prices in cart.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function set_dynamic_cart_prices( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				return;
		}

		if ( ! $cart || is_null( $cart ) ) {
				return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
				continue;
			}

			$product_id = $cart_item['data']->get_id();

			if ( ! $this->is_room_product( $product_id ) ) {
				continue;
			}

			$room     = $this->get_room_by_product_id( $product_id );
			$guests   = ! empty( $cart_item['cnwshotel_guests'] ) ? max( 1, absint( $cart_item['cnwshotel_guests'] ) ) : 1;
			$checkin  = ! empty( $cart_item['cnwshotel_checkin'] ) ? $cart_item['cnwshotel_checkin'] : '';
			$checkout = ! empty( $cart_item['cnwshotel_checkout'] ) ? $cart_item['cnwshotel_checkout'] : '';

			$cart_item['data']->set_price( $this->get_room_line_total( $cart_item['data'], $room, $guests, $checkin, $checkout ) );
		}
	}

	/**
	 * Releases cart hold when item is removed.
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param WC_Cart $cart Cart object.
	 */
	public function release_cart_hold( $cart_item_key, $cart ) {

		if ( ! class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			return;
		}

		$cart_item_key = sanitize_text_field( $cart_item_key );
		$hold_key      = $cart_item_key;
		$removed_item  = array();

		/*
		* The hold is created before WooCommerce generates its final cart-item
		 * key. The temporary pre_* key is stored on the cart item as
		* cnwshotel_booking_key and is also the key stored in the hold table.
		* Resolve that original key before releasing the hold.
		 */
		if ( $cart && isset( $cart->removed_cart_contents[ $cart_item_key ] ) && is_array( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
			$removed_item = $cart->removed_cart_contents[ $cart_item_key ];
		} elseif ( $cart && method_exists( $cart, 'get_cart_item' ) ) {
			$candidate = $cart->get_cart_item( $cart_item_key );

			if ( is_array( $candidate ) ) {
				$removed_item = $candidate;
			}
		}

		if ( ! empty( $removed_item['cnwshotel_booking_key'] ) ) {
			$hold_key = sanitize_text_field( $removed_item['cnwshotel_booking_key'] );
		}

		$holds = new CNWSHOTEL_Cart_Holds();
		$holds->release_cart_item_hold( $hold_key );
	}

	/**
	 * Releases any remaining Hotel holds when WooCommerce empties the cart.
	 *
	 * Individual removals are handled above. This is a safe fallback for cart
	 * clear operations and for integrations that empty the cart in one step.
	 */
	public function release_current_session_holds() {

		if ( ! class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			return;
		}

		$holds = new CNWSHOTEL_Cart_Holds();
		$holds->release_session_holds();
	}

	/**
	 * Keeps room products sold individually in cart.
	 *
	 * @param bool       $sold_individually Current value.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function disable_sold_individually_for_rooms( $sold_individually, $product ) {

		if ( $product && is_a( $product, 'WC_Product' ) && $this->is_room_product( $product->get_id() ) ) {
			return true;
		}

		return $sold_individually;
	}

	/**
	 * Forces room products in stock.
	 *
	 * @param bool       $in_stock Current value.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function force_room_products_in_stock( $in_stock, $product ) {

		if ( $product && is_a( $product, 'WC_Product' ) && $this->is_room_product( $product->get_id() ) ) {
			return true;
		}

		return $in_stock;
	}

	/**
	 * Prevents backorders for room products.
	 *
	 * @param bool       $backorders_allowed Current value.
	 * @param int        $product_id Product ID.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public function force_room_products_backorders( $backorders_allowed, $product_id, $product ) {

		if ( $product && is_a( $product, 'WC_Product' ) && $this->is_room_product( $product->get_id() ) ) {
			return false;
		}

		if ( ! $product && $product_id && $this->is_room_product( $product_id ) ) {
			return false;
		}

		return $backorders_allowed;
	}

	/**
	 * Hides internal booking meta from formatted output.
	 *
	 * @param array         $formatted_meta Meta.
	 * @param WC_Order_Item $item Item.
	 * @return array
	 */
	public function filter_formatted_order_item_meta( $formatted_meta, $item ) {
		unset( $item );

		foreach ( $formatted_meta as $meta_id => $meta ) {
			if ( empty( $meta->key ) ) {
				continue;
			}

			if ( in_array( $meta->key, array( 'cnwshotel_hold_key', 'cnwshotel_booking_group', '_cnwshotel_booking_id', '_cnwshotel_booking_status' ), true ) ) {
				unset( $formatted_meta[ $meta_id ] );
			}
		}

		return $formatted_meta;
	}
}
