<?php
/**
 * Free simple single-unit booking engine.
 *
 * Free booking model:
 * - Date + guests search.
 * - One available room/unit per booking.
 * - No children, beds, shared/private hostel logic or multi-room allocation.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculates availability and creates simple single-unit bookings.
 */
class CNWSHOTEL_Booking_Engine {

	/**
	 * Per-request unit capacity cache.
	 *
	 * @var array<int,array<int,object>>
	 */
	private $unit_capacity_cache = array();

	/**
	 * Per-request minimum stay cache.
	 *
	 * @var array<string,int>
	 */
	private $minimum_stay_cache = array();

	/**
	 * Gets active units and capacity for a room type.
	 *
	 * If no bed rows exist, each unit falls back to the room type capacity.
	 *
	 * @param int  $room_type_id Room table ID.
	 * @param bool $for_update Whether to lock rows.
	 * @return array<int,object>
	 */
	private function get_unit_capacities( $room_type_id, $for_update = false ) {

		global $wpdb;

		$rooms_table     = $wpdb->prefix . 'cnwshotel_rooms';
		$units_table     = $wpdb->prefix . 'cnwshotel_room_units';
		$beds_table      = $wpdb->prefix . 'cnwshotel_room_unit_beds';
		$bed_types_table = $wpdb->prefix . 'cnwshotel_bed_types';
		$room_type_id    = absint( $room_type_id );

		if ( ! $for_update && isset( $this->unit_capacity_cache[ $room_type_id ] ) ) {
			return $this->unit_capacity_cache[ $room_type_id ];
		}

		if ( $for_update ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking allocation must lock the selected unit rows inside the active transaction.
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
                        u.id,
                        u.unit_number,
                        u.floor,
                        u.status,
                        CASE
                            WHEN COALESCE(SUM(bt.capacity), 0) > 0 THEN COALESCE(SUM(bt.capacity), 0)
                            ELSE r.max_persons
                        END AS unit_capacity
                     FROM %i u
                     INNER JOIN %i r ON r.id = u.room_type_id
                     LEFT JOIN %i rub ON u.id = rub.room_unit_id
                     LEFT JOIN %i bt ON rub.bed_type_id = bt.id
                     WHERE u.room_type_id = %d
                       AND u.status = 'active'
                     GROUP BY u.id, u.unit_number, u.floor, u.status, r.max_persons
                     ORDER BY unit_capacity ASC, unit_number ASC
                     FOR UPDATE",
					$units_table,
					$rooms_table,
					$beds_table,
					$bed_types_table,
					absint( $room_type_id )
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Unit capacity rows are retained in the per-request cache below.
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    u.id,
                    u.unit_number,
                    u.floor,
                    u.status,
                    CASE
                        WHEN COALESCE(SUM(bt.capacity), 0) > 0 THEN COALESCE(SUM(bt.capacity), 0)
                        ELSE r.max_persons
                    END AS unit_capacity
                 FROM %i u
                 INNER JOIN %i r ON r.id = u.room_type_id
                 LEFT JOIN %i rub ON u.id = rub.room_unit_id
                 LEFT JOIN %i bt ON rub.bed_type_id = bt.id
                 WHERE u.room_type_id = %d
                   AND u.status = 'active'
                 GROUP BY u.id, u.unit_number, u.floor, u.status, r.max_persons
                 ORDER BY unit_capacity ASC, unit_number ASC",
				$units_table,
				$rooms_table,
				$beds_table,
				$bed_types_table,
				absint( $room_type_id )
			)
		);

		$this->unit_capacity_cache[ $room_type_id ] = $results;

