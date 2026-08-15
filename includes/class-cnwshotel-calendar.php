<?php
/**
 * Admin availability calendar for WSH Hotel Booking Management.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the admin availability calendar.
 */
class CNWSHOTEL_Calendar {

	/**
	 * Renders the calendar page wrapper.
	 */
	public function render_page() {

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WSH Calendar', 'wsh-hotel' ) . '</h1>';
		$this->render_calendar();
		echo '</div>';
	}

	/**
	 * Renders a 14-day room availability overview.
	 */
	public function render_calendar() {

		global $wpdb;

		$rooms_table    = $wpdb->prefix . 'cnwshotel_rooms';
		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin calendar reads plugin room table.
		$rooms = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id ASC',
				$rooms_table
			)
		);

		$days     = 14;
		$offset   = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_calendar_offset() : 0;
		$start_ts = time() + ( $offset * DAY_IN_SECONDS );
		$base_url = admin_url( 'admin.php?page=cnwshotel-calendar' );

		echo '<div class="cnwshotel_calendar_shell">';
		echo '<div class="cnwshotel_calendar_card">';
		echo '<div class="cnwshotel_calendar_topbar">';
		echo '<div>';
		echo '<h2 class="cnwshotel_calendar_title">' . esc_html__( 'Booking overview', 'wsh-hotel' ) . '</h2>';
		echo '<p class="cnwshotel_calendar_subtitle">';
		echo esc_html(
			sprintf(
				/* translators: %s: start date of calendar view */
				__( 'Showing next 14 days from %s', 'wsh-hotel' ),
				wp_date( 'd M Y', $start_ts )
			)
		);
		echo '</p>';
		echo '</div>';

		echo '<div class="cnwshotel_calendar_actions">';
		echo '<a class="button" href="' . esc_url( add_query_arg( 'offset', $offset - $days, $base_url ) ) . '">' . esc_html__( 'Previous', 'wsh-hotel' ) . '</a>';
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( 'offset', $offset + $days, $base_url ) ) . '">' . esc_html__( 'Next', 'wsh-hotel' ) . '</a>';
		echo '</div>';
		echo '</div>';

		if ( ! $rooms ) {
			echo '<p class="cnwshotel_admin_muted">' . esc_html__( 'No rooms found yet.', 'wsh-hotel' ) . '</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<div class="cnwshotel_calendar_table_wrap">';
		echo '<table class="cnwshotel_calendar_table">';
		echo '<thead><tr>';
		echo '<th class="cnwshotel_calendar_room_col">' . esc_html__( 'Room', 'wsh-hotel' ) . '</th>';

		for ( $i = 0; $i < $days; $i++ ) {
			$day_ts = $start_ts + ( $i * DAY_IN_SECONDS );
			echo '<th>' . esc_html( wp_date( 'd M', $day_ts ) ) . '</th>';
		}
		echo '</tr></thead>';

		echo '<tbody>';
		foreach ( $rooms as $room ) {
			echo '<tr>';
			$room_title = get_the_title( $room->post_id );
			if ( ! $room_title ) {
				$room_title = ! empty( $room->room_number ) ? $room->room_number : ( 'Room #' . intval( $room->id ) );
			}
			echo '<td class="cnwshotel_calendar_room_col">' . esc_html( $room_title ) . '</td>';

			for ( $i = 0; $i < $days; $i++ ) {
				$date_ts = $start_ts + ( $i * DAY_IN_SECONDS );
				$date    = wp_date( 'Y-m-d', $date_ts );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin calendar reads booking counts.
				$confirmed = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM %i
                         WHERE room_id = %d
                         AND booking_status = 'confirmed'
                         AND checkin <= %s
                         AND checkout > %s",
						$bookings_table,
						absint( $room->id ),
						$date,
						$date
					)
				);

				  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin calendar reads booking counts.
				$blocked = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM %i
                         WHERE room_id = %d
                         AND booking_status = 'blocked'
                         AND checkin <= %s
                         AND checkout > %s",
						$bookings_table,
						absint( $room->id ),
						$date,
						$date
					)
				);

					echo '<td>';
				if ( $confirmed ) {
					echo '<div class="cnwshotel_calendar_status cnwshotel_calendar_status_confirmed" title="' . esc_attr__( 'Booked', 'wsh-hotel' ) . '">' . esc_html__( 'Booked', 'wsh-hotel' ) . '</div>';
				} elseif ( $blocked ) {
					echo '<div class="cnwshotel_calendar_status cnwshotel_calendar_status_blocked" title="' . esc_attr__( 'Blocked', 'wsh-hotel' ) . '">' . esc_html__( 'Blocked', 'wsh-hotel' ) . '</div>';
				} else {
					echo '<div class="cnwshotel_calendar_status cnwshotel_calendar_status_available" title="' . esc_attr__( 'Available', 'wsh-hotel' ) . '">' . esc_html__( 'Available', 'wsh-hotel' ) . '</div>';
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		echo '<div class="cnwshotel_calendar_legend">';
		echo '<div class="cnwshotel_calendar_legend_item"><span class="cnwshotel_calendar_dot cnwshotel_calendar_dot_confirmed"></span> ' . esc_html__( 'Booked', 'wsh-hotel' ) . '</div>';
		echo '<div class="cnwshotel_calendar_legend_item"><span class="cnwshotel_calendar_dot cnwshotel_calendar_dot_blocked"></span> ' . esc_html__( 'Blocked', 'wsh-hotel' ) . '</div>';
		echo '<div class="cnwshotel_calendar_legend_item"><span class="cnwshotel_calendar_dot cnwshotel_calendar_dot_available"></span> ' . esc_html__( 'Available', 'wsh-hotel' ) . '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
}
