<?php
/**
 * Setup wizard for WSH Hotel Booking Management free version.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the guided setup wizard for the free plugin.
 */
class CNWSHOTEL_Setup_Wizard {

	const OPTION_PAGE_ID    = 'cnwshotel_booking_page_id';
	const OPTION_ROOM_ID    = 'cnwshotel_latest_setup_room_id';
	const BOOKING_PAGE_SLUG = 'room';

	/**
	 * Registers hooks.
	 */
	public function __construct() {

		add_action( 'admin_post_cnwshotel_run_setup_wizard', array( __CLASS__, 'handle_setup' ) );
	}

	/**
	 * Renders setup wizard page content.
	 */
	public static function render_page() {

		$page_id  = absint( get_option( self::OPTION_PAGE_ID, 0 ) );
		$room_id  = absint( get_option( self::OPTION_ROOM_ID, 0 ) );
		$page_url = $page_id ? get_permalink( $page_id ) : '';
		$room_url = $room_id ? get_edit_post_link( $room_id, '' ) : '';
		$done     = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_setup_done_flag() : 0;

		echo '<div class="cnwshotel_wizard">';

		if ( $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Setup completed. Your room and booking page are ready.', 'wsh-hotel' ) . '</p></div>';
		}

		echo '<div class="cnwshotel_wizard_panel" role="region" aria-label="' . esc_attr__( 'Setup wizard', 'wsh-hotel' ) . '">';
		echo '<div class="cnwshotel_wizard_rail">';
		self::step_item( '1', __( 'Room details', 'wsh-hotel' ), true );
		self::step_item( '2', __( 'Guests and price', 'wsh-hotel' ) );
		self::step_item( '3', __( 'Image and intro', 'wsh-hotel' ) );
		self::step_item( '4', __( 'Booking page', 'wsh-hotel' ) );
		echo '</div>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cnwshotel_wizard_form">';
		echo '<input type="hidden" name="action" value="cnwshotel_run_setup_wizard">';
		wp_nonce_field( CNWSHOTEL_Frontend_Request::SETUP_NONCE_ACTION, CNWSHOTEL_Frontend_Request::SETUP_NONCE_FIELD );

		echo '<section class="cnwshotel_wizard_screen is-active" data-cnwshotel_wizard_screen="0">';
		echo '<h2>' . esc_html__( 'Create a bookable room', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'Create one bookable room type or unit. You can run this wizard again later to add more rooms.', 'wsh-hotel' ) . '</p>';
		echo '<label for="cnwshotel_setup_room_name">' . esc_html__( 'Room name', 'wsh-hotel' ) . '</label>';
		echo '<input type="text" id="cnwshotel_setup_room_name" name="cnwshotel_setup_room_name" class="regular-text" required placeholder="' . esc_attr__( 'Standard room', 'wsh-hotel' ) . '">';
		echo '<label for="cnwshotel_setup_unit_name">' . esc_html__( 'Room number or unit name', 'wsh-hotel' ) . '</label>';
		echo '<input type="text" id="cnwshotel_setup_unit_name" name="cnwshotel_setup_unit_name" class="regular-text" placeholder="' . esc_attr__( 'Room 101', 'wsh-hotel' ) . '">';
		echo '</section>';

		echo '<section class="cnwshotel_wizard_screen" data-cnwshotel_wizard_screen="1">';
		echo '<h2>' . esc_html__( 'Set guests and price', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'The free engine matches one room or unit by the requested guest count.', 'wsh-hotel' ) . '</p>';
		echo '<label for="cnwshotel_setup_capacity">' . esc_html__( 'Guests this room can fit', 'wsh-hotel' ) . '</label>';
		echo '<input type="number" id="cnwshotel_setup_capacity" name="cnwshotel_setup_capacity" class="small-text" min="1" value="2" required>';
		echo '<label for="cnwshotel_setup_price">' . esc_html__( 'Price per night', 'wsh-hotel' ) . '</label>';
		echo '<input type="number" id="cnwshotel_setup_price" name="cnwshotel_setup_price" class="regular-text" min="0" step="0.01" value="0" required>';
		echo '</section>';

		echo '<section class="cnwshotel_wizard_screen" data-cnwshotel_wizard_screen="2">';
		echo '<h2>' . esc_html__( 'Add image and intro', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'This is used on the room card and room page.', 'wsh-hotel' ) . '</p>';
		echo '<input type="hidden" id="cnwshotel_setup_image_id" name="cnwshotel_setup_image_id" value="0">';
		echo '<div class="cnwshotel_wizard_image_preview" id="cnwshotel_setup_image_preview"></div>';
		echo '<p><button type="button" class="button" id="cnwshotel_setup_select_image">' . esc_html__( 'Choose room image', 'wsh-hotel' ) . '</button></p>';
		echo '<label for="cnwshotel_setup_intro">' . esc_html__( 'Short intro', 'wsh-hotel' ) . '</label>';
		echo '<textarea id="cnwshotel_setup_intro" name="cnwshotel_setup_intro" rows="5" class="large-text" placeholder="' . esc_attr__( 'Short text shown on the room card.', 'wsh-hotel' ) . '"></textarea>';
		echo '</section>';

		echo '<section class="cnwshotel_wizard_screen" data-cnwshotel_wizard_screen="3">';
		echo '<h2>' . esc_html__( 'Create booking page', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'The wizard creates the booking page if it does not already exist. The shortcodes can also be copied from Settings later.', 'wsh-hotel' ) . '</p>';
		echo '<div class="cnwshotel_wizard_shortcodes">';
		echo '<code>[cnwshotel_search]</code>';
		echo '<code>[cnwshotel_room_results]</code>';
		echo '</div>';
		echo '</section>';

		echo '<div class="cnwshotel_wizard_actions">';
		echo '<button type="button" class="button cnwshotel_wizard_prev" disabled>' . esc_html__( 'Back', 'wsh-hotel' ) . '</button>';
		echo '<button type="button" class="button button-primary cnwshotel_wizard_next">' . esc_html__( 'Next', 'wsh-hotel' ) . '</button>';
		echo '<button type="submit" class="button button-primary button-hero cnwshotel_wizard_submit">' . esc_html__( 'Save room and booking page', 'wsh-hotel' ) . '</button>';
		echo '</div>';

		echo '</form>';
		echo '</div>';

		echo '<div class="cnwshotel_admin_grid">';
		echo '<div class="cnwshotel_admin_card">';
		echo '<h3>' . esc_html__( 'Booking page', 'wsh-hotel' ) . '</h3>';
		if ( $page_url ) {
			echo '<p><a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $page_id ) ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'No booking page has been created yet.', 'wsh-hotel' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="cnwshotel_admin_card">';
		echo '<h3>' . esc_html__( 'Latest setup room', 'wsh-hotel' ) . '</h3>';
		if ( $room_url ) {
			echo '<p><a href="' . esc_url( $room_url ) . '">' . esc_html( get_the_title( $room_id ) ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'No room has been created through the setup wizard yet.', 'wsh-hotel' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="cnwshotel_admin_card">';
		echo '<h3>' . esc_html__( 'Free booking flow', 'wsh-hotel' ) . '</h3>';
		echo '<p>' . esc_html__( 'Guests search by check-in, check-out and guest count. The free engine matches one available room or unit that can fit the requested guests.', 'wsh-hotel' ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Handles setup wizard form submission.
	 */
	public static function handle_setup() {

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) || 'POST' !== CNWSHOTEL_Frontend_Request::request_method() ) {
			wp_die( esc_html__( 'Invalid setup request.', 'wsh-hotel' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run the setup wizard.', 'wsh-hotel' ) );
		}

		check_admin_referer( CNWSHOTEL_Frontend_Request::SETUP_NONCE_ACTION, CNWSHOTEL_Frontend_Request::SETUP_NONCE_FIELD );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified immediately above; fields are sanitized by sanitize_setup_submission().
		$post_data  = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$submission = CNWSHOTEL_Frontend_Request::sanitize_setup_submission( $post_data );

		$room_name = (string) $submission['room_name'];
		$unit_name = (string) $submission['unit_name'];
		$capacity  = absint( $submission['capacity'] );
		$price     = (float) $submission['price'];
		$intro     = (string) $submission['intro'];
		$image_id  = absint( $submission['image_id'] );

		if ( $image_id && ! self::is_valid_image_attachment( $image_id ) ) {
			$image_id = 0;
		}

		if ( '' === $room_name ) {
			$room_name = __( 'Standard room', 'wsh-hotel' );
		}

		if ( '' === $unit_name ) {
			$unit_name = $room_name;
		}

		$room_id = self::create_room( $room_name, $unit_name, $capacity, $price, $intro, $image_id );
		$page_id = self::create_booking_page();

		if ( class_exists( 'CNWSHOTEL_Room_CPT' ) ) {
			$room_cpt = new CNWSHOTEL_Room_CPT();
			$room_cpt->register_cpt();
		}

		flush_rewrite_rules( false );
		update_option( 'cnwshotel_rewrite_flushed', 1, false );

		$redirect = add_query_arg(
			array(
				'page'                   => 'cnwshotel-setup-wizard',
				'cnwshotel_setup_done'   => 1,
				'cnwshotel_booking_page' => absint( $page_id ),
				'cnwshotel_room_id'      => absint( $room_id ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Creates one room type and one unit.
	 *
	 * @param string $room_name Room name.
	 * @param string $unit_name Unit name.
	 * @param int    $capacity Capacity.
	 * @param float  $price Price.
	 * @param string $intro Intro.
	 * @param int    $image_id Attachment ID.
	 * @return int Post ID.
	 */
	private static function create_room( $room_name, $unit_name, $capacity, $price, $intro, $image_id ) {

		global $wpdb;

		$post_id = wp_insert_post(
			array(
				'post_title'   => $room_name,
				'post_type'    => 'cnwshotel_room',
				'post_status'  => 'publish',
				'post_content' => '',
				'post_excerpt' => $intro,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		if ( $image_id ) {
			set_post_thumbnail( absint( $post_id ), $image_id );
				update_post_meta( absint( $post_id ), 'cnwshotel_room_gallery', array( absint( $image_id ) ) );
		}

		update_post_meta( absint( $post_id ), 'cnwshotel_room_intro', $intro );

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';
		$units_table = $wpdb->prefix . 'cnwshotel_room_units';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Creates first setup room in plugin table.
		$wpdb->insert(
			$rooms_table,
			array(
				'post_id'         => absint( $post_id ),
				'room_number'     => $room_name,
				'quantity'        => 1,
				'max_persons'     => $capacity,
				'price'           => $price,
				'pricing_model'   => 'per_room',
				'allocation_mode' => 'exclusive_units',
			),
			array( '%d', '%s', '%d', '%d', '%f', '%s', '%s' )
		);

		$room_type_id = absint( $wpdb->insert_id );

		if ( $room_type_id ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Creates setup unit in plugin table.
			$wpdb->insert(
				$units_table,
				array(
					'room_type_id' => $room_type_id,
					'unit_number'  => $unit_name,
					'floor'        => 0,
					'status'       => 'active',
				),
				array( '%d', '%s', '%d', '%s' )
			);
		}

		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Simple' ) ) {
				$product = new WC_Product_Simple();
			$product->set_name( $room_name );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_regular_price( (string) $price );
			$product->set_price( (string) $price );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
				$product->set_manage_stock( false );
				$product->set_stock_status( 'instock' );

			$product_id = absint( $product->save() );

			if ( $product_id ) {
					update_post_meta( $product_id, '_cnwshotel_room_product', 'yes' );
				update_post_meta( $product_id, '_cnwshotel_room_post_id', absint( $post_id ) );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Connects room to WooCommerce product.
				$wpdb->update(
					$rooms_table,
					array( 'woo_product_id' => $product_id ),
					array( 'post_id' => absint( $post_id ) ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		update_option( self::OPTION_ROOM_ID, absint( $post_id ), false );

		return absint( $post_id );
	}

	/**
	 * Creates a booking page if one is not already stored/found.
	 *
	 * @return int Page ID.
	 */
	private static function create_booking_page() {

		$existing_id = absint( get_option( self::OPTION_PAGE_ID, 0 ) );

		if ( $existing_id && 'page' === get_post_type( $existing_id ) ) {
			return self::normalize_booking_page_slug( $existing_id );
		}

		$found_id = self::find_existing_shortcode_page();

		if ( $found_id ) {
			$found_id = self::normalize_booking_page_slug( $found_id );
			update_option( self::OPTION_PAGE_ID, absint( $found_id ), false );
			return absint( $found_id );
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Rooms', 'wsh-hotel' ),
				'post_name'    => self::BOOKING_PAGE_SLUG,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => "[cnwshotel_search]\n\n[cnwshotel_room_results]",
			),
			true
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_option( self::OPTION_PAGE_ID, absint( $page_id ), false );
			return absint( $page_id );
		}

		return 0;
	}

	/**
	 * Keeps the customer-facing booking/search page URL clean.
	 *
	 * Free creates the public booking/search page as /room/ by default. WordPress
	 * will keep the slug unique if another page already uses /room/.
	 *
	 * @param int $page_id Page ID.
	 * @return int Page ID.
	 */
	private static function normalize_booking_page_slug( $page_id ) {

		$page_id = absint( $page_id );

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return 0;
		}

		$post = get_post( $page_id );

		if ( ! $post instanceof WP_Post ) {
			return $page_id;
		}

		$content        = (string) $post->post_content;
		$is_plugin_page = false !== strpos( $content, '[cnwshotel_search]' ) || false !== strpos( $content, '[cnwshotel_room_results]' );

		if ( $is_plugin_page && self::BOOKING_PAGE_SLUG !== (string) $post->post_name ) {
			wp_update_post(
				array(
					'ID'        => $page_id,
					'post_name' => self::BOOKING_PAGE_SLUG,
				)
			);
			clean_post_cache( $page_id );
		}

		return $page_id;
	}

	/**
	 * Checks whether an attachment is a valid image for the wizard.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function is_valid_image_attachment( $attachment_id ) {

		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		return wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Finds an existing page already using CNWS Hotel free shortcodes.
	 *
	 * @return int Page ID.
	 */
	private static function find_existing_shortcode_page() {

		$query = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 1,
				's'                      => '[cnwshotel_search]',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return absint( $query->posts[0] );
		}

		return 0;
	}

	/**
	 * Renders a setup step item.
	 *
	 * @param string $number Step number.
	 * @param string $label  Step label.
	 * @param bool   $active Whether active.
	 */
	private static function step_item( $number, $label, $active = false ) {

		$class = $active ? ' is-active' : '';
		echo '<div class="cnwshotel_wizard_step' . esc_attr( $class ) . '">';
		echo '<span>' . esc_html( $number ) . '</span>';
		echo '<strong>' . esc_html( $label ) . '</strong>';
		echo '</div>';
	}
}
