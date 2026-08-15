<?php
/**
 * Layout helpers for room rendering.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Uses WordPress core hook names intentionally.
/**
 * Provides basic layout helpers for free room templates.
 */
class CNWSHOTEL_Layout_Engine {

	/**
	 * Gets the selected room layout.
	 *
	 * @return string
	 */
	public static function get_layout() {
		$layout = get_option( 'cnwshotel_room_layout' );

		if ( ! $layout ) {
			$layout = 'default';
		}
		return $layout;
	}

	/**
	 * Gets published room posts for layout rendering.
	 *
	 * @return array<int,WP_Post>
	 */
	public static function get_rooms() {
		$args = array(
			'post_type'      => 'cnwshotel_room',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);
		return get_posts( $args );
	}

	/**
	 * Renders a simple room card.
	 *
	 * @param WP_Post $room Room post.
	 */
	public static function render_room_card( $room ) {
		echo '<div class="cnwshotel_room_card">';
			echo '<div class="cnwshotel_room_inner">';
				echo '<h3 class="cnwshotel_room_title">';
				echo esc_html( $room->post_title );
				echo '</h3>';

				echo '<div class="cnwshotel_room_content">';
				echo wp_kses_post( apply_filters( 'the_content', $room->post_content ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';
	}
}
