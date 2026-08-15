<?php
/**
 * Plugin Name: WSH Hotel Booking Management
 * Description: Simple room booking for WordPress and WooCommerce. Create room types, show availability and accept direct bookings and payment in WooCommerce.
 * Version: 1.1.53
 * Author: CN Web-Style
 * Author URI: https://web-style.dk
 * Text Domain: wsh-hotel
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CNWSHOTEL_VERSION', '1.1.53' );
define( 'CNWSHOTEL_DB_VERSION', '1.1.47' );
define( 'CNWSHOTEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'CNWSHOTEL_URL', plugin_dir_url( __FILE__ ) );
define( 'CNWSHOTEL_FILE', __FILE__ );
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-frontend-request.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-db.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-installer.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-upgrade.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-upgrade-page.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-room-cpt.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-search.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-room-grid.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-booking-engine.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-bookings-admin.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-calendar.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-cart-holds.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-woo-sync.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-woo-order-handler.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-cleanup.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-setup-wizard.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-layout-engine.php';
require_once CNWSHOTEL_PATH . 'includes/class-cnwshotel-admin-menu.php';
register_activation_hook( __FILE__, 'cnwshotel_activate' );
register_deactivation_hook( __FILE__, 'cnwshotel_deactivate' );
add_action( 'plugins_loaded', 'cnwshotel_init' );
add_action( 'wp_enqueue_scripts', 'cnwshotel_enqueue_frontend_assets' );
add_action( 'admin_enqueue_scripts', 'cnwshotel_enqueue_admin_assets' );
add_filter( 'admin_body_class', 'cnwshotel_admin_body_class' );
add_action( 'before_woocommerce_init', 'cnwshotel_declare_woocommerce_feature_compatibility' );
/**
 * Declares compatibility with WooCommerce features used by the booking flow.
 */
function cnwshotel_declare_woocommerce_feature_compatibility() {

	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
}

/**
 * Runs on plugin activation.
 */
function cnwshotel_activate() {

	if ( class_exists( 'CNWSHOTEL_Room_CPT' ) ) {
		$room_cpt = new CNWSHOTEL_Room_CPT();
		$room_cpt->register_cpt();
	}

	CNWSHOTEL_Installer::activate();
	flush_rewrite_rules( false );
	update_option( 'cnwshotel_rewrite_flushed', 1, false );
	update_option( 'cnwshotel_cart_hold_minutes', 20, false );

	if ( class_exists( 'CNWSHOTEL_Cleanup' ) && method_exists( 'CNWSHOTEL_Cleanup', 'schedule_events' ) ) {
		CNWSHOTEL_Cleanup::schedule_events();
	}
}

/**
 * Runs on plugin deactivation.
 */
function cnwshotel_deactivate() {

	CNWSHOTEL_Installer::deactivate();
	flush_rewrite_rules( false );
}

/**
 * Initializes free plugin services.
 */
function cnwshotel_init() {

	new CNWSHOTEL_Upgrade();
	new CNWSHOTEL_Room_CPT();
	new CNWSHOTEL_Search();
	new CNWSHOTEL_Room_Grid();
	new CNWSHOTEL_Calendar();
	new CNWSHOTEL_Bookings_Admin();
	new CNWSHOTEL_Cart_Holds();
	new CNWSHOTEL_Admin_Menu();
	new CNWSHOTEL_Setup_Wizard();

	if ( class_exists( 'WooCommerce' ) ) {
		new CNWSHOTEL_Woo_Sync();
		new CNWSHOTEL_Woo_Order_Handler();
	}

	if ( class_exists( 'CNWSHOTEL_Cleanup' ) ) {
		new CNWSHOTEL_Cleanup();
	}
}

/**
 * Checks whether the current frontend page needs CNWS Hotel assets.
 *
 * @param bool $include_woocommerce Whether WooCommerce cart/checkout screens should match.
 * @return bool
 */
function cnwshotel_should_enqueue_frontend_assets( $include_woocommerce = true ) {

	if ( is_admin() ) {
		return false;
	}

	if ( is_singular( 'cnwshotel_room' ) ) {
		return true;
	}

	$booking_page_id = absint( get_option( 'cnwshotel_booking_page_id', 0 ) );

	if ( $booking_page_id && is_page( $booking_page_id ) ) {
		return true;
	}

	if ( is_singular() ) {
		$post = get_post();

		if ( $post instanceof WP_Post ) {
			$content = (string) $post->post_content;

			if ( has_shortcode( $content, 'cnwshotel_search' ) || has_shortcode( $content, 'cnwshotel_room_results' ) || has_shortcode( $content, 'cnwshotel_rooms' ) ) {
				return true;
			}
		}
	}

	if ( $include_woocommerce && function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && ( is_cart() || is_checkout() ) ) {
		return true;
	}

	return false;
}

/**
 * Enqueues frontend assets only where the booking flow can be displayed.
 */
