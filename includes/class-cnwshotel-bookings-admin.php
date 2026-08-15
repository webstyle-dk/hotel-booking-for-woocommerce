<?php
/**
 * Booking administration actions and screens.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Handles booking admin actions, unit moves and booking list rendering.
 */
class CNWSHOTEL_Bookings_Admin {

	/**
	 * Registers booking admin action handlers.
	 */
	public function __construct() {

		add_action( 'admin_post_cnwshotel_update_booking_status', array( $this, 'update_booking_status' ) );

		add_action( 'admin_post_cnwshotel_move_booking_unit', array( $this, 'move_booking_unit' ) );
	}

	/**
	 * Gets the currently assigned unit for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return object|null
	 */
	private function get_booking_unit_data( $booking_id ) {

		global $wpdb;

		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';

		$units_table = $wpdb->prefix . 'cnwshotel_room_units';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		$posts_table = $wpdb->posts;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT

                    u.id,

                    u.room_type_id,

                    u.unit_number,

                    u.floor,

                    r.post_id,

                    p.post_title AS room_title

                 FROM %i bu

                 INNER JOIN %i u ON bu.unit_id = u.id

                 LEFT JOIN %i r ON r.id = u.room_type_id

                 LEFT JOIN %i p ON p.ID = r.post_id

                 WHERE bu.booking_id = %d

                 LIMIT 1',
				$booking_units_table,
				$units_table,
				$rooms_table,
				$posts_table,
				absint( $booking_id )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $result;
	}

	/**
	 * Gets room and post context for a room unit.
	 *
	 * @param int $unit_id Unit ID.
	 * @return object|null
	 */
	private function get_unit_context( $unit_id ) {

		global $wpdb;

		$units_table = $wpdb->prefix . 'cnwshotel_room_units';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		$posts_table = $wpdb->posts;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT

                    u.id,

                    u.room_type_id,

                    u.unit_number,

                    u.floor,

                    r.post_id,

                    p.post_title AS room_title

                 FROM %i u

                 INNER JOIN %i r ON r.id = u.room_type_id

                 LEFT JOIN %i p ON p.ID = r.post_id

                 WHERE u.id = %d

                 LIMIT 1',
				$units_table,
				$rooms_table,
				$posts_table,
				absint( $unit_id )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $result;
	}

	/**
	 * Gets available units that can be assigned to an existing booking.
	 *
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param int    $booking_id Booking ID.
	 * @param int    $guests_required Required guest capacity.
	 * @return array<int,object>
	 */
	private function get_available_units_for_booking( $checkin, $checkout, $booking_id, $guests_required ) {

		global $wpdb;

		$current_unit = $this->get_booking_unit_data( $booking_id );

		$current_unit_id = $current_unit && ! empty( $current_unit->id ) ? absint( $current_unit->id ) : 0;

		$guests_required = max( 1, absint( $guests_required ) );

		$units_table = $wpdb->prefix . 'cnwshotel_room_units';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		$beds_table = $wpdb->prefix . 'cnwshotel_room_unit_beds';

		$bed_types_table = $wpdb->prefix . 'cnwshotel_bed_types';

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';

		$holds_table = $wpdb->prefix . 'cnwshotel_cart_holds';

		$posts_table = $wpdb->posts;

		$now = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT

                    u.id,

                    u.room_type_id,

                    u.unit_number,

                    u.floor,

                    r.post_id,

                    p.post_title AS room_title,

                    CASE

                        WHEN COALESCE(SUM(bt.capacity), 0) > 0 THEN COALESCE(SUM(bt.capacity), 0)

                        ELSE r.max_persons

                    END AS unit_capacity

                 FROM %i u

                 INNER JOIN %i r ON r.id = u.room_type_id

                 LEFT JOIN %i p ON p.ID = r.post_id

                 LEFT JOIN %i rub ON u.id = rub.room_unit_id

                 LEFT JOIN %i bt ON rub.bed_type_id = bt.id

                 WHERE u.status = 'active'

                   AND NOT EXISTS (

                        SELECT 1

                        FROM %i bu

                        INNER JOIN %i b ON bu.booking_id = b.id

                        WHERE bu.unit_id = u.id

                          AND b.id <> %d

                          AND b.checkin < %s

                          AND b.checkout > %s

                          AND b.booking_status IN ('confirmed', 'blocked')

                   )

                   AND NOT EXISTS (

                        SELECT 1

                        FROM %i h

                        WHERE h.unit_id = u.id

                          AND h.checkin < %s

                          AND h.checkout > %s

                          AND h.hold_status = 'active'

                          AND h.expires_at > %s

                   )

                 GROUP BY u.id, u.room_type_id, u.unit_number, u.floor, r.post_id, r.max_persons, p.post_title

                 HAVING unit_capacity >= %d OR u.id = %d

                 ORDER BY p.post_title ASC, u.floor ASC, u.unit_number ASC",
				$units_table,
				$rooms_table,
				$posts_table,
				$beds_table,
				$bed_types_table,
				$booking_units_table,
				$bookings_table,
				absint( $booking_id ),
				$checkout,
				$checkin,
				$holds_table,
				$checkout,
				$checkin,
				$now,
				$guests_required,
				$current_unit_id
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $results;
	}

