<?php
/**
 * Request sanitization helpers for CNWS Hotel Free.
 *
 * Admin handlers verify their capability and nonce before passing unslashed
 * data into the sanitization methods below. Frontend public form submissions
 * validate their own form nonce before reading business fields.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes sanitized request access and nonce validation helpers.
 */
class CNWSHOTEL_Frontend_Request {

	const SEARCH_NONCE_ACTION  = 'cnwshotel_search_rooms';
	const SEARCH_NONCE_FIELD   = 'cnwshotel_search_nonce';
	const BOOKING_NONCE_ACTION = 'cnwshotel_book_room';
	const BOOKING_NONCE_FIELD  = 'cnwshotel_booking_nonce';
	const SETUP_NONCE_ACTION   = 'cnwshotel_run_setup_wizard';
	const SETUP_NONCE_FIELD    = 'cnwshotel_setup_nonce';
	const ROOM_NONCE_ACTION    = 'cnwshotel_save_room';
	const ROOM_NONCE_FIELD     = 'cnwshotel_room_nonce';
	const STATUS_NONCE_ACTION  = 'cnwshotel_update_booking_status';
	const STATUS_NONCE_FIELD   = 'cnwshotel_booking_status_nonce';
	const MOVE_NONCE_ACTION    = 'cnwshotel_move_booking_unit';
	const MOVE_NONCE_FIELD     = 'cnwshotel_move_booking_unit_nonce';

	/**
	 * Gets the current request method.
	 *
	 * @return string
	 */
	public static function request_method() {

		$method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_SCALAR );
		$method = is_string( $method ) ? strtoupper( $method ) : '';

