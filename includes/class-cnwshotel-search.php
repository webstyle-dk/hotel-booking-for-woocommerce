<?php
/**

 * Frontend search shortcode for WSH Hotel free version.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit;
}

/**
 * Renders the public room search shortcode.
 */
class CNWSHOTEL_Search {

	/**

	 * Registers shortcode.
	 */
	public function __construct() {

		add_shortcode( 'cnwshotel_search', array( $this, 'render_search' ) );
	}

	/**
	 * Renders simple date + guests search form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_search( $atts = array() ) {

		$atts = shortcode_atts(
			array(

				'style'        => 'default',

				'results_page' => 'room',

			),
			$atts,
			'cnwshotel_search'
		);

		$results_page = sanitize_title( $atts['results_page'] );

		if ( '' === $results_page ) {

			$results_page = 'room';

		}

		$booking_page_id = absint( get_option( 'cnwshotel_booking_page_id', 0 ) );

		$results_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url( '/' . $results_page . '/' );

		$request     = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_search_request( 'get' ) : array();
		$unavailable = ! empty( $request['unavailable'] );
		$checkin     = ! empty( $request['checkin'] ) ? (string) $request['checkin'] : '';
		$checkout    = ! empty( $request['checkout'] ) ? (string) $request['checkout'] : '';
		$guests      = ! empty( $request['guests'] ) ? max( 1, absint( $request['guests'] ) ) : 1;

		$today = current_time( 'Y-m-d' );

		if ( empty( $checkin ) || $checkin < $today ) {

				$checkin = $today;

		}

		if ( empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {

			$checkout = wp_date( 'Y-m-d', strtotime( $checkin . ' +1 day' ) );

		}

		ob_start();

		?>

		<div class="cnwshotel_search cnwshotel_search_<?php echo esc_attr( sanitize_html_class( $atts['style'] ) ); ?>">

			<?php
			if ( $unavailable ) :
				?>

				<div class="woocommerce-error cnwshotel_search_notice">

					<?php echo esc_html__( 'The selected room is not available for those dates or guest count. Please search again.', 'wsh-hotel' ); ?>

				</div>

			<?php endif; ?>

			<form method="get" action="<?php echo esc_url( $results_url ); ?>" class="cnwshotel_search_form">

				<?php
				if ( class_exists( 'CNWSHOTEL_Frontend_Request' ) ) {
					CNWSHOTEL_Frontend_Request::search_nonce_field(); }
				?>

				<div class="cnwshotel_search_grid">

					<div class="cnwshotel_field">

						<label for="cnwshotel_checkin"><?php echo esc_html__( 'Check-in', 'wsh-hotel' ); ?></label>

						<input type="date" name="cnwshotel_checkin" id="cnwshotel_checkin" value="<?php echo esc_attr( $checkin ); ?>" min="<?php echo esc_attr( $today ); ?>" required>

					</div>

					<div class="cnwshotel_field">

						<label for="cnwshotel_checkout"><?php echo esc_html__( 'Check-out', 'wsh-hotel' ); ?></label>

						<input type="date" name="cnwshotel_checkout" id="cnwshotel_checkout" value="<?php echo esc_attr( $checkout ); ?>" required>

					</div>

					<div class="cnwshotel_field">

						<label for="cnwshotel_guests"><?php echo esc_html__( 'Guests', 'wsh-hotel' ); ?></label>

						<input type="number" name="cnwshotel_guests" id="cnwshotel_guests" value="<?php echo esc_attr( $guests ); ?>" min="1" required>

					</div>

					<div class="cnwshotel_field cnwshotel_field_submit">

						<button type="submit" class="cnwshotel_search_btn"><?php echo esc_html__( 'Search', 'wsh-hotel' ); ?></button>

					</div>

				</div>

			</form>

		</div>

		<?php

		return ob_get_clean();
	}
}
