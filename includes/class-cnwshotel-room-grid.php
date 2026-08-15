<?php
/**
 * Frontend room result shortcode for WSH Hotel free version.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders room result grids for hotel booking shortcodes.
 */
class CNWSHOTEL_Room_Grid {

	/**
	 * Registers shortcode.
	 */
	public function __construct() {

		add_shortcode( 'cnwshotel_rooms', array( $this, 'render_rooms' ) );
		add_shortcode( 'cnwshotel_room_results', array( $this, 'render_room_results' ) );
	}

	/**
	 * Gets room card template path.
	 *
	 * @return string|false
	 */
	private function get_room_card_template() {

		$path = CNWSHOTEL_PATH . 'templates/components/room-card.php';

		return file_exists( $path ) ? $path : false;
	}

	/**
	 * Gets plugin room rows for room post IDs in one query.
	 *
	 * @param array<int,int> $post_ids Room post IDs.
	 * @return array<int,object> Map of post ID => room row.
	 */
	private function get_room_rows_by_post_ids( $post_ids ) {

		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$table             = $wpdb->prefix . 'cnwshotel_rooms';
		$placeholder_sql   = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$prepare_arguments = array_merge( array( $table ), $post_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room rows are loaded once for the current shortcode render.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The IN() placeholders are generated internally as fixed %d tokens.
				"SELECT * FROM %i WHERE post_id IN ($placeholder_sql)",
				$prepare_arguments
			)
		);
		$map = array();

		foreach ( $rows as $row ) {
			$post_id = absint( $row->post_id );

			if ( in_array( $post_id, $post_ids, true ) ) {
				$map[ $post_id ] = $row;
			}
		}

		return $map;
	}

	/**
	 * Renders room results.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_rooms( $atts = array() ) {

		return $this->render_room_list( $atts, false, 'cnwshotel_rooms' );
	}

	/**
	 * Renders filtered room search results.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_room_results( $atts = array() ) {

		return $this->render_room_list( $atts, true, 'cnwshotel_room_results' );
	}

	/**
	 * Renders room cards for overview or search-result context.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param bool   $filter_by_request Whether to filter by the current booking request.
	 * @param string $shortcode_tag Current shortcode tag.
	 * @return string
	 */
	private function render_room_list( $atts = array(), $filter_by_request = false, $shortcode_tag = 'cnwshotel_rooms' ) {

		$atts = shortcode_atts(
			array(
				'posts_per_page' => -1,
				'empty_text'     => __( 'No rooms found for the selected dates and guest count.', 'wsh-hotel' ),
			),
			$atts,
			$shortcode_tag
		);

		$request  = $filter_by_request && class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_search_request( 'get' ) : array();
		$checkin  = ! empty( $request['checkin'] ) ? (string) $request['checkin'] : '';
		$checkout = ! empty( $request['checkout'] ) ? (string) $request['checkout'] : '';
		$guests   = ! empty( $request['guests'] ) ? max( 1, absint( $request['guests'] ) ) : 0;

		$query = new WP_Query(
			array(
				'post_type'              => 'cnwshotel_room',
				'post_status'            => 'publish',
				'posts_per_page'         => intval( $atts['posts_per_page'] ),
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => true,
			)
		);

		ob_start();

		echo '<div class="cnwshotel_rooms_wrapper">';

		if ( ! $query->have_posts() ) {
			echo '<div class="cnwshotel_no_rooms">' . esc_html__( 'No rooms created yet.', 'wsh-hotel' ) . '</div>';
			echo '</div>';
			return ob_get_clean();
		}

		$template            = $this->get_room_card_template();
		$engine              = class_exists( 'CNWSHOTEL_Booking_Engine' ) ? new CNWSHOTEL_Booking_Engine() : null;
		$found               = 0;
		$post_ids            = wp_list_pluck( (array) $query->posts, 'ID' );
		$rooms_by_post_id    = $this->get_room_rows_by_post_ids( $post_ids );
		$availability_counts = null;

		if ( $filter_by_request && ! empty( $checkin ) && ! empty( $checkout ) && $engine ) {
			$availability_counts = $engine->get_availability_count_map_for_search( $post_ids, $checkin, $checkout, $guests );
		}

		echo '<div class="cnwshotel_room_list">';

		while ( $query->have_posts() ) {
			$query->the_post();

			$post_id = get_the_ID();
			$room    = isset( $rooms_by_post_id[ $post_id ] ) ? $rooms_by_post_id[ $post_id ] : null;

			if ( ! $room ) {
				continue;
			}

			if ( $filter_by_request && $guests > 0 && absint( $room->max_persons ) > 0 && $guests > absint( $room->max_persons ) ) {
				continue;
			}

			if ( is_array( $availability_counts ) && empty( $availability_counts[ $post_id ] ) ) {
				continue;
			}

			$availability_override = is_array( $availability_counts ) && isset( $availability_counts[ $post_id ] ) ? absint( $availability_counts[ $post_id ] ) : null;
			++$found;

			if ( $template ) {
				include $template;
			}
		}

		wp_reset_postdata();

		echo '</div>';

		if ( 0 === $found ) {
			echo '<div class="cnwshotel_no_rooms">' . esc_html( $atts['empty_text'] ) . '</div>';
		}

		echo '</div>';

		return ob_get_clean();
	}
}