		return in_array( $method, array( 'GET', 'POST' ), true ) ? $method : '';
	}

	/**
	 * Gets the current request URI for internal route checks.
	 *
	 * @return string
	 */
	public static function request_uri() {

		$uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL, FILTER_REQUIRE_SCALAR );

		return is_string( $uri ) ? esc_url_raw( $uri ) : '';
	}

	/**
	 * Checks whether the request method is POST.
	 *
	 * @return bool
	 */
	public static function is_post_request() {

		return 'POST' === self::request_method();
	}

	/**
	 * Sanitizes a Y-m-d date.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date( $value ) {

		if ( is_array( $value ) ) {
			return '';
		}

		$date = sanitize_text_field( (string) $value );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		[$year, $month, $day] = array_map( 'absint', explode( '-', $date ) );

		return wp_checkdate( $month, $day, $year, $date ) ? $date : '';
	}

	/**
	 * Sanitizes an integer with optional bounds.
	 *
	 * @param mixed    $value Raw value.
	 * @param int      $fallback Fallback value.
	 * @param int      $min Minimum value.
	 * @param int|null $max Maximum value.
	 * @return int
	 */
	public static function sanitize_int( $value, $fallback = 0, $min = 0, $max = null ) {

		$int = ( is_scalar( $value ) && '' !== (string) $value ) ? (int) $value : (int) $fallback;
		$int = max( (int) $min, $int );

		if ( null !== $max ) {
			$int = min( (int) $max, $int );
		}

		return $int;
	}

	/**
	 * Sanitizes a decimal value with a minimum.
	 *
	 * @param mixed $value Raw value.
	 * @param float $fallback Fallback value.
	 * @param float $min Minimum value.
	 * @return float
	 */
	public static function sanitize_float( $value, $fallback = 0.0, $min = 0.0 ) {

		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', $value );
		}

		$float = is_numeric( $value ) ? (float) $value : (float) $fallback;

		return max( (float) $min, $float );
	}

	/**
	 * Outputs a hidden search nonce field.
	 *
	 * @return void
	 */
	public static function search_nonce_field() {

		wp_nonce_field( self::SEARCH_NONCE_ACTION, self::SEARCH_NONCE_FIELD, false );
	}

	/**
	 * Outputs a hidden booking nonce field.
	 *
	 * @return void
	 */
	public static function booking_nonce_field() {

		wp_nonce_field( self::BOOKING_NONCE_ACTION, self::BOOKING_NONCE_FIELD, false );
	}

	/**
	 * Creates a fresh nonce for read-only search links.
	 *
	 * @return string
	 */
	public static function get_search_nonce_value() {

		return wp_create_nonce( self::SEARCH_NONCE_ACTION );
	}

	/**
	 * Returns search nonce query arguments.
	 *
	 * @return array<string,string>
	 */
	public static function get_search_nonce_query_arg() {

		return array( self::SEARCH_NONCE_FIELD => self::get_search_nonce_value() );
	}

	/**
	 * Gets a scalar value from an unslashed request array.
	 *
	 * @param array<string,mixed> $data Request data.
	 * @param string              $key Field key.
	 * @param mixed               $fallback Fallback value.
	 * @return mixed
	 */
	private static function scalar_from_array( $data, $key, $fallback = '' ) {

		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? $data[ $key ] : $fallback;
	}

	/**
	 * Gets an array value from an unslashed request array.
	 *
	 * @param array<string,mixed> $data Request data.
	 * @param string              $key Field key.
	 * @return array<int|string,mixed>
	 */
	private static function array_from_array( $data, $key ) {

		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
	}

	/**
	 * Gets a sanitized scalar query value.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private static function query_text( $key ) {

		$value = filter_input( INPUT_GET, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_SCALAR );

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Gets a sanitized integer-like query value.
	 *
	 * @param string $key Query key.
	 * @return int
	 */
	private static function query_int( $key ) {

		$value = filter_input( INPUT_GET, $key, FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_SCALAR );

		return is_string( $value ) || is_int( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Checks whether one of the public search query fields exists.
	 *
	 * @return bool
	 */
	private static function has_search_query_fields() {

		return filter_has_var( INPUT_GET, 'cnwshotel_checkin' )
			|| filter_has_var( INPUT_GET, 'cnwshotel_checkout' )
			|| filter_has_var( INPUT_GET, 'cnwshotel_guests' )
			|| filter_has_var( INPUT_GET, 'cnwshotel_unavailable' );
	}

	/**
	 * Gets a nonce-protected public search request.
	 *
	 * @param string $source Request source.
	 * @return array{checkin:string,checkout:string,guests:int,unavailable:bool,has_dates:bool,nonce_valid:bool}
	 */
	public static function get_search_request( $source = 'get' ) {

		$defaults = array(
			'checkin'     => '',
			'checkout'    => '',
			'guests'      => 1,
			'unavailable' => false,
			'has_dates'   => false,
			'nonce_valid' => true,
		);

		if ( 'get' !== strtolower( (string) $source ) || 'GET' !== self::request_method() ) {
			return $defaults;
		}

		if ( ! self::has_search_query_fields() ) {
			return $defaults;
		}

		$nonce = self::query_text( self::SEARCH_NONCE_FIELD );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::SEARCH_NONCE_ACTION ) ) {
			$defaults['nonce_valid'] = false;
			return $defaults;
		}

		$checkin_raw  = self::query_text( 'cnwshotel_checkin' );
		$checkout_raw = self::query_text( 'cnwshotel_checkout' );
		$guests_raw   = self::query_int( 'cnwshotel_guests' );
		$notice_raw   = self::query_text( 'cnwshotel_unavailable' );

		$checkin  = self::sanitize_date( $checkin_raw );
		$checkout = self::sanitize_date( $checkout_raw );

		return array(
			'checkin'     => $checkin,
			'checkout'    => $checkout,
			'guests'      => self::sanitize_int( $guests_raw, 1, 1, 99 ),
			'unavailable' => '' !== sanitize_text_field( (string) $notice_raw ),
			'has_dates'   => '' !== $checkin && '' !== $checkout,
			'nonce_valid' => true,
		);
	}

	/**
	 * Sanitizes setup wizard data after the admin handler has verified access.
	 *
	 * @param array<string,mixed> $data Unslashed POST data.
	 * @return array<string,mixed>
	 */
	public static function sanitize_setup_submission( $data ) {

		$room_name_raw = self::scalar_from_array( $data, 'cnwshotel_setup_room_name' );
		$unit_name_raw = self::scalar_from_array( $data, 'cnwshotel_setup_unit_name' );
		$capacity_raw  = self::scalar_from_array( $data, 'cnwshotel_setup_capacity', 1 );
		$price_raw     = self::scalar_from_array( $data, 'cnwshotel_setup_price' );
		$intro_raw     = self::scalar_from_array( $data, 'cnwshotel_setup_intro' );
		$image_id_raw  = self::scalar_from_array( $data, 'cnwshotel_setup_image_id', 0 );

		return array(
			'room_name' => sanitize_text_field( (string) $room_name_raw ),
			'unit_name' => sanitize_text_field( (string) $unit_name_raw ),
			'capacity'  => self::sanitize_int( $capacity_raw, 1, 1, 99 ),
			'price'     => self::sanitize_float( $price_raw, 0.0, 0.0 ),
			'intro'     => sanitize_textarea_field( (string) $intro_raw ),
			'image_id'  => self::sanitize_int( $image_id_raw, 0, 0 ),
		);
	}

	/**
	 * Checks the exact WordPress room-save request before related hooks run.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function verify_room_save_request( $post_id ) {

		$post_id = absint( $post_id );

		if ( ! $post_id || 'POST' !== self::request_method() || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence is checked before using check_admin_referer() so programmatic saves without this meta box can return cleanly.
		if ( ! isset( $_POST[ self::ROOM_NONCE_FIELD ] ) || ! is_scalar( $_POST[ self::ROOM_NONCE_FIELD ] ) ) {
			return false;
		}

		return (bool) check_admin_referer( self::ROOM_NONCE_ACTION, self::ROOM_NONCE_FIELD );
	}

	/**
	 * Sanitizes complete room-save data after the save handler has verified access.
	 *
	 * @param array<string,mixed> $data Unslashed POST data.
	 * @return array<string,mixed>
	 */
	public static function sanitize_room_save_submission( $data ) {

		$room_numbers_raw = map_deep( self::array_from_array( $data, 'room_number' ), 'sanitize_text_field' );
		$floors_raw       = map_deep( self::array_from_array( $data, 'floor' ), 'absint' );
		$statuses_raw     = map_deep( self::array_from_array( $data, 'status' ), 'sanitize_key' );
		$max_persons_raw  = self::scalar_from_array( $data, 'cnwshotel_max_persons', 1 );
		$price_raw        = self::scalar_from_array( $data, 'cnwshotel_price' );
		$pricing_raw      = self::scalar_from_array( $data, 'cnwshotel_pricing_model', 'per_room' );
		$intro_raw        = self::scalar_from_array( $data, 'cnwshotel_room_intro' );
		$size_raw         = self::scalar_from_array( $data, 'cnwshotel_room_size', 0 );
		$gallery_raw      = self::scalar_from_array( $data, 'cnwshotel_room_gallery' );

		$pricing_model = sanitize_key( (string) $pricing_raw );
		if ( ! in_array( $pricing_model, array( 'per_room', 'per_person' ), true ) ) {
			$pricing_model = 'per_room';
		}

		$room_numbers = array_values( array_map( 'sanitize_text_field', (array) map_deep( $room_numbers_raw, 'sanitize_text_field' ) ) );
		$floors       = array_values( array_map( 'absint', (array) map_deep( $floors_raw, 'absint' ) ) );
		$statuses     = array_values( array_map( 'sanitize_key', (array) map_deep( $statuses_raw, 'sanitize_key' ) ) );
		$gallery      = json_decode( (string) $gallery_raw, true );
		$gallery      = is_array( $gallery ) ? array_values( array_filter( array_map( 'absint', $gallery ) ) ) : array();

		return array(
			'room_numbers'  => $room_numbers,
			'floors'        => $floors,
			'statuses'      => $statuses,
			'max_persons'   => self::sanitize_int( $max_persons_raw, 1, 1, 99 ),
			'price'         => self::sanitize_float( $price_raw, 0.0, 0.0 ),
			'pricing_model' => $pricing_model,
			'room_intro'    => sanitize_textarea_field( (string) $intro_raw ),
			'room_size'     => self::sanitize_int( $size_raw, 0, 0, 10000 ),
			'gallery'       => $gallery,
		);
	}

	/**
	 * Gets a nonced frontend booking form submission.
	 *
	 * @return array{product_id:int,checkin:string,checkout:string,guests:int}|false
	 */
	public static function get_booking_form_submission() {

		static $checked = false;
		static $result  = false;

		if ( $checked ) {
			return $result;
		}

		$checked = true;

		if ( 'POST' !== self::request_method() ) {
			return $result;
		}

		$nonce = filter_input( INPUT_POST, self::BOOKING_NONCE_FIELD, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_SCALAR );
		$nonce = is_string( $nonce ) ? sanitize_text_field( $nonce ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::BOOKING_NONCE_ACTION ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The exact public booking nonce is verified immediately above; fields are sanitized by sanitize_booking_form_submission().
		$post_data = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$result    = self::sanitize_booking_form_submission( $post_data );

		return $result;
	}

	/**
	 * Sanitizes a verified frontend booking form submission.
	 *
	 * @param array<string,mixed> $data Unslashed POST data.
	 * @return array{product_id:int,checkin:string,checkout:string,guests:int}|false
	 */
	private static function sanitize_booking_form_submission( $data ) {

		$marker_raw   = self::scalar_from_array( $data, 'cnwshotel_booking_form', 0 );
		$product_raw  = self::scalar_from_array( $data, 'add-to-cart', 0 );
		$checkin_raw  = self::scalar_from_array( $data, 'cnwshotel_checkin' );
		$checkout_raw = self::scalar_from_array( $data, 'cnwshotel_checkout' );
		$guests_raw   = self::scalar_from_array( $data, 'cnwshotel_guests', 1 );

		if ( 1 !== self::sanitize_int( $marker_raw, 0, 0, 1 ) ) {
			return false;
		}

		$product_id = self::sanitize_int( $product_raw, 0, 0 );
		if ( ! $product_id ) {
			return false;
		}

		return array(
			'product_id' => $product_id,
			'checkin'    => self::sanitize_date( $checkin_raw ),
			'checkout'   => self::sanitize_date( $checkout_raw ),
			'guests'     => self::sanitize_int( $guests_raw, 1, 1, 99 ),
		);
	}

	/**
	 * Sanitizes booking-status action data after the admin handler has verified access.
	 *
	 * @param array<string,mixed> $data Unslashed POST data.
	 * @return array{booking_id:int,status:string}|false
	 */
	public static function sanitize_booking_status_submission( $data ) {

		$booking_id_raw = self::scalar_from_array( $data, 'booking_id', 0 );
		$status_raw     = self::scalar_from_array( $data, 'checkin_status' );

		$status = sanitize_key( (string) $status_raw );
		if ( ! in_array( $status, array( 'pending', 'checked_in', 'checked_out', 'no_show' ), true ) ) {
			return false;
		}

		return array(
			'booking_id' => self::sanitize_int( $booking_id_raw, 0, 0 ),
			'status'     => $status,
		);
	}

	/**
	 * Sanitizes move-booking-unit action data after the admin handler has verified access.
	 *
	 * @param array<string,mixed> $data Unslashed POST data.
	 * @return array{booking_id:int,unit_id:int}|false
	 */
	public static function sanitize_move_booking_unit_submission( $data ) {

		$booking_id_raw = self::scalar_from_array( $data, 'booking_id', 0 );
		$unit_id_raw    = self::scalar_from_array( $data, 'unit_id', 0 );

		return array(
			'booking_id' => self::sanitize_int( $booking_id_raw, 0, 0 ),
			'unit_id'    => self::sanitize_int( $unit_id_raw, 0, 0 ),
		);
	}

	/**
	 * Gets the current read-only WordPress admin page slug.
	 *
	 * @return string
	 */
	public static function get_admin_page_slug() {

		return sanitize_key( self::query_text( 'page' ) );
	}

	/**
	 * Gets the read-only setup completion flag from an admin redirect URL.
	 *
	 * @return int
	 */
	public static function get_setup_done_flag() {

		return self::sanitize_int( self::query_int( 'cnwshotel_setup_done' ), 0, 0, 1 );
	}

	/**
	 * Gets the read-only Settings tab from an admin URL.
	 *
	 * @return string
	 */
	public static function get_settings_tab() {

		$tab = sanitize_key( self::query_text( 'tab' ) );

		return in_array( $tab, array( 'booking', 'shortcodes', 'system' ), true ) ? $tab : 'booking';
	}

	/**
	 * Gets the read-only Settings API success flag from an admin URL.
	 *
	 * @return bool
	 */
	public static function get_settings_updated_flag() {

		$value = self::query_text( 'settings-updated' );

		return '' !== $value;
	}

	/**
	 * Gets the read-only calendar offset from an admin navigation URL.
	 *
	 * @return int
	 */
	public static function get_calendar_offset() {

		return self::sanitize_int( self::query_int( 'offset' ), 0, 0, 3650 );
	}

	/**
	 * Gets a sanitized server value.
	 *
	 * @param string $key Server key.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	public static function server_text( $key, $fallback = '' ) {

		$key = preg_replace( '/[^A-Z0-9_]/', '', strtoupper( (string) $key ) );

		if ( '' === $key ) {
			return $fallback;
		}

		$value = filter_input( INPUT_SERVER, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_SCALAR );
		$value = is_string( $value ) ? $value : $fallback;

		return sanitize_text_field( (string) $value );
	}
}