	/**
	 * Redirects back to the bookings admin screen.
	 */
	private function redirect_to_bookings() {
		wp_safe_redirect( admin_url( 'admin.php?page=cnwshotel-bookings' ) );
		exit;
	}

	/**
	 * Handles the admin-post request for updating booking stay status.
	 */
	public function update_booking_status() {

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) || 'POST' !== CNWSHOTEL_Frontend_Request::request_method() ) {
			wp_die( esc_html__( 'Invalid booking request.', 'wsh-hotel' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update bookings.', 'wsh-hotel' ) );
		}

		check_admin_referer( CNWSHOTEL_Frontend_Request::STATUS_NONCE_ACTION, CNWSHOTEL_Frontend_Request::STATUS_NONCE_FIELD );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified immediately above; fields are sanitized by sanitize_booking_status_submission().
		$post_data  = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$submission = CNWSHOTEL_Frontend_Request::sanitize_booking_status_submission( $post_data );

		if ( false === $submission ) {
			wp_die( esc_html__( 'Invalid or unauthorized booking request.', 'wsh-hotel' ) );
		}

		global $wpdb;

		$booking_id = absint( $submission['booking_id'] );
		$status     = (string) $submission['status'];

		if ( ! $booking_id || ! in_array( $status, array( 'pending', 'checked_in', 'checked_out', 'no_show' ), true ) ) {

			$this->redirect_to_bookings();

		}

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT b.id, b.room_id, r.post_id

                 FROM %i b

                 LEFT JOIN %i r ON b.room_id = r.id

                 WHERE b.id = %d

                 LIMIT 1',
				$bookings_table,
				$rooms_table,
				absint( $booking_id )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $booking ) {

			$this->redirect_to_bookings();

		}

		$update_data = array(

			'checkin_status' => $status,

		);

		if ( 'checked_in' === $status ) {

			$update_data['arrival_time'] = current_time( 'mysql' );

		}

		if ( 'checked_out' === $status ) {

			$update_data['expected_checkout_time'] = current_time( 'mysql' );

		}

		if ( 'no_show' === $status ) {

			$update_data['early_checkout'] = 1;

		}

		$update_formats = array( '%s' );

		if ( isset( $update_data['arrival_time'] ) ) {

			$update_formats[] = '%s';

		}

		if ( isset( $update_data['expected_checkout_time'] ) ) {

			$update_formats[] = '%s';

		}

		if ( isset( $update_data['early_checkout'] ) ) {

			$update_formats[] = '%d';

		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional admin update action.
		$wpdb->update(
			$bookings_table,
			$update_data,
			array( 'id' => $booking_id ),
			$update_formats,
			array( '%d' )
		);

		if ( ! empty( $booking->post_id ) ) {

			wp_cache_delete( 'cnwshotel_availability_' . $booking->post_id, 'cnwshotel' );

		}

		$this->redirect_to_bookings();
	}

	/**
	 * Handles the admin-post request for moving a booking to another unit.
	 */
	public function move_booking_unit() {

		if ( ! class_exists( 'CNWSHOTEL_Frontend_Request' ) || 'POST' !== CNWSHOTEL_Frontend_Request::request_method() ) {
			wp_die( esc_html__( 'Invalid booking request.', 'wsh-hotel' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update bookings.', 'wsh-hotel' ) );
		}

		check_admin_referer( CNWSHOTEL_Frontend_Request::MOVE_NONCE_ACTION, CNWSHOTEL_Frontend_Request::MOVE_NONCE_FIELD );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce and capability are verified immediately above; fields are sanitized by sanitize_move_booking_unit_submission().
		$post_data  = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$submission = CNWSHOTEL_Frontend_Request::sanitize_move_booking_unit_submission( $post_data );

		if ( false === $submission ) {
			wp_die( esc_html__( 'Invalid or unauthorized booking request.', 'wsh-hotel' ) );
		}

		global $wpdb;

		$booking_id  = absint( $submission['booking_id'] );
		$new_unit_id = absint( $submission['unit_id'] );

		if ( ! $booking_id || ! $new_unit_id ) {

			$this->redirect_to_bookings();

		}

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT b.*, r.post_id, r.allocation_mode

                 FROM %i b

                 LEFT JOIN %i r ON b.room_id = r.id

                 WHERE b.id = %d

                 LIMIT 1',
				$bookings_table,
				$rooms_table,
				absint( $booking_id )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $booking ) {

			$this->redirect_to_bookings();

		}

		$guests_required = max( 1, absint( isset( $booking->guests_real ) ? $booking->guests_real : $booking->guests ) );

		$available_units = $this->get_available_units_for_booking( $booking->checkin, $booking->checkout, $booking_id, $guests_required );

		$allowed_ids = array_map(
			static function ( $unit ) {

				return intval( $unit->id );
			},
			$available_units
		);

		if ( ! in_array( $new_unit_id, $allowed_ids, true ) ) {

			$this->redirect_to_bookings();

		}

		$new_unit = $this->get_unit_context( $new_unit_id );

		if ( ! $new_unit || empty( $new_unit->room_type_id ) ) {

			$this->redirect_to_bookings();

		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_link = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id

                 FROM %i

                 WHERE booking_id = %d

                 LIMIT 1',
				$booking_units_table,
				absint( $booking_id )
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $existing_link ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional admin update action.
			$wpdb->update(
				$booking_units_table,
				array( 'unit_id' => $new_unit_id ),
				array( 'id' => intval( $existing_link ) ),
				array( '%d' ),
				array( '%d' )
			);

		} else {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional admin insert action.
			$wpdb->insert(
				$booking_units_table,
				array(

					'booking_id' => $booking_id,

					'unit_id'    => $new_unit_id,

				),
				array( '%d', '%d' )
			);

		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional admin update action.
		$wpdb->update(
			$bookings_table,
			array( 'room_id' => absint( $new_unit->room_type_id ) ),
			array( 'id' => $booking_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( ! empty( $booking->post_id ) ) {

			wp_cache_delete( 'cnwshotel_availability_' . absint( $booking->post_id ), 'cnwshotel' );

		}

		if ( ! empty( $new_unit->post_id ) ) {

			wp_cache_delete( 'cnwshotel_availability_' . absint( $new_unit->post_id ), 'cnwshotel' );

		}

		$this->redirect_to_bookings();
	}

	/**
	 * Renders the bookings admin page.
	 */
	public function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_die( esc_html__( 'Access denied.', 'wsh-hotel' ) );

		}

		global $wpdb;

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.*, r.post_id, r.allocation_mode

                 FROM %i b

                 LEFT JOIN %i r ON b.room_id = r.id

                 ORDER BY b.checkin ASC, b.id DESC

                 LIMIT 500',
				$bookings_table,
				$rooms_table
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		echo '<div class="wrap">';

		echo '<h1>' . esc_html__( 'Hotel Bookings', 'wsh-hotel' ) . '</h1>';

		if ( empty( $bookings ) ) {

			echo '<p>' . esc_html__( 'No bookings found yet.', 'wsh-hotel' ) . '</p>';

			echo '</div>';

			return;

		}

		echo '<table class="widefat striped">';

		echo '<thead>';

		echo '<tr>';

		echo '<th>' . esc_html__( 'ID', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Room', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Unit', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Floor', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Check-in', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Check-out', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Guests', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Paid capacity', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Booking status', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Stay status', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Order ID', 'wsh-hotel' ) . '</th>';

		echo '<th>' . esc_html__( 'Actions', 'wsh-hotel' ) . '</th>';

		echo '</tr>';

		echo '</thead>';

		echo '<tbody>';

		foreach ( $bookings as $booking ) {

			$room_title = '-';

			if ( ! empty( $booking->post_id ) ) {

				$title = get_the_title( $booking->post_id );

				if ( $title ) {

					$room_title = $title;

				}
			}

			$unit = $this->get_booking_unit_data( $booking->id );

			$unit_label = $unit && ! empty( $unit->unit_number ) ? $unit->unit_number : '-';

			$floor_label = $unit && isset( $unit->floor ) && '' !== $unit->floor ? $unit->floor : '-';

			$stay_status = ! empty( $booking->checkin_status ) ? $booking->checkin_status : 'pending';

			echo '<tr>';

			echo '<td>' . esc_html( (string) intval( $booking->id ) ) . '</td>';

			echo '<td>' . esc_html( $room_title ) . '</td>';

			echo '<td>' . esc_html( $unit_label ) . '</td>';

			echo '<td>' . esc_html( (string) $floor_label ) . '</td>';

			echo '<td>' . esc_html( $booking->checkin ) . '</td>';

			echo '<td>' . esc_html( $booking->checkout ) . '</td>';

			echo '<td>' . esc_html( (string) intval( isset( $booking->guests_real ) ? $booking->guests_real : $booking->guests ) ) . '</td>';

			echo '<td>' . esc_html( (string) intval( isset( $booking->guests_paid ) ? $booking->guests_paid : $booking->guests ) ) . '</td>';

			echo '<td>' . esc_html( $booking->booking_status ) . '</td>';

			echo '<td>' . esc_html( $stay_status ) . '</td>';

			echo '<td>' . ( ! empty( $booking->order_id ) ? esc_html( (string) intval( $booking->order_id ) ) : '-' ) . '</td>';

			echo '<td>';

				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

				wp_nonce_field( CNWSHOTEL_Frontend_Request::STATUS_NONCE_ACTION, CNWSHOTEL_Frontend_Request::STATUS_NONCE_FIELD );

				echo '<input type="hidden" name="action" value="cnwshotel_update_booking_status">';

				echo '<input type="hidden" name="booking_id" value="' . esc_attr( (string) intval( $booking->id ) ) . '">';

				echo '<select name="checkin_status">';

				echo '<option value="pending" ' . selected( $stay_status, 'pending', false ) . '>' . esc_html__( 'Pending', 'wsh-hotel' ) . '</option>';

				echo '<option value="checked_in" ' . selected( $stay_status, 'checked_in', false ) . '>' . esc_html__( 'Checked in', 'wsh-hotel' ) . '</option>';

				echo '<option value="checked_out" ' . selected( $stay_status, 'checked_out', false ) . '>' . esc_html__( 'Checked out', 'wsh-hotel' ) . '</option>';

				echo '<option value="no_show" ' . selected( $stay_status, 'no_show', false ) . '>' . esc_html__( 'No-show', 'wsh-hotel' ) . '</option>';

				echo '</select> ';

				echo '<button type="submit" class="button button-small">' . esc_html__( 'Save', 'wsh-hotel' ) . '</button>';

				echo '</form>';

				$guests_required = max( 1, absint( isset( $booking->guests_real ) ? $booking->guests_real : $booking->guests ) );

				$available_units = $this->get_available_units_for_booking( $booking->checkin, $booking->checkout, $booking->id, $guests_required );

			if ( ! empty( $available_units ) ) {

				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

				wp_nonce_field( CNWSHOTEL_Frontend_Request::MOVE_NONCE_ACTION, CNWSHOTEL_Frontend_Request::MOVE_NONCE_FIELD );

				echo '<input type="hidden" name="action" value="cnwshotel_move_booking_unit">';

				echo '<input type="hidden" name="booking_id" value="' . esc_attr( (string) intval( $booking->id ) ) . '">';

				echo '<select name="unit_id">';

				foreach ( $available_units as $move_unit ) {

					$selected = ( $unit && intval( $move_unit->id ) === intval( $unit->id ) );

					$room_name = ! empty( $move_unit->room_title ) ? $move_unit->room_title : __( 'Room type', 'wsh-hotel' );

					$label = sprintf(
						/* translators: 1: room type title, 2: room unit number */

						__( '%1$s - Room %2$s', 'wsh-hotel' ),
						$room_name,
						$move_unit->unit_number
					);

					if ( '' !== $move_unit->floor && null !== $move_unit->floor ) {

						$label .= ' / ' . sprintf(
							/* translators: %s: floor number */

							__( 'Floor %s', 'wsh-hotel' ),
							$move_unit->floor
						);

					}

					$label .= ' / ' . sprintf(
						/* translators: %d: guest capacity */

						__( 'Capacity %d', 'wsh-hotel' ),
						absint( $move_unit->unit_capacity )
					);

					echo '<option value="' . esc_attr( (string) intval( $move_unit->id ) ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $label ) . '</option>';

				}

				echo '</select> ';

				echo '<button type="submit" class="button button-small">' . esc_html__( 'Move room', 'wsh-hotel' ) . '</button>';

				echo '</form>';

			}

			echo '</td>';

			echo '</tr>';

		}

		echo '</tbody>';

		echo '</table>';

		echo '</div>';
	}
}