function cnwshotel_enqueue_frontend_assets() {

	$should_enqueue_css = cnwshotel_should_enqueue_frontend_assets( true );
	$should_enqueue_js  = cnwshotel_should_enqueue_frontend_assets( false );

	if ( ! $should_enqueue_css && ! $should_enqueue_js ) {
		return;
	}

	$frontend_css_path = CNWSHOTEL_PATH . 'assets/css/cnwshotel-frontend.css';
	$frontend_js_path  = CNWSHOTEL_PATH . 'assets/js/cnwshotel-booking.js';

	if ( $should_enqueue_css && file_exists( $frontend_css_path ) ) {
		wp_enqueue_style(
			'cnwshotel-frontend',
			CNWSHOTEL_URL . 'assets/css/cnwshotel-frontend.css',
			array(),
			filemtime( $frontend_css_path )
		);
	}

	if ( $should_enqueue_js && file_exists( $frontend_js_path ) ) {
		wp_enqueue_script(
			'cnwshotel-booking',
			CNWSHOTEL_URL . 'assets/js/cnwshotel-booking.js',
			array(),
			filemtime( $frontend_js_path ),
			true
		);

		wp_localize_script(
			'cnwshotel-booking',
			'cnwshotelBookingLabels',
			array(
				'staySingle'        => esc_html__( '1 night', 'wsh-hotel' ),
				/* translators: %d: number of nights. */
				'stayMultiple'      => esc_html__( '%d nights', 'wsh-hotel' ),
				'currencyCode'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'DKK',
				'decimalSeparator'  => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',',
				'thousandSeparator' => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '.',
				'priceDecimals'     => function_exists( 'wc_get_price_decimals' ) ? absint( wc_get_price_decimals() ) : 2,
			)
		);
	}
}

/**
 * Gets current admin page slug without relying on unsanitized superglobals.
 *
 * @return string
 */
function cnwshotel_get_admin_page_slug() {

	return class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_admin_page_slug() : '';
}

/**
 * Enqueues admin assets on CNWS Hotel screens.
 *
 * @param string $hook Current admin hook.
 */
function cnwshotel_enqueue_admin_assets( $hook ) {

	unset( $hook );

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$page   = cnwshotel_get_admin_page_slug();

	$is_cnwshotel_page = 0 === strpos( $page, 'cnwshotel-' );
	$is_room_cpt       = $screen && isset( $screen->post_type ) && 'cnwshotel_room' === $screen->post_type;

	if ( ! $is_cnwshotel_page && ! $is_room_cpt ) {
		return;
	}

	$admin_css_path = CNWSHOTEL_PATH . 'assets/css/cnwshotel-admin.css';
	$admin_js_path  = CNWSHOTEL_PATH . 'assets/js/cnwshotel-admin.js';

	if ( file_exists( $admin_css_path ) ) {
		wp_enqueue_style(
			'cnwshotel-admin',
			CNWSHOTEL_URL . 'assets/css/cnwshotel-admin.css',
			array(),
			filemtime( $admin_css_path )
		);
	}

	if ( 'cnwshotel-setup-wizard' === $page ) {
		wp_enqueue_media();
	}

	if ( file_exists( $admin_js_path ) ) {
		wp_enqueue_script(
			'cnwshotel-admin',
			CNWSHOTEL_URL . 'assets/js/cnwshotel-admin.js',
			array(),
			filemtime( $admin_js_path ),
			true
		);

		wp_localize_script(
			'cnwshotel-admin',
			'cnwshotelRoomAdmin',
			array(
				'roomNumberPlaceholder' => esc_html__( 'Room number or unit name', 'wsh-hotel' ),
				'floorPlaceholder'      => esc_html__( 'Floor', 'wsh-hotel' ),
				'active'                => esc_html__( 'Active', 'wsh-hotel' ),
				'inactive'              => esc_html__( 'Inactive', 'wsh-hotel' ),
				'removeRoom'            => esc_html__( 'Remove room', 'wsh-hotel' ),
				'removeConfirm'         => esc_html__( 'Remove this room?', 'wsh-hotel' ),
				'mediaTitle'            => esc_html__( 'Choose room image', 'wsh-hotel' ),
				'mediaButton'           => esc_html__( 'Use this image', 'wsh-hotel' ),
				'mediaGalleryTitle'     => esc_html__( 'Select images', 'wsh-hotel' ),
				'mediaGalleryButton'    => esc_html__( 'Use images', 'wsh-hotel' ),
				'removeImage'           => esc_html__( 'Remove image', 'wsh-hotel' ),
			)
		);
	}
}

/**
 * Adds CNWS Hotel body class for plugin pages.
 *
 * @param string $classes Existing body classes.
 * @return string
 */
function cnwshotel_admin_body_class( $classes ) {

	$page   = cnwshotel_get_admin_page_slug();
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	$is_cnwshotel_page = 0 === strpos( $page, 'cnwshotel-' );
	$is_room_cpt_list  = $screen && isset( $screen->post_type, $screen->base ) && 'cnwshotel_room' === $screen->post_type && 'edit' === $screen->base;

	if ( $is_cnwshotel_page || $is_room_cpt_list ) {
		$classes .= ' cnwshotel_admin_app';
	}

	return $classes;
}
