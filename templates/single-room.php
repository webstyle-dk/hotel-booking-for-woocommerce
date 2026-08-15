<?php
/**
 * Single room template for WSH Hotel free version.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,-- Template files use local variables and custom plugin tables.
get_header();
global $post, $wpdb;
$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Template reads room table.
$room = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM %i WHERE post_id = %d LIMIT 1',
		$rooms_table,
		absint( $post->ID )
	)
);
if ( ! $room ) {
	echo '<main id="primary" class="site-main cnwshotel_room_template_main"><div class="cnwshotel_theme_container"><div class="cnwshotel_single_room">' . esc_html__( 'Room data not found.', 'wsh-hotel' ) . '</div></div></main>';
	get_footer();
	return;
}

$layout       = class_exists( 'CNWSHOTEL_Layout_Engine' ) ? CNWSHOTEL_Layout_Engine::get_layout() : 'default';
$today        = current_time( 'Y-m-d' );
$gallery      = get_post_meta( $post->ID, 'cnwshotel_room_gallery', true );
$room_size    = absint( get_post_meta( $post->ID, 'cnwshotel_room_size', true ) );
$availability = class_exists( 'CNWSHOTEL_Booking_Engine' ) ? CNWSHOTEL_Booking_Engine::get_room_availability( $post->ID ) : 0;
if ( is_string( $gallery ) ) {
	$gallery = json_decode( $gallery, true );
}

if ( ! is_array( $gallery ) ) {
	$gallery = array();
}

$main_image = '';
if ( has_post_thumbnail( $post->ID ) ) {
	$main_image = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
}

if ( ! $main_image && ! empty( $gallery ) ) {
	$main_image = wp_get_attachment_url( absint( $gallery[0] ) );
}

$intro = get_post_meta( $post->ID, 'cnwshotel_room_intro', true );
if ( '' === $intro ) {
	$intro = wp_trim_words( wp_strip_all_tags( get_the_content() ), 40, '...' );
}

$request  = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_search_request( 'get' ) : array();
$checkin  = ! empty( $request['checkin'] ) ? (string) $request['checkin'] : '';
$checkout = ! empty( $request['checkout'] ) ? (string) $request['checkout'] : '';
$guests   = ! empty( $request['guests'] ) ? max( 1, absint( $request['guests'] ) ) : 1;
if ( empty( $checkin ) || $checkin < $today ) {
	$checkin = $today;
}

if ( empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {
	$checkout = wp_date( 'Y-m-d', strtotime( $checkin . ' +1 day' ) );
}

$woo_product_id = absint( $room->woo_product_id ?? 0 );
$rooms_overview = absint( get_option( 'cnwshotel_booking_page_id', 0 ) );
$rooms_url      = $rooms_overview ? get_permalink( $rooms_overview ) : home_url( '/room/' );
$currency_code  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'DKK';
$night_count    = max( 1, (int) round( ( strtotime( $checkout ) - strtotime( $checkin ) ) / DAY_IN_SECONDS ) );
$base_price     = (float) $room->price;
$initial_total  = $base_price * $night_count;
if ( 'per_person' === $room->pricing_model ) {
	$initial_total *= max( 1, absint( $guests ) );
}
?>

<main id="primary" class="site-main cnwshotel_room_template_main">
	<div class="cnwshotel_theme_container">
		<div class="cnwshotel_room_page cnwshotel_layout_<?php echo esc_attr( $layout ); ?>">
			<div class="cnwshotel_single_room">
				<h1 class="cnwshotel_room_title"><?php echo esc_html( get_the_title( $post->ID ) ); ?></h1>

				<div class="cnwshotel_room_topbar cnwshotel_room_topbar_back_only">
					<a href="<?php echo esc_url( $rooms_url ); ?>" class="cnwshotel_room_side_tab">
						<?php echo esc_html__( 'Back to room overview', 'wsh-hotel' ); ?>
					</a>
				</div>

				<div class="cnwshotel_room_grid">
					<div class="cnwshotel_room_left">
						<section id="cnwshotel_room_overview" class="cnwshotel_room_section">
							<div class="cnwshotel_room_gallery">
								<div class="cnwshotel_room_main">
									<?php
									if ( $main_image ) :
										?>
										<img id="cnwshotel_main_image" src="<?php echo esc_url( $main_image ); ?>" alt="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>">
									<?php endif; ?>
								</div>

								<?php
								if ( ! empty( $gallery ) ) :
									?>
									<div class="cnwshotel_room_thumbs">
										<?php
										foreach ( $gallery as $img ) :
											?>
											<?php $thumb = wp_get_attachment_image_url( absint( $img ), 'thumbnail' ); ?>
											<?php $large = wp_get_attachment_image_url( absint( $img ), 'large' ); ?>
											<?php
											if ( $thumb && $large ) :
												?>
												<img class="cnwshotel_thumb" src="<?php echo esc_url( $thumb ); ?>" data-full="<?php echo esc_url( $large ); ?>" alt="">
											<?php endif; ?>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>

							<?php
							if ( $intro ) :
								?>
								<div class="cnwshotel_room_intro"><p><?php echo esc_html( $intro ); ?></p></div>
							<?php endif; ?>

							<?php
							$room_content_plain = trim( wp_strip_all_tags( (string) $post->post_content ) );
							$intro_plain        = trim( wp_strip_all_tags( (string) $intro ) );
							?>
							<?php
							if ( '' !== $room_content_plain && $room_content_plain !== $intro_plain ) :
								?>
								<div class="cnwshotel_room_description">
									<?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); ?>
								</div>
							<?php endif; ?>
						</section>
					</div>

					<div class="cnwshotel_room_right">
						<section id="cnwshotel_room_booking" class="cnwshotel_room_bookbox">
							<h2><?php echo esc_html__( 'Book this room', 'wsh-hotel' ); ?></h2>

							<div class="cnwshotel_price_data" data-price="<?php echo esc_attr( $base_price ); ?>" data-pricing-model="<?php echo esc_attr( $room->pricing_model ); ?>" data-currency-code="<?php echo esc_attr( $currency_code ); ?>">
								<div class="cnwshotel_price_main">
									<?php echo esc_html( number_format_i18n( $base_price, 2 ) ); ?> <?php echo esc_html( $currency_code ); ?>
								</div>
								<div class="cnwshotel_price_sub">
									<?php echo 'per_person' === $room->pricing_model ? esc_html__( 'per guest per night', 'wsh-hotel' ) : esc_html__( 'per room per night', 'wsh-hotel' ); ?>
								</div>
							</div>

							<div class="cnwshotel_booking_summary_mini">
								<div class="cnwshotel_summary_row">
									<span><?php echo esc_html__( 'Stay', 'wsh-hotel' ); ?></span>
									<strong class="cnwshotel_stay">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of nights. */
												_n( '%d night', '%d nights', $night_count, 'wsh-hotel' ),
												$night_count
											)
										);
										?>
									</strong>
								</div>
								<div class="cnwshotel_summary_row cnwshotel_summary_total">
									<span><?php echo esc_html__( 'Estimated price', 'wsh-hotel' ); ?></span>
									<strong class="cnwshotel_total">
										<?php echo esc_html( number_format_i18n( $initial_total, 2 ) . ' ' . $currency_code ); ?>
									</strong>
								</div>
							</div>

							<?php
							if ( $woo_product_id && function_exists( 'wc_get_cart_url' ) ) :
								?>
								<form method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cnwshotel_booking_form">
									<?php
									if ( class_exists( 'CNWSHOTEL_Frontend_Request' ) ) {
										CNWSHOTEL_Frontend_Request::booking_nonce_field(); }
									?>
									<input type="hidden" name="cnwshotel_booking_form" value="1">
									<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $woo_product_id ); ?>">
									<input type="hidden" name="quantity" value="1">

									<p>
										<label for="cnwshotel_single_checkin"><?php echo esc_html__( 'Check-in', 'wsh-hotel' ); ?></label>
										<input type="date" id="cnwshotel_single_checkin" name="cnwshotel_checkin" value="<?php echo esc_attr( $checkin ); ?>" min="<?php echo esc_attr( $today ); ?>" required>
									</p>

									<p>
										<label for="cnwshotel_single_checkout"><?php echo esc_html__( 'Check-out', 'wsh-hotel' ); ?></label>
										<input type="date" id="cnwshotel_single_checkout" name="cnwshotel_checkout" value="<?php echo esc_attr( $checkout ); ?>" required>
									</p>

									<p>
										<label for="cnwshotel_single_guests"><?php echo esc_html__( 'Guests', 'wsh-hotel' ); ?></label>
										<input type="number" id="cnwshotel_single_guests" name="cnwshotel_guests" value="<?php echo esc_attr( $guests ); ?>" min="1" max="<?php echo esc_attr( max( 1, absint( $room->max_persons ) ) ); ?>" required>
									</p>

									<button type="submit" class="cnwshotel_book_btn"><?php echo esc_html__( 'Book now', 'wsh-hotel' ); ?></button>
								</form>
								<?php
							else :
								?>
								<p><?php echo esc_html__( 'No WooCommerce product is connected to this room yet.', 'wsh-hotel' ); ?></p>
							<?php endif; ?>

							<div class="cnwshotel_room_facts">
								<div class="cnwshotel_fact">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: guest capacity. */
											__( 'Up to %d guests', 'wsh-hotel' ),
											absint( $room->max_persons )
										)
									);
									?>
								</div>

								<?php
								if ( $room_size > 0 ) :
									?>
									<div class="cnwshotel_fact">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: room size in square meters. */
												__( '%d sq m', 'wsh-hotel' ),
												$room_size
											)
										);
										?>
									</div>
								<?php endif; ?>

								<div class="cnwshotel_fact">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: available room count. */
											_n( '%d available room', '%d available rooms', $availability, 'wsh-hotel' ),
											$availability
										)
									);
									?>
								</div>
							</div>

						</section>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