		return $results;
	}

	/**
	 * Gets available units with capacity for a date range.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param bool   $for_update Whether to lock rows.
	 * @return array<int,array{id:int,capacity:int}>
	 */
	public function get_available_units_with_capacity( $room_id, $checkin, $checkout, $for_update = false ) {

		global $wpdb;

		$room_id             = absint( $room_id );
		$bookings_table      = $wpdb->prefix . 'cnwshotel_bookings';
		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';
		$units               = $this->get_unit_capacities( $room_id, $for_update );

		if ( ! $room_id || empty( $units ) ) {
			return array();
		}

		if ( $for_update ) {
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The booking transaction must read and lock current conflict rows from the plugin's custom tables; cached data would be unsafe here.
			$conflict_unit_ids = array_map(
				'absint',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT bu.unit_id
                         FROM %i bu
                         INNER JOIN %i b ON bu.booking_id = b.id
                         WHERE b.room_id = %d
                           AND b.checkin < %s
                           AND b.checkout > %s
                           AND b.booking_status IN ('confirmed', 'blocked')
                         FOR UPDATE",
						$booking_units_table,
						$bookings_table,
						$room_id,
						$checkout,
						$checkin
					)
				)
			);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability must be calculated from live booking rows in the plugin's custom tables; the result is used only in this request.
			$conflict_unit_ids = array_map(
				'absint',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT bu.unit_id
                         FROM %i bu
                         INNER JOIN %i b ON bu.booking_id = b.id
                         WHERE b.room_id = %d
                           AND b.checkin < %s
                           AND b.checkout > %s
                           AND b.booking_status IN ('confirmed', 'blocked')",
						$booking_units_table,
						$bookings_table,
						$room_id,
						$checkout,
						$checkin
					)
				)
			);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$conflicts       = array_flip( $conflict_unit_ids );
		$available_units = array();

		foreach ( $units as $unit ) {
			$unit_id = absint( $unit->id );

			if ( isset( $conflicts[ $unit_id ] ) ) {
				continue;
			}

			$available_units[] = array(
				'id'       => $unit_id,
				'capacity' => max( 1, absint( $unit->unit_capacity ) ),
			);
		}

		usort(
			$available_units,
			static function ( $a, $b ) {

				if ( $a['capacity'] === $b['capacity'] ) {
					return $a['id'] <=> $b['id'];
				}

				return $a['capacity'] <=> $b['capacity'];
			}
		);

		return $available_units;
	}

	/**
	 * Selects one unit that can fit the requested guests.
	 *
	 * @param array<int,array{id:int,capacity:int}> $available_units Available units.
	 * @param int                                   $guests_real Requested guests.
	 * @return array<int,int>
	 */
	public function select_units_for_guests( $available_units, $guests_real ) {

		$guests_real = max( 1, absint( $guests_real ) );

		foreach ( (array) $available_units as $unit ) {
			if ( absint( $unit['capacity'] ) >= $guests_real ) {
				return array( absint( $unit['id'] ) );
			}
		}

		return array();
	}

	/**
	 * Gets total active capacity.
	 *
	 * @param int  $room_id Room table ID.
	 * @param bool $for_update Whether to lock rows.
	 * @return int
	 */
	public function get_total_active_capacity( $room_id, $for_update = false ) {

		$units = $this->get_unit_capacities( $room_id, $for_update );

		if ( empty( $units ) ) {
				return 0;
		}

		$total_capacity = 0;

		foreach ( $units as $unit ) {
			$total_capacity += max( 1, absint( $unit->unit_capacity ) );
		}

		return $total_capacity;
	}

	/**
	 * Public compatibility wrapper.
	 *
	 * @param int $room_id Room table ID.
	 * @return int
	 */
	public function get_total_active_capacity_public( $room_id ) {

		return $this->get_total_active_capacity( $room_id, false );
	}

	/**
	 * Compatibility wrapper. Free does not have private-room mode.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @return int
	 */
	public function get_private_room_capacity_public( $room_id, $checkin, $checkout ) {

		$available_units = $this->get_available_units_with_capacity( $room_id, $checkin, $checkout, false );

		if ( empty( $available_units ) ) {
			return 0;
		}

		$max = 0;

		foreach ( $available_units as $unit ) {
			$max = max( $max, absint( $unit['capacity'] ) );
		}

		return $max;
	}

	/**
	 * Gets reserved guest capacity. Kept for compatibility with cart holds.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param bool   $for_update Whether to lock rows.
	 * @return int
	 */
	public function get_total_reserved_capacity( $room_id, $checkin, $checkout, $for_update = false ) {

		global $wpdb;

		$bookings_table = $wpdb->prefix . 'cnwshotel_bookings';

		if ( $for_update ) {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking allocation locks matching rows inside the active transaction.
			$reserved = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(guests_paid), 0)
                     FROM %i
                     WHERE room_id = %d
                       AND checkin < %s
                       AND checkout > %s
                       AND booking_status IN ('confirmed', 'blocked')
                     FOR UPDATE",
					$bookings_table,
					absint( $room_id ),
					$checkout,
					$checkin
				)
			);
		} else {
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reserved capacity is calculated for the current availability check.
				$reserved = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(guests_paid), 0)
                     FROM %i
                     WHERE room_id = %d
                       AND checkin < %s
                       AND checkout > %s
                       AND booking_status IN ('confirmed', 'blocked')",
						$bookings_table,
						absint( $room_id ),
						$checkout,
						$checkin
					)
				);
		}

		return absint( $reserved );
	}

	/**
	 * Creates a simple single-unit booking.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param int    $guests_real Guest count.
	 * @param bool   $is_private Deprecated compatibility argument.
	 * @return int|false Booking ID or false.
	 */
	public function create_booking( $room_id, $checkin, $checkout, $guests_real, $is_private = false ) {
		unset( $is_private );

		global $wpdb;

		$rooms_table         = $wpdb->prefix . 'cnwshotel_rooms';
		$bookings_table      = $wpdb->prefix . 'cnwshotel_bookings';
		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';
		$guests_real         = max( 1, absint( $guests_real ) );

		CNWSHOTEL_DB::start_transaction();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking allocation must lock the selected room row inside the active transaction.
		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d FOR UPDATE',
				$rooms_table,
				absint( $room_id )
			)
		);

		if ( ! $room || strtotime( $checkout ) <= strtotime( $checkin ) ) {
			CNWSHOTEL_DB::rollback();
			return false;
		}

		$minstay = $this->get_minimum_stay( absint( $room->post_id ), $checkin );
		$nights  = ( strtotime( $checkout ) - strtotime( $checkin ) ) / DAY_IN_SECONDS;

		if ( $nights < $minstay ) {
			CNWSHOTEL_DB::rollback();
			return false;
		}

		$available_units = $this->get_available_units_with_capacity( absint( $room_id ), $checkin, $checkout, true );
		$units_to_assign = $this->select_units_for_guests( $available_units, $guests_real );

		if ( empty( $units_to_assign ) ) {
			CNWSHOTEL_DB::rollback();
			return false;
		}

		$unit_id = absint( $units_to_assign[0] );

		$insert = CNWSHOTEL_DB::insert(
			$bookings_table,
			array(
				'room_id'        => absint( $room_id ),
				'checkin'        => $checkin,
				'checkout'       => $checkout,
				'guests'         => $guests_real,
				'guests_real'    => $guests_real,
				'guests_paid'    => $guests_real,
				'booking_status' => 'confirmed',
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		if ( ! $insert ) {
			CNWSHOTEL_DB::rollback();
			return false;
		}

		$booking_id = absint( CNWSHOTEL_DB::insert_id() );

		$linked = CNWSHOTEL_DB::insert(
			$booking_units_table,
			array(
				'booking_id' => $booking_id,
				'unit_id'    => $unit_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $linked ) {
			CNWSHOTEL_DB::rollback();
			return false;
		}

		wp_cache_delete( 'cnwshotel_availability_' . absint( $room->post_id ), 'cnwshotel' );

		CNWSHOTEL_DB::commit();

		return $booking_id;
	}

	/**
	 * Gets available room counts for a frontend search in bulk.
	 *
	 * This avoids the old pattern where the result grid queried availability once
	 * per room/unit. The method loads room rows, unit capacities, booking conflicts
	 * and active holds in grouped queries, then resolves availability in memory.
	 *
	 * @param array<int,int> $post_ids Room post IDs.
	 * @param string         $checkin Check-in date.
	 * @param string         $checkout Check-out date.
	 * @param int            $guests_real Requested guest count.
	 * @return array<int,int> Map of room post ID => available unit count.
	 */
	public function get_availability_count_map_for_search( $post_ids, $checkin, $checkout, $guests_real ) {

		global $wpdb;

		$post_ids    = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
		$guests_real = max( 1, absint( $guests_real ) );

		if ( empty( $post_ids ) || empty( $checkin ) || empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {
			return array();
		}

		$rooms_table         = $wpdb->prefix . 'cnwshotel_rooms';
		$units_table         = $wpdb->prefix . 'cnwshotel_room_units';
		$beds_table          = $wpdb->prefix . 'cnwshotel_room_unit_beds';
		$bed_types_table     = $wpdb->prefix . 'cnwshotel_bed_types';
		$bookings_table      = $wpdb->prefix . 'cnwshotel_bookings';
		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';
		$holds_table         = $wpdb->prefix . 'cnwshotel_cart_holds';
		$requested_posts     = array_fill_keys( $post_ids, true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room rows are loaded once for this request and filtered against the already trusted WordPress query result below.
		$all_rooms = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i',
				$rooms_table
			)
		);
		$rooms     = array();

		foreach ( $all_rooms as $room ) {
			$post_id = absint( $room->post_id );

			if ( isset( $requested_posts[ $post_id ] ) ) {
				$rooms[] = $room;
			}
		}

		if ( empty( $rooms ) ) {
				return array();
		}

		$eligible_rooms = array();
		$nights         = ( strtotime( $checkout ) - strtotime( $checkin ) ) / DAY_IN_SECONDS;
		$minstay_map    = $this->get_minimum_stay_map( $post_ids, $checkin );

		foreach ( $rooms as $room ) {
			if ( $guests_real > max( 1, absint( $room->max_persons ) ) ) {
				continue;
			}

			$post_id = absint( $room->post_id );
			$minstay = isset( $minstay_map[ $post_id ] ) ? absint( $minstay_map[ $post_id ] ) : 1;

			if ( $nights < $minstay ) {
				continue;
			}

			$eligible_rooms[ absint( $room->id ) ] = $room;
		}

		if ( empty( $eligible_rooms ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Active unit capacities are calculated only for the current availability request.
		$all_units = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    u.id,
                    u.room_type_id,
                    CASE
                        WHEN COALESCE(SUM(bt.capacity), 0) > 0 THEN COALESCE(SUM(bt.capacity), 0)
                        ELSE r.max_persons
                    END AS unit_capacity
                 FROM %i u
                 INNER JOIN %i r ON r.id = u.room_type_id
                 LEFT JOIN %i rub ON u.id = rub.room_unit_id
                 LEFT JOIN %i bt ON rub.bed_type_id = bt.id
                 WHERE u.status = 'active'
                 GROUP BY u.id, u.room_type_id, r.max_persons
                 ORDER BY unit_capacity ASC, u.id ASC",
				$units_table,
				$rooms_table,
				$beds_table,
				$bed_types_table
			)
		);
		$units     = array();

		foreach ( $all_units as $unit ) {
			if ( isset( $eligible_rooms[ absint( $unit->room_type_id ) ] ) ) {
				$units[] = $unit;
			}
		}

		if ( empty( $units ) ) {
				return array();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Search availability must use current booking rows from the plugin's custom tables; cached data could display an unavailable unit as bookable.
		$conflict_unit_ids = array_map(
			'absint',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT bu.unit_id
                     FROM %i bu
                     INNER JOIN %i b ON bu.booking_id = b.id
                     WHERE b.checkin < %s
                       AND b.checkout > %s
                       AND b.booking_status IN ('confirmed', 'blocked')",
					$booking_units_table,
					$bookings_table,
					$checkout,
					$checkin
				)
			)
		);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Search availability must use current active cart holds from the plugin's custom table; cached data could overbook a room.
		$held_unit_ids = array_map(
			'absint',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT unit_id
                     FROM %i
                     WHERE checkin < %s
                       AND checkout > %s
                       AND hold_status = 'active'
                       AND expires_at > %s",
					$holds_table,
					$checkout,
					$checkin,
					current_time( 'mysql' )
				)
			)
		);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$blocked_unit_ids    = array_flip( array_unique( array_merge( $conflict_unit_ids, $held_unit_ids ) ) );
		$availability_counts = array();

		foreach ( $units as $unit ) {
			$unit_id = absint( $unit->id );

			if ( isset( $blocked_unit_ids[ $unit_id ] ) ) {
				continue;
			}

			if ( max( 1, absint( $unit->unit_capacity ) ) < $guests_real ) {
				continue;
			}

			$room_id = absint( $unit->room_type_id );

			if ( ! isset( $eligible_rooms[ $room_id ] ) ) {
				continue;
			}

			$post_id = absint( $eligible_rooms[ $room_id ]->post_id );

			if ( ! isset( $availability_counts[ $post_id ] ) ) {
				$availability_counts[ $post_id ] = 0;
			}

			++$availability_counts[ $post_id ];
		}

		return $availability_counts;
	}

	/**
	 * Gets available room post IDs for a frontend search in bulk.
	 *
	 * @param array<int,int> $post_ids Room post IDs.
	 * @param string         $checkin Check-in date.
	 * @param string         $checkout Check-out date.
	 * @param int            $guests_real Requested guest count.
	 * @return array<int,bool> Map of room post ID => available.
	 */
	public function get_available_room_post_ids_for_search( $post_ids, $checkin, $checkout, $guests_real ) {

		$availability_counts = $this->get_availability_count_map_for_search( $post_ids, $checkin, $checkout, $guests_real );
		$available_posts     = array();

		foreach ( $availability_counts as $post_id => $count ) {
			if ( absint( $count ) > 0 ) {
				$available_posts[ absint( $post_id ) ] = true;
			}
		}

		return $available_posts;
	}

	/**
	 * Checks whether a simple single-unit booking can be created.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param int    $guests_real Guest count.
	 * @param bool   $is_private Deprecated compatibility argument.
	 * @return bool
	 */
	public function can_create_booking( $room_id, $checkin, $checkout, $guests_real, $is_private = false ) {
		unset( $is_private );

		global $wpdb;

		$rooms_table = $wpdb->prefix . 'cnwshotel_rooms';
		$guests_real = max( 1, absint( $guests_real ) );

		if ( empty( $checkin ) || empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room lookup is required for the current availability request.
		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d LIMIT 1',
				$rooms_table,
				absint( $room_id )
			)
		);

		if ( ! $room ) {
			return false;
		}

		$minstay = $this->get_minimum_stay( absint( $room->post_id ), $checkin );
		$nights  = ( strtotime( $checkout ) - strtotime( $checkin ) ) / DAY_IN_SECONDS;

		if ( $nights < $minstay ) {
			return false;
		}

		$available_units = $this->get_available_units_with_capacity( absint( $room_id ), $checkin, $checkout, false );
		$units_to_assign = $this->select_units_for_guests( $available_units, $guests_real );

		return ! empty( $units_to_assign );
	}

	/**
	 * Gets number of currently available units.
	 *
	 * @param int $post_id Room post ID.
	 * @return int
	 */
	public static function get_room_availability( $post_id ) {

		global $wpdb;

		$cache_key = 'cnwshotel_availability_' . absint( $post_id );
		$cached    = wp_cache_get( $cache_key, 'cnwshotel' );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		$rooms_table         = $wpdb->prefix . 'cnwshotel_rooms';
		$bookings_table      = $wpdb->prefix . 'cnwshotel_bookings';
		$booking_units_table = $wpdb->prefix . 'cnwshotel_booking_units';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Room availability lookup is cached immediately after this query.
		$room = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, post_id, quantity FROM %i WHERE post_id = %d LIMIT 1',
				$rooms_table,
				absint( $post_id )
			)
		);

		if ( ! $room ) {
			return 0;
		}

		$today    = current_time( 'Y-m-d' );
		$tomorrow = wp_date( 'Y-m-d', strtotime( $today . ' +1 day' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current availability is cached after the calculation below.
		$booked = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bu.unit_id)
                 FROM %i bu
                 INNER JOIN %i b ON bu.booking_id = b.id
                 WHERE b.room_id = %d
                   AND b.checkin < %s
                   AND b.checkout > %s
                   AND b.booking_status IN ('confirmed', 'blocked')",
				$booking_units_table,
				$bookings_table,
				absint( $room->id ),
				$tomorrow,
				$today
			)
		);

		$held = 0;

		if ( class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			$holds      = new CNWSHOTEL_Cart_Holds();
			$held_units = $holds->get_active_hold_units( absint( $room->id ), $today, $tomorrow );
			$held       = count( array_unique( array_map( 'absint', $held_units ) ) );
		}

		$available = max( 0, absint( $room->quantity ) - absint( $booked ) - absint( $held ) );

		wp_cache_set( $cache_key, $available, 'cnwshotel', 60 );

		return $available;
	}

	/**
	 * Gets seasonal modifier.
	 *
	 * @param int    $post_id Room post ID.
	 * @param string $date Date.
	 * @return float
	 */
	public function get_seasonal_modifier( $post_id, $date ) {

		$rules = $this->get_pricing_rules_for_range( absint( $post_id ), $date, wp_date( 'Y-m-d', strtotime( $date . ' +1 day' ) ) );

		if ( empty( $rules ) ) {
				return 0;
		}

		foreach ( $rules as $rule ) {
			if ( 'minstay' !== (string) $rule->modifier_type ) {
				return (float) $rule->modifier_value;
			}
		}

		return 0;
	}

	/**
	 * Gets pricing rules for a room and date range in one query.
	 *
	 * @param int    $post_id Room post ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @return array<int,object>
	 */
	private function get_pricing_rules_for_range( $post_id, $checkin, $checkout ) {

		global $wpdb;

		$post_id = absint( $post_id );

		if ( ! $post_id || empty( $checkin ) || empty( $checkout ) || strtotime( $checkout ) <= strtotime( $checkin ) ) {
			return array();
		}

		$table = $wpdb->prefix . 'cnwshotel_seasonal_pricing';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pricing rules are used only for the current price calculation.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT *
                 FROM %i
                 WHERE room_id = %d
                   AND date_start < %s
                   AND date_end >= %s
                 ORDER BY date_start DESC, id DESC',
				$table,
				$post_id,
				$checkout,
				$checkin
			)
		);
	}

	/**
	 * Calculates room price for date range.
	 *
	 * @param int    $post_id Room post ID.
	 * @param float  $base_price Base price.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @return float
	 */
	public function calculate_room_price( $post_id, $base_price, $checkin, $checkout ) {

		$total = 0;
		$start = strtotime( $checkin );
		$end   = strtotime( $checkout );

		if ( ! $start || ! $end || $end <= $start ) {
			return (float) $base_price;
		}

		$rules = $this->get_pricing_rules_for_range( absint( $post_id ), $checkin, $checkout );

		for ( $day = $start; $day < $end; $day += DAY_IN_SECONDS ) {
			$date  = wp_date( 'Y-m-d', $day );
			$price = (float) $base_price;

			foreach ( $rules as $rule ) {
				$date_start = (string) $rule->date_start;
				$date_end   = (string) $rule->date_end;

				if ( $date_start > $date || $date_end < $date ) {
					continue;
				}

				$modifier = (float) $rule->modifier_value;

				if ( 'minstay' === (string) $rule->modifier_type ) {
					continue;
				}

				if ( 'fixed' === (string) $rule->modifier_type ) {
					$price = $price + $modifier;
					break;
				}

				$price = $price + ( $price * ( $modifier / 100 ) );
				break;
			}

			$total += $price;
		}

		return (float) $total;
	}

	/**
	 * Gets minimum stay values for multiple room posts in one query.
	 *
	 * @param array<int,int> $post_ids Room post IDs.
	 * @param string         $checkin  Check-in date.
	 * @return array<int,int>
	 */
	private function get_minimum_stay_map( $post_ids, $checkin ) {

		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );

		if ( empty( $post_ids ) || empty( $checkin ) ) {
				return array();
		}

		$missing = array();
		$map     = array();

		foreach ( $post_ids as $post_id ) {
			$cache_key = absint( $post_id ) . '|' . $checkin;

			if ( isset( $this->minimum_stay_cache[ $cache_key ] ) ) {
				$map[ $post_id ] = absint( $this->minimum_stay_cache[ $cache_key ] );
				continue;
			}

			$missing[] = $post_id;
		}

		if ( ! empty( $missing ) ) {
				$table           = $wpdb->prefix . 'cnwshotel_seasonal_pricing';
				$requested_rooms = array_fill_keys( $missing, true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Minimum-stay rules are cached for the remainder of this request.
			$rules = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT room_id, modifier_value
                     FROM %i
                     WHERE modifier_type = 'minstay'
                       AND date_start <= %s
                       AND date_end >= %s
                     ORDER BY date_start DESC, id DESC",
					$table,
					$checkin,
					$checkin
				)
			);

			foreach ( $missing as $post_id ) {
				$map[ $post_id ] = 1;
			}

			foreach ( $rules as $rule ) {
				$room_post_id = absint( $rule->room_id );

				if ( ! isset( $requested_rooms[ $room_post_id ] ) ) {
					continue;
				}

				if ( ! isset( $map[ $room_post_id ] ) || 1 === absint( $map[ $room_post_id ] ) ) {
					$map[ $room_post_id ] = max( 1, absint( $rule->modifier_value ) );
				}
			}

			foreach ( $missing as $post_id ) {
				$cache_key                              = absint( $post_id ) . '|' . $checkin;
				$this->minimum_stay_cache[ $cache_key ] = max( 1, absint( $map[ $post_id ] ?? 1 ) );
			}
		}

		return $map;
	}

	/**
	 * Gets minimum stay rule.
	 *
	 * @param int    $post_id Room post ID.
	 * @param string $checkin Check-in date.
	 * @return int
	 */
	public function get_minimum_stay( $post_id, $checkin ) {

		$map = $this->get_minimum_stay_map( array( absint( $post_id ) ), $checkin );

		return isset( $map[ absint( $post_id ) ] ) ? max( 1, absint( $map[ absint( $post_id ) ] ) ) : 1;
	}

	/**
	 * Gets available units for cart hold.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param string $ignore_cart_item_key Cart key to ignore.
	 * @return array<int,array{id:int,capacity:int}>
	 */
	public function get_available_units_for_hold( $room_id, $checkin, $checkout, $ignore_cart_item_key = '' ) {

		$units = $this->get_available_units_with_capacity( $room_id, $checkin, $checkout, false );

		if ( ! class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			return $units;
		}

		$holds         = new CNWSHOTEL_Cart_Holds();
		$held_unit_ids = $holds->get_active_hold_units( $room_id, $checkin, $checkout, $ignore_cart_item_key );

		if ( empty( $held_unit_ids ) ) {
			return $units;
		}

		return array_values(
			array_filter(
				$units,
				static function ( $unit ) use ( $held_unit_ids ) {

					return ! in_array( absint( $unit['id'] ), array_map( 'absint', $held_unit_ids ), true );
				}
			)
		);
	}

	/**
	 * Selects units for hold.
	 *
	 * @param array $available_units Available units.
	 * @param int   $guests_real Guest count.
	 * @return array<int,int>
	 */
	public function select_units_for_hold( $available_units, $guests_real ) {

		return $this->select_units_for_guests( $available_units, $guests_real );
	}

	/**
	 * Gets total hold capacity.
	 *
	 * @param int $room_id Room table ID.
	 * @return int
	 */
	public function get_total_capacity_for_hold( $room_id ) {

		return $this->get_total_active_capacity( $room_id, false );
	}

	/**
	 * Gets reserved capacity including holds.
	 *
	 * @param int    $room_id Room table ID.
	 * @param string $checkin Check-in date.
	 * @param string $checkout Check-out date.
	 * @param string $ignore_cart_item_key Cart key to ignore.
	 * @return int
	 */
	public function get_reserved_capacity_for_hold( $room_id, $checkin, $checkout, $ignore_cart_item_key = '' ) {

		$reserved = $this->get_total_reserved_capacity( $room_id, $checkin, $checkout, false );

		if ( class_exists( 'CNWSHOTEL_Cart_Holds' ) ) {
			$holds     = new CNWSHOTEL_Cart_Holds();
			$reserved += $holds->get_active_hold_capacity( $room_id, $checkin, $checkout, $ignore_cart_item_key );
		}

		return absint( $reserved );
	}
}
