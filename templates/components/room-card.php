<?php
/**
 * Room card template for WSH Hotel free version.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partials intentionally use local variables passed from the parent template.
global $post;
if ( empty( $room ) ) {
	return;
}

$available     = isset( $availability_override ) && null !== $availability_override ? absint( $availability_override ) : ( class_exists( 'CNWSHOTEL_Booking_Engine' ) ? CNWSHOTEL_Booking_Engine::get_room_availability( $post->ID ) : 0 );
$max_persons   = absint( $room->max_persons ?? 0 );
$price         = isset( $room->price ) ? (float) $room->price : 0;
$currency_code = function_exists( 'get_woocommerce_currency' ) ? sanitize_text_field( (string) get_woocommerce_currency() ) : 'DKK';
$room_link     = get_permalink( $post->ID );
$room_title    = get_the_title( $post->ID );
if ( ! isset( $request ) || ! is_array( $request ) ) {
	$request = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_search_request( 'get' ) : array();
}

$checkin   = ! empty( $request['checkin'] ) ? (string) $request['checkin'] : '';
$checkout  = ! empty( $request['checkout'] ) ? (string) $request['checkout'] : '';
$guests    = ! empty( $request['guests'] ) ? max( 1, absint( $request['guests'] ) ) : 0;
$link_args = array();
if ( '' !== $checkin ) {
	$link_args['cnwshotel_checkin'] = $checkin;
}

if ( '' !== $checkout ) {
	$link_args['cnwshotel_checkout'] = $checkout;
}

if ( $guests > 0 ) {
	$link_args['cnwshotel_guests'] = $guests;
}

if ( ! empty( $link_args ) ) {
	if ( class_exists( 'CNWSHOTEL_Frontend_Request' ) ) {
		$link_args = array_merge( $link_args, CNWSHOTEL_Frontend_Request::get_search_nonce_query_arg() );
	}

	$room_link = add_query_arg( $link_args, $room_link );
}

$thumb_html = has_post_thumbnail( $post->ID )
	? get_the_post_thumbnail( $post->ID, 'large' )
: '<div class="cnwshotel_room_image_placeholder"></div>';
?>

<article class="cnwshotel_room_card cnwshotel_room_card_free">
	<a class="cnwshotel_room_link" href="<?php echo esc_url( $room_link ); ?>">
		<div class="cnwshotel_room_image">
			<?php echo wp_kses_post( $thumb_html ); ?>
			<div class="cnwshotel_room_overlay"></div>
			<div class="cnwshotel_room_overlay_content">
				<?php
				if ( $price > 0 ) :
					?>
					<div class="cnwshotel_room_topline">
						<span class="cnwshotel_room_top_price">
							<?php echo esc_html( number_format_i18n( $price, 2 ) ); ?> <?php echo esc_html( $currency_code ); ?>
						</span>
					</div>
				<?php endif; ?>

				<h3 class="cnwshotel_room_title"><?php echo esc_html( $room_title ); ?></h3>

				<div class="cnwshotel_room_hover_content">
					<?php
					if ( $max_persons > 0 ) :
						?>
						<div class="cnwshotel_room_meta">
							<span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: maximum guest count. */
										__( 'Fits up to %d guests', 'wsh-hotel' ),
										$max_persons
									)
								);
								?>
							</span>
						</div>
					<?php endif; ?>

					<div class="cnwshotel_room_availability">
						<?php
						if ( $available > 0 ) :
							?>
							<span class="cnwshotel_available">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: available room count. */
										_n( '%d room available', '%d rooms available', $available, 'wsh-hotel' ),
										$available
									)
								);
								?>
							</span>
							<?php
						else :
							?>
							<span class="cnwshotel_soldout"><?php echo esc_html__( 'Fully booked', 'wsh-hotel' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="cnwshotel_room_cta">
						<span class="cnwshotel_room_button"><?php echo esc_html__( 'View room', 'wsh-hotel' ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</a>
</article>
